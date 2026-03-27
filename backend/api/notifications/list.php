<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

$payload = requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $payload['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $notifs = [];
    $unread_count = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['is_read'] == 0) {
            $unread_count++;
        }
        $notifs[] = $row;
    }
    
    echo json_encode(["status" => "success", "data" => $notifs, "unread" => $unread_count]);
}
?>
