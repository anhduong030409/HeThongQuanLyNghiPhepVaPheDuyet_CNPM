-- ============================================================
-- HỆ THỐNG QUẢN LÝ NGHỈ PHÉP VÀ PHÊ DUYỆT
-- File: full_setup.sql
-- Chạy file này từ đầu để tạo toàn bộ hệ thống
-- Mật khẩu mặc định tất cả tài khoản: 123456
-- ============================================================

CREATE DATABASE IF NOT EXISTS quan_ly_nghi_phep
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE quan_ly_nghi_phep;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ============================================================
-- BẢNG: roles
-- ============================================================
DROP TABLE IF EXISTS roles;
CREATE TABLE roles (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(50)  NOT NULL UNIQUE,
  display_name VARCHAR(50),
  description  TEXT
);

-- ============================================================
-- BẢNG: departments
-- ============================================================
DROP TABLE IF EXISTS departments;
CREATE TABLE departments (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL UNIQUE,
  manager_id  INT NULL,
  description TEXT,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- BẢNG: users
-- ============================================================
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  role_id       INT          NOT NULL,
  department_id INT          NULL,
  manager_id    INT          NULL,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  phone         VARCHAR(20),
  is_active     TINYINT(1)   DEFAULT 1,
  avatar_url    VARCHAR(255),
  created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id)       REFERENCES roles(id),
  FOREIGN KEY (department_id) REFERENCES departments(id),
  FOREIGN KEY (manager_id)    REFERENCES users(id)
);

-- ============================================================
-- BẢNG: leave_types
-- ============================================================
DROP TABLE IF EXISTS leave_types;
CREATE TABLE leave_types (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  name               VARCHAR(100) NOT NULL,
  max_days_per_year  INT          DEFAULT 0,
  is_paid            TINYINT(1)   DEFAULT 1,
  requires_document  TINYINT(1)   DEFAULT 0,
  carry_over_days    INT          DEFAULT 0,
  is_active          TINYINT(1)   DEFAULT 1,
  priority_order     TINYINT      NOT NULL DEFAULT 99,
  can_combine        TINYINT(1)   NOT NULL DEFAULT 1,
  gender_restriction VARCHAR(10)  NULL DEFAULT NULL,
  policy_description TEXT         NULL
);

-- ============================================================
-- BẢNG: leave_balances
-- ============================================================
DROP TABLE IF EXISTS leave_balances;
CREATE TABLE leave_balances (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT            NOT NULL,
  leave_type_id INT            NOT NULL,
  year          YEAR           NOT NULL,
  total_days    DECIMAL(5,1)   DEFAULT 0.0,
  used_days     DECIMAL(5,1)   DEFAULT 0.0,
  UNIQUE KEY uq_balance (user_id, leave_type_id, year),
  FOREIGN KEY (user_id)       REFERENCES users(id),
  FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)
);

-- ============================================================
-- BẢNG: leave_requests
-- ============================================================
DROP TABLE IF EXISTS leave_requests;
CREATE TABLE leave_requests (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT            NOT NULL,
  leave_type_id INT            NULL,
  start_date    DATE           NOT NULL,
  end_date      DATE           NOT NULL,
  total_days    DECIMAL(5,1)   NOT NULL,
  reason        TEXT,
  status        ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
  document_url  VARCHAR(255),
  submitted_at  DATETIME       DEFAULT CURRENT_TIMESTAMP,
  cancelled_by  INT            NULL,
  cancelled_at  DATETIME       NULL,
  cancel_reason TEXT           NULL,
  FOREIGN KEY (user_id)       REFERENCES users(id),
  FOREIGN KEY (leave_type_id) REFERENCES leave_types(id),
  FOREIGN KEY (cancelled_by)  REFERENCES users(id)
);

-- ============================================================
-- BẢNG: leave_request_items
-- ============================================================
DROP TABLE IF EXISTS leave_request_items;
CREATE TABLE leave_request_items (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  request_id     INT          NOT NULL,
  leave_type_id  INT          NOT NULL,
  days_used      DECIMAL(5,1) NOT NULL,
  priority_order TINYINT      NOT NULL DEFAULT 1,
  created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id)    REFERENCES leave_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)
);

-- ============================================================
-- BẢNG: leave_approvals
-- ============================================================
DROP TABLE IF EXISTS leave_approvals;
CREATE TABLE leave_approvals (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  request_id     INT          NOT NULL,
  approver_id    INT          NOT NULL,
  approval_level TINYINT      NOT NULL COMMENT '1=Manager, 2=HR, 3=Director',
  decision       ENUM('approved','rejected') NOT NULL,
  comment        TEXT,
  decided_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_approval (request_id, approval_level),
  FOREIGN KEY (request_id)  REFERENCES leave_requests(id),
  FOREIGN KEY (approver_id) REFERENCES users(id)
);

-- ============================================================
-- BẢNG: notifications
-- ============================================================
DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT          NOT NULL,
  request_id INT          NULL,
  type       ENUM('submitted','approved','rejected','cancelled') NULL,
  message    TEXT,
  is_read    TINYINT(1)   DEFAULT 0,
  created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)    REFERENCES users(id),
  FOREIGN KEY (request_id) REFERENCES leave_requests(id)
);

