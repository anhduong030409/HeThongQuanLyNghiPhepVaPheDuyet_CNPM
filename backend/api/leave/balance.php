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

$sql = "SELECT b.id, ? as user_id, ? as year,
               COALESCE(b.total_days, t.max_days_per_year) as total_days, 
               COALESCE(b.used_days, 0) as used_days,
               (COALESCE(b.total_days, t.max_days_per_year) - COALESCE(b.used_days, 0)) as remaining_days,
               t.id as leave_type_id,
               t.name as leave_type_name,
               t.is_paid,
               t.max_days_per_year
        FROM leave_types t
        LEFT JOIN leave_balances b ON b.leave_type_id = t.id AND b.user_id = ? AND b.year = ?
        WHERE t.is_active = 1
        ORDER BY t.id ASC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iiii", $user_id, $year, $user_id, $year);
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