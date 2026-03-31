<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

requireRole(['admin']);

$data = json_decode(file_get_contents("php://input"), true);
$id   = $data['id'] ?? 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu ID người dùng"]);
    exit;
}

// Lấy trạng thái hiện tại
$sql = "SELECT is_active FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Người dùng không tồn tại"]);
    exit;
}

// Đảo ngược trạng thái
$new_status = ($user['is_active'] == 1) ? 0 : 1;
$update_sql = "UPDATE users SET is_active = ? WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "ii", $new_status, $id);

if (mysqli_stmt_execute($update_stmt)) {
    $msg = ($new_status == 1) ? "Đã mở khóa tài khoản" : "Đã vô hiệu hóa tài khoản";
    echo json_encode(["status" => "success", "message" => $msg]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
}
?>
