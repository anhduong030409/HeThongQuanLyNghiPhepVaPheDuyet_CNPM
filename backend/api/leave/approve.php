<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

$payload = requireRole(['admin', 'director', 'hr', 'manager']);
$method = $_SERVER['REQUEST_METHOD'];

// ================================================================
// GET — lấy danh sách đơn cần duyệt theo role
// ================================================================
if ($method === 'GET') {
    $role = $payload['role'];
    $status = $_GET['status'] ?? null;

    if (in_array($role, ['hr', 'admin', 'director'])) {
        $filter_status = $status ?? 'pending_hr';
        $sql = "SELECT r.*,
                       t.name       as leave_type_name,
                       u.full_name, u.email, u.department_id,
                       d.name       as department_name
                FROM leave_requests r
                LEFT JOIN leave_types t  ON t.id = r.leave_type_id
                JOIN users u             ON u.id = r.user_id
                LEFT JOIN departments d  ON d.id = u.department_id
                WHERE r.status = ?
                ORDER BY r.submitted_at ASC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $filter_status);

    } else {
        $filter_status = $status ?? 'pending';
        $sql = "SELECT r.*,
                       t.name       as leave_type_name,
                       u.full_name, u.email, u.department_id,
                       d.name       as department_name
                FROM leave_requests r
                LEFT JOIN leave_types t  ON t.id = r.leave_type_id
                JOIN users u             ON u.id = r.user_id
                LEFT JOIN departments d  ON d.id = u.department_id
                WHERE u.manager_id = ?
                  AND r.status     = ?
                ORDER BY r.submitted_at ASC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "is", $payload['id'], $filter_status);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $list = [];

    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['leave_type_id'] === null) {
            $stmt_items = mysqli_prepare(
                $conn,
                "SELECT i.*, t.name as leave_type_name
                 FROM leave_request_items i
                 JOIN leave_types t ON t.id = i.leave_type_id
                 WHERE i.request_id = ?
                 ORDER BY i.priority_order ASC"
            );
            mysqli_stmt_bind_param($stmt_items, "i", $row['id']);
            mysqli_stmt_execute($stmt_items);
            $res = mysqli_stmt_get_result($stmt_items);
            $row['items'] = [];
            while ($item = mysqli_fetch_assoc($res)) {
                $row['items'][] = $item;
            }
            $row['is_combined'] = true;
        } else {
            $row['items'] = [];
            $row['is_combined'] = false;
        }
        $list[] = $row;
    }

    echo json_encode(["status" => "success", "data" => $list]);
    exit;
}

// ================================================================
// POST — duyệt hoặc từ chối
// ================================================================
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $request_id = intval($data['request_id'] ?? 0);
    $decision = $data['decision'] ?? '';
    $comment = $data['comment'] ?? '';
    $role = $payload['role'];

    if (!$request_id || !in_array($decision, ['approved', 'rejected'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Thiếu thông tin"]);
        exit;
    }

    // Xác định status đơn phải có để role này được duyệt
    $expected_status = in_array($role, ['hr', 'admin', 'director']) ? 'pending_hr' : 'pending';

    $stmt_get = mysqli_prepare(
        $conn,
        "SELECT r.*, u.manager_id
         FROM leave_requests r
         JOIN users u ON u.id = r.user_id
         WHERE r.id = ? AND r.status = ?"
    );
    mysqli_stmt_bind_param($stmt_get, "is", $request_id, $expected_status);
    mysqli_stmt_execute($stmt_get);
    $request = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));

    if (!$request) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Đơn không tồn tại hoặc không thuộc quyền xử lý của bạn"]);
        exit;
    }

    // Manager chỉ được duyệt đơn của nhân viên mình quản lý
    if ($role === 'manager' && $request['manager_id'] != $payload['id']) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Bạn không có quyền duyệt đơn này"]);
        exit;
    }

    mysqli_begin_transaction($conn);
    try {
        // Xác định trạng thái mới
        if ($decision === 'rejected') {
            $new_status = 'rejected';
        } elseif (in_array($role, ['hr', 'admin', 'director'])) {
            // HR/Director duyệt cấp 2 → approved hoàn toàn
            $new_status = 'approved';
        } else {
            // Manager duyệt cấp 1:
            // approval_level=1 → chỉ 1 cấp → approved luôn
            // approval_level=2 → cần 2 cấp → chuyển HR
            $new_status = $request['approval_level'] == 1 ? 'approved' : 'pending_hr';
        }

        // Cập nhật status đơn
        $stmt_update = mysqli_prepare(
            $conn,
            "UPDATE leave_requests SET status = ? WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt_update, "si", $new_status, $request_id);
        mysqli_stmt_execute($stmt_update);

        // Xử lý balance
        if ($new_status === 'approved') {
            _deductBalance($conn, $request_id);
        } elseif ($new_status === 'rejected') {
            _restoreBalance($conn, $request_id);
        }

        // Ghi log vào leave_approvals
        $level = in_array($role, ['hr', 'admin', 'director']) ? 2 : 1;
        $stmt_log = mysqli_prepare(
            $conn,
            "INSERT INTO leave_approvals (request_id, approver_id, approval_level, decision, comment)
             VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmt_log,
            "iiiss",
            $request_id,
            $payload['id'],
            $level,
            $decision,
            $comment
        );
        mysqli_stmt_execute($stmt_log);

        // Gửi thông báo cho nhân viên
        if ($decision === 'approved' && $new_status === 'pending_hr') {
            $msg = "Đơn nghỉ phép của bạn đã được Manager duyệt — đang chờ HR xác nhận";
        } elseif ($decision === 'approved') {
            $msg = "Đơn nghỉ phép của bạn đã được phê duyệt hoàn toàn ✅";
        } else {
            $msg = "Đơn nghỉ phép của bạn bị từ chối. Lý do: " . ($comment ?: 'Không có lý do');
        }

        $stmt_notif = mysqli_prepare(
            $conn,
            "INSERT INTO notifications (user_id, request_id, type, message) VALUES (?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmt_notif,
            "iiss",
            $request['user_id'],
            $request_id,
            $decision,
            $msg
        );
        mysqli_stmt_execute($stmt_notif);

        mysqli_commit($conn);

        $response_msg = match ($new_status) {
            'pending_hr' => "Đã duyệt cấp 1 — đơn chuyển HR xét duyệt tiếp",
            'approved' => "Đã phê duyệt đơn thành công",
            'rejected' => "Đã từ chối đơn",
            default => "Đã xử lý đơn"
        };

        echo json_encode([
            "status" => "success",
            "message" => $response_msg,
            "new_status" => $new_status
        ]);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Lỗi hệ thống: " . $e->getMessage()]);
    }
    exit;
}

