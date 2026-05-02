CREATE DATABASE IF NOT EXISTS bayantap_db;
USE bayantap_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('treasurer', 'superuser') DEFAULT 'treasurer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    description VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS residents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    household_id VARCHAR(20) UNIQUE,
    block_no VARCHAR(20) NOT NULL,
    lot_no VARCHAR(20) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    initial_meter INT DEFAULT 0,
    monthly_rate DECIMAL(10,2) DEFAULT 0.00,
    contact_number VARCHAR(30),
    status ENUM('paid', 'unpaid', 'pending') DEFAULT 'unpaid',
    access_token VARCHAR(64) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS billings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resident_id INT NOT NULL,
    billing_month VARCHAR(20) NOT NULL,
    previous_reading INT NOT NULL,
    current_reading INT NOT NULL,
    usage_m3 INT NOT NULL,
    amount_due DECIMAL(10,2) NOT NULL,
    status ENUM('paid', 'unpaid', 'pending') DEFAULT 'unpaid',
    paid_date DATETIME NULL DEFAULT NULL,
    receipt_no VARCHAR(50) NULL DEFAULT NULL,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_no VARCHAR(50) NOT NULL UNIQUE,
    resident_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    treasurer_id INT,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
);

-- Insert dummy treasurer account (username: treasurer, password: bayantap2026)
INSERT IGNORE INTO users (username, password_hash, role) VALUES ('treasurer', '$2y$10$WMNoVjBHd3pKXEy0swnE4ObanwTSi4nPzLH2uW/BlUfhRODxsaOem', 'treasurer');

-- Insert dummy superuser account (username: admin, password: superuser2026)
INSERT IGNORE INTO users (username, password_hash, role) VALUES ('admin', '$2y$10$zdp15ZP5QsngIuNtSsbiFOrbENXIBRTZdF66FRwmJdNcPM.Vl8boO', 'superuser');

-- Insert initial current_rate (33.70 per cubic meter)
INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES ('current_rate', '33.70', 'Price per cubic meter for water usage');

-- Insert dummy residents
INSERT IGNORE INTO residents (id, household_id, block_no, lot_no, full_name, initial_meter, monthly_rate, status) VALUES 
(1, 'BT-0001', 'Blk 9', 'Lot 2', 'Justin Basco', 1245, 25.00, 'paid'),
(2, 'BT-0002', 'Blk 08', 'Lot 3', 'James Tanglao', 2156, 27.65, 'paid'),
(3, 'BT-0003', 'Blk 5', 'Lot 6', 'Ian Patrick Reyes', 987, 25.00, 'unpaid'),
(4, 'BT-0004', 'Blk 15', 'Lot 2', 'Janella Ashley S. Gomez', 1246, 30.00, 'paid');

-- Insert dummy billings
INSERT IGNORE INTO billings (id, resident_id, billing_month, previous_reading, current_reading, usage_m3, amount_due, status, paid_date, receipt_no) VALUES 
(1, 1, 'Jan 2026', 1245, 1268, 23, 575.00, 'paid', '2026-01-15 10:00:00', 'MV-2026-0001'),
(2, 2, 'Jan 2026', 2156, 2189, 32, 885.00, 'paid', '2026-01-11 11:30:00', 'MV-2026-0002'),
(3, 3, 'Jan 2026', 987, 1015, 28, 700.00, 'unpaid', NULL, NULL),
(4, 4, 'Jan 2026', 1246, 2268, 27, 960.00, 'paid', '2026-01-19 14:15:00', 'MV-2026-0004');

-- Insert dummy transactions (matching paid billings)
INSERT IGNORE INTO transactions (receipt_no, resident_id, amount_paid, payment_date, treasurer_id) VALUES
('MV-2026-0001', 1, 575.00, '2026-01-15 10:00:00', 1),
('MV-2026-0002', 2, 885.00, '2026-01-11 11:30:00', 1),
('MV-2026-0004', 4, 960.00, '2026-01-19 14:15:00', 1);
