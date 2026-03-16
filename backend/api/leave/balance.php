<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';

$user_id = $_GET['user_id'] ?? null;
$year    = $_GET['year']    ?? date('Y');

if (!$user_id) {
    echo json_encode(["status" => "error", "message" => "Thieu user_id"]);
    exit;
}

$sql = "SELECT b.*, t.name as leave_type_name,
        (b.total_days - b.used_days) as remaining_days
        FROM leave_balances b
        JOIN leave_types t ON b.leave_type_id = t.id
        WHERE b.user_id = ? AND b.year = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $user_id, $year);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$list = [];
while ($row = mysqli_fetch_assoc($result)) {
    $list[] = $row;
}

echo json_encode(["status" => "success", "data" => $list]);
?>