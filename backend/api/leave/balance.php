<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

requireAuth();

$user_id = $_GET['user_id'] ?? null;
$year    = $_GET['year']    ?? date('Y');

if (!$user_id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thieu user_id"]);
    exit;
}

$sql = "SELECT b.id, b.user_id, b.year,
               b.total_days, b.used_days,
               (b.total_days - b.used_days) as remaining_days,
               t.id as leave_type_id,
               t.name as leave_type_name,
               t.is_paid,
               t.max_days_per_year
        FROM leave_balances b
        JOIN leave_types t ON b.leave_type_id = t.id
        WHERE b.user_id = ? AND b.year = ?
        AND t.is_active = 1
        ORDER BY t.id ASC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $user_id, $year);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$list = [];
while ($row = mysqli_fetch_assoc($result)) {
    $list[] = $row;
}

echo json_encode([
    "status" => "success",
    "data"   => $list,
    "meta"   => [
        "user_id" => (int) $user_id,
        "year"    => (int) $year,
        "total"   => count($list)
    ]
]);
?>