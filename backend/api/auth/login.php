<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';
$password = MD5($data['password'] ?? '');

$sql = "SELECT u.*, r.name as role_name, d.name as dept_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.email = ? AND u.password_hash = ? AND u.is_active = 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $email, $password);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if ($user) {
    $secret = "DURALUX_SECRET_KEY_2026";

    // Token chứa đủ thông tin cần thiết cho các API
    $token = base64_encode(json_encode([
        "id" => $user['id'],
        "role" => $user['role_name'],
        "manager_id" => $user['manager_id'], // thêm để biết ai duyệt cho user này
        "expired" => time() + (60 * 60 * 8)
    ]));

    $signature = hash_hmac('sha256', $token, $secret);
    $jwt = $token . "." . $signature;

    echo json_encode([
        "status" => "success",
        "token" => $jwt,
        "data" => [
            "id" => $user['id'],
            "full_name" => $user['full_name'],
            "email" => $user['email'],
            "role" => $user['role_name'],
            "department" => $user['dept_name'],
            "manager_id" => $user['manager_id'], // frontend dùng để hiển thị tên người duyệt
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Sai email hoac mat khau"
    ]);
}
?>