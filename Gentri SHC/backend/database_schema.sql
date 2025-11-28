
CREATE DATABASE IF NOT EXISTS `superhealth_system`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `superhealth_system`;

-- =====================================================
-- CORE TABLES
-- =====================================================

-- Users table (consolidated - includes staff and doctor info)
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','staff','doctor') NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `first_name` VARCHAR(50) NULL,
  `last_name` VARCHAR(50) NULL,
  `middle_name` VARCHAR(50) NULL,
  `department_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uniq_username_role` (`username`,`role`),
  UNIQUE KEY `uniq_email` (`email`),
  KEY `idx_user_department_id` (`department_id`),
  KEY `idx_users_role_department` (`role`, `department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Departments table
CREATE TABLE IF NOT EXISTS `departments` (
  `department_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_name` VARCHAR(100) NOT NULL,
  `department_description` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `uniq_department_name` (`department_name`),
  KEY `idx_departments_name` (`department_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key constraint for users-departments relationship
ALTER TABLE `users` 
ADD CONSTRAINT `fk_user_department` FOREIGN KEY (`department_id`) 
REFERENCES `departments`(`department_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- =====================================================
-- PATIENT MANAGEMENT TABLES
-- =====================================================

-- Patients table (separate from users - patients don't need login)
CREATE TABLE IF NOT EXISTS `patients` (
  `patient_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `case_number` VARCHAR(20) NOT NULL,
  `philhealth_id` VARCHAR(50) NULL,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `middle_name` VARCHAR(50) NULL,
  `extension_name` VARCHAR(10) NULL,
  `date_of_birth` DATE NOT NULL,
  `age` INT UNSIGNED NOT NULL,
  `sex` ENUM('Male', 'Female') NOT NULL,
  `phone` VARCHAR(20) NULL,
  `address` TEXT NULL,
  `emergency_contact` VARCHAR(100) NULL,
  `emergency_phone` VARCHAR(20) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`patient_id`),
  UNIQUE KEY `uniq_case_number` (`case_number`),
  UNIQUE KEY `uniq_philhealth_id` (`philhealth_id`),
  KEY `idx_patient_name` (`last_name`, `first_name`),
  KEY `idx_patient_dob` (`date_of_birth`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Patient visits table for tracking each healthcare encounter
CREATE TABLE IF NOT EXISTS `patient_visits` (
  `visit_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` INT UNSIGNED NOT NULL,
  `department_id` INT UNSIGNED NOT NULL,
  `visit_type` ENUM('New', 'Old (Follow-up)') NOT NULL,
  `client_type` ENUM('PWD', 'Senior', 'Pregnant', 'Regular') NOT NULL,
  `priority_level` ENUM('Emergency', 'Priority', 'Regular') NOT NULL DEFAULT 'Regular',
  `status` ENUM('Waiting', 'In Progress', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Waiting',
  `queue_number` INT UNSIGNED NULL,
  `assigned_doctor_id` INT UNSIGNED NULL,
  `visit_date` DATE NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`visit_id`),
  KEY `idx_visit_patient` (`patient_id`),
  KEY `idx_visit_department` (`department_id`),
  KEY `idx_visit_doctor` (`assigned_doctor_id`),
  KEY `idx_visit_date` (`visit_date`),
  KEY `idx_visit_status` (`status`),
  CONSTRAINT `fk_visit_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_visit_department` FOREIGN KEY (`department_id`) REFERENCES `departments`(`department_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_visit_doctor` FOREIGN KEY (`assigned_doctor_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Medical records table for storing medical information
CREATE TABLE IF NOT EXISTS `medical_records` (
  `record_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `visit_id` INT UNSIGNED NOT NULL,
  `chief_complaint` TEXT NULL,
  `symptoms_ros2` TEXT NULL,
  `symptoms_ros3` TEXT NULL,
  `symptoms_ros4` TEXT NULL,
  `symptoms_ros5` TEXT NULL,
  `symptoms_ros6` TEXT NULL,
  `symptoms_ros8` TEXT NULL,
  `ros2` VARCHAR(10) NULL,
  `ros3` VARCHAR(10) NULL,
  `ros4` VARCHAR(10) NULL,
  `ros5` VARCHAR(10) NULL,
  `ros6` VARCHAR(10) NULL,
  `ros8` VARCHAR(10) NULL,
  `last_menstrual_period` DATE NULL,
  `first_menstrual_period` DATE NULL,
  `number_of_pregnancies` INT NULL,
  `smoking_status` VARCHAR(50) NULL,
  `smoking_years` INT NULL,
  `alcohol_status` VARCHAR(50) NULL,
  `alcohol_years` INT NULL,
  `past_medical_history` TEXT NULL,
  `pmh_others` TEXT NULL,
  `vital_signs` JSON NULL,
  `diagnosis` TEXT NULL,
  `treatment` TEXT NULL,
  `prescription` TEXT NULL,
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`record_id`),
  KEY `idx_record_visit` (`visit_id`),
  KEY `idx_record_created_by` (`created_by`),
  CONSTRAINT `fk_record_visit` FOREIGN KEY (`visit_id`) REFERENCES `patient_visits`(`visit_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_record_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- QUEUE MANAGEMENT TABLES
-- =====================================================

-- Queue entries table for real-time queue management
CREATE TABLE IF NOT EXISTS `queue_entries` (
  `queue_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `patient_id` INT UNSIGNED NOT NULL,
  `visit_id` INT UNSIGNED NOT NULL,
  `department_id` INT UNSIGNED NOT NULL,
  `queue_number` INT UNSIGNED NOT NULL,
  `priority` ENUM('Emergency', 'Priority', 'Regular') NOT NULL DEFAULT 'Regular',
  `status` ENUM('Waiting', 'Called', 'Serving', 'Completed', 'No Show') NOT NULL DEFAULT 'Waiting',
  `estimated_wait_time` INT UNSIGNED NULL COMMENT 'Estimated wait time in minutes',
  `called_at` TIMESTAMP NULL,
  `served_at` TIMESTAMP NULL,
  `completed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`queue_id`),
  UNIQUE KEY `uniq_queue_visit` (`visit_id`),
  KEY `idx_queue_patient` (`patient_id`),
  KEY `idx_queue_department` (`department_id`),
  KEY `idx_queue_status` (`status`),
  KEY `idx_queue_priority` (`priority`),
  KEY `idx_queue_number` (`queue_number`),
  CONSTRAINT `fk_queue_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients`(`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_queue_visit` FOREIGN KEY (`visit_id`) REFERENCES `patient_visits`(`visit_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_queue_department` FOREIGN KEY (`department_id`) REFERENCES `departments`(`department_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- VIEWS FOR EASY DATA ACCESS
-- =====================================================

-- Create a view for easy patient queue display
CREATE OR REPLACE VIEW `patient_queue_view` AS
SELECT 
    q.queue_id,
    q.queue_number,
    q.priority,
    q.status,
    q.estimated_wait_time,
    p.patient_id,
    p.case_number,
    p.first_name,
    p.last_name,
    p.middle_name,
    p.age,
    p.sex,
    v.visit_type,
    v.client_type,
    d.department_name,
    q.created_at as queue_created_at
FROM queue_entries q
JOIN patients p ON q.patient_id = p.patient_id
JOIN patient_visits v ON q.visit_id = v.visit_id
JOIN departments d ON q.department_id = d.department_id
ORDER BY q.priority DESC, q.queue_number ASC;

-- =====================================================
-- FUNCTIONS AND TRIGGERS
-- =====================================================

DROP FUNCTION IF EXISTS `generate_case_number`;
DELIMITER $
CREATE FUNCTION `generate_case_number`() 
RETURNS VARCHAR(20)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE next_number INT;
    DECLARE case_num VARCHAR(20);
    SELECT COALESCE(MAX(CAST(SUBSTRING(case_number, 4) AS UNSIGNED)), 0) + 1 
        INTO next_number 
        FROM patients 
        WHERE case_number LIKE 'PT-%';
    SET case_num = CONCAT('PT-', LPAD(next_number, 4, '0'));
    RETURN case_num;
END$
DELIMITER ;

DROP TRIGGER IF EXISTS `generate_queue_number`;
DELIMITER $
CREATE TRIGGER `generate_queue_number`
BEFORE INSERT ON `queue_entries`
FOR EACH ROW
BEGIN
    DECLARE next_queue_num INT;
    SELECT COALESCE(MAX(queue_number), 0) + 1 
        INTO next_queue_num 
        FROM queue_entries 
        WHERE department_id = NEW.department_id 
          AND DATE(created_at) = CURDATE();
    SET NEW.queue_number = next_queue_num;
END$
DELIMITER ;

-- =====================================================
-- SAMPLE DATA INSERTION
-- =====================================================

-- Insert sample departments
INSERT IGNORE INTO `departments` (`department_name`, `department_description`) VALUES
('Medical', 'General Medical Services'),
('OPD', 'Out Patient Department'),
('Dental', 'Dental Services'),
('Animal Bite', 'Animal Bite Treatment'),
('Emergency', 'Emergency Services'),
('OB-GYNE', 'Obstetrics and Gynecology'),
('General Medicine', 'Primary care and internal medicine'),
('Pediatrics', 'Healthcare for infants, children, and adolescents'),
('Cardiology', 'Heart-related diagnostics and treatment'),
('Radiology', 'Imaging services including X-ray and ultrasound'),
('Surgery', 'Operative care and post-operative management');

-- Insert default admin users with hashed passwords
-- Default password for all accounts: 'Pogi'
-- Insert sample users (admin, doctor, staff) with required fields

INSERT INTO users (username, password, role, email, first_name, last_name) VALUES
('admin', MD5('admin123'), 'admin', '2025-1-0001@example.com', 'Admin', 'User'),
('doctor1', MD5('doctor123'), 'doctor', '2025-2-0002@example.com', 'Doctor', 'One'),
('staff1', MD5('staff123'), 'staff', '2025-3-0003@example.com', 'Staff', 'One');
