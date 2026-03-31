<?php
function verifyToken($jwt) {
    $secret = "DURALUX_SECRET_KEY_2026";
    $parts  = explode(".", $jwt);

    if (count($parts) !== 2) return null;

    [$token, $signature] = $parts;

    // Kiem tra chu ky
    $expected = hash_hmac('sha256', $token, $secret);
    if (!hash_equals($expected, $signature)) return null;

    // Giai ma token
    $payload = json_decode(base64_decode($token), true);

    // Kiem tra het han
    if ($payload['expired'] < time()) return null;

    return $payload;
}

function requireAuth() {
    global $conn;
    $headers = getallheaders();
    $token   = $headers['Authorization'] ?? '';
    $token   = str_replace('Bearer ', '', $token);

    $payload = verifyToken($token);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Chua dang nhap"]);
        exit;
    }

    // Kiểm tra tài khoản còn hoạt động không
    $user_id = $payload['id'];
    $sql = "SELECT is_active FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if (!$user || $user['is_active'] != 1) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Tài khoản đã bị khóa hoặc không tồn tại"]);
        exit;
    }

    return $payload;
}

function requireRole($roles) {
    $payload = requireAuth();
    if (!in_array($payload['role'], $roles)) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Khong co quyen"]);
        exit;
    }
    return $payload;
}
?>