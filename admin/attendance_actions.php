<?php
/**
 * Attendance Actions Handler
 * Processes check-in and check-out with GPS tracking
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur'); // Malaysia timezone
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Attendance settings
$WORK_START_TIME = '08:30:00';  // Work starts at 8:30 AM
$LATE_THRESHOLD_MINUTES = 15;   // 15 minutes grace period
$HALF_DAY_HOURS = 4;             // Less than 4 hours = half day

// Get action
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'checkin':
            processCheckIn();
            break;
        case 'checkout':
            processCheckOut();
            break;
        default:
            $_SESSION['error'] = "Invalid action";
            header("Location: attendance_checkin.php");
            exit;
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header("Location: attendance_checkin.php");
    exit;
}

/**
 * Process employee check-in
 */
function processCheckIn() {
    global $WORK_START_TIME, $LATE_THRESHOLD_MINUTES;
    
    $employee_id = $_POST['employee_id'];
    $location = $_POST['location'] ?? '';
    
    if (empty($location)) {
        $_SESSION['error'] = "Location is required for check-in";
        header("Location: attendance_checkin.php?employee_id=$employee_id");
        exit;
    }
    
    // Get current date and time
    $attendance_date = date('Y-m-d');
    $check_in_time = date('H:i:s');
    
    // Check if already checked in today
    $existing = getOne("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?", 
                       [$employee_id, $attendance_date]);
    
    if ($existing && $existing['check_in_time']) {
        $_SESSION['error'] = "You have already checked in today at " . date('g:i A', strtotime($existing['check_in_time']));
        header("Location: attendance_checkin.php?employee_id=$employee_id");
        exit;
    }
    
    // Determine status based on check-in time
    $status = determineStatus($check_in_time, $WORK_START_TIME, $LATE_THRESHOLD_MINUTES);
    
    // Insert or update attendance record
    if ($existing) {
        // Update existing record
        $sql = "UPDATE attendance 
                SET check_in_time = ?, 
                    check_in_location = ?, 
                    status = ?
                WHERE attendance_id = ?";
        query($sql, [$check_in_time, $location, $status, $existing['attendance_id']]);
    } else {
        // Insert new record
        $sql = "INSERT INTO attendance (employee_id, attendance_date, check_in_time, check_in_location, status) 
                VALUES (?, ?, ?, ?, ?)";
        query($sql, [$employee_id, $attendance_date, $check_in_time, $location, $status]);
    }
    
    $_SESSION['success'] = "Checked in successfully at " . date('g:i A', strtotime($check_in_time));
    header("Location: attendance_checkin.php?employee_id=$employee_id");
    exit;
}

/**
 * Process employee check-out
 */
function processCheckOut() {
    global $HALF_DAY_HOURS;
    
    $employee_id = $_POST['employee_id'];
    $location = $_POST['location'] ?? '';
    
    if (empty($location)) {
        $_SESSION['error'] = "Location is required for check-out";
        header("Location: attendance_checkin.php?employee_id=$employee_id");
        exit;
    }
    
    // Get current date and time
    $attendance_date = date('Y-m-d');
    $check_out_time = date('H:i:s');
    
    // Get today's attendance record
    $attendance = getOne("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?", 
                        [$employee_id, $attendance_date]);
    
    if (!$attendance || !$attendance['check_in_time']) {
        $_SESSION['error'] = "You must check in before checking out";
        header("Location: attendance_checkin.php?employee_id=$employee_id");
        exit;
    }
    
    if ($attendance['check_out_time']) {
        $_SESSION['error'] = "You have already checked out today at " . date('g:i A', strtotime($attendance['check_out_time']));
        header("Location: attendance_checkin.php?employee_id=$employee_id");
        exit;
    }
    
    // Calculate work hours
    $work_hours = calculateWorkHours($attendance['check_in_time'], $check_out_time);
    
    // Adjust status if half day
    $status = $attendance['status'];
    if ($work_hours < $HALF_DAY_HOURS) {
        $status = 'half_day';
    }
    
    // Update attendance record
    $sql = "UPDATE attendance 
            SET check_out_time = ?, 
                check_out_location = ?, 
                work_hours = ?,
                status = ?
            WHERE attendance_id = ?";
    query($sql, [$check_out_time, $location, $work_hours, $status, $attendance['attendance_id']]);
    
    $_SESSION['success'] = "Checked out successfully at " . date('g:i A', strtotime($check_out_time)) . 
                          " (Work hours: " . number_format($work_hours, 2) . ")";
    header("Location: attendance_checkin.php?employee_id=$employee_id");
    exit;
}

/**
 * Determine attendance status based on check-in time
 */
function determineStatus($check_in_time, $work_start_time, $late_threshold_minutes) {
    $checkin_timestamp = strtotime($check_in_time);
    $start_timestamp = strtotime($work_start_time);
    $late_threshold_timestamp = $start_timestamp + ($late_threshold_minutes * 60);
    
    if ($checkin_timestamp <= $start_timestamp) {
        return 'present';  // On time
    } elseif ($checkin_timestamp <= $late_threshold_timestamp) {
        return 'present';  // Within grace period
    } else {
        return 'late';     // Late
    }
}

/**
 * Calculate work hours between check-in and check-out
 */
function calculateWorkHours($check_in_time, $check_out_time) {
    $checkin = strtotime($check_in_time);
    $checkout = strtotime($check_out_time);
    
    $seconds = $checkout - $checkin;
    $hours = $seconds / 3600;
    
    return round($hours, 2);
}
?>