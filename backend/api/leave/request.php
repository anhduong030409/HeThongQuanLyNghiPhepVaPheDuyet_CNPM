<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
$payload = requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

// ================================================================
// GET
// ================================================================
if ($method === 'GET') {
    $user_id = $_GET['user_id'] ?? null;

    if ($user_id) {
        $sql  = "SELECT r.*, t.name as leave_type_name
                 FROM leave_requests r
                 LEFT JOIN leave_types t ON r.leave_type_id = t.id
                 WHERE r.user_id = ?
                 ORDER BY r.submitted_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
    } else {
        $sql  = "SELECT r.*, t.name as leave_type_name, u.full_name, u.email
                 FROM leave_requests r
                 LEFT JOIN leave_types t ON r.leave_type_id = t.id
                 JOIN users u ON r.user_id = u.id
                 ORDER BY r.submitted_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $list   = [];

    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['leave_type_id'] === null) {
            $stmt_items = mysqli_prepare($conn,
                "SELECT i.*, t.name as leave_type_name
                 FROM leave_request_items i
                 JOIN leave_types t ON i.leave_type_id = t.id
                 WHERE i.request_id = ?
                 ORDER BY i.priority_order ASC"
            );
            mysqli_stmt_bind_param($stmt_items, "i", $row['id']);
            mysqli_stmt_execute($stmt_items);
            $row['items']       = [];
            $res_items          = mysqli_stmt_get_result($stmt_items);
            while ($item = mysqli_fetch_assoc($res_items)) {
                $row['items'][] = $item;
            }
            $row['is_combined'] = true;
        } else {
            $row['items']       = [];
            $row['is_combined'] = false;
        }
        $list[] = $row;
    }

    echo json_encode(["status" => "success", "data" => $list]);
    exit;
}

