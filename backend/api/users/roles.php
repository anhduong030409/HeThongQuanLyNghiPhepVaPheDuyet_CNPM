<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

requireRole(['admin']);

$result = mysqli_query($conn, "SELECT id, name, display_name FROM roles ORDER BY id");

$roles = [];
while ($row = mysqli_fetch_assoc($result)) {
    $roles[] = $row;
}

echo json_encode([
    "status" => "success",
    "data"   => $roles
]);
?>