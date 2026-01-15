<?php
/**
 * Leave Actions Handler
 * Processes leave requests, approvals, and cancellations
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';
require_once '../config/languages.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Get user language preference
if ($user_type === 'employee') {
    $user = getOne("SELECT language FROM employees WHERE employee_id = ?", [$user_id]);
} else {
    $user = getOne("SELECT language FROM admins WHERE admin_id = ?", [$user_id]);
}
$_SESSION['language'] = $user['language'] ?? 'en';

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
            throw new Exception(t('invalid_action'));
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    
    if ($user_type === 'admin') {
        header("Location: leave_management.php");
    } else {
        header("Location: my_leave.php");
    }
    exit;
}

// ==================== EMPLOYEE ACTIONS ====================

function handleLeaveRequest() {
    global $user_id, $user_type;
    
    // Only employees can request leave
    if ($user_type !== 'employee') {
        throw new Exception(t('only_employees_can_request_leave'));
    }
    
    // Get form data
    $leave_type_id = intval($_POST['leave_type_id'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $emergency_phone = trim($_POST['emergency_phone'] ?? '');
    
    // Validate
    if (!$leave_type_id || !$start_date || !$end_date || !$reason) {
        throw new Exception(t('fill_all_required_fields'));
    }
    
    // Validate dates
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    
    if ($end < $start) {
        throw new Exception(t('end_date_after_start_date'));
    }
    
    // Calculate total days
    $interval = $start->diff($end);
    $total_days = $interval->days + 1; // +1 to include both start and end date
    
    // Check if leave type exists
    $leave_type = getOne("SELECT * FROM leave_types WHERE leave_type_id = ?", [$leave_type_id]);
    if (!$leave_type) {
        throw new Exception(t('invalid_leave_type'));
    }
    
    // Get leave balance
    $current_year = date('Y');
    $balance = getOne("SELECT * FROM leave_balances 
                       WHERE employee_id = ? 
                       AND leave_type_id = ? 
                       AND year = ?",
                       [$user_id, $leave_type_id, $current_year]);
    
    // Create balance if doesn't exist
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
    
    // Check if enough balance (except for unpaid leave)
    if ($leave_type['type_name'] !== 'Unpaid Leave' && $total_days > $balance['remaining_days']) {
        throw new Exception(t('insufficient_leave_balance') . ". " . t('you_have') . " {$balance['remaining_days']} " . t('days_remaining') . ".");
    }
    
    // Check for overlapping leave
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
        throw new Exception(t('overlapping_leave_request'));
    }
    
    // Create leave request
    query("INSERT INTO leave_requests (
               employee_id, leave_type_id, start_date, end_date, total_days,
               reason, emergency_contact, emergency_phone, status, created_at
           ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())",
           [$user_id, $leave_type_id, $start_date, $end_date, $total_days, $reason, 
            $emergency_contact ?: null, $emergency_phone ?: null]);
    
    $_SESSION['success'] = t('leave_request_submitted');
    header("Location: my_leave.php");
    exit;
}

function handleCancelRequest() {
    global $user_id, $user_type;
    
    $leave_id = intval($_POST['leave_id'] ?? 0);
    
    if (!$leave_id) {
        throw new Exception(t('invalid_leave_request_id'));
    }
    
    // Get leave request
    $leave = getOne("SELECT * FROM leave_requests WHERE leave_id = ?", [$leave_id]);
    
    if (!$leave) {
        throw new Exception(t('leave_request_not_found'));
    }
    
    // Check ownership
    if ($user_type === 'employee' && $leave['employee_id'] != $user_id) {
        throw new Exception(t('cancel_own_leave_only'));
    }
    
    // Check if can be cancelled
    if ($leave['status'] !== 'pending') {
        throw new Exception(t('only_pending_can_cancel'));
    }
    
    // Cancel the request
    query("UPDATE leave_requests SET status = 'cancelled' WHERE leave_id = ?", [$leave_id]);
    
    $_SESSION['success'] = t('leave_request_cancelled');
    
    if ($user_type === 'admin') {
        header("Location: leave_management.php");
    } else {
        header("Location: my_leave.php");
    }
    exit;
}

// ==================== ADMIN ACTIONS ====================

function handleApproveRequest() {
    global $user_id, $user_type;
    
    // Only admins can approve
    if ($user_type !== 'admin') {
        throw new Exception(t('only_admins_can_approve'));
    }
    
    $leave_id = intval($_POST['leave_id'] ?? 0);
    $review_notes = trim($_POST['review_notes'] ?? '');
    
    if (!$leave_id) {
        throw new Exception(t('invalid_leave_request_id'));
    }
    
    // Get leave request
    $leave = getOne("SELECT * FROM leave_requests WHERE leave_id = ?", [$leave_id]);
    
    if (!$leave) {
        throw new Exception(t('leave_request_not_found'));
    }
    
    // Check if already processed
    if ($leave['status'] !== 'pending') {
        throw new Exception(t('already_processed'));
    }
    
    // Update request status
    query("UPDATE leave_requests SET 
           status = 'approved',
           reviewed_by = ?,
           reviewed_at = NOW(),
           review_notes = ?
           WHERE leave_id = ?",
           [$user_id, $review_notes ?: null, $leave_id]);
    
    // Update leave balance
    $current_year = date('Y');
    query("UPDATE leave_balances SET 
           used_days = used_days + ?,
           remaining_days = remaining_days - ?
           WHERE employee_id = ? 
           AND leave_type_id = ? 
           AND year = ?",
           [$leave['total_days'], $leave['total_days'], $leave['employee_id'], $leave['leave_type_id'], $current_year]);
    
    $_SESSION['success'] = t('leave_request_approved');
    header("Location: leave_management.php");
    exit;
}

function handleRejectRequest() {
    global $user_id, $user_type;
    
    // Only admins can reject
    if ($user_type !== 'admin') {
        throw new Exception(t('only_admins_can_reject'));
    }
    
    $leave_id = intval($_POST['leave_id'] ?? 0);
    $review_notes = trim($_POST['review_notes'] ?? '');
    
    if (!$leave_id) {
        throw new Exception(t('invalid_leave_request_id'));
    }
    
    if (empty($review_notes)) {
        throw new Exception(t('provide_rejection_reason'));
    }
    
    // Get leave request
    $leave = getOne("SELECT * FROM leave_requests WHERE leave_id = ?", [$leave_id]);
    
    if (!$leave) {
        throw new Exception(t('leave_request_not_found'));
    }
    
    // Check if already processed
    if ($leave['status'] !== 'pending') {
        throw new Exception(t('already_processed'));
    }
    
    // Update request status
    query("UPDATE leave_requests SET 
           status = 'rejected',
           reviewed_by = ?,
           reviewed_at = NOW(),
           review_notes = ?
           WHERE leave_id = ?",
           [$user_id, $review_notes, $leave_id]);
    
    $_SESSION['success'] = t('leave_request_rejected');
    header("Location: leave_management.php");
    exit;
}
?>