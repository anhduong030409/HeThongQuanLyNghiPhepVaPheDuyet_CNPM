<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';

$data          = json_decode(file_get_contents("php://input"), true);
$request_id    = $data['request_id'];
$approver_id   = $data['approver_id'];
$decision      = $data['decision'];    // 'approved' hoac 'rejected'
$comment       = $data['comment'] ?? '';
$approval_level = $data['approval_level']; // 1=Manager, 2=HR

// Them vao bang duyet
$sql = "INSERT INTO leave_approvals
        (request_id, approver_id, approval_level, decision, comment)
        VALUES (?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iiiss",
    $request_id, $approver_id, $approval_level, $decision, $comment);
mysqli_stmt_execute($stmt);

// Cap nhat trang thai don
if ($decision === 'rejected') {
    $status = 'rejected';
} else if ($approval_level == 2) {
    $status = 'approved'; // HR duyet = hoan tat
} else {
    $status = 'pending';  // Manager duyet -> cho HR
}

$sql_update = "UPDATE leave_requests SET status = ? WHERE id = ?";
$stmt_update = mysqli_prepare($conn, $sql_update);
mysqli_stmt_bind_param($stmt_update, "si", $status, $request_id);
mysqli_stmt_execute($stmt_update);

// Neu approved hoan toan -> tru so ngay
if ($status === 'approved') {
    $sql_req = "SELECT user_id, leave_type_id, total_days FROM leave_requests WHERE id = ?";
    $stmt_req = mysqli_prepare($conn, $sql_req);
    mysqli_stmt_bind_param($stmt_req, "i", $request_id);
    mysqli_stmt_execute($stmt_req);
    $req = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_req));

    $sql_bal = "UPDATE leave_balances
                SET used_days = used_days + ?
                WHERE user_id = ? AND leave_type_id = ? AND year = YEAR(CURDATE())";
    $stmt_bal = mysqli_prepare($conn, $sql_bal);
    mysqli_stmt_bind_param($stmt_bal, "dii",
        $req['total_days'], $req['user_id'], $req['leave_type_id']);
    mysqli_stmt_execute($stmt_bal);
}

// Tao thong bao
$sql_notif = "SELECT user_id FROM leave_requests WHERE id = ?";
$stmt_notif = mysqli_prepare($conn, $sql_notif);
mysqli_stmt_bind_param($stmt_notif, "i", $request_id);
mysqli_stmt_execute($stmt_notif);
$req_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_notif));

$msg = $decision === 'approved' ? "Don nghi phep da duoc duyet" : "Don nghi phep bi tu choi";
$sql_n = "INSERT INTO notifications (user_id, request_id, type, message)
          VALUES (?, ?, ?, ?)";
$stmt_n = mysqli_prepare($conn, $sql_n);
mysqli_stmt_bind_param($stmt_n, "iiss",
    $req_user['user_id'], $request_id, $decision, $msg);
mysqli_stmt_execute($stmt_n);

echo json_encode(["status" => "success", "message" => "Cap nhat thanh cong"]);
?>