// ================================================================
// POST
// ================================================================
if ($method === 'POST') {
    $action = $_GET['action'] ?? 'create';
    $data   = json_decode(file_get_contents("php://input"), true);

    // ---- CANCEL ----
    if ($action === 'cancel') {
        $id = $data['id'] ?? 0;
        if (!$id) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Thiếu ID"]);
            exit;
        }
        $stmt = mysqli_prepare($conn,
            "UPDATE leave_requests
             SET status='cancelled', cancelled_by=?, cancelled_at=NOW()
             WHERE id=? AND user_id=? AND status='pending'"
        );
        mysqli_stmt_bind_param($stmt, "iii", $payload['id'], $id, $payload['id']);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            _restoreBalance($conn, $id);
            echo json_encode(["status" => "success", "message" => "Đã hủy đơn"]);
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Không thể hủy đơn này"]);
        }
        exit;
    }

    // ---- SUGGEST ----
    if ($action === 'suggest') {
        $total_days = floatval($data['total_days'] ?? 0);
        $user_id    = $payload['id'];

        // Validate: không cho nghỉ quá 90 ngày liên tục
        if ($total_days <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Số ngày không hợp lệ"]);
            exit;
        }
        if ($total_days > 90) {
            http_response_code(400);
            echo json_encode([
                "status"  => "error",
                "message" => "Số ngày nghỉ quá lớn ($total_days ngày). Vui lòng kiểm tra lại ngày kết thúc."
            ]);
            exit;
        }

        $suggestion = _suggestCombine($conn, $user_id, $total_days);
        echo json_encode(["status" => "success", "data" => $suggestion]);
        exit;
    }

    // ---- CREATE ----
    $user_id    = $payload['id'];
    $start_date = $data['start_date'] ?? '';
    $end_date   = $data['end_date']   ?? '';
    $total_days = floatval($data['total_days'] ?? 0);
    $reason     = $data['reason']     ?? '';
    $items      = $data['items']      ?? [];

    if (!$start_date || !$end_date || !$reason || $total_days <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Thiếu thông tin bắt buộc"]);
        exit;
    }

    // Validate ngày hợp lệ
    if (strtotime($end_date) < strtotime($start_date)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Ngày kết thúc phải sau ngày bắt đầu"]);
        exit;
    }

    if ($total_days > 90) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Số ngày nghỉ không được vượt quá 90 ngày"]);
        exit;
    }

    $leave_type_id = $data['leave_type_id'] ?? null;
    if ($leave_type_id && empty($items)) {
        $items = [['leave_type_id' => $leave_type_id, 'days_used' => $total_days]];
    }

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Chưa chọn loại phép"]);
        exit;
    }

    // Validate tổng items khớp total_days
    $sum = array_sum(array_column($items, 'days_used'));
    if (round($sum, 1) !== round($total_days, 1)) {
        http_response_code(400);
        echo json_encode([
            "status"  => "error",
            "message" => "Tổng ngày các loại phép ($sum) không khớp với số ngày nghỉ ($total_days)"
        ]);
        exit;
    }

    // Validate số dư từng loại
    foreach ($items as $item) {
        $tid  = intval($item['leave_type_id']);
        $days = floatval($item['days_used']);

        $stmt_type = mysqli_prepare($conn, "SELECT name, max_days_per_year FROM leave_types WHERE id = ?");
        mysqli_stmt_bind_param($stmt_type, "i", $tid);
        mysqli_stmt_execute($stmt_type);
        $type_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_type));

        // Phép không giới hạn (max 999) bỏ qua kiểm tra số dư
        if ($type_row['max_days_per_year'] >= 999) continue;

        $stmt_bal = mysqli_prepare($conn,
            "SELECT (total_days - used_days) as remaining
             FROM leave_balances
             WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURDATE())"
        );
        mysqli_stmt_bind_param($stmt_bal, "ii", $user_id, $tid);
        mysqli_stmt_execute($stmt_bal);
        $bal = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_bal));

        if (!$bal || $bal['remaining'] < $days) {
            http_response_code(400);
            echo json_encode([
                "status"  => "error",
                "message" => "Không đủ số dư \"{$type_row['name']}\". Còn lại: " . ($bal['remaining'] ?? 0) . " ngày"
            ]);
            exit;
        }
    }

    // Transaction
    mysqli_begin_transaction($conn);
    try {
        $insert_type_id = count($items) > 1 ? null : $items[0]['leave_type_id'];

        $stmt_req = mysqli_prepare($conn,
            "INSERT INTO leave_requests
             (user_id, leave_type_id, start_date, end_date, total_days, reason, submitted_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        mysqli_stmt_bind_param($stmt_req, "iissds",
            $user_id, $insert_type_id, $start_date, $end_date, $total_days, $reason
        );
        mysqli_stmt_execute($stmt_req);
        $request_id = mysqli_insert_id($conn);

        foreach ($items as $order => $item) {
            $tid       = intval($item['leave_type_id']);
            $days_used = floatval($item['days_used']);
            $priority  = $order + 1;

            $stmt_item = mysqli_prepare($conn,
                "INSERT INTO leave_request_items (request_id, leave_type_id, days_used, priority_order)
                 VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt_item, "iidi", $request_id, $tid, $days_used, $priority);
            mysqli_stmt_execute($stmt_item);

            // Trừ số dư — nếu chưa có row balance thì tạo mới
            $stmt_check = mysqli_prepare($conn,
                "SELECT id FROM leave_balances
                 WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURDATE())"
            );
            mysqli_stmt_bind_param($stmt_check, "ii", $user_id, $tid);
            mysqli_stmt_execute($stmt_check);
            $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));

            if ($exists) {
                $stmt_update = mysqli_prepare($conn,
                    "UPDATE leave_balances SET used_days = used_days + ?
                     WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURDATE())"
                );
                mysqli_stmt_bind_param($stmt_update, "dii", $days_used, $user_id, $tid);
                mysqli_stmt_execute($stmt_update);
            } else {
                // Phép không lương: tạo row mới với total = used
                $stmt_insert = mysqli_prepare($conn,
                    "INSERT INTO leave_balances (user_id, leave_type_id, year, total_days, used_days)
                     VALUES (?, ?, YEAR(CURDATE()), ?, ?)"
                );
                mysqli_stmt_bind_param($stmt_insert, "iidd", $user_id, $tid, $days_used, $days_used);
                mysqli_stmt_execute($stmt_insert);
            }
        }

        $msg        = "Đơn nghỉ phép mới đã được gửi";
        $stmt_notif = mysqli_prepare($conn,
            "INSERT INTO notifications (user_id, request_id, type, message) VALUES (?, ?, 'submitted', ?)"
        );
        mysqli_stmt_bind_param($stmt_notif, "iis", $user_id, $request_id, $msg);
        mysqli_stmt_execute($stmt_notif);

        mysqli_commit($conn);
        echo json_encode(["status" => "success", "message" => "Gửi đơn thành công"]);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Lỗi hệ thống: " . $e->getMessage()]);
    }
    exit;
}

