<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

$payload = requireRole(['admin', 'hr', 'manager']);
$method  = $_SERVER['REQUEST_METHOD'];

// ===== GET - lay danh sach don cho duyet =====
if ($method === 'GET') {
    $status = $_GET['status'] ?? 'pending';

    // Manager chi thay don cua nhan vien trong phong ban minh
    // HR/Admin thay tat ca
    if ($payload['role'] === 'manager') {
        $sql = "SELECT r.*, t.name as leave_type_name,
                       u.full_name, u.email, u.department_id
                FROM leave_requests r
                JOIN leave_types t ON r.leave_type_id = t.id
                JOIN users u ON r.user_id = u.id
                JOIN users m ON m.id = ? AND m.department_id = u.department_id
                WHERE r.status = ?
                ORDER BY r.submitted_at ASC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "is", $payload['id'], $status);
    } else {
        $sql = "SELECT r.*, t.name as leave_type_name,
                       u.full_name, u.email
                FROM leave_requests r
                JOIN leave_types t ON r.leave_type_id = t.id
                JOIN users u ON r.user_id = u.id
                WHERE r.status = ?
                ORDER BY r.submitted_at ASC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $status);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $list   = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $list[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $list]);
    exit;
}

// ===== POST - duyet hoac tu choi =====
if ($method === 'POST') {
    $data       = json_decode(file_get_contents("php://input"), true);
    $request_id = $data['request_id'] ?? 0;
    $decision   = $data['decision']   ?? ''; // approved | rejected
    $comment    = $data['comment']    ?? '';

    if (!$request_id || !in_array($decision, ['approved', 'rejected'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Thieu thong tin"]);
        exit;
    }

    // Lay thong tin don
    $stmt_get = mysqli_prepare($conn,
        "SELECT * FROM leave_requests WHERE id = ? AND status = 'pending'"
    );
    mysqli_stmt_bind_param($stmt_get, "i", $request_id);
    mysqli_stmt_execute($stmt_get);
    $request = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));

    if (!$request) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Don khong ton tai hoac da xu ly"]);
        exit;
    }

    // Cap nhat trang thai don
    $stmt = mysqli_prepare($conn,
        "UPDATE leave_requests SET status=?
         WHERE id=?"
    );
    mysqli_stmt_bind_param($stmt, "si", $decision, $request_id);
    mysqli_stmt_execute($stmt);

    // Neu duyet → tru used_days trong leave_balances
    if ($decision === 'approved') {
        $stmt_bal = mysqli_prepare($conn,
            "UPDATE leave_balances
             SET used_days = used_days + ?
             WHERE user_id = ? AND leave_type_id = ?
             AND year = YEAR(?)"
        );
        mysqli_stmt_bind_param($stmt_bal, "diis",
            $request['total_days'],
            $request['user_id'],
            $request['leave_type_id'],
            $request['start_date']
        );
        mysqli_stmt_execute($stmt_bal);
    }

    // Tao thong bao cho nhan vien
    $msg = $decision === 'approved'
        ? "Don nghi phep cua ban da duoc duyet"
        : "Don nghi phep cua ban bi tu choi. Ly do: " . $comment;

    $stmt_notif = mysqli_prepare($conn,
        "INSERT INTO notifications (user_id, request_id, type, message)
         VALUES (?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt_notif, "iiss",
        $request['user_id'], $request_id, $decision, $msg
    );
    mysqli_stmt_execute($stmt_notif);

    // Ghi vao leave_approvals
    $level = $payload['role'] === 'manager' ? 1 : 2;
    $stmt_appr = mysqli_prepare($conn,
        "INSERT INTO leave_approvals
         (request_id, approver_id, approval_level, decision, comment, decided_at)
         VALUES (?, ?, ?, ?, ?, NOW())"
    );
    mysqli_stmt_bind_param($stmt_appr, "iiiss",
        $request_id, $payload['id'], $level, $decision, $comment
    );
    mysqli_stmt_execute($stmt_appr);

    echo json_encode(["status" => "success", "message" => "Da xu ly don"]);
    exit;
}
?>