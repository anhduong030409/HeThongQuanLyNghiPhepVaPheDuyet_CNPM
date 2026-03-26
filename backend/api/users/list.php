<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

requireAuth();

$sql = "SELECT u.id, u.full_name, u.email, u.phone, u.is_active,
               u.gender,
               u.department_id, u.manager_id, u.created_at,
               r.name        as role_name,
               r.display_name as role_display,
               d.name        as dept_name,
               m.full_name   as manager_name
        FROM users u
        JOIN roles r       ON u.role_id       = r.id
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN users m  ON u.manager_id    = m.id
        WHERE 1=1";

$params = [];
$types = '';

// Lọc theo ID cụ thể (dùng cho loadApprover)
if (!empty($_GET['id'])) {
    $sql .= " AND u.id = ?";
    $params[] = intval($_GET['id']);
    $types .= 'i';
}

// Lọc theo role (slug: admin, hr, manager, employee)
if (!empty($_GET['role'])) {
    $sql .= " AND r.name = ?";
    $params[] = $_GET['role'];
    $types .= 's';
}

$sql .= " ORDER BY u.id ASC";

if ($params) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $sql);
}

$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

// Nếu lọc theo id → trả 1 object thay vì array
if (!empty($_GET['id']) && count($users) === 1) {
    echo json_encode(["status" => "success", "data" => $users[0]]);
} else {
    echo json_encode(["status" => "success", "data" => $users]);
}
?>