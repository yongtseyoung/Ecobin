<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'request':
            handleLeaveRequest();
            break;
        
        case 'cancel':
            handleCancelRequest();
            break;
        
        case 'approve':
            handleApproveRequest();
            break;
        
        case 'reject':
            handleRejectRequest();
            break;
        
        default:
            throw new Exception("Invalid action");
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    
    if ($user_type === 'admin') {
        header("Location: leave.php");
    } else {
        header("Location: my_leave.php");
    }
    exit;
}

function handleLeaveRequest() {
    global $user_id, $user_type;
    
    if ($user_type !== 'employee') {
        throw new Exception("Only employees can request leave");
    }
    
    $leave_type_id = intval($_POST['leave_type_id'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $emergency_phone = trim($_POST['emergency_phone'] ?? '');
    
    if (!$leave_type_id || !$start_date || !$end_date || !$reason) {
        throw new Exception("Please fill in all required fields");
    }
    
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    
    if ($end < $start) {
        throw new Exception("End date must be after start date");
    }
    
    $interval = $start->diff($end);
    $total_days = $interval->days + 1; 
    
    $leave_type = getOne("SELECT * FROM leave_types WHERE leave_type_id = ?", [$leave_type_id]);
    if (!$leave_type) {
        throw new Exception("Invalid leave type");
    }
    
    $current_year = date('Y');
    $balance = getOne("SELECT * FROM leave_balances 
                       WHERE employee_id = ? 
                       AND leave_type_id = ? 
                       AND year = ?",
                       [$user_id, $leave_type_id, $current_year]);
    
    if (!$balance) {
        query("INSERT INTO leave_balances (employee_id, leave_type_id, total_days, used_days, remaining_days, year)
               VALUES (?, ?, ?, 0, ?, ?)",
               [$user_id, $leave_type_id, $leave_type['max_days_per_year'], $leave_type['max_days_per_year'], $current_year]);
        
        $balance = getOne("SELECT * FROM leave_balances 
                          WHERE employee_id = ? 
                          AND leave_type_id = ? 
                          AND year = ?",
                          [$user_id, $leave_type_id, $current_year]);
    }
    
    if ($leave_type['type_name'] !== 'Unpaid Leave' && $total_days > $balance['remaining_days']) {
        throw new Exception("Insufficient leave balance. You have {$balance['remaining_days']} days remaining.");
    }
    
    $overlap = getOne("SELECT * FROM leave_requests 
                       WHERE employee_id = ? 
                       AND status IN ('pending', 'approved')
                       AND (
                           (start_date <= ? AND end_date >= ?) OR
                           (start_date <= ? AND end_date >= ?) OR
                           (start_date >= ? AND end_date <= ?)
                       )",
                       [$user_id, $start_date, $start_date, $end_date, $end_date, $start_date, $end_date]);
    
    if ($overlap) {
        throw new Exception("You already have a leave request for overlapping dates");
    }
    
    query("INSERT INTO leave_requests (
               employee_id, leave_type_id, start_date, end_date, total_days,
               reason, emergency_contact, emergency_phone, status, created_at
           ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())",
           [$user_id, $leave_type_id, $start_date, $end_date, $total_days, $reason, 
            $emergency_contact ?: null, $emergency_phone ?: null]);
    
    $_SESSION['success'] = "Leave request submitted successfully! Waiting for approval.";
    header("Location: my_leave.php");
    exit;
}

function handleCancelRequest() {
    global $user_id, $user_type;
    
    $leave_id = intval($_POST['leave_id'] ?? 0);
    
    if (!$leave_id) {
        throw new Exception("Invalid leave request ID");
    }
    
    $leave = getOne("SELECT * FROM leave_requests WHERE leave_id = ?", [$leave_id]);
    
    if (!$leave) {
        throw new Exception("Leave request not found");
    }
    
    if ($user_type === 'employee' && $leave['employee_id'] != $user_id) {
        throw new Exception("You can only cancel your own leave requests");
    }
    
    if ($leave['status'] !== 'pending') {
        throw new Exception("Only pending leave requests can be cancelled");
    }
    
    query("UPDATE leave_requests SET status = 'cancelled' WHERE leave_id = ?", [$leave_id]);
    
    $_SESSION['success'] = "Leave request cancelled successfully";
    
    if ($user_type === 'admin') {
        header("Location: leave.php");
    } else {
        header("Location: my_leave.php");
    }
    exit;
}


function handleApproveRequest() {
    global $user_id, $user_type;
    
    if ($user_type !== 'admin') {
        throw new Exception("Only admins can approve leave requests");
    }
    
    $leave_id = intval($_POST['leave_id'] ?? 0);
    $review_notes = trim($_POST['review_notes'] ?? '');
    
    if (!$leave_id) {
        throw new Exception("Invalid leave request ID");
    }
    
    $leave = getOne("SELECT * FROM leave_requests WHERE leave_id = ?", [$leave_id]);
    
    if (!$leave) {
        throw new Exception("Leave request not found");
    }
    
    if ($leave['status'] !== 'pending') {
        throw new Exception("This leave request has already been processed");
    }
    
    query("UPDATE leave_requests SET 
           status = 'approved',
           reviewed_by = ?,
           reviewed_at = NOW(),
           review_notes = ?
           WHERE leave_id = ?",
           [$user_id, $review_notes ?: null, $leave_id]);
    
    $current_year = date('Y');
    query("UPDATE leave_balances SET 
           used_days = used_days + ?,
           remaining_days = remaining_days - ?
           WHERE employee_id = ? 
           AND leave_type_id = ? 
           AND year = ?",
           [$leave['total_days'], $leave['total_days'], $leave['employee_id'], $leave['leave_type_id'], $current_year]);
    
    $_SESSION['success'] = "Leave request approved successfully";
    header("Location: leave.php");
    exit;
}

function handleRejectRequest() {
    global $user_id, $user_type;
    
    if ($user_type !== 'admin') {
        throw new Exception("Only admins can reject leave requests");
    }
    
    $leave_id = intval($_POST['leave_id'] ?? 0);
    $review_notes = trim($_POST['review_notes'] ?? '');
    
    if (!$leave_id) {
        throw new Exception("Invalid leave request ID");
    }
    
    if (empty($review_notes)) {
        throw new Exception("Please provide a reason for rejection");
    }
    
    $leave = getOne("SELECT * FROM leave_requests WHERE leave_id = ?", [$leave_id]);
    
    if (!$leave) {
        throw new Exception("Leave request not found");
    }
    
    if ($leave['status'] !== 'pending') {
        throw new Exception("This leave request has already been processed");
    }
    
    query("UPDATE leave_requests SET 
           status = 'rejected',
           reviewed_by = ?,
           reviewed_at = NOW(),
           review_notes = ?
           WHERE leave_id = ?",
           [$user_id, $review_notes, $leave_id]);
    
    $_SESSION['success'] = "Leave request rejected";
    header("Location: leave.php");
    exit;
}
?>