<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';

if (empty($type) || empty($id)) {
    $_SESSION['error'] = "Invalid request";
    header("Location: users.php");
    exit;
}

header("Location: user_actions.php?action=delete&type=$type&id=$id");
exit;
?>