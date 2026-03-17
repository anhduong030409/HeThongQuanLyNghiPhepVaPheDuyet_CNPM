<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

requireAuth();

$result = mysqli_query($conn, "SELECT id, name FROM departments ORDER BY id ASC");
$data   = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

echo json_encode(["status" => "success", "data" => $data]);
?>