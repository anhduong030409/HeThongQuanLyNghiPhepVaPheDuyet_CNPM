<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

requireAuth();

$sql = "SELECT u.id, u.full_name, u.email, u.phone, u.is_active, u.created_at,
               r.display_name as role_name,
               d.name as dept_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN departments d ON u.department_id = d.id
        ORDER BY u.id ASC";

$result = mysqli_query($conn, $sql);
$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

echo json_encode(["status" => "success", "data" => $users]);
?>