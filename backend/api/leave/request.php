<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
$payload = requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

// ===== GET - lay danh sach don =====
if ($method === 'GET') {
    $user_id = $_GET['user_id'] ?? null;

    if ($user_id) {
        $sql = "SELECT r.*, t.name as leave_type_name
                FROM leave_requests r
                JOIN leave_types t ON r.leave_type_id = t.id
                WHERE r.user_id = ?
                ORDER BY r.submitted_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
    } else {
        $sql = "SELECT r.*, t.name as leave_type_name, u.full_name
                FROM leave_requests r
                JOIN leave_types t ON r.leave_type_id = t.id
                JOIN users u ON r.user_id = u.id
                ORDER BY r.submitted_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $list   = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $list[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $list]);
    exit;
}

// ===== POST - phan tach theo action =====
if ($method === 'POST') {
    $action = $_GET['action'] ?? 'create';
    $data   = json_decode(file_get_contents("php://input"), true);

    // CANCEL
    if ($action === 'cancel') {
        $id = $data['id'] ?? 0;

        if (!$id) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Thieu ID"]);
            exit;
        }

        $stmt = mysqli_prepare($conn,
            "UPDATE leave_requests SET status='cancelled', cancelled_by=?, cancelled_at=NOW()
             WHERE id=? AND user_id=? AND status='pending'"
        );
        mysqli_stmt_bind_param($stmt, "iii", $payload['id'], $id, $payload['id']);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            echo json_encode(["status" => "success", "message" => "Da huy don"]);
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Khong the huy don nay"]);
        }
        exit;
    }

    // CREATE
    $user_id       = $payload['id'];
    $leave_type_id = $data['leave_type_id'] ?? 0;
    $start_date    = $data['start_date']    ?? '';
    $end_date      = $data['end_date']      ?? '';
    $total_days    = $data['total_days']    ?? 0;
    $reason        = $data['reason']        ?? '';

    if (!$leave_type_id || !$start_date || !$end_date || !$reason) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Thieu thong tin bat buoc"]);
        exit;
    }

    $sql_check  = "SELECT (total_days - used_days) as remaining
                   FROM leave_balances
                   WHERE user_id = ? AND leave_type_id = ?
                   AND year = YEAR(CURDATE())";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "ii", $user_id, $leave_type_id);
    mysqli_stmt_execute($stmt_check);
    $balance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));

    if ($balance && $balance['remaining'] > 0 && $balance['remaining'] < $total_days) {
        http_response_code(400);
        echo json_encode([
            "status"  => "error",
            "message" => "Khong du so ngay nghi. Con lai: " . $balance['remaining'] . " ngay"
        ]);
        exit;
    }

    $sql  = "INSERT INTO leave_requests
             (user_id, leave_type_id, start_date, end_date, total_days, reason, submitted_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iissds",
        $user_id, $leave_type_id, $start_date, $end_date, $total_days, $reason
    );

    if (mysqli_stmt_execute($stmt)) {
        $request_id = mysqli_insert_id($conn);
        $msg        = "Don nghi phep moi da duoc gui";
        $stmt_notif = mysqli_prepare($conn,
            "INSERT INTO notifications (user_id, request_id, type, message)
             VALUES (?, ?, 'submitted', ?)"
        );
        mysqli_stmt_bind_param($stmt_notif, "iis", $user_id, $request_id, $msg);
        mysqli_stmt_execute($stmt_notif);
        echo json_encode(["status" => "success", "message" => "Gui don thanh cong"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
    }
    exit;
}
?>