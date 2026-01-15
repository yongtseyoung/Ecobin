-- EcoBin Smart Waste Management System - Latest Clean Database (20 Tables)
-- University Malaysia Sabah (UMS) Residential College
-- Updated: November 11, 2025

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Database: ecobin

-- ============================================
-- 1. ADMINS
-- ============================================
CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY (`username`),
  UNIQUE KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admins` VALUES
(2, 'admin', 'admin123', 'admin@ecobin.ums.edu.my', 'Leopold', '0123456789', '2025-10-24 02:15:30', '2025-11-11 09:12:20', 'active'),
(3, 'Jordan', 'jordan123', 'Jordan@yahoo.com', 'Jordan Carter', '01110254968', '2025-10-26 17:32:45', '2025-10-26 17:35:35', 'active');

-- ============================================
-- 2. AREAS
-- ============================================
CREATE TABLE `areas` (
  `area_id` int(11) NOT NULL AUTO_INCREMENT,
  `area_name` varchar(50) NOT NULL,
  `block` varchar(10) DEFAULT NULL,
  `total_floors` int(11) NOT NULL DEFAULT 4,
  `total_bins` int(11) NOT NULL DEFAULT 4,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`area_id`),
  UNIQUE KEY (`area_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `areas` VALUES
(1, 'A1', 'Block A', 4, 4, 'Residential College Block A1', '2025-10-23 10:35:33'),
(2, 'A2', 'Block A', 4, 4, 'Residential College Block A2', '2025-10-23 10:35:33'),
(3, 'A3', 'Block A', 4, 4, 'Residential College Block A3', '2025-10-23 10:35:33'),
(4, 'B1', 'Block B', 4, 4, 'Residential College Block B1', '2025-10-23 10:35:33'),
(5, 'B2', 'Block B', 4, 4, 'Residential College Block B2', '2025-10-23 10:35:33');

-- ============================================
-- 3. EMPLOYEES
-- ============================================
CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('active','on_leave','inactive') DEFAULT 'active',
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY (`username`),
  UNIQUE KEY (`email`),
  FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `employees` VALUES
(4, 'Cyrileo', 'cleaner1234', 'cyrileo@yahoo.com', 'Cyril Leopold Yong', '01110547963', 1, '2025-10-25 14:35:00', '2025-11-11 00:58:05', 'active'),
(5, 'Ken', 'Cleaner123', 'kencarson@yahoo.com', 'Ken Carson', '01110547963', 2, '2025-10-26 17:47:17', '2025-10-30 11:18:05', 'active');

