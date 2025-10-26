<?php
/**
 * EcoBin Logout
 * 
 * Destroys the session and redirects to login page
 */

// Start session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Set success message for login page
session_start();
$_SESSION['success'] = "You have been logged out successfully";

// Redirect to login page
header("Location: ../login.php");
exit;
?>
