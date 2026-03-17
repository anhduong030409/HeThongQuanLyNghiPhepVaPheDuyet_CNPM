<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

requireRole(['admin']);

$data = json_decode(file_get_contents("php://input"), true);
$id   = $data['id'] ?? 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thieu ID"]);
    exit;
}

// Soft delete
$stmt = mysqli_prepare($conn, "UPDATE users SET is_active = 0 WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(["status" => "success", "message" => "Da vo hieu hoa nhan vien"]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
}
?>