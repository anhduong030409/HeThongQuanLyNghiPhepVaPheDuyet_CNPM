<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

// Tất cả role trừ employee đều có thể duyệt
$payload = requireRole(['admin', 'director', 'hr', 'manager']);
$method = $_SERVER['REQUEST_METHOD'];

// ================================================================
// GET — lấy danh sách đơn cần duyệt
// Quy tắc: chỉ thấy đơn của người có manager_id = mình
// ================================================================
if ($method === 'GET') {
    $status = $_GET['status'] ?? 'pending';

    // Lấy đơn của những user có manager_id = người đang đăng nhập
    $sql = "SELECT r.*,
                   t.name  as leave_type_name,
                   u.full_name, u.email, u.department_id,
                   d.name  as department_name
            FROM leave_requests r
            LEFT JOIN leave_types t  ON t.id = r.leave_type_id
            JOIN users u             ON u.id = r.user_id
            LEFT JOIN departments d  ON d.id = u.department_id
            WHERE u.manager_id = ?
              AND r.status     = ?
            ORDER BY r.submitted_at ASC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $payload['id'], $status);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $list = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Nếu đơn ghép thì lấy thêm items
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
            $row['items'] = [];
            $res = mysqli_stmt_get_result($stmt_items);
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

    if (!$request_id || !in_array($decision, ['approved', 'rejected'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Thiếu thông tin"]);
        exit;
    }

    // Lấy thông tin đơn + kiểm tra người duyệt có quyền không
    $stmt_get = mysqli_prepare(
        $conn,
        "SELECT r.*, u.manager_id
         FROM leave_requests r
         JOIN users u ON u.id = r.user_id
         WHERE r.id = ? AND r.status = 'pending'"
    );
    mysqli_stmt_bind_param($stmt_get, "i", $request_id);
    mysqli_stmt_execute($stmt_get);
    $request = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));

    if (!$request) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Đơn không tồn tại hoặc đã xử lý"]);
        exit;
    }

    // Kiểm tra đúng người duyệt — manager_id của nhân viên phải là người đang đăng nhập
    if ($request['manager_id'] != $payload['id']) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Bạn không có quyền duyệt đơn này"]);
        exit;
    }

    mysqli_begin_transaction($conn);
    try {
        // Cập nhật trạng thái đơn
        $stmt_update = mysqli_prepare(
            $conn,
            "UPDATE leave_requests SET status = ? WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt_update, "si", $decision, $request_id);
        mysqli_stmt_execute($stmt_update);

        // Nếu TỪ CHỐI → hoàn lại used_days đã trừ lúc gửi đơn
        if ($decision === 'rejected') {
            _restoreBalance($conn, $request_id);
        }

        // Ghi log vào leave_approvals
        $level = _getApprovalLevel($payload['role']);
        $stmt_log = mysqli_prepare(
            $conn,
            "INSERT INTO leave_approvals
             (request_id, approver_id, approval_level, decision, comment)
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
        $msg = $decision === 'approved'
            ? "Đơn nghỉ phép của bạn đã được duyệt"
            : "Đơn nghỉ phép của bạn bị từ chối. Lý do: " . $comment;

        $stmt_notif = mysqli_prepare(
            $conn,
            "INSERT INTO notifications (user_id, request_id, type, message)
             VALUES (?, ?, ?, ?)"
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
        echo json_encode(["status" => "success", "message" => "Đã xử lý đơn"]);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Lỗi hệ thống: " . $e->getMessage()]);
    }
    exit;
}

// ================================================================
// HELPER: xác định approval_level theo role
// ================================================================
function _getApprovalLevel($role)
{
    $map = [
        'manager' => 1,
        'hr' => 2,
        'director' => 3,
        'admin' => 3,
    ];
    return $map[$role] ?? 1;
}

// ================================================================
// HELPER: hoàn lại used_days khi từ chối
// (used_days đã bị trừ lúc nhân viên gửi đơn)
// ================================================================
function _restoreBalance($conn, $request_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT r.user_id, i.leave_type_id, i.days_used
         FROM leave_request_items i
         JOIN leave_requests r ON r.id = i.request_id
         WHERE i.request_id = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $request_id);
    mysqli_stmt_execute($stmt);
    $items = mysqli_stmt_get_result($stmt);

    while ($item = mysqli_fetch_assoc($items)) {
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