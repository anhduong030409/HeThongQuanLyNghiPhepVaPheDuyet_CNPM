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
    $headers = getallheaders();
    $token   = $headers['Authorization'] ?? '';
    $token   = str_replace('Bearer ', '', $token);

    $payload = verifyToken($token);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Chua dang nhap"]);
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