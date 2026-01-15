<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';
require_once '../config/languages.php';

// Check authentication - admins only
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
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
    global $admin_id;
    
    $language = $_POST['language'] ?? 'en';
    
    if (!in_array($language, ['en', 'ms'])) {
        throw new Exception("Invalid language selection");
    }
    
    // Update database
    query("UPDATE admins SET language = ? WHERE admin_id = ?", [$language, $admin_id]);
    
    // Update session
    $_SESSION['language'] = $language;
    
    $messages = [
        'en' => 'Language updated successfully!',
        'ms' => 'Bahasa berjaya dikemaskini!'
    ];
    
    $_SESSION['success'] = $messages[$language];
    header("Location: profile.php");
    exit;
}
?>