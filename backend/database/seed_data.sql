-- ============================================================
-- HỆ THỐNG QUẢN LÝ NGHỈ PHÉP
-- File: seed_data.sql
-- Chạy file này để import toàn bộ data mẫu
-- Mật khẩu mặc định tất cả tài khoản: 123456
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ============================================================
-- 1. ROLES
-- ============================================================
TRUNCATE TABLE roles;

INSERT INTO roles (id, name, display_name, description) VALUES
(1, 'admin',    'Quản trị viên', 'Quản trị hệ thống, không tham gia nghỉ phép'),
(2, 'director', 'Giám đốc',      'Cấp cao nhất, duyệt đơn HR và Manager trực tiếp'),
(3, 'hr',       'Nhân sự',       'Quản lý nhân sự, duyệt đơn Manager'),
(4, 'manager',  'Quản lý',       'Trưởng phòng, duyệt đơn Employee'),
(5, 'employee', 'Nhân viên',     'Nhân viên, gửi đơn nghỉ phép');

-- ============================================================
-- 2. DEPARTMENTS
-- ============================================================
TRUNCATE TABLE departments;

INSERT INTO departments (id, name, description) VALUES
(1, 'IT',  'Phòng Công nghệ thông tin'),
(2, 'HR',  'Phòng Nhân sự'),
(3, 'KD',  'Phòng Kinh doanh');

-- ============================================================
-- 3. USERS
-- Thứ tự: Admin → Director → HR → Manager → Employee
-- manager_id theo sơ đồ tổ chức:
--   Director  (id=2): manager_id = NULL
--   HR        (id=3): manager_id = 2 (Director)
--   Mgr IT    (id=4): manager_id = 2 (Director)
--   Mgr KD    (id=5): manager_id = 2 (Director)
--   Emp IT 1  (id=6): manager_id = 4 (Manager IT)
--   Emp IT 2  (id=7): manager_id = 4 (Manager IT)
--   Emp KD    (id=8): manager_id = 5 (Manager KD)
--   Emp HR    (id=9): manager_id = 3 (HR)
-- ============================================================
TRUNCATE TABLE users;

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
-- 4. LEAVE TYPES
-- priority_order: số nhỏ = ưu tiên dùng trước
-- can_combine: 1 = tự động ghép, 0 = chọn thủ công
-- ============================================================
TRUNCATE TABLE leave_types;

INSERT INTO leave_types (id, name, max_days_per_year, is_paid, requires_document, carry_over_days, is_active, priority_order, can_combine, gender_restriction, policy_description) VALUES
(1, 'Phép năm',      12,  1, 0, 5, 1, 3, 1, NULL,     'Nhân viên được 12 ngày phép năm có lương mỗi năm. Thâm niên 5–10 năm: 14 ngày. Trên 10 năm: 16 ngày. Tối đa 5 ngày chuyển sang năm sau.'),
(2, 'Phép ốm',       30,  1, 1, 0, 1, 4, 0, NULL,     'Tối đa 30 ngày/năm, hưởng 75% lương do BHXH chi trả. Nghỉ trên 2 ngày liên tục cần nộp giấy xác nhận của cơ sở y tế.'),
(3, 'Phép bù',       999, 1, 0, 0, 1, 2, 1, NULL,     'Tích lũy từ những ngày làm thêm vào ngày nghỉ hoặc ngày lễ. 1 ngày OT = 1 ngày phép bù. Phép bù không chuyển sang năm sau.'),
(4, 'Phép đặc biệt', 9,   1, 1, 0, 1, 1, 0, NULL,     'Kết hôn: 3 ngày. Con cái kết hôn: 1 ngày. Tang cha/mẹ/vợ/chồng/con: 3 ngày. Tang ông/bà/anh/chị/em: 1 ngày.'),
(5, 'Thai sản',      180, 1, 1, 0, 1, 0, 0, 'female', 'Áp dụng cho lao động nữ. Tối đa 6 tháng (180 ngày), hưởng 100% lương bình quân đóng BHXH 6 tháng trước khi nghỉ.'),
(6, 'Không lương',   999, 0, 0, 0, 1, 5, 1, NULL,     'Áp dụng khi đã dùng hết tất cả các loại phép có lương. Không được hưởng lương trong thời gian nghỉ.');

-- ============================================================
-- 5. LEAVE BALANCES
-- Tính số ngày theo tháng còn lại trong năm
-- Bỏ Admin (id=1) vì không tham gia nghỉ phép
-- Phép không giới hạn (999) → total_days = 0 ban đầu
-- ============================================================
TRUNCATE TABLE leave_balances;

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

-- ============================================================
-- 6. Bật lại FK và hoàn tất
-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;

-- Kiểm tra nhanh
SELECT 'roles' as bang, COUNT(*) as so_luong FROM roles
UNION ALL SELECT 'users',         COUNT(*) FROM users
UNION ALL SELECT 'departments',   COUNT(*) FROM departments
UNION ALL SELECT 'leave_types',   COUNT(*) FROM leave_types
UNION ALL SELECT 'leave_balances',COUNT(*) FROM leave_balances;
