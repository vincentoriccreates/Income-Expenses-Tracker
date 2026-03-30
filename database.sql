-- ============================================================
-- White Villas Resort - Income & Expenses Tracker
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS wvr_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wvr_tracker;

-- ============================================================
-- Categories Table
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('income','expense') NOT NULL,
    color VARCHAR(7) DEFAULT '#6c757d',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- Petty Expenses Table
-- ============================================================
CREATE TABLE IF NOT EXISTS petty_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    week_number INT,
    month VARCHAR(20),
    category_id INT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_date (date),
    INDEX idx_week (week_number),
    INDEX idx_month (month)
) ENGINE=InnoDB;

-- ============================================================
-- H/L (High/Low) Expenses Table
-- ============================================================
CREATE TABLE IF NOT EXISTS hl_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    week_number INT,
    month VARCHAR(20),
    category_id INT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_date (date),
    INDEX idx_week (week_number),
    INDEX idx_month (month)
) ENGINE=InnoDB;

-- ============================================================
-- Income - Paid by Cash Table
-- ============================================================
CREATE TABLE IF NOT EXISTS income_cash (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    week_number INT,
    month VARCHAR(20),
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (date),
    INDEX idx_category (category),
    INDEX idx_week (week_number)
) ENGINE=InnoDB;

-- ============================================================
-- Income - Paid by Card Table
-- ============================================================
CREATE TABLE IF NOT EXISTS income_card (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    week_number INT,
    month VARCHAR(20),
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (date),
    INDEX idx_category (category),
    INDEX idx_week (week_number)
) ENGINE=InnoDB;

-- ============================================================
-- Income - Room Charged Table
-- ============================================================
CREATE TABLE IF NOT EXISTS income_roomcharged (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    room_reference VARCHAR(100) DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    week_number INT,
    month VARCHAR(20),
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (date),
    INDEX idx_week (week_number)
) ENGINE=InnoDB;

-- ============================================================
-- Seed Default Categories
-- ============================================================
INSERT INTO categories (name, type, color) VALUES
-- Expense Categories
('Petty Expenses', 'expense', '#dc3545'),
('H/L Expenses', 'expense', '#fd7e14'),
('Salary', 'expense', '#6f42c1'),
('Utilities', 'expense', '#20c997'),
('Maintenance', 'expense', '#0dcaf0'),
('Supplies', 'expense', '#6c757d'),
('Food & Beverages', 'expense', '#ffc107'),
('Transportation', 'expense', '#198754'),
-- Income Categories
('Resto Income', 'income', '#0d6efd'),
('Drinks Income', 'income', '#6610f2'),
('Rooms Income', 'income', '#d63384'),
('Motor Income', 'income', '#20c997'),
('Other Income', 'income', '#ffc107'),
('Room Charged', 'income', '#0dcaf0');

-- ============================================================
-- Sample Data (September 2025 - first few rows)
-- ============================================================
INSERT INTO petty_expenses (date, description, amount, week_number, month) VALUES
('2025-09-01', 'LACTACYD c/o Ma''am Edna', 105.00, 36, 'September'),
('2025-09-01', 'Laras sa Kahoy', 50.00, 36, 'September'),
('2025-09-01', '6 pcs. Masking Tape', 330.00, 36, 'September'),
('2025-09-01', '2 Litters Declogger', 398.00, 36, 'September'),
('2025-09-01', 'Parcel', 599.00, 36, 'September'),
('2025-09-01', 'SIQ. Cable Bill', 2110.00, 36, 'September'),
('2025-09-01', 'Native Eggs', 200.00, 36, 'September'),
('2025-09-01', '5 pcs. White Envelope', 100.00, 36, 'September'),
('2025-09-01', 'PROSIELCO PAYMENT', 23135.94, 36, 'September'),
('2025-09-01', 'GASOLINE', 442.00, 36, 'September'),
('2025-09-01', 'SALARY Nickie', 3000.00, 36, 'September'),
('2025-09-01', 'c/o Daye', 1500.00, 36, 'September'),
('2025-09-01', '3 days Motorbike c/o Banoy', 1500.00, 36, 'September'),
('2025-09-01', 'c/o Eng. J.B', 500.00, 36, 'September'),
('2025-09-01', 'Guard Salary', 10615.20, 36, 'September');

