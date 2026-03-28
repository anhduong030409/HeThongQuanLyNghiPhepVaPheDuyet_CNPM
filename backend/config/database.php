<?php
// Toggle: Set to true for server, false for local
define('IS_SERVER', false); // Sửa ở đây

if (IS_SERVER) {
    // Server Configuration (InfinityFree)
    define('DB_HOST', 'sql100.infinityfree.com');
    define('DB_USER', 'if0_41497117');
    define('DB_PASS', '5t9fc0o8vqm'); // Đã xóa dấu cách thừa
    define('DB_NAME', 'if0_41497117_quan_ly_nghi_phep');
} else {
    // Local Configuration (Laragon/XAMPP)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '123456');
    define('DB_NAME', 'quan_ly_nghi_phep');
}

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die(json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . mysqli_connect_error()
    ]));
}
mysqli_set_charset($conn, "utf8");
?>