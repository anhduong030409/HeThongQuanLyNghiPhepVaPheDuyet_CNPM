CREATE DATABASE IF NOT EXISTS quan_ly_nghi_phep;
USE quan_ly_nghi_phep;

-- Bang nhan vien
CREATE TABLE nhan_vien (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ho_ten VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phong_ban VARCHAR(100),
    chuc_vu VARCHAR(50),
    vai_tro ENUM('nhan_vien', 'quan_ly', 'hr') DEFAULT 'nhan_vien',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bang loai nghi phep
CREATE TABLE loai_nghi_phep (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ten_loai VARCHAR(100) NOT NULL,
    so_ngay_toi_da INT DEFAULT 0,
    mo_ta TEXT
);

-- Bang don xin nghi phep
CREATE TABLE don_nghi_phep (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nhan_vien_id INT NOT NULL,
    loai_nghi_id INT NOT NULL,
    ngay_bat_dau DATE NOT NULL,
    ngay_ket_thuc DATE NOT NULL,
    so_ngay INT NOT NULL,
    ly_do TEXT,
    trang_thai ENUM('cho_duyet', 'da_duyet', 'tu_choi') DEFAULT 'cho_duyet',
    nguoi_duyet_id INT,
    ghi_chu TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nhan_vien_id) REFERENCES nhan_vien(id),
    FOREIGN KEY (loai_nghi_id) REFERENCES loai_nghi_phep(id)
);

-- Du lieu mau
INSERT INTO nhan_vien (ho_ten, email, password, phong_ban, chuc_vu, vai_tro) VALUES
('Admin', 'admin@gmail.com', MD5('123456'), 'HR', 'Quan ly', 'hr'),
('Nguyen Van A', 'nva@gmail.com', MD5('123456'), 'IT', 'Developer', 'nhan_vien'),
('Tran Thi B', 'ttb@gmail.com', MD5('123456'), 'IT', 'Tester', 'quan_ly');

INSERT INTO loai_nghi_phep (ten_loai, so_ngay_toi_da, mo_ta) VALUES
('Nghi phep nam', 12, 'Nghi phep theo nam'),
('Nghi om', 5, 'Nghi vi ly do suc khoe'),
('Nghi viec rieng', 3, 'Nghi giai quyet viec ca nhan');
