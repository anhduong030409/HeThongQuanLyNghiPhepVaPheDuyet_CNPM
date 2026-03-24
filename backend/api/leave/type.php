<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ===== LIST =====
if ($method === 'GET' && $action === 'list') {
    $result = mysqli_query($conn, "SELECT * FROM leave_types ORDER BY priority_order ASC, id ASC");
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $data]);
}

// ===== CREATE =====
else if ($method === 'POST' && $action === 'create') {
    requireRole(['admin', 'hr']);
    $data = json_decode(file_get_contents("php://input"), true);
    $name = $data['name'] ?? '';
    $max_days = $data['max_days_per_year'] ?? 0;
    $is_paid = $data['is_paid'] ?? 1;
    $requires_document = $data['requires_document'] ?? 0;
    $carry_over_days = $data['carry_over_days'] ?? 0;
    $is_active = $data['is_active'] ?? 1;
    $priority_order = $data['priority_order'] ?? 99;
    $can_combine = $data['can_combine'] ?? 1;
    $gender_restriction = $data['gender_restriction'] ?? null;
    $policy_description = $data['policy_description'] ?? null;

    if (!$name) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Tên loại phép không được trống"]);
        exit;
    }

    $sql = "INSERT INTO leave_types
                (name, max_days_per_year, is_paid, requires_document, carry_over_days, is_active, priority_order, can_combine, gender_restriction, policy_description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "siiiiiisss",
        $name,
        $max_days,
        $is_paid,
        $requires_document,
        $carry_over_days,
        $is_active,
        $priority_order,
        $can_combine,
        $gender_restriction,
        $policy_description
    );

    if (!mysqli_stmt_execute($stmt)) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
        exit;
    }

    // Ham tinh ngay theo thang
    function calcProratedDays($max_days_per_year, $months_left)
    {
        if ($max_days_per_year <= 0)
            return 0;
        $days = $max_days_per_year / 12 * $months_left;
        $rounded = round($days * 2) / 2;
        return $rounded < 0.5 ? 0 : $rounded;
    }

    // Lay ID loai phep vua tao
    $new_type_id = mysqli_insert_id($conn);
    $year = date('Y');
    $month_now = (int) date('n');
    $months_left = 13 - $month_now;

    // Tu dong tao leave_balances cho tat ca nhan vien dang active
    $users = mysqli_query($conn, "SELECT id FROM users WHERE is_active = 1");
    while ($u = mysqli_fetch_assoc($users)) {
        $total_days = calcProratedDays($max_days, $months_left);
        $stmt_b = mysqli_prepare(
            $conn,
            "INSERT IGNORE INTO leave_balances (user_id, leave_type_id, year, total_days, used_days)
             VALUES (?, ?, ?, ?, 0)"
        );
        mysqli_stmt_bind_param($stmt_b, "iiid", $u['id'], $new_type_id, $year, $max_days);
        mysqli_stmt_execute($stmt_b);
    }

    echo json_encode(["status" => "success", "message" => "Tạo loại phép thành công"]);
}

// ===== UPDATE =====
else if ($method === 'POST' && $action === 'update') {
    requireRole(['admin', 'hr']);
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? 0;
    $name = $data['name'] ?? '';
    $max_days = $data['max_days_per_year'] ?? 0;
    $is_paid = $data['is_paid'] ?? 1;
    $requires_document = $data['requires_document'] ?? 0;
    $carry_over_days = $data['carry_over_days'] ?? 0;
    $is_active = $data['is_active'] ?? 1;
    $priority_order = $data['priority_order'] ?? 99;
    $can_combine = $data['can_combine'] ?? 1;
    $gender_restriction = $data['gender_restriction'] ?? null;
    $policy_description = $data['policy_description'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Thiếu ID"]);
        exit;
    }

    $sql = "UPDATE leave_types
             SET name=?, max_days_per_year=?, is_paid=?, requires_document=?, carry_over_days=?,
                 is_active=?, priority_order=?, can_combine=?, gender_restriction=?, policy_description=?
             WHERE id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "siiiiiisssi",
        $name,
        $max_days,
        $is_paid,
        $requires_document,
        $carry_over_days,
        $is_active,
        $priority_order,
        $can_combine,
        $gender_restriction,
        $policy_description,
        $id
    );

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(["status" => "success", "message" => "Cập nhật thành công"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
    }
}

// ===== DELETE (vo hieu hoa) =====
else if ($method === 'POST' && $action === 'delete') {
    requireRole(['admin', 'hr']);
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? 0;

    if (!$id) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Thiếu ID"]);
        exit;
    }

    $stmt = mysqli_prepare($conn, "UPDATE leave_types SET is_active = 0 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(["status" => "success", "message" => "Đã vô hiệu hóa loại phép"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
    }
}