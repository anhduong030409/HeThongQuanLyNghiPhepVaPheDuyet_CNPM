<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

// Chỉ cho phép Admin hoặc HR (tùy chính sách, ở đây chọn Admin)
requireRole(['admin']);

$data = json_decode(file_get_contents("php://input"), true);
$id   = $data['id'] ?? 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu ID người dùng"]);
    exit;
}

// Hàm tạo mật khẩu ngẫu nhiên
function generateRandomPassword($length = 8) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    return substr(str_shuffle($chars), 0, $length);
}

$new_password = generateRandomPassword(8);
$password_hash = MD5($new_password);

$sql  = "UPDATE users SET password_hash = ? WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $password_hash, $id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        "status" => "success", 
        "message" => "Đặt lại mật khẩu thành công",
        "new_password" => $new_password
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
}
?>
