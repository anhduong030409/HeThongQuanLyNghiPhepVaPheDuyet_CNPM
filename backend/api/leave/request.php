<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET - lay danh sach don nghi phep
if ($method === 'GET') {
    $nv_id = $_GET['nhan_vien_id'] ?? null;
    
    if ($nv_id) {
        $sql = "SELECT d.*, l.ten_loai, n.ho_ten 
                FROM don_nghi_phep d
                JOIN loai_nghi_phep l ON d.loai_nghi_id = l.id
                JOIN nhan_vien n ON d.nhan_vien_id = n.id
                WHERE d.nhan_vien_id = ?
                ORDER BY d.created_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $nv_id);
    } else {
        $sql = "SELECT d.*, l.ten_loai, n.ho_ten 
                FROM don_nghi_phep d
                JOIN loai_nghi_phep l ON d.loai_nghi_id = l.id
                JOIN nhan_vien n ON d.nhan_vien_id = n.id
                ORDER BY d.created_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $list = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $list[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $list]);
}

// POST - tao don nghi phep moi
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $nv_id     = $data['nhan_vien_id'];
    $loai_id   = $data['loai_nghi_id'];
    $ngay_bd   = $data['ngay_bat_dau'];
    $ngay_kt   = $data['ngay_ket_thuc'];
    $so_ngay   = $data['so_ngay'];
    $ly_do     = $data['ly_do'];

    $sql = "INSERT INTO don_nghi_phep 
            (nhan_vien_id, loai_nghi_id, ngay_bat_dau, ngay_ket_thuc, so_ngay, ly_do)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iississ", $nv_id, $loai_id, $ngay_bd, $ngay_kt, $so_ngay, $ly_do);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(["status" => "success", "message" => "Gui don thanh cong"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gui don that bai"]);
    }
}
?>