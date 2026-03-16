<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);

$id          = $data['id'];
$trang_thai  = $data['trang_thai'];  // 'da_duyet' hoac 'tu_choi'
$nguoi_duyet = $data['nguoi_duyet_id'];
$ghi_chu     = $data['ghi_chu'] ?? '';

$sql = "UPDATE don_nghi_phep 
        SET trang_thai = ?, nguoi_duyet_id = ?, ghi_chu = ?
        WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sisi", $trang_thai, $nguoi_duyet, $ghi_chu, $id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(["status" => "success", "message" => "Cap nhat thanh cong"]);
} else {
    echo json_encode(["status" => "error", "message" => "Cap nhat that bai"]);
}
?>
```

---

### `.gitignore`
```
backend/config/database.php