// ================================================================
// HELPER: gợi ý ghép phép
// FIX: phép không lương luôn được thêm vào cuối nếu vẫn còn thiếu
// ================================================================
function _suggestCombine($conn, $user_id, $total_days) {
    // Lấy tất cả loại phép active, có thể ghép, sắp xếp theo priority
    $sql = "SELECT lt.id, lt.name, lt.priority_order, lt.max_days_per_year, lt.can_combine,
                   COALESCE(lb.total_days - lb.used_days, 0) as remaining
            FROM leave_types lt
            LEFT JOIN leave_balances lb
              ON lb.leave_type_id = lt.id
              AND lb.user_id = ?
              AND lb.year = YEAR(CURDATE())
            WHERE lt.is_active = 1 AND lt.can_combine = 1
            ORDER BY lt.priority_order ASC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $types = mysqli_stmt_get_result($stmt);

    $result    = [];
    $remaining = $total_days;
    $has_unlimited = false;

    while ($type = mysqli_fetch_assoc($types)) {
        if ($remaining <= 0) break;

        $is_unlimited = intval($type['max_days_per_year']) >= 999;
        $available    = floatval($type['remaining']);

        if ($is_unlimited) {
            // Phép không lương: đánh dấu để dùng sau nếu vẫn còn thiếu
            $has_unlimited = $type;
            continue;
        }

        // Phép có giới hạn: chỉ dùng nếu còn số dư
        if ($available <= 0) continue;

        $use = min($available, $remaining);
        $result[]  = [
            'leave_type_id'   => intval($type['id']),
            'leave_type_name' => $type['name'],
            'days_used'       => $use,
            'remaining'       => $available,
        ];
        $remaining -= $use;
    }

    // Nếu vẫn còn thiếu → thêm phép không lương để bù phần còn lại
    if ($remaining > 0 && $has_unlimited) {
        $result[] = [
            'leave_type_id'   => intval($has_unlimited['id']),
            'leave_type_name' => $has_unlimited['name'],
            'days_used'       => $remaining,
            'remaining'       => 999,
        ];
        $remaining = 0;
    }

    return [
        'items'         => $result,
        'total_days'    => $total_days,
        'fully_covered' => $remaining <= 0,
        'uncovered'     => max(0, $remaining),
    ];
}

// ================================================================
// HELPER: hoàn lại số dư khi hủy đơn
// ================================================================
function _restoreBalance($conn, $request_id) {
    $stmt = mysqli_prepare($conn,
        "SELECT r.user_id, i.leave_type_id, i.days_used
         FROM leave_request_items i
         JOIN leave_requests r ON r.id = i.request_id
         WHERE i.request_id = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $request_id);
    mysqli_stmt_execute($stmt);
    $items = mysqli_stmt_get_result($stmt);

    while ($item = mysqli_fetch_assoc($items)) {
        $stmt_restore = mysqli_prepare($conn,
            "UPDATE leave_balances
             SET used_days = GREATEST(0, used_days - ?)
             WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURDATE())"
        );
        mysqli_stmt_bind_param($stmt_restore, "dii",
            $item['days_used'], $item['user_id'], $item['leave_type_id']
        );
        mysqli_stmt_execute($stmt_restore);
    }
}
?>