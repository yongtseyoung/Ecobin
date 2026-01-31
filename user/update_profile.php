<?php


session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';
require_once '../config/languages.php';

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
    
    if (!in_array($language, ['en', 'ms'])) {
        throw new Exception("Invalid language selection");
    }
    
    query("UPDATE employees SET language = ? WHERE employee_id = ?", [$language, $employee_id]);
    
    $_SESSION['language'] = $language;
    
    $messages = [
        'en' => 'Language updated successfully! The system interface has been changed to English.',
        'ms' => 'Bahasa berjaya dikemaskini! Antara muka sistem telah ditukar ke Bahasa Malaysia.'
    ];
    
    $_SESSION['success'] = $messages[$language];
    header("Location: profile.php");
    exit;
}
?>