// ================================================================
// HELPER: trừ balance khi approved
// ================================================================
function _deductBalance($conn, $request_id)
{
    // Thử lấy từ leave_request_items trước (đơn kết hợp)
    $stmt = mysqli_prepare(
        $conn,
        "SELECT r.user_id, i.leave_type_id, i.days_used
         FROM leave_request_items i
         JOIN leave_requests r ON r.id = i.request_id
         WHERE i.request_id = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $request_id);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    // Nếu không có items → đơn đơn giản, lấy từ leave_requests
    if (empty($rows)) {
        $stmt2 = mysqli_prepare(
            $conn,
            "SELECT user_id, leave_type_id, total_days as days_used
             FROM leave_requests WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt2, "i", $request_id);
        mysqli_stmt_execute($stmt2);
        $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt2), MYSQLI_ASSOC);
    }

    foreach ($rows as $item) {
        $stmt_deduct = mysqli_prepare(
            $conn,
            "UPDATE leave_balances
             SET used_days = used_days + ?
             WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURDATE())"
        );
        mysqli_stmt_bind_param(
            $stmt_deduct,
            "dii",
            $item['days_used'],
            $item['user_id'],
            $item['leave_type_id']
        );
        mysqli_stmt_execute($stmt_deduct);
    }
}

// ================================================================
// HELPER: hoàn lại balance khi rejected
// ================================================================
function _restoreBalance($conn, $request_id)
{
    // Thử lấy từ leave_request_items trước (đơn kết hợp)
    $stmt = mysqli_prepare(
        $conn,
        "SELECT r.user_id, i.leave_type_id, i.days_used
         FROM leave_request_items i
         JOIN leave_requests r ON r.id = i.request_id
         WHERE i.request_id = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $request_id);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    // Nếu không có items → đơn đơn giản
    if (empty($rows)) {
        $stmt2 = mysqli_prepare(
            $conn,
            "SELECT user_id, leave_type_id, total_days as days_used
             FROM leave_requests WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt2, "i", $request_id);
        mysqli_stmt_execute($stmt2);
        $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt2), MYSQLI_ASSOC);
    }

    foreach ($rows as $item) {
        $stmt_restore = mysqli_prepare(
            $conn,
            "UPDATE leave_balances
             SET used_days = GREATEST(0, used_days - ?)
             WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURDATE())"
        );
        mysqli_stmt_bind_param(
            $stmt_restore,
            "dii",
            $item['days_used'],
            $item['user_id'],
            $item['leave_type_id']
        );
        mysqli_stmt_execute($stmt_restore);
    }
}
?>