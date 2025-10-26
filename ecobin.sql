-- Create database
CREATE DATABASE ecobin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE ecobin;

-- ============================================
-- TABLE 1: ADMINS
-- ============================================
CREATE TABLE admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL COMMENT 'Hashed password using password_hash()',
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 2: EMPLOYEES
-- ============================================
CREATE TABLE employees (
    employee_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL COMMENT 'Hashed password using password_hash()',
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20),
    hire_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status ENUM('active', 'on_leave', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 3: AREAS
-- ============================================
CREATE TABLE areas (
    area_id INT AUTO_INCREMENT PRIMARY KEY,
    area_name VARCHAR(50) UNIQUE NOT NULL COMMENT 'Block name (A1, A2, A3, B1, B2)',
    total_floors INT NOT NULL DEFAULT 4,
    total_bins INT NOT NULL DEFAULT 4,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 4: EMPLOYEE_AREAS
-- ============================================
CREATE TABLE employee_areas (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    area_id INT NOT NULL,
    is_primary BOOLEAN DEFAULT TRUE COMMENT 'Is this a primary area for the employee?',
    assigned_date DATE NOT NULL DEFAULT (CURRENT_DATE),
    status ENUM('active', 'inactive') DEFAULT 'active',
    notes TEXT COMMENT 'Any special notes about this assignment',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (area_id) REFERENCES areas(area_id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_area (employee_id, area_id),
    INDEX idx_employee_areas (employee_id, status),
    INDEX idx_area_employees (area_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 5: IOT_DEVICES
-- ============================================
CREATE TABLE iot_devices (
    device_id INT AUTO_INCREMENT PRIMARY KEY,
    device_code VARCHAR(50) UNIQUE NOT NULL COMMENT 'Device serial/identifier',
    device_mac_address VARCHAR(20) UNIQUE,
    device_model VARCHAR(50) COMMENT 'ESP32 DevKit V1',
    firmware_version VARCHAR(20),
    battery_level DECIMAL(5,2) COMMENT 'Battery percentage 0-100',
    signal_strength INT COMMENT 'WiFi signal strength in dBm',
    device_status ENUM('online', 'offline', 'maintenance', 'error') DEFAULT 'offline',
    installation_date DATE,
    last_ping TIMESTAMP NULL COMMENT 'Last communication with server',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 6: BINS
-- ============================================
CREATE TABLE bins (
    bin_id INT AUTO_INCREMENT PRIMARY KEY,
    area_id INT NOT NULL,
    device_id INT UNIQUE COMMENT 'One device per bin',
    bin_code VARCHAR(50) UNIQUE NOT NULL COMMENT 'e.g., A1-F1, A1-F2',
    floor_number INT NOT NULL CHECK (floor_number BETWEEN 1 AND 4),
    location_details VARCHAR(200) COMMENT 'Specific location description',
    bin_capacity DECIMAL(10,2) COMMENT 'Maximum capacity in liters',
    current_fill_level DECIMAL(5,2) DEFAULT 0 CHECK (current_fill_level BETWEEN 0 AND 100),
    gps_latitude DECIMAL(10,8),
    gps_longitude DECIMAL(11,8),
    status ENUM('normal', 'full', 'needs_maintenance', 'offline') DEFAULT 'normal',
    last_collection TIMESTAMP NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (area_id) REFERENCES areas(area_id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES iot_devices(device_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 7: SENSOR_READINGS
-- ============================================
CREATE TABLE sensor_readings (
    reading_id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    bin_id INT NOT NULL,
    fill_level DECIMAL(5,2) NOT NULL CHECK (fill_level BETWEEN 0 AND 100),
    battery_voltage DECIMAL(5,2) COMMENT 'Device battery voltage',
    temperature DECIMAL(5,2) COMMENT 'Temperature in Celsius',
    signal_quality INT COMMENT 'Signal quality percentage',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When sensor took reading',
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When server received data',
    is_anomaly BOOLEAN DEFAULT FALSE COMMENT 'Flag for unusual readings',
    FOREIGN KEY (device_id) REFERENCES iot_devices(device_id) ON DELETE CASCADE,
    FOREIGN KEY (bin_id) REFERENCES bins(bin_id) ON DELETE CASCADE,
    INDEX idx_sensor_readings_bin_time (bin_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 8: TASKS
-- ============================================
CREATE TABLE tasks (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    created_by INT NOT NULL COMMENT 'Admin who created the task',
    area_id INT NOT NULL,
    assigned_to INT NOT NULL COMMENT 'Employee assigned to task',
    task_title VARCHAR(200) NOT NULL,
    task_type ENUM('collection', 'maintenance', 'inspection', 'emergency') NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    description TEXT,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    is_auto_generated BOOLEAN DEFAULT FALSE COMMENT 'Was this task auto-created by system?',
    triggered_by_bin INT NULL COMMENT 'Bin that triggered auto-task creation',
    scheduled_date DATE NOT NULL,
    scheduled_time TIME,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    completion_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(admin_id) ON DELETE CASCADE,
    FOREIGN KEY (area_id) REFERENCES areas(area_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (triggered_by_bin) REFERENCES bins(bin_id) ON DELETE SET NULL,
    INDEX idx_tasks_assigned (assigned_to, status),
    INDEX idx_tasks_date (scheduled_date),
    INDEX idx_tasks_area (area_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 9: TASK_BINS (Junction Table)
-- ============================================
CREATE TABLE task_bins (
    task_bin_id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    bin_id INT NOT NULL,
    collection_status ENUM('pending', 'collected', 'skipped') DEFAULT 'pending',
    weight_collected DECIMAL(10,2) COMMENT 'Weight in kilograms',
    notes TEXT,
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (bin_id) REFERENCES bins(bin_id) ON DELETE CASCADE,
    UNIQUE KEY unique_task_bin (task_id, bin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 10: ATTENDANCE
-- ============================================
CREATE TABLE attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    check_in_time TIME,
    check_in_photo VARCHAR(255) COMMENT 'Photo file path for check-in',
    check_in_location VARCHAR(100),
    check_out_time TIME,
    check_out_photo VARCHAR(255) COMMENT 'Photo file path for check-out',
    check_out_location VARCHAR(100),
    work_hours DECIMAL(5,2) COMMENT 'Calculated work hours',
    status ENUM('present', 'late', 'absent', 'half_day') DEFAULT 'present',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_date (employee_id, attendance_date),
    INDEX idx_attendance_employee_date (employee_id, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 11: PERFORMANCE_METRICS
-- ============================================
CREATE TABLE performance_metrics (
    metric_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    area_id INT NULL COMMENT 'Specific area performance (NULL = overall)',
    period_type ENUM('daily', 'weekly', 'monthly') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    tasks_assigned INT DEFAULT 0,
    tasks_completed INT DEFAULT 0,
    tasks_on_time INT DEFAULT 0,
    total_bins_collected INT DEFAULT 0,
    total_weight_collected DECIMAL(10,2) DEFAULT 0,
    attendance_days INT DEFAULT 0,
    late_days INT DEFAULT 0,
    completion_rate DECIMAL(5,2) COMMENT 'Percentage of tasks completed',
    on_time_rate DECIMAL(5,2) COMMENT 'Percentage of on-time completions',
    attendance_rate DECIMAL(5,2) COMMENT 'Percentage of attendance',
    performance_score DECIMAL(5,2) COMMENT 'Overall score 0-100',
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (area_id) REFERENCES areas(area_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 12: COLLECTION_REPORTS
-- ============================================
CREATE TABLE collection_reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    area_id INT NOT NULL,
    submitted_by INT NOT NULL,
    collection_date DATE NOT NULL,
    collection_start TIME,
    collection_end TIME,
    total_bins_collected INT DEFAULT 0,
    total_bins_skipped INT DEFAULT 0,
    total_weight DECIMAL(10,2) COMMENT 'Total weight in kilograms',
    waste_condition ENUM('normal', 'hazardous', 'recyclable', 'mixed') DEFAULT 'normal',
    issues_encountered TEXT,
    photos TEXT COMMENT 'JSON array of photo URLs',
    report_notes TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_by INT COMMENT 'Admin who verified',
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (area_id) REFERENCES areas(area_id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES admins(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 13: INVENTORY
-- ============================================
CREATE TABLE inventory (
    inventory_id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL,
    item_category ENUM('consumable', 'equipment', 'safety_gear', 'other') NOT NULL,
    storage_location VARCHAR(100),
    current_quantity INT DEFAULT 0,
    minimum_quantity INT DEFAULT 0 COMMENT 'Reorder threshold',
    unit VARCHAR(20) COMMENT 'Unit of measurement (pieces, boxes, etc.)',
    unit_price DECIMAL(10,2),
    last_restocked DATE,
    last_restocked_quantity INT,
    status ENUM('in_stock', 'low_stock', 'out_of_stock') DEFAULT 'in_stock',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 14: SUPPLY_REQUESTS
-- ============================================
CREATE TABLE supply_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    inventory_id INT NOT NULL,
    quantity_requested INT NOT NULL,
    reason TEXT,
    urgency ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('pending', 'approved', 'rejected', 'fulfilled') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT COMMENT 'Admin who reviewed',
    reviewed_at TIMESTAMP NULL,
    admin_notes TEXT,
    fulfilled_at TIMESTAMP NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (inventory_id) REFERENCES inventory(inventory_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES admins(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 15: LEAVE_APPLICATIONS
-- ============================================
CREATE TABLE leave_applications (
    leave_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type ENUM('sick', 'annual', 'emergency', 'unpaid') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days INT NOT NULL,
    reason TEXT NOT NULL,
    supporting_document VARCHAR(255) COMMENT 'Medical certificate or other document',
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT COMMENT 'Admin who reviewed',
    reviewed_at TIMESTAMP NULL,
    admin_remarks TEXT,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES admins(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 16: MAINTENANCE_REPORTS
-- ============================================
CREATE TABLE maintenance_reports (
    maintenance_id INT AUTO_INCREMENT PRIMARY KEY,
    reported_by INT NOT NULL,
    bin_id INT COMMENT 'Related bin if applicable',
    device_id INT COMMENT 'Related device if applicable',
    issue_type ENUM('bin_damage', 'sensor_malfunction', 'battery_low', 'hardware_failure', 'other') NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    issue_description TEXT NOT NULL,
    photo_evidence TEXT COMMENT 'JSON array of photo URLs',
    status ENUM('reported', 'in_progress', 'resolved', 'cancelled') DEFAULT 'reported',
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_to INT COMMENT 'Admin assigned to fix',
    started_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,
    resolution_notes TEXT,
    cost DECIMAL(10,2) COMMENT 'Repair or replacement cost',
    FOREIGN KEY (reported_by) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (bin_id) REFERENCES bins(bin_id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES iot_devices(device_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES admins(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 17: WASTE_ANALYTICS
-- ============================================
CREATE TABLE waste_analytics (
    analytics_id INT AUTO_INCREMENT PRIMARY KEY,
    area_id INT COMMENT 'NULL means campus-wide analytics',
    period_type ENUM('daily', 'weekly', 'monthly', 'yearly') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_bins_monitored INT DEFAULT 0,
    total_collections INT DEFAULT 0,
    total_bins_collected INT DEFAULT 0,
    total_bins_skipped INT DEFAULT 0,
    total_weight_collected DECIMAL(10,2) DEFAULT 0,
    average_fill_rate DECIMAL(5,2) COMMENT 'Average fill percentage',
    average_weight_per_bin DECIMAL(10,2),
    collection_efficiency DECIMAL(5,2) COMMENT 'Efficiency percentage',
    peak_waste_day VARCHAR(20) COMMENT 'Day with highest waste',
    peak_waste_time VARCHAR(20) COMMENT 'Time period with most waste',
    maintenance_requests INT DEFAULT 0,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (area_id) REFERENCES areas(area_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 18: NOTIFICATIONS
-- ============================================
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_type ENUM('admin', 'employee', 'all_admins', 'all_employees', 'all') NOT NULL,
    recipient_id INT COMMENT 'Specific user ID, NULL for broadcast',
    notification_type ENUM('task_assigned', 'bin_full', 'maintenance_alert', 'leave_request', 'supply_request', 'general') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    related_type ENUM('task', 'bin', 'leave', 'supply', 'maintenance', 'none') DEFAULT 'none',
    related_id INT COMMENT 'ID of related entity',
    is_read BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    INDEX idx_notifications_recipient (recipient_type, recipient_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ADDITIONAL INDEXES FOR PERFORMANCE
-- ============================================
CREATE INDEX idx_bins_area ON bins(area_id);
CREATE INDEX idx_bins_status ON bins(status);

-- ============================================
-- INITIAL DATA - 5 BLOCKS
-- ============================================

-- Insert the 5 residential college blocks
INSERT INTO areas (area_name, total_floors, total_bins, description) VALUES
('A1', 4, 4, 'Residential College Block A1'),
('A2', 4, 4, 'Residential College Block A2'),
('A3', 4, 4, 'Residential College Block A3'),
('B1', 4, 4, 'Residential College Block B1'),
('B2', 4, 4, 'Residential College Block B2');

-- ============================================
-- SAMPLE ADMIN ACCOUNT
-- ============================================
-- Password: admin123 (hashed)
INSERT INTO admins (username, password, email, full_name, phone_number) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@ecobin.ums.edu.my', 'System Administrator', '0123456789');

-- ============================================
-- SAMPLE EMPLOYEE ACCOUNTS
-- ============================================
-- Password: cleaner123 (hashed)
INSERT INTO employees (username, password, email, full_name, phone_number, hire_date) VALUES
('john_doe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'john@ecobin.ums.edu.my', 'John Doe', '0123456780', '2024-01-15'),
('mary_tan', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mary@ecobin.ums.edu.my', 'Mary Tan', '0123456781', '2024-02-01'),
('ali_hassan', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ali@ecobin.ums.edu.my', 'Ali Hassan', '0123456782', '2024-03-01');

-- ============================================
-- EMPLOYEE AREA ASSIGNMENTS (NEW!)
-- ============================================
-- John is assigned to Block A1 and A2 (primary areas)
INSERT INTO employee_areas (employee_id, area_id, is_primary, assigned_date, notes) VALUES
(1, 1, TRUE, '2024-01-15', 'Primary cleaner for Block A1'),
(1, 2, TRUE, '2024-01-15', 'Primary cleaner for Block A2');

-- Mary is assigned to Block A3 and B1 (primary areas)
INSERT INTO employee_areas (employee_id, area_id, is_primary, assigned_date, notes) VALUES
(2, 3, TRUE, '2024-02-01', 'Primary cleaner for Block A3'),
(2, 4, TRUE, '2024-02-01', 'Primary cleaner for Block B1');

-- Ali is assigned to Block B2 (primary area)
INSERT INTO employee_areas (employee_id, area_id, is_primary, assigned_date, notes) VALUES
(3, 5, TRUE, '2024-03-01', 'Primary cleaner for Block B2');

-- ============================================
-- SAMPLE INVENTORY ITEMS
-- ============================================
INSERT INTO inventory (item_name, item_category, storage_location, current_quantity, minimum_quantity, unit, unit_price) VALUES
('Trash Bags (Large)', 'consumable', 'Central Storage', 200, 50, 'pieces', 0.50),
('Rubber Gloves', 'safety_gear', 'Central Storage', 100, 30, 'pairs', 2.00),
('Cleaning Spray', 'consumable', 'Central Storage', 50, 20, 'bottles', 8.50),
('Broom', 'equipment', 'Central Storage', 15, 5, 'pieces', 12.00),
('Waste Cart', 'equipment', 'Central Storage', 8, 3, 'pieces', 150.00);

