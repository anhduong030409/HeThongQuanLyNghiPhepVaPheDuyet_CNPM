<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';

$sql  = "SELECT * FROM leave_types WHERE is_active = 1";
$result = mysqli_query($conn, $sql);

$list = [];
while ($row = mysqli_fetch_assoc($result)) {
    $list[] = $row;
}

echo json_encode(["status" => "success", "data" => $list]);
?>