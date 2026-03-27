<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

$payload = requireAuth();

// Chỉ HR, Admin, Director mới được xem báo cáo
if (!in_array($payload['role'], ['hr', 'admin', 'director'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Không có quyền truy cập"]);
    exit;
}

$dept_id   = isset($_GET['dept_id'])   && $_GET['dept_id'] !== '' ? (int)$_GET['dept_id'] : null;
$year      = isset($_GET['year'])      ? (int)$_GET['year']      : (int)date('Y');
$date_from = $_GET['date_from'] ?? "$year-01-01";
$date_to   = $_GET['date_to']   ?? "$year-12-31";

// ---- Lấy danh sách nhân viên ----
$where_dept = $dept_id ? "AND u.department_id = $dept_id" : "";

$sql_users = "
    SELECT u.id, u.full_name, d.name AS dept_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.is_active = 1 AND u.role_id != 1
    $where_dept
    ORDER BY d.name, u.full_name
";

$users_result = mysqli_query($conn, $sql_users);
if (!$users_result) {
    echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
    exit;
}

$data = [];
$sum_total = 0; $sum_used = 0; $sum_pending = 0; $sum_remain = 0;

while ($user = mysqli_fetch_assoc($users_result)) {
    $uid = (int)$user['id'];

    // Tổng phép được cấp trong năm
    $sql_total = "SELECT COALESCE(SUM(total_days), 0) AS total 
                  FROM leave_balances 
                  WHERE user_id = $uid AND year = $year";
    $r_total = mysqli_fetch_assoc(mysqli_query($conn, $sql_total));
    $total_days = (float)($r_total['total'] ?? 0);

    // Phép đã dùng (approved) trong khoảng thời gian
    $sql_used = "SELECT COALESCE(SUM(total_days), 0) AS used 
                 FROM leave_requests 
                 WHERE user_id = $uid AND status = 'approved'
                   AND start_date BETWEEN '$date_from' AND '$date_to'";
    $r_used = mysqli_fetch_assoc(mysqli_query($conn, $sql_used));
    $used_days = (float)($r_used['used'] ?? 0);

    // Phép đang chờ duyệt
    $sql_pending = "SELECT COALESCE(SUM(total_days), 0) AS pending 
                    FROM leave_requests 
                    WHERE user_id = $uid AND status IN ('pending','pending_hr')
                      AND start_date BETWEEN '$date_from' AND '$date_to'";
    $r_pending = mysqli_fetch_assoc(mysqli_query($conn, $sql_pending));
    $pending_days = (float)($r_pending['pending'] ?? 0);

    $remain = max(0, $total_days - $used_days);

    // Cảnh báo
    if ($total_days > 0 && $remain <= 0) {
        $note = 'Hết phép năm'; $note_class = 'text-danger';
    } elseif ($total_days > 0 && $remain <= 2) {
        $note = 'Sắp hết phép'; $note_class = 'text-warning';
    } else {
        $note = ''; $note_class = '';
    }

    $sum_total   += $total_days;
    $sum_used    += $used_days;
    $sum_pending += $pending_days;
    $sum_remain  += $remain;

    $data[] = [
        'id'           => $uid,
        'full_name'    => $user['full_name'],
        'dept_name'    => $user['dept_name'] ?? '--',
        'total_days'   => $total_days,
        'used_days'    => $used_days,
        'pending_days' => $pending_days,
        'remain_days'  => $remain,
        'note'         => $note,
        'note_class'   => $note_class,
    ];
}

echo json_encode([
    "status" => "success",
    "data"   => $data,
    "summary" => [
        "total"   => $sum_total,
        "used"    => $sum_used,
        "pending" => $sum_pending,
        "remain"  => $sum_remain,
        "count"   => count($data)
    ]
]);
?>
