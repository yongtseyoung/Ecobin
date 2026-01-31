<?php


session_start();

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.php");
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['error'] = "Please enter both username and password";
    header("Location: ../login.php");
    exit;
}

try {
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
    
    if (!$user) {
        $_SESSION['error'] = "Invalid username or password";
        header("Location: ../login.php");
        exit;
    }
    
    if ($password !== $user['password']) {
        $_SESSION['error'] = "Invalid username or password";
        header("Location: ../login.php");
        exit;
    }
    
    if ($user['status'] !== 'active') {
        $_SESSION['error'] = "Your account has been deactivated. Please contact admin.";
        header("Location: ../login.php");
        exit;
    }
    
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['phone_number'] = $user['phone_number'] ?? '';
    
    if ($user['user_type'] === 'admin') {
        $updateSql = "UPDATE admins SET last_login = NOW() WHERE admin_id = ?";
    } else {
        $updateSql = "UPDATE employees SET last_login = NOW() WHERE employee_id = ?";
    }
    query($updateSql, [$user['user_id']]);
    
    if ($user['user_type'] === 'admin') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../user/employee_dashboard.php");
    }
    exit;
    
} catch (Exception $e) {
    $_SESSION['error'] = "System error. Please try again later.";
    
    error_log("Login Error: " . $e->getMessage());
    
    header("Location: ../login.php");
    exit;
}
?>