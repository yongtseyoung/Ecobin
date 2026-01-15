<?php
/**
 * Delete User
 * Redirects to action handler with delete action
 */

session_start();
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get parameters
$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';

if (empty($type) || empty($id)) {
    $_SESSION['error'] = "Invalid request";
    header("Location: users.php");
    exit;
}

// Redirect to action handler
header("Location: user_actions.php?action=delete&type=$type&id=$id");
exit;
?>