<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

$payload = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = $_GET['id'] ?? $payload['id']; // Lấy ID của bản thân nếu không có param id

    // Kiểm tra quyền (nhân viên chỉ được xem của chính mình)
    if ($id != $payload['id'] && !in_array($payload['role'], ['admin', 'hr', 'director', 'manager'])) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Không có quyền truy cập thông tin này"]);
        exit;
    }

    $sql = "SELECT u.id, u.full_name, u.email, u.phone, u.gender, u.is_active, u.created_at,
                   r.name as role_name, r.display_name as role_display, 
                   d.name as dept_name,
                   m.full_name as manager_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN users m ON u.manager_id = m.id
            WHERE u.id = ?";
            
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user) {
        // Trả về không cần password_hash
        echo json_encode(["status" => "success", "data" => $user]);
    } else {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Tài khoản không tồn tại"]);
    }
}
?>
