-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 09, 2025 at 07:35 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ecobin`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'Hashed password using password_hash()',
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `password`, `email`, `full_name`, `phone_number`, `created_at`, `last_login`, `status`) VALUES
(2, 'admin', 'admin123', 'admin@ecobin.ums.edu.my', 'Leopold', '0123456789', '2025-10-24 02:15:30', '2025-11-09 18:28:22', 'active'),
(3, 'Jordan', 'jordan123', 'Jordan@yahoo.com', 'Jordan Carter', '01110254968', '2025-10-26 17:32:45', '2025-10-26 17:35:35', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `areas`
--

CREATE TABLE `areas` (
  `area_id` int(11) NOT NULL,
  `area_name` varchar(50) NOT NULL COMMENT 'Block name (A1, A2, A3, B1, B2)',
  `block` varchar(10) DEFAULT NULL,
  `total_floors` int(11) NOT NULL DEFAULT 4,
  `total_bins` int(11) NOT NULL DEFAULT 4,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `areas`
--

INSERT INTO `areas` (`area_id`, `area_name`, `block`, `total_floors`, `total_bins`, `description`, `created_at`) VALUES
(1, 'A1', 'Block A', 4, 4, 'Residential College Block A1', '2025-10-23 10:35:33'),
(2, 'A2', 'Block A', 4, 4, 'Residential College Block A2', '2025-10-23 10:35:33'),
(3, 'A3', 'Block A', 4, 4, 'Residential College Block A3', '2025-10-23 10:35:33'),
(4, 'B1', 'Block B', 4, 4, 'Residential College Block B1', '2025-10-23 10:35:33'),
(5, 'B2', 'Block B', 4, 4, 'Residential College Block B2', '2025-10-23 10:35:33');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_in_location` varchar(100) DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `check_out_location` varchar(100) DEFAULT NULL,
  `work_hours` decimal(5,2) DEFAULT NULL COMMENT 'Calculated work hours',
  `status` enum('present','late','absent','half_day') DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `employee_id`, `attendance_date`, `check_in_time`, `check_in_location`, `check_out_time`, `check_out_location`, `work_hours`, `status`, `notes`, `created_at`) VALUES
(1, 4, '2025-10-26', '19:07:17', '6.03097681003443,116.13263135495914', '19:08:46', '6.0310838545347245,116.1326774670569', 0.02, 'half_day', NULL, '2025-10-26 18:07:17'),
(2, 5, '2025-10-27', '02:17:05', '6.030951952904455,116.1326222867768', '02:19:35', '6.031117891997709,116.1326436869611', 0.04, 'half_day', NULL, '2025-10-26 18:17:05'),
(3, 4, '2025-10-27', '02:59:12', '6.0311314344599625,116.13265893106727', '02:59:14', '6.0311314344599625,116.13265893106727', 0.00, 'half_day', NULL, '2025-10-26 18:59:12'),
(4, 4, '2025-10-30', '08:45:00', NULL, '17:00:00', NULL, 8.25, 'present', '', '2025-10-30 10:42:50'),
(5, 5, '2025-10-30', '19:11:39', '6.0342876,116.1178869', '19:11:40', '6.0342876,116.1178869', 0.00, 'half_day', NULL, '2025-10-30 11:11:39'),
(6, 4, '2025-11-03', '19:55:05', '5.98016,116.1003008', '19:55:07', '5.98016,116.1003008', 0.00, 'half_day', NULL, '2025-11-03 11:55:05'),
(7, 5, '2025-11-03', '19:55:11', '5.98016,116.1003008', '19:55:12', '5.98016,116.1003008', 0.00, 'half_day', NULL, '2025-11-03 11:55:11');

-- --------------------------------------------------------

--
-- Table structure for table `auto_collections`
--

