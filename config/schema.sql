-- ============================================================
--  AquaBill Water Billing System – Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS aquabill CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aquabill;

-- Users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin','Staff') NOT NULL DEFAULT 'Staff',
    avatar CHAR(2) DEFAULT 'U',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customers
CREATE TABLE IF NOT EXISTS customers (
    id VARCHAR(10) PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address TEXT NOT NULL,
    contact VARCHAR(20) NOT NULL,
    meter_no VARCHAR(30) NOT NULL UNIQUE,
    status ENUM('Active','Disconnected') NOT NULL DEFAULT 'Active',
    created_at DATE NOT NULL
);

-- Meter Readings
CREATE TABLE IF NOT EXISTS readings (
    id VARCHAR(10) PRIMARY KEY,
    customer_id VARCHAR(10) NOT NULL,
    reading_date DATE NOT NULL,
    previous_reading DECIMAL(10,2) NOT NULL DEFAULT 0,
    current_reading DECIMAL(10,2) NOT NULL,
    consumption DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Bills
CREATE TABLE IF NOT EXISTS bills (
    id VARCHAR(10) PRIMARY KEY,
    customer_id VARCHAR(10) NOT NULL,
    reading_id VARCHAR(10),
    billing_month VARCHAR(30) NOT NULL,
    prev_reading DECIMAL(10,2) NOT NULL,
    curr_reading DECIMAL(10,2) NOT NULL,
    consumption DECIMAL(10,2) NOT NULL,
    rate_per_cubic DECIMAL(8,2) NOT NULL DEFAULT 35.00,
    base_charge DECIMAL(8,2) NOT NULL DEFAULT 120.00,
    penalty DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('Unpaid','Paid','Overdue','Waived','Disputed') NOT NULL DEFAULT 'Unpaid',
    due_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Payments
CREATE TABLE IF NOT EXISTS payments (
    id VARCHAR(10) PRIMARY KEY,
    bill_id VARCHAR(10) NOT NULL,
    customer_id VARCHAR(10) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    method ENUM('Cash','GCash','PayMaya','Bank Transfer','Check') NOT NULL DEFAULT 'Cash',
    receipt_no VARCHAR(20) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- ─── SEED DATA ────────────────────────────────────────────────────────────────
-- Users (passwords stored as plain text for demo; system login accepts both plain and bcrypt)
-- To use bcrypt: UPDATE users SET password=PASSWORD_HASH_HERE WHERE email='admin@barangay.gov';
INSERT IGNORE INTO users (name, email, password, role, avatar) VALUES
('Admin User',   'admin@barangay.gov', 'admin123', 'Admin', 'A'),
('Staff Member', 'staff@barangay.gov', 'staff123', 'Staff', 'S');

INSERT IGNORE INTO customers VALUES
('C001','Maria Santos','Blk 3 Lot 5, Sta. Ana St.','09171234567','M-0011','Active','2022-01-15'),
('C002','Jose Reyes','45 Rizal Ave., Brgy. Uno','09182345678','M-0022','Active','2022-03-20'),
('C003','Ana Gonzales','12 Mabini St., Brgy. Dos','09193456789','M-0033','Active','2021-11-10'),
('C004','Pedro Cruz','67 Del Pilar St.','09204567890','M-0044','Disconnected','2020-06-05'),
('C005','Lina Bautista','89 Bonifacio Rd.','09215678901','M-0055','Active','2023-02-28'),
('C006','Ramon Dela Cruz','22 Aguinaldo St.','09226789012','M-0066','Active','2022-08-14');

INSERT IGNORE INTO readings VALUES
('R001','C001','2024-12-05',1200,1225,25),
('R002','C002','2024-12-06',980,998,18),
('R003','C003','2024-12-07',2340,2368,28),
('R004','C005','2024-12-08',560,582,22),
('R005','C006','2024-12-09',1750,1773,23),
('R006','C001','2025-01-05',1225,1252,27),
('R007','C002','2025-01-06',998,1019,21),
('R008','C003','2025-01-07',2368,2401,33);

INSERT IGNORE INTO bills VALUES
('B001','C001','R001','December 2024',1200,1225,25,35,120,0,995,'Paid','2025-01-10',NOW()),
('B002','C002','R002','December 2024',980,998,18,35,120,0,750,'Paid','2025-01-10',NOW()),
('B003','C003','R003','December 2024',2340,2368,28,35,120,0,1100,'Unpaid','2025-01-10',NOW()),
('B004','C005','R004','December 2024',560,582,22,35,120,50,940,'Overdue','2025-01-10',NOW()),
('B005','C006','R005','December 2024',1750,1773,23,35,120,0,925,'Unpaid','2025-01-10',NOW()),
('B006','C001','R006','January 2025',1225,1252,27,35,120,0,1065,'Unpaid','2025-02-10',NOW()),
('B007','C002','R007','January 2025',998,1019,21,35,120,0,855,'Paid','2025-02-10',NOW()),
('B008','C003','R008','January 2025',2368,2401,33,35,120,0,1275,'Unpaid','2025-02-10',NOW());

INSERT IGNORE INTO payments VALUES
('P001','B001','C001',995,'2025-01-08','Cash','RCP-0001','Full payment',NOW()),
('P002','B002','C002',750,'2025-01-09','GCash','RCP-0002','',NOW()),
('P003','B007','C002',855,'2025-02-07','Cash','RCP-0003','',NOW());

-- Billing Rate Tiers (created on first visit to rates.php, or run this now)
CREATE TABLE IF NOT EXISTS billing_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(80) NOT NULL,
    min_cubic DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_cubic DECIMAL(10,2) DEFAULT NULL,
    rate_per_cubic DECIMAL(8,2) NOT NULL,
    base_charge DECIMAL(8,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO billing_rates (label, min_cubic, max_cubic, rate_per_cubic, base_charge) VALUES
('Lifeline (0–10 m³)',     0,  10, 20.00, 120.00),
('Basic (11–20 m³)',      11,  20, 30.00,   0.00),
('Standard (21–40 m³)',   21,  40, 35.00,   0.00),
('Commercial (41–100 m³)',41, 100, 45.00,   0.00),
('Industrial (>100 m³)', 101,NULL, 55.00,   0.00);
