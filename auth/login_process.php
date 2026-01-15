<?php
/**
 * EcoBin Login Processing
 * 
 * Handles the login authentication
 * Checks credentials against database
 * Creates session on success
 */

// Start session
session_start();

// Include database connection
require_once '../config/database.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.php");
    exit;
}

// Get form data
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validate inputs
if (empty($username) || empty($password)) {
    $_SESSION['error'] = "Please enter both username and password";
    header("Location: ../login.php");
    exit;
}

try {
    // Try to find user in admins table
    $sql = "SELECT 
                admin_id as user_id,
                username,
                password,
                email,
                full_name,
                phone_number,
                'admin' as user_type,
                status
            FROM admins 
            WHERE username = ? AND status = 'active'
            LIMIT 1";
    
    $user = getOne($sql, [$username]);
    
    // If not found in admins, try employees table
    if (!$user) {
        $sql = "SELECT 
                    employee_id as user_id,
                    username,
                    password,
                    email,
                    full_name,
                    phone_number,
                    'employee' as user_type,
                    status
                FROM employees 
                WHERE username = ? AND status = 'active'
                LIMIT 1";
        
        $user = getOne($sql, [$username]);
    }
    
    // Check if user exists
    if (!$user) {
        $_SESSION['error'] = "Invalid username or password";
        header("Location: ../login.php");
        exit;
    }
    
    // Verify password
    if ($password !== $user['password']) {
        $_SESSION['error'] = "Invalid username or password";
        header("Location: ../login.php");
        exit;
    }
    
    // Check if account is active
    if ($user['status'] !== 'active') {
        $_SESSION['error'] = "Your account has been deactivated. Please contact admin.";
        header("Location: ../login.php");
        exit;
    }
    
    // Login successful! Create session
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['phone_number'] = $user['phone_number'] ?? '';
    
    // Update last login time
    if ($user['user_type'] === 'admin') {
        $updateSql = "UPDATE admins SET last_login = NOW() WHERE admin_id = ?";
    } else {
        $updateSql = "UPDATE employees SET last_login = NOW() WHERE employee_id = ?";
    }
    query($updateSql, [$user['user_id']]);
    
    // Redirect based on user type
    if ($user['user_type'] === 'admin') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../user/employee_dashboard.php");
    }
    exit;
    
} catch (Exception $e) {
    // Database error
    $_SESSION['error'] = "System error. Please try again later.";
    
    // Log error
    error_log("Login Error: " . $e->getMessage());
    
    header("Location: ../login.php");
    exit;
}
?>