CREATE TABLE `auto_collections` (
  `auto_collection_id` int(11) NOT NULL,
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
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bins`
--

CREATE TABLE `bins` (
  `bin_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL COMMENT 'One device per bin',
  `bin_code` varchar(50) NOT NULL COMMENT 'e.g., A1-F1, A1-F2',
  `floor_number` int(11) NOT NULL CHECK (`floor_number` between 1 and 4),
  `location_details` varchar(200) DEFAULT NULL COMMENT 'Specific location description',
  `bin_capacity` decimal(10,2) DEFAULT NULL COMMENT 'Maximum capacity in liters',
  `current_fill_level` decimal(5,2) DEFAULT 0.00 CHECK (`current_fill_level` between 0 and 100),
  `gps_latitude` decimal(10,8) DEFAULT NULL,
  `gps_longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('normal','full','needs_maintenance','offline') DEFAULT 'normal',
  `last_collection` timestamp NULL DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_weight_reading` timestamp NULL DEFAULT NULL,
  `weight_sensor_status` enum('online','offline','error') DEFAULT 'offline',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `battery_level` decimal(5,2) DEFAULT 100.00 COMMENT 'Current battery level percentage (0-100)',
  `current_weight` decimal(10,2) DEFAULT 0.00 COMMENT 'Current weight in kg (for future weight sensor)',
  `max_weight` decimal(8,2) DEFAULT 50.00,
  `lid_status` enum('open','closed') DEFAULT 'closed' COMMENT 'Current status of the lid (controlled by servo motor)',
  `last_opened` datetime DEFAULT NULL COMMENT 'Last time lid was opened by IR sensor'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bins`
--

INSERT INTO `bins` (`bin_id`, `area_id`, `device_id`, `bin_code`, `floor_number`, `location_details`, `bin_capacity`, `current_fill_level`, `gps_latitude`, `gps_longitude`, `status`, `last_collection`, `last_updated`, `last_weight_reading`, `weight_sensor_status`, `created_at`, `battery_level`, `current_weight`, `max_weight`, `lid_status`, `last_opened`) VALUES
(1, 1, NULL, 'BIN-A1-F1', 1, 'KKTPAR', 50.00, 0.00, 5.98550000, 116.06830000, 'normal', '2025-11-03 15:27:53', '2025-11-03 15:27:53', NULL, 'offline', '2025-10-30 17:07:55', 48.00, 0.00, 50.00, 'closed', NULL),
(2, 2, NULL, 'BIN-A2-F2', 1, 'KKTPAR', 50.00, 85.00, 5.98310000, 116.08320000, 'full', NULL, '2025-11-03 08:03:25', NULL, 'offline', '2025-11-03 08:02:21', 80.00, 0.00, 50.00, 'closed', NULL),
(3, 3, NULL, 'BIN-A3-F3', 3, 'KKTPAR', 50.00, 50.00, 5.98730000, 116.08300000, 'normal', NULL, '2025-11-03 08:03:02', NULL, 'offline', '2025-11-03 08:02:42', 80.00, 0.00, 50.00, 'closed', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `collection_reports`
--

CREATE TABLE `collection_reports` (
  `report_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `bin_id` int(11) DEFAULT NULL,
  `area_id` int(11) NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `collection_date` date NOT NULL,
  `collection_start` time DEFAULT NULL,
  `collection_end` time DEFAULT NULL,
  `total_bins_collected` int(11) DEFAULT 0,
  `total_bins_skipped` int(11) DEFAULT 0,
  `total_weight` decimal(10,2) DEFAULT NULL COMMENT 'Total weight in kilograms',
  `waste_condition` enum('normal','hazardous','recyclable','mixed') DEFAULT 'normal',
  `issues_encountered` text DEFAULT NULL,
  `photos` text DEFAULT NULL COMMENT 'JSON array of photo URLs',
  `report_notes` text DEFAULT NULL,
  `is_auto_generated` tinyint(1) DEFAULT 0,
  `auto_collection_id` int(11) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_by` int(11) DEFAULT NULL COMMENT 'Admin who verified',
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'Hashed password using password_hash()',
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('active','on_leave','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `username`, `password`, `email`, `full_name`, `phone_number`, `area_id`, `created_at`, `last_login`, `status`) VALUES
(4, 'Cyrileo', 'cleaner1234', 'cyrileo@yahoo.com', 'Cyril Leopold Yong', '01110547963', 1, '2025-10-25 14:35:00', '2025-11-08 20:51:24', 'active'),
(5, 'Ken', 'Cleaner123', 'kencarson@yahoo.com', 'Ken Carson', '01110547963', 2, '2025-10-26 17:47:17', '2025-10-30 11:18:05', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `item_category` enum('consumable','equipment','safety_gear','other') NOT NULL,
  `storage_location` varchar(100) DEFAULT NULL,
  `current_quantity` int(11) DEFAULT 0,
  `minimum_quantity` int(11) DEFAULT 0 COMMENT 'Reorder threshold',
  `unit` varchar(20) DEFAULT NULL COMMENT 'Unit of measurement (pieces, boxes, etc.)',
  `unit_price` decimal(10,2) DEFAULT NULL,
  `last_restocked` date DEFAULT NULL,
  `last_restocked_quantity` int(11) DEFAULT NULL,
  `status` enum('in_stock','low_stock','out_of_stock') DEFAULT 'in_stock',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `item_name`, `item_category`, `storage_location`, `current_quantity`, `minimum_quantity`, `unit`, `unit_price`, `last_restocked`, `last_restocked_quantity`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'Trash Bags (Large)', 'consumable', 'Central Storage', 200, 50, 'pieces', 0.50, NULL, NULL, 'in_stock', NULL, '2025-10-23 10:35:33', '2025-10-23 10:35:33'),
(2, 'Rubber Gloves', 'safety_gear', 'Central Storage', 100, 30, 'pairs', 2.00, NULL, NULL, 'in_stock', NULL, '2025-10-23 10:35:33', '2025-10-23 10:35:33'),
(3, 'Cleaning Spray', 'consumable', 'Central Storage', 50, 20, 'bottles', 8.50, NULL, NULL, 'in_stock', NULL, '2025-10-23 10:35:33', '2025-10-23 10:35:33'),
(4, 'Broom', 'equipment', 'Central Storage', 15, 5, 'pieces', 12.00, NULL, NULL, 'in_stock', NULL, '2025-10-23 10:35:33', '2025-10-23 10:35:33'),
(5, 'Waste Cart', 'equipment', 'Central Storage', 8, 3, 'pieces', 150.00, NULL, NULL, 'in_stock', NULL, '2025-10-23 10:35:33', '2025-10-23 10:35:33');

-- --------------------------------------------------------

--
-- Table structure for table `iot_devices`
--

CREATE TABLE `iot_devices` (
  `device_id` int(11) NOT NULL,
  `device_code` varchar(50) NOT NULL COMMENT 'Device serial/identifier',
  `device_mac_address` varchar(20) DEFAULT NULL,
  `device_model` varchar(50) DEFAULT NULL COMMENT 'ESP32 DevKit V1',
  `firmware_version` varchar(20) DEFAULT NULL,
  `battery_level` decimal(5,2) DEFAULT NULL COMMENT 'Battery percentage 0-100',
  `signal_strength` int(11) DEFAULT NULL COMMENT 'WiFi signal strength in dBm',
  `device_status` enum('online','offline','maintenance','error') DEFAULT 'offline',
  `installation_date` date DEFAULT NULL,
  `last_ping` timestamp NULL DEFAULT NULL COMMENT 'Last communication with server',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_balances`
--

CREATE TABLE `leave_balances` (
  `balance_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `total_days` decimal(5,1) DEFAULT 14.0,
  `used_days` decimal(5,1) DEFAULT 0.0,
  `remaining_days` decimal(5,1) DEFAULT 14.0,
  `year` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_balances`
--

INSERT INTO `leave_balances` (`balance_id`, `employee_id`, `leave_type_id`, `total_days`, `used_days`, `remaining_days`, `year`, `created_at`, `updated_at`) VALUES
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

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `leave_id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`leave_id`, `employee_id`, `leave_type_id`, `start_date`, `end_date`, `total_days`, `reason`, `status`, `reviewed_by`, `reviewed_at`, `review_notes`, `emergency_contact`, `emergency_phone`, `attachment_path`, `created_at`, `updated_at`) VALUES
(1, 4, 1, '2025-11-09', '2025-11-11', 3.0, 'Family day', 'approved', 2, '2025-11-07 18:07:38', NULL, 'Leopold', '01110547963', NULL, '2025-11-07 18:06:34', '2025-11-07 18:07:38');

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `leave_type_id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `max_days_per_year` int(11) DEFAULT 14,
  `requires_approval` tinyint(1) DEFAULT 1,
  `color_code` varchar(7) DEFAULT '#3498db',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`leave_type_id`, `type_name`, `description`, `max_days_per_year`, `requires_approval`, `color_code`, `is_active`, `created_at`) VALUES
(1, 'Annual Leave', 'Paid annual vacation leave', 14, 1, '#27ae60', 1, '2025-11-03 16:02:31'),
(2, 'Sick Leave', 'Medical or health-related leave', 14, 1, '#e74c3c', 1, '2025-11-03 16:02:31'),
(3, 'Emergency Leave', 'Urgent family or personal emergency', 7, 1, '#e67e22', 1, '2025-11-03 16:02:31'),
(4, 'Unpaid Leave', 'Leave without pay', 30, 1, '#95a5a6', 1, '2025-11-03 16:02:31'),
(5, 'Maternity Leave', 'Maternity leave for female employees', 90, 1, '#e91e63', 1, '2025-11-03 16:02:31'),
(6, 'Paternity Leave', 'Paternity leave for male employees', 7, 1, '#3f51b5', 1, '2025-11-03 16:02:31');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_reports`
--

CREATE TABLE `maintenance_reports` (
  `maintenance_id` int(11) NOT NULL,
  `reported_by` int(11) NOT NULL,
  `bin_id` int(11) DEFAULT NULL COMMENT 'Related bin if applicable',
  `device_id` int(11) DEFAULT NULL COMMENT 'Related device if applicable',
  `issue_type` enum('bin_damage','sensor_malfunction','battery_low','hardware_failure','other') NOT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `issue_description` text NOT NULL,
  `photo_evidence` text DEFAULT NULL COMMENT 'JSON array of photo URLs',
  `status` enum('reported','in_progress','resolved','cancelled') DEFAULT 'reported',
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_to` int(11) DEFAULT NULL COMMENT 'Admin assigned to fix',
  `started_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL COMMENT 'Repair or replacement cost'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `recipient_type` enum('admin','employee','all_admins','all_employees','all') NOT NULL,
  `recipient_id` int(11) DEFAULT NULL COMMENT 'Specific user ID, NULL for broadcast',
  `notification_type` enum('task_assigned','bin_full','maintenance_alert','leave_request','supply_request','general') NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `related_type` enum('task','bin','leave','supply','maintenance','none') DEFAULT 'none',
  `related_id` int(11) DEFAULT NULL COMMENT 'ID of related entity',
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `performance_metrics`
--

CREATE TABLE `performance_metrics` (
  `metric_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `area_id` int(11) DEFAULT NULL COMMENT 'Specific area performance (NULL = overall)',
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
  `completion_rate` decimal(5,2) DEFAULT NULL COMMENT 'Percentage of tasks completed',
  `on_time_rate` decimal(5,2) DEFAULT NULL COMMENT 'Percentage of on-time completions',
  `avg_task_completion_hours` decimal(5,2) DEFAULT NULL,
  `attendance_rate` decimal(5,2) DEFAULT NULL COMMENT 'Percentage of attendance',
  `performance_score` decimal(5,2) DEFAULT NULL COMMENT 'Overall score 0-100',
  `task_performance_stars` int(11) DEFAULT NULL,
  `attendance_stars` int(11) DEFAULT NULL,
  `efficiency_stars` int(11) DEFAULT NULL,
  `overall_stars` decimal(2,1) DEFAULT NULL,
  `performance_grade` enum('excellent','good','average','needs_improvement','poor') DEFAULT NULL,
  `admin_comments` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sensor_readings`
--

CREATE TABLE `sensor_readings` (
  `reading_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `bin_id` int(11) NOT NULL,
  `fill_level` decimal(5,2) NOT NULL CHECK (`fill_level` between 0 and 100),
  `battery_voltage` decimal(5,2) DEFAULT NULL COMMENT 'Device battery voltage',
  `temperature` decimal(5,2) DEFAULT NULL COMMENT 'Temperature in Celsius',
  `signal_quality` int(11) DEFAULT NULL COMMENT 'Signal quality percentage',
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When sensor took reading',
  `received_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When server received data',
  `is_anomaly` tinyint(1) DEFAULT 0 COMMENT 'Flag for unusual readings',
  `gps_latitude` decimal(10,8) DEFAULT NULL COMMENT 'GPS latitude at time of reading',
  `gps_longitude` decimal(11,8) DEFAULT NULL COMMENT 'GPS longitude at time of reading',
  `weight` decimal(10,2) DEFAULT NULL COMMENT 'Weight reading in kg (for future load cell)',
  `lid_status` enum('open','closed') DEFAULT NULL COMMENT 'Lid status at time of reading',
  `distance` decimal(10,2) DEFAULT NULL COMMENT 'Distance in cm measured by ultrasonic sensor'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supply_requests`
--

CREATE TABLE `supply_requests` (
  `request_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `quantity_requested` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `urgency` enum('low','medium','high') DEFAULT 'medium',
  `status` enum('pending','approved','rejected','fulfilled') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL COMMENT 'Admin who reviewed',
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `fulfilled_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL COMMENT 'Admin who created the task',
  `area_id` int(11) NOT NULL,
  `assigned_to` int(11) NOT NULL COMMENT 'Employee assigned to task',
  `task_title` varchar(200) NOT NULL,
  `task_type` enum('collection','maintenance','inspection','emergency') NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `description` text DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `is_auto_generated` tinyint(1) DEFAULT 0 COMMENT 'Was this task auto-created by system?',
  `triggered_by_bin` int(11) DEFAULT NULL COMMENT 'Bin that triggered auto-task creation',
  `scheduled_date` date NOT NULL,
  `scheduled_time` time DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `completion_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`task_id`, `created_by`, `area_id`, `assigned_to`, `task_title`, `task_type`, `priority`, `description`, `status`, `is_auto_generated`, `triggered_by_bin`, `scheduled_date`, `scheduled_time`, `started_at`, `completed_at`, `completion_notes`, `created_at`) VALUES
(2, 2, 1, 4, 'URGENT: Collect Bin BIN-A1-F1 (OVERFLOWING 95%)', 'collection', 'urgent', 'Auto-generated collection task triggered by IoT sensor.\nLocation: KKTPAR\nFill Level: 95%\nBattery: 80%\nGPS: 5.9807, 116.0769', 'pending', 1, 1, '2025-10-31', NULL, NULL, NULL, NULL, '2025-10-30 17:31:46'),
(3, 2, 2, 5, 'Collect Bin BIN-A2-F2 (85% full)', 'collection', 'high', 'Auto-generated collection task triggered by IoT sensor.\nLocation: KKTPAR\nFill Level: 85%\nBattery: 80%\nGPS: 5.9831, 116.0832', 'pending', 1, 2, '2025-11-03', NULL, NULL, NULL, NULL, '2025-11-03 08:03:25'),
(4, 2, 1, 4, 'Change battery for Bin A1', 'collection', 'high', NULL, 'completed', 0, 1, '2025-11-03', '09:00:00', NULL, '2025-11-03 15:27:53', NULL, '2025-11-03 15:26:02');

-- --------------------------------------------------------

--
-- Table structure for table `waste_analytics`
--

CREATE TABLE `waste_analytics` (
  `analytics_id` int(11) NOT NULL,
  `area_id` int(11) DEFAULT NULL COMMENT 'NULL means campus-wide analytics',
  `period_type` enum('daily','weekly','monthly','yearly') NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_bins_monitored` int(11) DEFAULT 0,
  `total_collections` int(11) DEFAULT 0,
  `total_bins_collected` int(11) DEFAULT 0,
  `total_bins_skipped` int(11) DEFAULT 0,
  `total_weight_collected` decimal(10,2) DEFAULT 0.00,
  `average_fill_rate` decimal(5,2) DEFAULT NULL COMMENT 'Average fill percentage',
  `average_weight_per_bin` decimal(10,2) DEFAULT NULL,
  `collection_efficiency` decimal(5,2) DEFAULT NULL COMMENT 'Efficiency percentage',
  `peak_waste_day` varchar(20) DEFAULT NULL COMMENT 'Day with highest waste',
  `peak_waste_time` varchar(20) DEFAULT NULL COMMENT 'Time period with most waste',
  `maintenance_requests` int(11) DEFAULT 0,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `weight_sensor_calibration`
--

CREATE TABLE `weight_sensor_calibration` (
  `calibration_id` int(11) NOT NULL,
  `bin_id` int(11) NOT NULL,
  `calibration_factor` decimal(10,2) DEFAULT 1.00,
  `offset_value` decimal(8,2) DEFAULT 0.00,
  `max_capacity` decimal(8,2) DEFAULT 50.00,
  `last_calibrated` timestamp NULL DEFAULT NULL,
  `calibrated_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`area_id`),
  ADD UNIQUE KEY `area_name` (`area_name`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `unique_employee_date` (`employee_id`,`attendance_date`),
  ADD KEY `idx_attendance_employee_date` (`employee_id`,`attendance_date`);

--
-- Indexes for table `auto_collections`
--
ALTER TABLE `auto_collections`
  ADD PRIMARY KEY (`auto_collection_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_bin_date` (`bin_id`,`collection_detected_at`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `bins`
--
ALTER TABLE `bins`
  ADD PRIMARY KEY (`bin_id`),
  ADD UNIQUE KEY `bin_code` (`bin_code`),
  ADD UNIQUE KEY `device_id` (`device_id`),
  ADD KEY `idx_bins_area` (`area_id`),
  ADD KEY `idx_bins_status` (`status`),
  ADD KEY `idx_fill_level` (`current_fill_level`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_battery` (`battery_level`);

--
-- Indexes for table `collection_reports`
--
ALTER TABLE `collection_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `area_id` (`area_id`),
  ADD KEY `submitted_by` (`submitted_by`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_bin` (`bin_id`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `collection_reports_ibfk_auto` (`auto_collection_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_employee_area` (`area_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`);

--
-- Indexes for table `iot_devices`
--
ALTER TABLE `iot_devices`
  ADD PRIMARY KEY (`device_id`),
  ADD UNIQUE KEY `device_code` (`device_code`),
  ADD UNIQUE KEY `device_mac_address` (`device_mac_address`);

--
-- Indexes for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD PRIMARY KEY (`balance_id`),
  ADD UNIQUE KEY `unique_employee_type_year` (`employee_id`,`leave_type_id`,`year`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`leave_id`),
  ADD KEY `leave_type_id` (`leave_type_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`leave_type_id`),
  ADD UNIQUE KEY `type_name` (`type_name`);

--
-- Indexes for table `maintenance_reports`
--
ALTER TABLE `maintenance_reports`
  ADD PRIMARY KEY (`maintenance_id`),
  ADD KEY `reported_by` (`reported_by`),
  ADD KEY `bin_id` (`bin_id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notifications_recipient` (`recipient_type`,`recipient_id`,`is_read`);

--
-- Indexes for table `performance_metrics`
--
ALTER TABLE `performance_metrics`
  ADD PRIMARY KEY (`metric_id`),
  ADD KEY `area_id` (`area_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_employee_period` (`employee_id`,`period_start`,`period_end`);

--
-- Indexes for table `sensor_readings`
--
ALTER TABLE `sensor_readings`
  ADD PRIMARY KEY (`reading_id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `idx_sensor_readings_bin_time` (`bin_id`,`recorded_at`),
  ADD KEY `idx_recorded_at` (`recorded_at`),
  ADD KEY `idx_anomaly` (`is_anomaly`);

--
-- Indexes for table `supply_requests`
--
ALTER TABLE `supply_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `triggered_by_bin` (`triggered_by_bin`),
  ADD KEY `idx_tasks_assigned` (`assigned_to`,`status`),
  ADD KEY `idx_tasks_date` (`scheduled_date`),
  ADD KEY `idx_tasks_area` (`area_id`,`status`);

--
-- Indexes for table `waste_analytics`
--
ALTER TABLE `waste_analytics`
  ADD PRIMARY KEY (`analytics_id`),
  ADD KEY `area_id` (`area_id`);

--
-- Indexes for table `weight_sensor_calibration`
--
ALTER TABLE `weight_sensor_calibration`
  ADD PRIMARY KEY (`calibration_id`),
  ADD UNIQUE KEY `bin_id` (`bin_id`),
  ADD KEY `calibrated_by` (`calibrated_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `areas`
--
ALTER TABLE `areas`
  MODIFY `area_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `auto_collections`
--
ALTER TABLE `auto_collections`
  MODIFY `auto_collection_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bins`
--
ALTER TABLE `bins`
  MODIFY `bin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `collection_reports`
--
ALTER TABLE `collection_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `iot_devices`
--
ALTER TABLE `iot_devices`
  MODIFY `device_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_balances`
--
ALTER TABLE `leave_balances`
  MODIFY `balance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `leave_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `leave_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `maintenance_reports`
--
ALTER TABLE `maintenance_reports`
  MODIFY `maintenance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `performance_metrics`
--
ALTER TABLE `performance_metrics`
  MODIFY `metric_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sensor_readings`
--
ALTER TABLE `sensor_readings`
  MODIFY `reading_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supply_requests`
--
ALTER TABLE `supply_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `waste_analytics`
--
ALTER TABLE `waste_analytics`
  MODIFY `analytics_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `weight_sensor_calibration`
--
ALTER TABLE `weight_sensor_calibration`
  MODIFY `calibration_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `auto_collections`
--
ALTER TABLE `auto_collections`
  ADD CONSTRAINT `auto_collections_ibfk_1` FOREIGN KEY (`bin_id`) REFERENCES `bins` (`bin_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `auto_collections_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL;

--
-- Constraints for table `bins`
--
ALTER TABLE `bins`
  ADD CONSTRAINT `bins_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bins_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `iot_devices` (`device_id`) ON DELETE SET NULL;

--
-- Constraints for table `collection_reports`
--
ALTER TABLE `collection_reports`
  ADD CONSTRAINT `collection_reports_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `collection_reports_ibfk_2` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `collection_reports_ibfk_3` FOREIGN KEY (`submitted_by`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `collection_reports_ibfk_4` FOREIGN KEY (`verified_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `collection_reports_ibfk_5` FOREIGN KEY (`bin_id`) REFERENCES `bins` (`bin_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `collection_reports_ibfk_6` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `collection_reports_ibfk_auto` FOREIGN KEY (`auto_collection_id`) REFERENCES `auto_collections` (`auto_collection_id`) ON DELETE SET NULL;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `fk_employee_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE SET NULL;

--
-- Constraints for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD CONSTRAINT `leave_balances_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_balances_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `maintenance_reports`
--
ALTER TABLE `maintenance_reports`
  ADD CONSTRAINT `maintenance_reports_ibfk_1` FOREIGN KEY (`reported_by`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_reports_ibfk_2` FOREIGN KEY (`bin_id`) REFERENCES `bins` (`bin_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_reports_ibfk_3` FOREIGN KEY (`device_id`) REFERENCES `iot_devices` (`device_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_reports_ibfk_4` FOREIGN KEY (`assigned_to`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `performance_metrics`
--
ALTER TABLE `performance_metrics`
  ADD CONSTRAINT `performance_metrics_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `performance_metrics_ibfk_2` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `performance_metrics_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `sensor_readings`
--
ALTER TABLE `sensor_readings`
  ADD CONSTRAINT `sensor_readings_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `iot_devices` (`device_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sensor_readings_ibfk_2` FOREIGN KEY (`bin_id`) REFERENCES `bins` (`bin_id`) ON DELETE CASCADE;

--
-- Constraints for table `supply_requests`
--
ALTER TABLE `supply_requests`
  ADD CONSTRAINT `supply_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `supply_requests_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `supply_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admins` (`admin_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_4` FOREIGN KEY (`triggered_by_bin`) REFERENCES `bins` (`bin_id`) ON DELETE SET NULL;

--
-- Constraints for table `waste_analytics`
--
ALTER TABLE `waste_analytics`
  ADD CONSTRAINT `waste_analytics_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE CASCADE;

--
-- Constraints for table `weight_sensor_calibration`
--
ALTER TABLE `weight_sensor_calibration`
  ADD CONSTRAINT `weight_sensor_calibration_ibfk_1` FOREIGN KEY (`bin_id`) REFERENCES `bins` (`bin_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `weight_sensor_calibration_ibfk_2` FOREIGN KEY (`calibrated_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
