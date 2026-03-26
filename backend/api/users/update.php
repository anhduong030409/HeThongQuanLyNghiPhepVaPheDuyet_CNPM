<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

requireRole(['admin', 'hr']);

$data          = json_decode(file_get_contents("php://input"), true);
$id            = $data['id']            ?? 0;
$full_name     = $data['full_name']     ?? '';
$phone         = $data['phone']         ?? '';
$role_id       = $data['role_id']       ?? 4;
$department_id = $data['department_id'] ?? null;
$manager_id    = $data['manager_id']    ?? null;
$gender        = $data['gender']        ?? 'male';
$is_active     = $data['is_active']     ?? 1;

if (!in_array($gender, ['male', 'female', 'other'])) {
    $gender = 'male';
}

if (!$id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thieu ID"]);
    exit;
}

$sql  = "UPDATE users SET full_name=?, phone=?, role_id=?, department_id=?, manager_id=?, gender=?, is_active=?
         WHERE id=?";
$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "ssiissii", $full_name, $phone, $role_id, $department_id, $manager_id, $gender, $is_active, $id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(["status" => "success", "message" => "Cap nhat thanh cong"]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
}
?>