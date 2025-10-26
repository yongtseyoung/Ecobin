<?php
/**
 * User Actions Handler
 * Processes create, update, and delete operations
 */

session_start();
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            createUser();
            break;
        case 'update':
            updateUser();
            break;
        case 'delete':
            deleteUser();
            break;
        default:
            $_SESSION['error'] = "Invalid action";
            header("Location: users.php");
            exit;
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header("Location: users.php");
    exit;
}

/**
 * Create new user (admin or employee)
 */
function createUser() {
    $user_type = $_POST['user_type'];
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $phone_number = trim($_POST['phone_number']);
    $status = $_POST['status'];
    
    // Validate inputs
    if (empty($username) || empty($password) || empty($email) || empty($full_name)) {
        $_SESSION['error'] = "All required fields must be filled";
        header("Location: user-add.php?type=$user_type");
        exit;
    }
    
    // Check if username already exists
    $check_admin = getOne("SELECT admin_id FROM admins WHERE username = ?", [$username]);
    $check_employee = getOne("SELECT employee_id FROM employees WHERE username = ?", [$username]);
    
    if ($check_admin || $check_employee) {
        $_SESSION['error'] = "Username already exists";
        header("Location: user-add.php?type=$user_type");
        exit;
    }
    
    // Check if email already exists
    $check_admin_email = getOne("SELECT admin_id FROM admins WHERE email = ?", [$email]);
    $check_employee_email = getOne("SELECT employee_id FROM employees WHERE email = ?", [$email]);
    
    if ($check_admin_email || $check_employee_email) {
        $_SESSION['error'] = "Email already exists";
        header("Location: user-add.php?type=$user_type");
        exit;
    }
    
    // Use plain password (as per current system setup)
    $hashed_password = $password;
    
    if ($user_type === 'admin') {
        // Create admin
        $sql = "INSERT INTO admins (username, password, email, full_name, phone_number, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        query($sql, [$username, $hashed_password, $email, $full_name, $phone_number, $status]);
        
        $_SESSION['success'] = "Admin created successfully!";
        header("Location: users.php?tab=admins");
    } else {
        // Create employee
        $area_id = !empty($_POST['area_id']) ? $_POST['area_id'] : null;
        
        $sql = "INSERT INTO employees (username, password, email, full_name, phone_number, area_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        query($sql, [$username, $hashed_password, $email, $full_name, $phone_number, $area_id, $status]);
        
        $_SESSION['success'] = "Employee created successfully!";
        header("Location: users.php?tab=employees");
    }
    exit;
}

/**
 * Update existing user
 */
function updateUser() {
    $user_type = $_POST['user_type'];
    $user_id = $_POST['user_id'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $phone_number = trim($_POST['phone_number']);
    $status = $_POST['status'];
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($username) || empty($email) || empty($full_name)) {
        $_SESSION['error'] = "All required fields must be filled";
        header("Location: user-edit.php?type=$user_type&id=$user_id");
        exit;
    }
    
    // Check if username exists (excluding current user)
    if ($user_type === 'admin') {
        $check = getOne("SELECT admin_id FROM admins WHERE username = ? AND admin_id != ?", [$username, $user_id]);
    } else {
        $check = getOne("SELECT employee_id FROM employees WHERE username = ? AND employee_id != ?", [$username, $user_id]);
    }
    
    if ($check) {
        $_SESSION['error'] = "Username already exists";
        header("Location: user-edit.php?type=$user_type&id=$user_id");
        exit;
    }
    
    if ($user_type === 'admin') {
        // Update admin
        if (!empty($password)) {
            $sql = "UPDATE admins 
                    SET username = ?, password = ?, email = ?, full_name = ?, phone_number = ?, status = ? 
                    WHERE admin_id = ?";
            query($sql, [$username, $password, $email, $full_name, $phone_number, $status, $user_id]);
        } else {
            $sql = "UPDATE admins 
                    SET username = ?, email = ?, full_name = ?, phone_number = ?, status = ? 
                    WHERE admin_id = ?";
            query($sql, [$username, $email, $full_name, $phone_number, $status, $user_id]);
        }
        
        $_SESSION['success'] = "Admin updated successfully!";
        header("Location: users.php?tab=admins");
    } else {
        // Update employee
        $area_id = !empty($_POST['area_id']) ? $_POST['area_id'] : null;
        
        if (!empty($password)) {
            $sql = "UPDATE employees 
                    SET username = ?, password = ?, email = ?, full_name = ?, phone_number = ?, area_id = ?, status = ? 
                    WHERE employee_id = ?";
            query($sql, [$username, $password, $email, $full_name, $phone_number, $area_id, $status, $user_id]);
        } else {
            $sql = "UPDATE employees 
                    SET username = ?, email = ?, full_name = ?, phone_number = ?, area_id = ?, status = ? 
                    WHERE employee_id = ?";
            query($sql, [$username, $email, $full_name, $phone_number, $area_id, $status, $user_id]);
        }
        
        $_SESSION['success'] = "Employee updated successfully!";
        header("Location: users.php?tab=employees");
    }
    exit;
}

/**
 * Delete user (soft delete - set status to inactive)
 */
function deleteUser() {
    $user_type = $_GET['type'];
    $user_id = $_GET['id'];
    
    // Prevent admin from deleting themselves
    if ($user_type === 'admin' && $user_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "You cannot delete your own account";
        header("Location: users.php?tab=admins");
        exit;
    }
    
    if ($user_type === 'admin') {
        // Permanently delete admin
        $sql = "DELETE FROM admins WHERE admin_id = ?";
        query($sql, [$user_id]);
        $_SESSION['success'] = "Admin deleted permanently!";
        header("Location: users.php?tab=admins");
    } else {
        // Permanently delete employee
        $sql = "DELETE FROM employees WHERE employee_id = ?";
        query($sql, [$user_id]);
        $_SESSION['success'] = "Employee deleted permanently!";
        header("Location: users.php?tab=employees");
    }
    exit;
}
?>