-- ============================================================
-- DATA: roles
-- ============================================================
INSERT INTO roles (id, name, display_name, description) VALUES
(1, 'admin',    'Quản trị viên', 'Quản trị hệ thống, không tham gia nghỉ phép'),
(2, 'director', 'Giám đốc',      'Cấp cao nhất, duyệt đơn HR và Manager trực tiếp'),
(3, 'hr',       'Nhân sự',       'Quản lý nhân sự, duyệt đơn Manager'),
(4, 'manager',  'Quản lý',       'Trưởng phòng, duyệt đơn Employee'),
(5, 'employee', 'Nhân viên',     'Nhân viên, gửi đơn nghỉ phép');

-- ============================================================
-- DATA: departments
-- ============================================================
INSERT INTO departments (id, name, description) VALUES
(1, 'IT', 'Phòng Công nghệ thông tin'),
(2, 'HR', 'Phòng Nhân sự'),
(3, 'KD', 'Phòng Kinh doanh');

-- ============================================================
-- DATA: users
-- ============================================================
INSERT INTO users (id, full_name, email, password_hash, phone, role_id, department_id, manager_id, is_active) VALUES
(1, 'Admin HT',            'admin@company.com',      MD5('123456'), '0900000001', 1, 2, NULL, 1),
(2, 'Nguyen Van Giam Doc', 'director@company.com',   MD5('123456'), '0900000002', 2, 1, NULL, 1),
(3, 'Tran Thi HR',         'hr@company.com',         MD5('123456'), '0900000003', 3, 2, 2,   1),
(4, 'Le Van Manager IT',   'manager.it@company.com', MD5('123456'), '0900000004', 4, 1, 2,   1),
(5, 'Pham Van Manager KD', 'manager.kd@company.com', MD5('123456'), '0900000005', 4, 3, 2,   1),
(6, 'Nguyen Van Emp1',     'emp1@company.com',       MD5('123456'), '0900000006', 5, 1, 4,   1),
(7, 'Le Thi Emp2',         'emp2@company.com',       MD5('123456'), '0900000007', 5, 1, 4,   1),
(8, 'Tran Van Emp3',       'emp3@company.com',       MD5('123456'), '0900000008', 5, 3, 5,   1),
(9, 'Hoang Thi Emp4',      'emp4@company.com',       MD5('123456'), '0900000009', 5, 2, 3,   1);

-- ============================================================
-- DATA: leave_types
-- ============================================================
INSERT INTO leave_types (id, name, max_days_per_year, is_paid, requires_document, carry_over_days, is_active, priority_order, can_combine, gender_restriction, policy_description) VALUES
(1, 'Phép năm',      12,  1, 0, 5, 1, 3, 1, NULL,     'Nhân viên được 12 ngày phép năm có lương mỗi năm. Thâm niên 5–10 năm: 14 ngày. Trên 10 năm: 16 ngày. Tối đa 5 ngày chuyển sang năm sau.'),
(2, 'Phép ốm',       30,  1, 1, 0, 1, 4, 0, NULL,     'Tối đa 30 ngày/năm, hưởng 75% lương do BHXH chi trả. Nghỉ trên 2 ngày liên tục cần nộp giấy xác nhận của cơ sở y tế.'),
(3, 'Phép bù',       999, 1, 0, 0, 1, 2, 1, NULL,     'Tích lũy từ những ngày làm thêm vào ngày nghỉ hoặc ngày lễ. 1 ngày OT = 1 ngày phép bù. Phép bù không chuyển sang năm sau.'),
(4, 'Phép đặc biệt', 9,   1, 1, 0, 1, 1, 0, NULL,     'Kết hôn: 3 ngày. Con cái kết hôn: 1 ngày. Tang cha/mẹ/vợ/chồng/con: 3 ngày. Tang ông/bà/anh/chị/em: 1 ngày.'),
(5, 'Thai sản',      180, 1, 1, 0, 1, 0, 0, 'female', 'Áp dụng cho lao động nữ. Tối đa 6 tháng (180 ngày), hưởng 100% lương bình quân đóng BHXH 6 tháng trước khi nghỉ.'),
(6, 'Không lương',   999, 0, 0, 0, 1, 5, 1, NULL,     'Áp dụng khi đã dùng hết tất cả các loại phép có lương. Không được hưởng lương trong thời gian nghỉ.');

-- ============================================================
-- DATA: leave_balances
-- Tính số ngày theo tháng còn lại trong năm
-- Bỏ Admin (id=1) vì không tham gia nghỉ phép
-- Phép không giới hạn (999) → total_days = 0 ban đầu
-- ============================================================
INSERT INTO leave_balances (user_id, leave_type_id, year, total_days, used_days)
SELECT
    u.id,
    lt.id,
    YEAR(CURDATE()),
    CASE
        WHEN lt.max_days_per_year >= 999 THEN 0
        ELSE ROUND((lt.max_days_per_year / 12.0 * (13 - MONTH(CURDATE()))) * 2) / 2
    END,
    0
FROM users u
CROSS JOIN leave_types lt
WHERE lt.is_active = 1
  AND u.id != 1
  AND u.role_id != 1;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- KIỂM TRA KẾT QUẢ
-- ============================================================
SELECT 'roles'          AS bang, COUNT(*) AS so_luong FROM roles
UNION ALL SELECT 'departments',   COUNT(*) FROM departments
UNION ALL SELECT 'users',         COUNT(*) FROM users
UNION ALL SELECT 'leave_types',   COUNT(*) FROM leave_types
UNION ALL SELECT 'leave_balances',COUNT(*) FROM leave_balances;
