<?php
/**
 * Admin Maintenance Actions Handler
 * Handles all admin actions for maintenance reports
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check authentication - admins only
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    if ($action === 'update_status') {
        handleUpdateStatus();
    } elseif ($action === 'assign') {
        handleAssign();
    } elseif ($action === 'update_notes') {
        handleUpdateNotes();
    } elseif ($action === 'delete') {
        handleDelete();
    } else {
        throw new Exception("Invalid action");
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    
    // Redirect based on action
    $report_id = intval($_POST['report_id'] ?? 0);
    if ($report_id && in_array($action, ['update_status', 'assign', 'update_notes'])) {
        header("Location: report_details.php?id=" . $report_id);
    } else {
        header("Location: maintenance_reports.php");
    }
    exit;
}

// ==================== UPDATE STATUS ====================
function handleUpdateStatus() {
    global $admin_id;
    
    $report_id = intval($_POST['report_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    
    // Validate
    if (!$report_id) {
        throw new Exception("Invalid report ID");
    }
    
    $valid_statuses = ['pending', 'in_progress', 'resolved', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        throw new Exception("Invalid status");
    }
    
    // Get current report details
    $report = getOne("SELECT * FROM maintenance_reports WHERE report_id = ?", [$report_id]);
    if (!$report) {
        throw new Exception("Report not found");
    }
    
    // Prevent changing status of cancelled reports
    if ($report['status'] === 'cancelled' && $new_status !== 'cancelled') {
        throw new Exception("Cannot change status of cancelled reports");
    }
    
    // Update status
    if ($new_status === 'resolved') {
        // Set resolved_at timestamp when marking as resolved
        query("UPDATE maintenance_reports SET 
               status = ?,
               resolved_at = NOW(),
               updated_at = NOW()
               WHERE report_id = ?", [$new_status, $report_id]);
    } else {
        // Regular status update
        query("UPDATE maintenance_reports SET 
               status = ?,
               updated_at = NOW()
               WHERE report_id = ?", [$new_status, $report_id]);
        
        // Clear resolved_at if changing from resolved to another status
        if ($report['status'] === 'resolved' && $new_status !== 'resolved') {
            query("UPDATE maintenance_reports SET resolved_at = NULL WHERE report_id = ?", [$report_id]);
        }
    }
    
    $_SESSION['success'] = "Report status updated to: " . ucfirst(str_replace('_', ' ', $new_status));
    
    // Redirect back to report details or list
    if (isset($_POST['redirect_to_details'])) {
        header("Location: report_details.php?id=" . $report_id);
    } else {
        header("Location: maintenance_reports.php");
    }
    exit;
}

// ==================== ASSIGN TO ADMIN ====================
function handleAssign() {
    global $admin_id;
    
    $report_id = intval($_POST['report_id'] ?? 0);
    $assigned_to = intval($_POST['assigned_to'] ?? 0);
    
    // Validate
    if (!$report_id) {
        throw new Exception("Invalid report ID");
    }
    
    // Get report details
    $report = getOne("SELECT * FROM maintenance_reports WHERE report_id = ?", [$report_id]);
    if (!$report) {
        throw new Exception("Report not found");
    }
    
    // If assigned_to is 0, unassign (set to NULL)
    if ($assigned_to === 0) {
        query("UPDATE maintenance_reports SET 
               assigned_to = NULL,
               updated_at = NOW()
               WHERE report_id = ?", [$report_id]);
        
        $_SESSION['success'] = "Report unassigned successfully";
    } else {
        // Verify admin exists
        $admin = getOne("SELECT * FROM admins WHERE admin_id = ?", [$assigned_to]);
        if (!$admin) {
            throw new Exception("Selected admin not found");
        }
        
        // Assign to admin
        query("UPDATE maintenance_reports SET 
               assigned_to = ?,
               updated_at = NOW()
               WHERE report_id = ?", [$assigned_to, $report_id]);
        
        $_SESSION['success'] = "Report assigned to " . htmlspecialchars($admin['full_name']);
    }
    
    header("Location: report_details.php?id=" . $report_id);
    exit;
}

// ==================== UPDATE ADMIN NOTES ====================
function handleUpdateNotes() {
    global $admin_id;
    
    $report_id = intval($_POST['report_id'] ?? 0);
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    
    // Validate
    if (!$report_id) {
        throw new Exception("Invalid report ID");
    }
    
    // Get report details
    $report = getOne("SELECT * FROM maintenance_reports WHERE report_id = ?", [$report_id]);
    if (!$report) {
        throw new Exception("Report not found");
    }
    
    // Update notes (can be empty to clear notes)
    query("UPDATE maintenance_reports SET 
           admin_notes = ?,
           updated_at = NOW()
           WHERE report_id = ?", [$admin_notes ?: null, $report_id]);
    
    if (empty($admin_notes)) {
        $_SESSION['success'] = "Admin notes cleared";
    } else {
        $_SESSION['success'] = "Admin notes updated successfully";
    }
    
    header("Location: report_details.php?id=" . $report_id);
    exit;
}

// ==================== DELETE REPORT ====================
function handleDelete() {
    global $admin_id;
    
    $report_id = intval($_POST['report_id'] ?? 0);
    
    // Validate
    if (!$report_id) {
        throw new Exception("Invalid report ID");
    }
    
    // Get report details
    $report = getOne("SELECT * FROM maintenance_reports WHERE report_id = ?", [$report_id]);
    if (!$report) {
        throw new Exception("Report not found");
    }
    
    // Only allow deletion of resolved or cancelled reports
    if (!in_array($report['status'], ['resolved', 'cancelled'])) {
        throw new Exception("Only resolved or cancelled reports can be deleted");
    }
    
    // Delete photo file if exists
    if (!empty($report['photo_path'])) {
        $photo_full_path = '../' . $report['photo_path'];
        if (file_exists($photo_full_path)) {
            unlink($photo_full_path);
        }
    }
    
    // Delete report from database
    query("DELETE FROM maintenance_reports WHERE report_id = ?", [$report_id]);
    
    $_SESSION['success'] = "Report #" . $report_id . " deleted successfully";
    header("Location: maintenance_reports.php");
    exit;
}
?>