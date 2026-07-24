-- ============================================================
-- PATCH v2: Create ALL missing tables + Fix accounts role ENUM
-- Database: sanipjgf_sanity_db
-- Run this in phpMyAdmin > SQL tab
-- ============================================================

-- Fix 1: Add 'accounts' role to ENUM (fixes accountant login)
ALTER TABLE users
  MODIFY COLUMN role ENUM('admin','timetabler','teacher','parent','student','accounts') NOT NULL DEFAULT 'teacher';

-- Fix 2: Repair any users whose role was saved as '' (empty string)
UPDATE users SET role = 'accounts' WHERE role = '';

-- Fix 3: Create student_pricing table
CREATE TABLE IF NOT EXISTS student_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL UNIQUE,
    price_online_meet  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price_online_zoom  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price_school       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price_home_visit   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Fix 4: Create student_invoices table
CREATE TABLE IF NOT EXISTS student_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance DECIMAL(12,2) GENERATED ALWAYS AS (total_amount - amount_paid) STORED,
    status ENUM('draft','sent','partial','paid','overdue') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Fix 5: Create student_payments table
CREATE TABLE IF NOT EXISTS student_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    invoice_id INT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('mpesa','bank_transfer','cash','cheque','other') NOT NULL DEFAULT 'mpesa',
    reference_number VARCHAR(100) NULL,
    notes TEXT NULL,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES student_invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Fix 6: Create teacher_pricing table
CREATE TABLE IF NOT EXISTS teacher_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL UNIQUE,
    pay_online_meet  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pay_online_zoom  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pay_school       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pay_home_visit   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Fix 7: Create teacher_payments table
CREATE TABLE IF NOT EXISTS teacher_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    reference VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Verification: show all tables in the database
SHOW TABLES;
