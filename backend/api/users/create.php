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
$role_id       = isset($data['role_id']) ? (int)$data['role_id'] : null;
$department_id = $data['department_id'] ?? null;
$manager_id    = $data['manager_id']    ?? null;
$gender        = $data['gender']        ?? 'male'; // 'male' | 'female' | 'other'

if (!$role_id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu role_id"]);
    exit;
}
if (!$full_name || !$email) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Thiếu thông tin bắt buộc"]);
    exit;
}

// Kiểm tra email trùng
$check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
mysqli_stmt_bind_param($check, "s", $email);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);
if (mysqli_stmt_num_rows($check) > 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email đã tồn tại"]);
    exit;
}

// Tạo nhân viên — thêm cột gender
$sql = "INSERT INTO users (full_name, email, password_hash, phone, role_id, department_id, manager_id, gender)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssssiiss",
    $full_name, $email, $password, $phone,
    $role_id, $department_id, $manager_id, $gender
);

if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
    exit;
}

$new_user_id = mysqli_insert_id($conn);
$year        = date('Y');
$month_now   = (int)date('n');
$months_left = 13 - $month_now;

// Lấy tất cả loại phép active, kèm thông tin cần thiết
$leave_types = mysqli_query($conn,
    "SELECT id, max_days_per_year, gender_restriction
     FROM leave_types
     WHERE is_active = 1"
);

function calcProratedDays($max_days_per_year, $months_left): float
{
    if ($max_days_per_year <= 0) return 0;
    $days    = $max_days_per_year / 12 * $months_left;
    $rounded = round($days * 2) / 2;
    return $rounded < 0.5 ? 0 : $rounded;
}

while ($lt = mysqli_fetch_assoc($leave_types)) {
    // Bỏ qua Thai sản nếu không phải nữ
    if ($lt['gender_restriction'] === 'female' && $gender !== 'female') {
        continue;
    }

    // Phép bù (max_days_per_year = 999) → tích lũy từ làm thêm
    // Khởi tạo total_days = 0, không tính prorated
    $is_accumulative = $lt['max_days_per_year'] >= 999;

    if ($is_accumulative) {
        $total_days = 0; // không cấp sẵn, tích lũy dần
    } else {
        $total_days = calcProratedDays($lt['max_days_per_year'], $months_left);
    }

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
    "message" => "Tạo nhân viên thành công",
    "user_id" => $new_user_id
]);
?>