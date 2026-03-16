<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';
$password = MD5($data['password'] ?? '');

$sql = "SELECT * FROM nhan_vien WHERE email = ? AND password = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $email, $password);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if ($user) {
    session_start();
    $_SESSION['user'] = $user;
    echo json_encode([
        "status"  => "success",
        "message" => "Dang nhap thanh cong",
        "data"    => [
            "id"       => $user['id'],
            "ho_ten"   => $user['ho_ten'],
            "email"    => $user['email'],
            "vai_tro"  => $user['vai_tro'],
            "phong_ban"=> $user['phong_ban']
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        "status"  => "error",
        "message" => "Sai email hoac mat khau"
    ]);
}
?>