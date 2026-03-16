<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
$payload = requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

// GET - lay danh sach don
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
                JOIN users u       ON r.user_id = u.id
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
}

// POST - tao don moi
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    $user_id       = $data['user_id'];
    $leave_type_id = $data['leave_type_id'];
    $start_date    = $data['start_date'];
    $end_date      = $data['end_date'];
    $total_days    = $data['total_days'];
    $reason        = $data['reason'];

    // Kiem tra so ngay con lai
    $sql_check = "SELECT total_days - used_days as remaining
                  FROM leave_balances
                  WHERE user_id = ? AND leave_type_id = ?
                  AND year = YEAR(CURDATE())";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "ii", $user_id, $leave_type_id);
    mysqli_stmt_execute($stmt_check);
    $balance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));

    if (!$balance || $balance['remaining'] < $total_days) {
        echo json_encode([
            "status"  => "error",
            "message" => "Khong du so ngay nghi"
        ]);
        exit;
    }

    // Them don
    $sql = "INSERT INTO leave_requests
            (user_id, leave_type_id, start_date, end_date, total_days, reason)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iissds", $user_id, $leave_type_id,
                           $start_date, $end_date, $total_days, $reason);

    if (mysqli_stmt_execute($stmt)) {
        $request_id = mysqli_insert_id($conn);

        // Tao thong bao
        $msg = "Don nghi phep moi da duoc gui";
        $sql_notif = "INSERT INTO notifications (user_id, request_id, type, message)
                      VALUES (?, ?, 'submitted', ?)";
        $stmt_notif = mysqli_prepare($conn, $sql_notif);
        mysqli_stmt_bind_param($stmt_notif, "iis", $user_id, $request_id, $msg);
        mysqli_stmt_execute($stmt_notif);

        echo json_encode([
            "status"  => "success",
            "message" => "Gui don thanh cong"
        ]);
    } else {
        echo json_encode([
            "status"  => "error",
            "message" => "Gui don that bai"
        ]);
    }
}
?>