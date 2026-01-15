<?php
/**
 * Attendance Actions Handler
 * Processes check-in and check-out with GPS tracking
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur'); // Malaysia timezone
require_once '../config/database.php';
require_once '../config/languages.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Attendance settings
$WORK_START_TIME = '08:30:00';  // Work starts at 8:30 AM

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
    // Security check for employee_id
    if ($_SESSION['user_type'] === 'employee') {
        $employee_id = $_SESSION['user_id'];
    } else {
        $employee_id = $_POST['employee_id'] ?? null;
        if (!$employee_id) {
            $_SESSION['error'] = "Employee ID is required";
            header("Location: attendance_checkin.php");
            exit;
        }
    }
    
    $location = $_POST['location'] ?? '';
    $late_reason = trim($_POST['late_reason'] ?? '');
    
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
    
    // Determine status using new function
    $status = determineStatus($check_in_time);
    
    // For very late check-ins (absent status), require a reason
    if ($status === 'absent' && empty($late_reason)) {
        $_SESSION['error'] = "A reason is required for check-ins after 12:00 PM (noon)";
        header("Location: attendance_checkin.php?employee_id=$employee_id");
        exit;
    }
    
    // Prepare notes with late reason if provided
    $notes = null;
    if (!empty($late_reason)) {
        $notes = "Late reason: " . $late_reason;
    }
    
    // Insert or update attendance record
    if ($existing) {
        $sql = "UPDATE attendance 
                SET check_in_time = ?, 
                    check_in_location = ?, 
                    status = ?,
                    notes = ?
                WHERE attendance_id = ?";
        query($sql, [$check_in_time, $location, $status, $notes, $existing['attendance_id']]);
    } else {
        $sql = "INSERT INTO attendance (employee_id, attendance_date, check_in_time, check_in_location, status, notes) 
                VALUES (?, ?, ?, ?, ?, ?)";
        query($sql, [$employee_id, $attendance_date, $check_in_time, $location, $status, $notes]);
    }
    
    $_SESSION['success'] = "Checked in successfully at " . date('g:i A', strtotime($check_in_time));
    header("Location: attendance_checkin.php?employee_id=$employee_id");
    exit;
}

/**
 * Process employee check-out
 */
function processCheckOut() {
    // Security check for employee_id
    if ($_SESSION['user_type'] === 'employee') {
        $employee_id = $_SESSION['user_id'];
    } else {
        $employee_id = $_POST['employee_id'] ?? null;
        if (!$employee_id) {
            $_SESSION['error'] = "Employee ID is required";
            header("Location: attendance_checkin.php");
            exit;
        }
    }
    
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
    
    // ============ CHANGED: Keep original check-in status (don't change based on work hours) ============
    $status = $attendance['status'];
    // Status is determined by check-in time only, not by work duration
    // ============ END CHANGED ============
    
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
 * Option A: Industry Standard
 * 
 * Rules:
 * - Before 8:30 AM = PRESENT (On time - rewards early arrivals)
 * - 8:31 AM - 11:59 AM = LATE (Still acceptable, has afternoon to work)
 * - After 12:00 PM = ABSENT (Missed half day - requires manager approval)
 */
function determineStatus($check_in_time) {
    // Extract hour and minute from check-in time (HH:MM:SS format)
    list($hour, $minute, $second) = explode(':', $check_in_time);
    $hour = (int)$hour;
    $minute = (int)$minute;
    
    // Before 8:30 AM = ON TIME (rewards early birds!)
    if ($hour < 8 || ($hour == 8 && $minute <= 30)) {
        return 'present';
    }
    // 8:31 AM - 11:59 AM = LATE (still has afternoon to work)
    elseif ($hour < 12) {
        return 'late';
    }
    // After 12:00 PM (noon) = ABSENT (missed half day - needs approval)
    else {
        return 'absent';
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