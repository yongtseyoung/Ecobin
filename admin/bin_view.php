<?php
/**
 * Individual Bin View - Detailed information and sensor history
 */
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

$bin_id = $_GET['id'] ?? 0;

// Get bin details with area and device info
$bin = getOne("SELECT b.*, a.area_name, a.block,
               d.device_code, d.device_status, d.firmware_version, d.last_ping
               FROM bins b
               LEFT JOIN areas a ON b.area_id = a.area_id
               LEFT JOIN iot_devices d ON b.device_id = d.device_id
               WHERE b.bin_id = ?", [$bin_id]);

// Get recent sensor readings (last 24 hours)
$readings = getAll("SELECT * FROM sensor_readings 
                    WHERE bin_id = ? 
                    ORDER BY recorded_at DESC 
                    LIMIT 50", [$bin_id]);

// Get tasks related to this bin
$tasks = getAll("SELECT t.*, e.full_name as employee_name
                 FROM tasks t
                 LEFT JOIN employees e ON t.assigned_to = e.employee_id
                 WHERE t.triggered_by_bin = ?
                 ORDER BY t.created_at DESC
                 LIMIT 10", [$bin_id]);

// Display:
// - Current status card (fill level, battery, GPS)
// - Sensor history chart (fill level over time)
// - Recent readings table
// - Related tasks
// - Device information
// - Quick actions (manual task create, simulate collection)
?>