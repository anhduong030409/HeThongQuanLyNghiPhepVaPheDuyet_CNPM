<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

requireRole(['admin', 'hr']);

$data          = json_decode(file_get_contents("php://input"), true);
$full_name     = $data['full_name']     ?? '';
$email         = $data['email']         ?? '';
$password      = MD5($data['password']  ?? '123456');
$phone         = $data['phone']         ?? '';
$role_id       = $data['role_id']       ?? 4;
$department_id = $data['department_id'] ?? null;

if (!$full_name || !$email) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thieu thong tin bat buoc"]);
    exit;
}

// Kiem tra email trung
$check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
mysqli_stmt_bind_param($check, "s", $email);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);
if (mysqli_stmt_num_rows($check) > 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email da ton tai"]);
    exit;
}

// Tao nhan vien
$sql  = "INSERT INTO users (full_name, email, password_hash, phone, role_id, department_id)
         VALUES (?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssssis", $full_name, $email, $password, $phone, $role_id, $department_id);

if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
    exit;
}

// Lay ID nhan vien vua tao
$new_user_id = mysqli_insert_id($conn);
$year        = date('Y');

// Lay tat ca loai phep dang active
$leave_types = mysqli_query($conn, "SELECT id, max_days_per_year FROM leave_types WHERE is_active = 1");

function calcProratedDays($max_days_per_year, $months_left) {
    if ($max_days_per_year <= 0) return 0;
    $days    = $max_days_per_year / 12 * $months_left;
    $rounded = round($days * 2) / 2;
    return $rounded < 0.5 ? 0 : $rounded;
}

$month_now   = (int) date('n');
$months_left = 13 - $month_now;
// Tu dong tao leave_balances cho tung loai phep
while ($lt = mysqli_fetch_assoc($leave_types)) {
    // Tinh so ngay theo thang con lai
    $total_days = calcProratedDays($lt['max_days_per_year'], $months_left);

    $stmt_balance = mysqli_prepare($conn,
        "INSERT INTO leave_balances (user_id, leave_type_id, year, total_days, used_days)
         VALUES (?, ?, ?, ?, 0)"
    );
    mysqli_stmt_bind_param($stmt_balance, "iiid",
        $new_user_id, $lt['id'], $year, $total_days
    );
    mysqli_stmt_execute($stmt_balance);
}

echo json_encode([
    "status"  => "success",
    "message" => "Tao nhan vien thanh cong",
    "user_id" => $new_user_id
]);
?>