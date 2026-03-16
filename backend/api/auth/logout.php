<?php
require_once '../../config/cors.php';
session_start();
session_destroy();
echo json_encode([
    "status"  => "success",
    "message" => "Dang xuat thanh cong"
]);
?>