<?php
/**
 * Profile Update Handler
 * Updates user profile including language preference
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';
require_once '../config/languages.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    if ($action === 'update_language') {
        handleUpdateLanguage();
    } else {
        throw new Exception("Invalid action");
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: profile.php");
    exit;
}

function handleUpdateLanguage() {
    global $employee_id;
    
    $language = $_POST['language'] ?? 'en';
    
    // Validate language
    if (!in_array($language, ['en', 'ms'])) {
        throw new Exception("Invalid language selection");
    }
    
    // Update database
    query("UPDATE employees SET language = ? WHERE employee_id = ?", [$language, $employee_id]);
    
    // Update session
    $_SESSION['language'] = $language;
    
    // Success message in selected language
    $messages = [
        'en' => 'Language updated successfully! The system interface has been changed to English.',
        'ms' => 'Bahasa berjaya dikemaskini! Antara muka sistem telah ditukar ke Bahasa Malaysia.'
    ];
    
    $_SESSION['success'] = $messages[$language];
    header("Location: profile.php");
    exit;
}
?>