-- ============================================
-- 4. IOT_DEVICES
-- ============================================
CREATE TABLE `iot_devices` (
  `device_id` int(11) NOT NULL AUTO_INCREMENT,
  `device_code` varchar(50) NOT NULL,
  `device_mac_address` varchar(20) DEFAULT NULL,
  `device_model` varchar(50) DEFAULT NULL,
  `firmware_version` varchar(20) DEFAULT NULL,
  `battery_level` decimal(5,2) DEFAULT NULL,
  `signal_strength` int(11) DEFAULT NULL,
  `device_status` enum('online','offline','maintenance','error') DEFAULT 'offline',
  `installation_date` date DEFAULT NULL,
  `last_ping` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`device_id`),
  UNIQUE KEY (`device_code`),
  UNIQUE KEY (`device_mac_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. BINS
-- ============================================
CREATE TABLE `bins` (
  `bin_id` int(11) NOT NULL AUTO_INCREMENT,
  `area_id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `bin_code` varchar(50) NOT NULL,
  `floor_number` int(11) NOT NULL CHECK (`floor_number` between 1 and 4),
  `location_details` varchar(200) DEFAULT NULL,
  `bin_capacity` decimal(10,2) DEFAULT NULL,
  `current_fill_level` decimal(5,2) DEFAULT 0.00 CHECK (`current_fill_level` between 0 and 100),
  `gps_latitude` decimal(10,8) DEFAULT NULL,
  `gps_longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('normal','full','needs_maintenance','offline') DEFAULT 'normal',
  `last_collection` timestamp NULL DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_weight_reading` timestamp NULL DEFAULT NULL,
  `weight_sensor_status` enum('online','offline','error') DEFAULT 'offline',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `battery_level` decimal(5,2) DEFAULT 100.00,
  `current_weight` decimal(10,2) DEFAULT 0.00,
  `max_weight` decimal(8,2) DEFAULT 50.00,
  `lid_status` enum('open','closed') DEFAULT 'closed',
  `last_opened` datetime DEFAULT NULL,
  PRIMARY KEY (`bin_id`),
  UNIQUE KEY (`bin_code`),
  UNIQUE KEY (`device_id`),
  KEY `idx_area` (`area_id`),
  KEY `idx_status` (`status`),
  KEY `idx_fill_level` (`current_fill_level`),
  FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE CASCADE,
  FOREIGN KEY (`device_id`) REFERENCES `iot_devices` (`device_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `bins` VALUES
(1, 1, NULL, 'BIN-A1-F1', 1, 'KKTPAR', 50.00, 0.00, 5.98550000, 116.06830000, 'normal', '2025-11-03 15:27:53', '2025-11-03 15:27:53', NULL, 'offline', '2025-10-30 17:07:55', 48.00, 0.00, 50.00, 'closed', NULL),
(2, 2, NULL, 'BIN-A2-F2', 1, 'KKTPAR', 50.00, 85.00, 5.98310000, 116.08320000, 'full', NULL, '2025-11-03 08:03:25', NULL, 'offline', '2025-11-03 08:02:21', 80.00, 0.00, 50.00, 'closed', NULL),
(3, 3, NULL, 'BIN-A3-F3', 3, 'KKTPAR', 50.00, 50.00, 5.98730000, 116.08300000, 'normal', NULL, '2025-11-03 08:03:02', NULL, 'offline', '2025-11-03 08:02:42', 80.00, 0.00, 50.00, 'closed', NULL);

-- ============================================
-- 6. ATTENDANCE
-- ============================================
CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_in_location` varchar(100) DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `check_out_location` varchar(100) DEFAULT NULL,
  `work_hours` decimal(5,2) DEFAULT NULL,
  `status` enum('present','late','absent','half_day') DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY (`employee_id`, `attendance_date`),
  KEY `idx_employee_date` (`employee_id`, `attendance_date`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `attendance` VALUES
(1, 4, '2025-10-26', '19:07:17', '6.03097681003443,116.13263135495914', '19:08:46', '6.0310838545347245,116.1326774670569', 0.02, 'half_day', NULL, '2025-10-26 18:07:17'),
(2, 5, '2025-10-27', '02:17:05', '6.030951952904455,116.1326222867768', '02:19:35', '6.031117891997709,116.1326436869611', 0.04, 'half_day', NULL, '2025-10-26 18:17:05'),
(3, 4, '2025-10-27', '02:59:12', '6.0311314344599625,116.13265893106727', '02:59:14', '6.0311314344599625,116.13265893106727', 0.00, 'half_day', NULL, '2025-10-26 18:59:12'),
(4, 4, '2025-10-30', '08:45:00', NULL, '17:00:00', NULL, 8.25, 'present', '', '2025-10-30 10:42:50'),
(5, 5, '2025-10-30', '19:11:39', '6.0342876,116.1178869', '19:11:40', '6.0342876,116.1178869', 0.00, 'half_day', NULL, '2025-10-30 11:11:39'),
(6, 4, '2025-11-03', '19:55:05', '5.98016,116.1003008', '19:55:07', '5.98016,116.1003008', 0.00, 'half_day', NULL, '2025-11-03 11:55:05'),
(7, 5, '2025-11-03', '19:55:11', '5.98016,116.1003008', '19:55:12', '5.98016,116.1003008', 0.00, 'half_day', NULL, '2025-11-03 11:55:11');

-- ============================================
-- 7. TASKS
-- ============================================
CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL AUTO_INCREMENT,
  `created_by` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `assigned_to` int(11) NOT NULL,
  `task_title` varchar(200) NOT NULL,
  `task_type` enum('collection','maintenance','inspection','emergency') NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `description` text DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `is_auto_generated` tinyint(1) DEFAULT 0,
  `triggered_by_bin` int(11) DEFAULT NULL,
  `scheduled_date` date NOT NULL,
  `scheduled_time` time DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `completion_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`task_id`),
  KEY `idx_assigned` (`assigned_to`, `status`),
  KEY `idx_date` (`scheduled_date`),
  KEY `idx_area` (`area_id`, `status`),
  FOREIGN KEY (`created_by`) REFERENCES `admins` (`admin_id`) ON DELETE CASCADE,
  FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_to`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  FOREIGN KEY (`triggered_by_bin`) REFERENCES `bins` (`bin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tasks` VALUES
(2, 2, 1, 4, 'URGENT: Collect Bin BIN-A1-F1 (OVERFLOWING 95%)', 'collection', 'urgent', 'Auto-generated collection task triggered by IoT sensor.\nLocation: KKTPAR\nFill Level: 95%\nBattery: 80%\nGPS: 5.9807, 116.0769', 'pending', 1, 1, '2025-10-31', NULL, NULL, NULL, NULL, '2025-10-30 17:31:46'),
(3, 2, 2, 5, 'Collect Bin BIN-A2-F2 (85% full)', 'collection', 'high', 'Auto-generated collection task triggered by IoT sensor.\nLocation: KKTPAR\nFill Level: 85%\nBattery: 80%\nGPS: 5.9831, 116.0832', 'pending', 1, 2, '2025-11-03', NULL, NULL, NULL, NULL, '2025-11-03 08:03:25'),
(4, 2, 1, 4, 'Change battery for Bin A1', 'collection', 'high', NULL, 'completed', 0, 1, '2025-11-03', '09:00:00', NULL, '2025-11-03 15:27:53', NULL, '2025-11-03 15:26:02');

-- ============================================
-- 8. AUTO_COLLECTIONS
-- ============================================
CREATE TABLE `auto_collections` (
  `auto_collection_id` int(11) NOT NULL AUTO_INCREMENT,
  `bin_id` int(11) NOT NULL,
  `weight_before` decimal(8,2) NOT NULL,
  `weight_after` decimal(8,2) NOT NULL,
  `weight_collected` decimal(8,2) NOT NULL,
  `collection_detected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `fill_level_before` decimal(5,2) DEFAULT NULL,
  `fill_level_after` decimal(5,2) DEFAULT NULL,
  `status` enum('pending','verified','ignored') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `linked_report_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`auto_collection_id`),
  KEY `idx_bin_date` (`bin_id`, `collection_detected_at`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`bin_id`) REFERENCES `bins` (`bin_id`) ON DELETE CASCADE,
  FOREIGN KEY (`verified_by`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 9. COLLECTION_REPORTS
-- ============================================
CREATE TABLE `collection_reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) DEFAULT NULL,
  `bin_id` int(11) DEFAULT NULL,
  `area_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `collection_date` date NOT NULL,
  `collection_start` time DEFAULT NULL,
  `collection_end` time DEFAULT NULL,
  `total_weight` decimal(10,2) DEFAULT NULL,
  `waste_condition` enum('normal','hazardous','recyclable','mixed') DEFAULT 'normal',
  `issues_encountered` text DEFAULT NULL,
  `photos` text DEFAULT NULL,
  `report_notes` text DEFAULT NULL,
  `is_auto_generated` tinyint(1) DEFAULT 0,
  `auto_collection_id` int(11) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`report_id`),
  FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE SET NULL,
  FOREIGN KEY (`bin_id`) REFERENCES `bins` (`bin_id`) ON DELETE SET NULL,
  FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  FOREIGN KEY (`auto_collection_id`) REFERENCES `auto_collections` (`auto_collection_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- 10. INVENTORY
-- ============================================
CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(100) NOT NULL,
  `item_category` enum('consumable','equipment','safety_gear','other') NOT NULL,
  `storage_location` varchar(100) DEFAULT NULL,
  `current_quantity` int(11) DEFAULT 0,
  `minimum_quantity` int(11) DEFAULT 0,
  `unit` varchar(20) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `last_restocked` date DEFAULT NULL,
  `last_restocked_quantity` int(11) DEFAULT NULL,
  `status` enum('in_stock','low_stock','out_of_stock') DEFAULT 'in_stock',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`inventory_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `inventory` VALUES
(1, 'Trash Bags (Large)', 'consumable', 'Central Storage', 200, 50, 'pieces', 0.50, NULL, NULL, 'in_stock', NULL, '2025-10-23 10:35:33', '2025-10-23 10:35:33'),
(2, 'Rubber Gloves', 'safety_gear', 'Central Storage', 100, 30, 'pairs', 2.00, NULL, NULL, 'in_stock', NULL, '2025-10-23 10:35:33', '2025-10-23 10:35:33'),
(3, 'Cleaning Spray', 'consumable', 'Central Storage', 50, 20, 'bottles', 8.50, NULL, NULL, 'in_stock', NULL, '2025-10-23 10:35:33', '2025-10-23 10:35:33'),
(4, 'Broom', 'equipment', 'Central Storage', 15, 5, 'pieces', 12.00, NULL, NULL, 'in_stock', NULL, '2025-10-23 10:35:33', '2025-10-23 10:35:33'),
(5, 'Waste Cart', 'equipment', 'Central Storage', 8, 3, 'pieces', 150.00, NULL, NULL, 'in_stock', NULL, '2025-10-23 10:35:33', '2025-10-23 10:35:33');

-- ============================================
-- 11. LEAVE_TYPES
-- ============================================
CREATE TABLE `leave_types` (
  `leave_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `max_days_per_year` int(11) DEFAULT 14,
  `requires_approval` tinyint(1) DEFAULT 1,
  `color_code` varchar(7) DEFAULT '#3498db',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`leave_type_id`),
  UNIQUE KEY (`type_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `leave_types` VALUES
(1, 'Annual Leave', 'Paid annual vacation leave', 14, 1, '#27ae60', 1, '2025-11-03 16:02:31'),
(2, 'Sick Leave', 'Medical or health-related leave', 14, 1, '#e74c3c', 1, '2025-11-03 16:02:31'),
(3, 'Emergency Leave', 'Urgent family or personal emergency', 7, 1, '#e67e22', 1, '2025-11-03 16:02:31'),
(4, 'Unpaid Leave', 'Leave without pay', 30, 1, '#95a5a6', 1, '2025-11-03 16:02:31'),
(5, 'Maternity Leave', 'Maternity leave for female employees', 90, 1, '#e91e63', 1, '2025-11-03 16:02:31'),
(6, 'Paternity Leave', 'Paternity leave for male employees', 7, 1, '#3f51b5', 1, '2025-11-03 16:02:31');

-- ============================================
-- 12. LEAVE_BALANCES
-- ============================================
CREATE TABLE `leave_balances` (
  `balance_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `total_days` decimal(5,1) DEFAULT 14.0,
  `used_days` decimal(5,1) DEFAULT 0.0,
  `remaining_days` decimal(5,1) DEFAULT 14.0,
  `year` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`balance_id`),
  UNIQUE KEY (`employee_id`, `leave_type_id`, `year`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `leave_balances` VALUES
(1, 4, 1, 14.0, 3.0, 11.0, 2025, '2025-11-03 16:02:31', '2025-11-07 18:07:38'),
(2, 5, 1, 14.0, 0.0, 14.0, 2025, '2025-11-03 16:02:31', '2025-11-03 16:02:31'),
(3, 4, 2, 14.0, 0.0, 14.0, 2025, '2025-11-03 16:02:31', '2025-11-03 16:02:31'),
(4, 5, 2, 14.0, 0.0, 14.0, 2025, '2025-11-03 16:02:31', '2025-11-03 16:02:31'),
(5, 4, 3, 7.0, 0.0, 7.0, 2025, '2025-11-03 16:02:31', '2025-11-03 16:02:31'),
(6, 5, 3, 7.0, 0.0, 7.0, 2025, '2025-11-03 16:02:31', '2025-11-03 16:02:31'),
(7, 4, 4, 30.0, 0.0, 30.0, 2025, '2025-11-03 16:02:31', '2025-11-03 16:02:31'),
(8, 5, 4, 30.0, 0.0, 30.0, 2025, '2025-11-03 16:02:31', '2025-11-03 16:02:31'),
(9, 4, 5, 90.0, 0.0, 90.0, 2025, '2025-11-03 16:02:31', '2025-11-03 16:02:31'),
(10, 5, 5, 90.0, 0.0, 90.0, 2025, '2025-11-03 16:02:31', '2025-11-03 16:02:31'),
(11, 4, 6, 7.0, 0.0, 7.0, 2025, '2025-11-03 16:02:31', '2025-11-03 16:02:31'),
(12, 5, 6, 7.0, 0.0, 7.0, 2025, '2025-11-03 16:02:31', '2025-11-03 16:02:31');

-- ============================================
-- 13. LEAVE_REQUESTS
-- ============================================
CREATE TABLE `leave_requests` (
  `leave_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` decimal(5,1) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `emergency_contact` varchar(100) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`leave_id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`start_date`, `end_date`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `leave_requests` VALUES
(1, 4, 1, '2025-11-09', '2025-11-11', 3.0, 'Family day', 'approved', 2, '2025-11-07 18:07:38', NULL, 'Leopold', '01110547963', NULL, '2025-11-07 18:06:34', '2025-11-07 18:07:38'),
(2, 4, 3, '2025-11-12', '2025-11-13', 2.0, 'Chin is having a wedding ceremony', 'rejected', 2, '2025-11-11 00:57:37', 'My work is more important than Chin\'s wedding', 'Leopold', '01110547963', NULL, '2025-11-11 00:56:44', '2025-11-11 00:57:37');

-- ============================================
-- 14. MAINTENANCE_REPORTS
-- ============================================
CREATE TABLE maintenance_reports (
    report_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    issue_title VARCHAR(255) NOT NULL,
    issue_description TEXT NOT NULL,
    issue_category ENUM('bin_issue', 'equipment_issue', 'facility_issue', 'safety_hazard', 'other') NOT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    location VARCHAR(255) NOT NULL,
    photo_path VARCHAR(255) NULL,
    status ENUM('pending', 'in_progress', 'resolved', 'cancelled') DEFAULT 'pending',
    assigned_to INT NULL,
    admin_notes TEXT NULL,
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES admins(admin_id) ON DELETE SET NULL,
    INDEX idx_employee (employee_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_reported_at (reported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 15. NOTIFICATIONS
-- ============================================
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_type` enum('admin','employee','all_admins','all_employees','all') NOT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `notification_type` enum('task_assigned','bin_full','maintenance_alert','leave_request','supply_request','general') NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `related_type` enum('task','bin','leave','supply','maintenance','none') DEFAULT 'none',
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `idx_recipient` (`recipient_type`, `recipient_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 16. PERFORMANCE_METRICS
-- ============================================
CREATE TABLE `performance_metrics` (
  `metric_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `area_id` int(11) DEFAULT NULL,
  `period_type` enum('daily','weekly','monthly') NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `tasks_assigned` int(11) DEFAULT 0,
  `tasks_completed` int(11) DEFAULT 0,
  `tasks_on_time` int(11) DEFAULT 0,
  `total_bins_collected` int(11) DEFAULT 0,
  `total_weight_collected` decimal(10,2) DEFAULT 0.00,
  `attendance_days` int(11) DEFAULT 0,
  `working_days` int(11) DEFAULT 22,
  `late_days` int(11) DEFAULT 0,
  `completion_rate` decimal(5,2) DEFAULT NULL,
  `on_time_rate` decimal(5,2) DEFAULT NULL,
  `avg_task_completion_hours` decimal(5,2) DEFAULT NULL,
  `attendance_rate` decimal(5,2) DEFAULT NULL,
  `performance_score` decimal(5,2) DEFAULT NULL,
  `task_performance_stars` int(11) DEFAULT NULL,
  `attendance_stars` int(11) DEFAULT NULL,
  `efficiency_stars` int(11) DEFAULT NULL,
  `overall_stars` decimal(2,1) DEFAULT NULL,
  `performance_grade` enum('excellent','good','average','needs_improvement','poor') DEFAULT NULL,
  `admin_comments` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`metric_id`),
  KEY `idx_employee_period` (`employee_id`, `period_start`, `period_end`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE SET NULL,
  FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 17. SENSOR_READINGS
-- ============================================
CREATE TABLE `sensor_readings` (
  `reading_id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) NOT NULL,
  `bin_id` int(11) NOT NULL,
  `fill_level` decimal(5,2) NOT NULL CHECK (`fill_level` between 0 and 100),
  `battery_voltage` decimal(5,2) DEFAULT NULL,
  `signal_quality` int(11) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_anomaly` tinyint(1) DEFAULT 0,
  `gps_latitude` decimal(10,8) DEFAULT NULL,
  `gps_longitude` decimal(11,8) DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `lid_status` enum('open','closed') DEFAULT NULL,
  `distance` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`reading_id`),
  KEY `idx_bin_time` (`bin_id`, `recorded_at`),
  KEY `idx_recorded_at` (`recorded_at`),
  KEY `idx_anomaly` (`is_anomaly`),
  FOREIGN KEY (`device_id`) REFERENCES `iot_devices` (`device_id`) ON DELETE CASCADE,
  FOREIGN KEY (`bin_id`) REFERENCES `bins` (`bin_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 18. SUPPLY_REQUESTS
-- ============================================
CREATE TABLE `supply_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `quantity_requested` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `urgency` enum('low','medium','high') DEFAULT 'medium',
  `status` enum('pending','approved','rejected','fulfilled') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `fulfilled_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 19. WASTE_ANALYTICS
-- ============================================
CREATE TABLE `waste_analytics` (
  `analytics_id` int(11) NOT NULL AUTO_INCREMENT,
  `area_id` int(11) DEFAULT NULL,
  `period_type` enum('daily','weekly','monthly','yearly') NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_bins_monitored` int(11) DEFAULT 0,
  `total_collections` int(11) DEFAULT 0,
  `total_bins_collected` int(11) DEFAULT 0,
  `total_bins_skipped` int(11) DEFAULT 0,
  `total_weight_collected` decimal(10,2) DEFAULT 0.00,
  `average_fill_rate` decimal(5,2) DEFAULT NULL,
  `average_weight_per_bin` decimal(10,2) DEFAULT NULL,
  `collection_efficiency` decimal(5,2) DEFAULT NULL,
  `peak_waste_day` varchar(20) DEFAULT NULL,
  `peak_waste_time` varchar(20) DEFAULT NULL,
  `maintenance_requests` int(11) DEFAULT 0,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`analytics_id`),
  FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 20. WEIGHT_SENSOR_CALIBRATION
-- ============================================
CREATE TABLE `weight_sensor_calibration` (
  `calibration_id` int(11) NOT NULL AUTO_INCREMENT,
  `bin_id` int(11) NOT NULL,
  `calibration_factor` decimal(10,2) DEFAULT 1.00,
  `offset_value` decimal(8,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`calibration_id`),
  FOREIGN KEY (`bin_id`) REFERENCES `bins` (`bin_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;