<?php
/**
 * Maintenance Report Actions Handler (Employee)
 * Handles report submission and cancellation
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';
require_once '../config/languages.php';

// Check authentication - employees only
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['user_id'];

// Get employee for language preference
$employee = getOne("SELECT language FROM employees WHERE employee_id = ?", [$employee_id]);
$_SESSION['language'] = $employee['language'] ?? 'en';

$action = $_POST['action'] ?? '';

try {
    if ($action === 'submit_report') {
        handleSubmitReport();
    } elseif ($action === 'cancel_report') {
        handleCancelReport();
    } else {
        throw new Exception(t('invalid_action'));
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    
    // Redirect based on action
    if ($action === 'submit_report') {
        header("Location: report_issue.php");
    } else {
        header("Location: my_reports.php");
    }
    exit;
}

// ==================== SUBMIT NEW REPORT ====================
function handleSubmitReport() {
    global $employee_id;
    
    // Get form data
    $issue_title = trim($_POST['issue_title'] ?? '');
    $issue_description = trim($_POST['issue_description'] ?? '');
    $issue_category = $_POST['issue_category'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';
    $location = trim($_POST['location'] ?? '');
    
    // Validate required fields
    if (empty($issue_title) || empty($issue_description) || empty($issue_category) || empty($location)) {
        throw new Exception(t('fill_all_required_fields'));
    }
    
    // Validate title length
    if (strlen($issue_title) < 5) {
        throw new Exception(t('title_too_short'));
    }
    
    // Validate description length
    if (strlen($issue_description) < 10) {
        throw new Exception(t('description_too_short'));
    }
    
    // Validate category
    $valid_categories = ['bin_issue', 'equipment_issue', 'facility_issue', 'safety_hazard', 'other'];
    if (!in_array($issue_category, $valid_categories)) {
        throw new Exception(t('invalid_issue_category'));
    }
    
    // Validate priority
    $valid_priorities = ['low', 'medium', 'high'];
    if (!in_array($priority, $valid_priorities)) {
        throw new Exception(t('invalid_priority_level'));
    }
    
    // Handle photo upload
    $photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photo_path = handlePhotoUpload($_FILES['photo']);
    }
    
    // Insert report into database
    query("INSERT INTO maintenance_reports (
               employee_id, issue_title, issue_description,
               issue_category, priority, location, photo_path,
               status, reported_at
           ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())",
           [$employee_id, $issue_title, $issue_description,
            $issue_category, $priority, $location, $photo_path]);
    
    $_SESSION['success'] = t('report_submitted_success');
    header("Location: my_reports.php");
    exit;
}

// ==================== HANDLE PHOTO UPLOAD ====================
function handlePhotoUpload($file) {
    // Validate file size (5MB max)
    $max_size = 5 * 1024 * 1024; // 5MB in bytes
    if ($file['size'] > $max_size) {
        throw new Exception(t('file_size_error'));
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception(t('file_type_error'));
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = '../uploads/maintenance_reports/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'report_' . time() . '_' . uniqid() . '.' . $extension;
    $upload_path = $upload_dir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception(t('failed_upload_photo'));
    }
    
    // Return relative path for database storage
    return 'uploads/maintenance_reports/' . $filename;
}

// ==================== CANCEL REPORT ====================
function handleCancelReport() {
    global $employee_id;
    
    $report_id = intval($_POST['report_id'] ?? 0);
    
    if (!$report_id) {
        throw new Exception(t('invalid_report_id'));
    }
    
    // Get report details
    $report = getOne("SELECT * FROM maintenance_reports WHERE report_id = ? AND employee_id = ?", 
                     [$report_id, $employee_id]);
    
    if (!$report) {
        throw new Exception(t('report_not_found_no_permission'));
    }
    
    // Check if report can be cancelled (only pending reports)
    if ($report['status'] !== 'pending') {
        throw new Exception(t('only_pending_reports_cancel'));
    }
    
    // Update status to cancelled
    query("UPDATE maintenance_reports SET 
           status = 'cancelled',
           updated_at = NOW()
           WHERE report_id = ?", [$report_id]);
    
    $_SESSION['success'] = t('report_cancelled_success');
    header("Location: my_reports.php");
    exit;
}
?>