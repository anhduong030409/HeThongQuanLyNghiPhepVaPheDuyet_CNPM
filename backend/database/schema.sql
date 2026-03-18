CREATE DATABASE IF NOT EXISTS quan_ly_nghi_phep;
USE quan_ly_nghi_phep;

-- Bang vai tro
CREATE TABLE roles (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         ENUM('admin','hr','manager','employee') NOT NULL,
    display_name VARCHAR(50),
    description  TEXT
);

-- Bang phong ban
CREATE TABLE departments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) UNIQUE NOT NULL,
    manager_id  INT NULL,
    description TEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Bang nguoi dung
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    role_id       INT NOT NULL,
    department_id INT,
    manager_id    INT NULL,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone         VARCHAR(20),
    is_active     BOOL DEFAULT 1,
    avatar_url    VARCHAR(255),
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id)       REFERENCES roles(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (manager_id)    REFERENCES users(id)
);

-- Them FK manager_id cho departments
ALTER TABLE departments
ADD FOREIGN KEY (manager_id) REFERENCES users(id);

-- Bang loai nghi phep
CREATE TABLE leave_types (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    name               VARCHAR(100) NOT NULL,
    max_days_per_year  INT DEFAULT 0,
    is_paid            BOOL DEFAULT 1,
    requires_document  BOOL DEFAULT 0,
    carry_over_days    INT DEFAULT 0,
    is_active          BOOL DEFAULT 1
);

-- Bang don xin nghi phep
CREATE TABLE leave_requests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date   DATE NOT NULL,
    end_date     DATE NOT NULL,
    total_days   DECIMAL(5,1) NOT NULL,
    reason       TEXT,
    status       ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
    document_url VARCHAR(255),
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    cancelled_by INT NULL,
    cancelled_at DATETIME NULL,
    cancel_reason TEXT NULL,
    FOREIGN KEY (user_id)       REFERENCES users(id),
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id),
    FOREIGN KEY (cancelled_by)  REFERENCES users(id)
);

-- Bang duyet don (2 cap)
CREATE TABLE leave_approvals (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    request_id     INT NOT NULL,
    approver_id    INT NOT NULL,
    approval_level TINYINT NOT NULL COMMENT '1=Manager, 2=HR',
    decision       ENUM('approved','rejected') NOT NULL,
    comment        TEXT,
    decided_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (request_id, approval_level),
    FOREIGN KEY (request_id)  REFERENCES leave_requests(id),
    FOREIGN KEY (approver_id) REFERENCES users(id)
);

-- Bang so ngay nghi con lai
CREATE TABLE leave_balances (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    leave_type_id INT NOT NULL,
    year          YEAR NOT NULL,
    total_days    DECIMAL(5,1) DEFAULT 0,
    used_days     DECIMAL(5,1) DEFAULT 0,
    FOREIGN KEY (user_id)       REFERENCES users(id),
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)
);

-- Bang thong bao
CREATE TABLE notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    request_id INT,
    type       ENUM('submitted','approved','rejected','cancelled'),
    message    TEXT,
    is_read    BOOL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id),
    FOREIGN KEY (request_id) REFERENCES leave_requests(id)
);

-- ===== DU LIEU MAU =====
INSERT INTO roles (name, display_name) VALUES
('admin',    'Quan tri vien'),
('hr',       'Nhan su'),
('manager',  'Quan ly'),
('employee', 'Nhan vien');

INSERT INTO departments (name, description) VALUES
('IT',  'Phong Cong nghe thong tin'),
('HR',  'Phong Nhan su'),
('KD',  'Phong Kinh doanh');

INSERT INTO users (role_id, department_id, full_name, email, password_hash, phone) VALUES
(2, 2, 'HR Admin',     'hr@gmail.com',      MD5('123456'), '0900000001'),
(3, 1, 'Manager IT',   'manager@gmail.com', MD5('123456'), '0900000002'),
(4, 1, 'Nguyen Van A', 'nva@gmail.com',     MD5('123456'), '0900000003'),
(4, 1, 'Tran Thi B',   'ttb@gmail.com',     MD5('123456'), '0900000004');

INSERT INTO leave_types (name, max_days_per_year, is_paid, requires_document) VALUES
('Nghi phep nam',   12, 1, 0),
('Nghi om',          5, 1, 1),
('Nghi viec rieng',  3, 0, 0);

INSERT INTO leave_balances (user_id, leave_type_id, year, total_days, used_days) VALUES
(3, 1, 2026, 12, 0),
(3, 2, 2026,  5, 0),
(4, 1, 2026, 12, 0),
(4, 2, 2026,  5, 0);



