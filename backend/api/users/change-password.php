<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

// Chỉ cho phép người dùng đã đăng nhập
$payload = requireAuth();
$user_id = $payload['id'];

$data             = json_decode(file_get_contents("php://input"), true);
$old_password     = $data['old_password']     ?? '';
$new_password     = $data['new_password']     ?? '';
$confirm_password = $data['confirm_password'] ?? '';

if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Vui lòng nhập đầy đủ thông tin"]);
    exit;
}

if ($new_password !== $confirm_password) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Mật khẩu mới không khớp"]);
    exit;
}

// Kiểm tra mật khẩu cũ
$sql = "SELECT password_hash FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user || $user['password_hash'] !== MD5($old_password)) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Mật khẩu cũ không chính xác"]);
    exit;
}

// Cập nhật mật khẩu mới
$new_password_hash = MD5($new_password);
$update_sql = "UPDATE users SET password_hash = ? WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "si", $new_password_hash, $user_id);

if (mysqli_stmt_execute($update_stmt)) {
    echo json_encode(["status" => "success", "message" => "Đổi mật khẩu thành công"]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Lỗi hệ thống: " . mysqli_error($conn)]);
}
?>
