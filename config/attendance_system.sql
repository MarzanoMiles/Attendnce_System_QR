-- attendance_system.sql
-- Full database schema for Automated Student Attendance Monitoring System
-- San Pablo City Central School - Kindergarten Department

CREATE DATABASE IF NOT EXISTS attendance_system;
USE attendance_system;

-- ============================================================
-- TABLE: users (admin + teachers)
-- ============================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin','teacher') NOT NULL DEFAULT 'teacher',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: sections (kindergarten sections)
-- ============================================================
CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_name VARCHAR(50) NOT NULL,
    adviser_id INT,
    school_year VARCHAR(20) DEFAULT '2024-2025',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (adviser_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: students
-- ============================================================
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lrn VARCHAR(20) UNIQUE,                      -- Learner Reference Number
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    gender ENUM('Male','Female') NOT NULL,
    birth_date DATE,
    address TEXT,
    section_id INT,
    photo VARCHAR(255) DEFAULT 'default.png',
    qr_code VARCHAR(255),                        -- path to QR image
    qr_token VARCHAR(100) UNIQUE,                -- unique scan token
    parent_name VARCHAR(100),
    parent_contact VARCHAR(20),                  -- for SMS
    parent_email VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: attendance
-- ============================================================
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    date DATE NOT NULL,
    time_in TIME,
    time_out TIME,
    status ENUM('present','absent','late','excused') DEFAULT 'present',
    remarks TEXT,
    recorded_by INT,                             -- user who recorded
    scan_type ENUM('qr','manual') DEFAULT 'qr',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_attendance (student_id, date)  -- one record per day
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: sms_logs
-- ============================================================
CREATE TABLE sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    recipient_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('arrival','departure','absence') NOT NULL,
    status ENUM('sent','failed','pending') DEFAULT 'pending',
    api_response TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: system_settings
-- ============================================================
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- SEED: Default admin user (password: admin123)
-- ============================================================
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@spccs.edu.ph', 'admin'),
('teacher1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maria Santos', 'msantos@spccs.edu.ph', 'teacher'),
('teacher2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jose Reyes', 'jreyes@spccs.edu.ph', 'teacher');

-- NOTE: Password hash above is for 'password' (Laravel default hash used for demo)
-- For production use: password_hash('admin123', PASSWORD_BCRYPT)
-- Run this to get the correct hash and update manually.

-- ============================================================
-- SEED: Sections
-- ============================================================
INSERT INTO sections (section_name, adviser_id, school_year) VALUES
('Sampaguita', 2, '2024-2025'),
('Rosal', 3, '2024-2025'),
('Camia', NULL, '2024-2025');

-- ============================================================
-- SEED: Sample students
-- ============================================================
INSERT INTO students (lrn, first_name, middle_name, last_name, gender, birth_date, section_id, parent_name, parent_contact, qr_token) VALUES
('100000000001', 'Juan', 'Cruz', 'Dela Cruz', 'Male', '2019-03-15', 1, 'Maria Dela Cruz', '09171234567', 'STU-100000000001-A1B2C3'),
('100000000002', 'Ana', 'Reyes', 'Santos', 'Female', '2019-06-20', 1, 'Pedro Santos', '09182345678', 'STU-100000000002-D4E5F6'),
('100000000003', 'Miguel', 'Gomez', 'Garcia', 'Male', '2019-01-10', 2, 'Rosa Garcia', '09193456789', 'STU-100000000003-G7H8I9'),
('100000000004', 'Sofia', 'Lim', 'Torres', 'Female', '2019-09-05', 2, 'Luis Torres', '09204567890', 'STU-100000000004-J1K2L3'),
('100000000005', 'Carlos', 'Bautista', 'Villanueva', 'Male', '2019-04-22', 1, 'Carmen Villanueva', '09215678901', 'STU-100000000005-M4N5O6');

-- ============================================================
-- SEED: System settings
-- ============================================================
INSERT INTO system_settings (setting_key, setting_value) VALUES
('school_name', 'San Pablo City Central School'),
('school_address', 'San Pablo City, Laguna'),
('school_year', '2024-2025'),
('grade_level', 'Kindergarten'),
('time_in_start', '07:00:00'),
('time_in_end', '08:00:00'),
('late_threshold', '07:31:00'),
('time_out_start', '11:00:00'),
('time_out_end', '12:00:00'),
('semaphore_api_key', ''),
('semaphore_sender_name', 'SPCCS'),
('sms_arrival_template', 'Good day! {student_name} has arrived at school at {time}. - SPCCS Kindergarten'),
('sms_departure_template', 'Good day! {student_name} has left school at {time}. - SPCCS Kindergarten'),
('sms_absence_template', 'Good day! {student_name} was marked ABSENT today {date}. Please inform the school. - SPCCS Kindergarten');

-- ============================================================
-- SEED: Sample attendance records
-- ============================================================
INSERT INTO attendance (student_id, date, time_in, time_out, status, scan_type, recorded_by) VALUES
(1, CURDATE(), '07:15:00', NULL, 'present', 'qr', 2),
(2, CURDATE(), '07:45:00', NULL, 'late', 'qr', 2),
(3, CURDATE(), NULL, NULL, 'absent', 'manual', 3),
(4, CURDATE(), '07:10:00', '11:30:00', 'present', 'qr', 3),
(5, CURDATE(), '07:20:00', NULL, 'present', 'qr', 2);