INSERT INTO hl_expenses (date, description, amount, week_number, month) VALUES
('2025-09-01', 'Drinks Resto Expenses', 1221.00, 36, 'September'),
('2025-09-01', 'H/L EXPENSES', 4099.00, 36, 'September'),
('2025-09-03', 'Drinks Expenses', 0.00, 36, 'September'),
('2025-09-03', 'Resto Expenses', 1360.05, 36, 'September'),
('2025-09-04', 'Resto Expenses', 894.80, 36, 'September'),
('2025-09-05', 'Drinks Expenses', 964.00, 36, 'September'),
('2025-09-05', 'H/L EXPENSES', 340.00, 36, 'September'),
('2025-09-05', 'Resto Expenses (Kitchen)', 4917.60, 36, 'September'),
('2025-09-06', 'H/L Expenses', 1170.00, 36, 'September'),
('2025-09-06', 'Resto Expenses (Kitchen)', 1845.35, 36, 'September'),
('2025-09-06', 'Drinks Expenses (BAR)', 10311.00, 36, 'September');

INSERT INTO income_cash (date, category, amount, week_number, month) VALUES
('2025-09-01', 'Resto Income', 7955.00, 36, 'September'),
('2025-09-01', 'Other Income', 1080.00, 36, 'September'),
('2025-09-01', 'Drinks Income', 1035.00, 36, 'September'),
('2025-09-01', 'Rooms Income', 0.00, 36, 'September'),
('2025-09-01', 'MotorBike Income', 0.00, 36, 'September'),
('2025-09-02', 'Other', 2780.00, 36, 'September'),
('2025-09-03', 'Resto Income', 4700.00, 36, 'September'),
('2025-09-04', 'Resto Income', 1106.00, 36, 'September'),
('2025-09-04', 'Other Income', 135.00, 36, 'September'),
('2025-09-05', 'Resto Income', 525.00, 36, 'September'),
('2025-09-05', 'Drinks Income', 85.00, 36, 'September'),
('2025-09-05', 'MotorBike Income', 500.00, 36, 'September');

INSERT INTO income_card (date, category, amount, week_number, month) VALUES
('2025-09-01', 'Drinks Income', 160.00, 36, 'September'),
('2025-09-01', 'Motor Income', 1500.00, 36, 'September'),
('2025-09-01', 'Other Income', 400.00, 36, 'September'),
('2025-09-01', 'Resto Income', 830.00, 36, 'September'),
('2025-09-07', 'Resto Income', 1716.00, 36, 'September'),
('2025-09-08', 'Motor Income', 1500.00, 37, 'September'),
('2025-09-08', 'Resto Income', 745.00, 37, 'September'),
('2025-09-08', 'Drinks Income', 340.00, 37, 'September'),
('2025-09-11', 'Resto Income', 7270.00, 37, 'September'),
('2025-09-11', 'Other Income', 4910.00, 37, 'September'),
('2025-09-11', 'Drinks Income', 6865.00, 37, 'September');

INSERT INTO income_roomcharged (date, room_reference, amount, week_number, month) VALUES
('2025-09-01', NULL, 2525.00, 36, 'September'),
('2025-09-02', NULL, 3795.00, 36, 'September'),
('2025-09-03', NULL, 0.00, 36, 'September'),
('2025-09-04', NULL, 2905.00, 36, 'September'),
('2025-09-05', 'B3, B4, B5', 7065.00, 36, 'September'),
('2025-09-05', 'V2, P2, P7', 2160.00, 36, 'September'),
('2025-09-06', 'V2,P2, P7', 1716.00, 36, 'September'),
('2025-09-06', 'P4', 1540.00, 36, 'September'),
('2025-09-06', 'B3, B4, B5', 8415.00, 36, 'September'),
('2025-09-07', 'B3, B4, B5', 10515.00, 36, 'September'),
('2025-09-07', 'P4', 2085.00, 36, 'September');
