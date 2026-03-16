<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '123456');
define('DB_NAME', 'quan_ly_nghi_phep');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die(json_encode([
        "status"  => "error",
        "message" => mysqli_connect_error()
    ]));
}
mysqli_set_charset($conn, "utf8");
?>