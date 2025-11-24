<?php
/**
 * Employee Profile Page
 * View and edit employee profile information with profile picture upload
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check employee authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['user_id'];
$current_page = 'profile';

// Get employee data
$employee = getOne("
    SELECT e.*, a.area_name 
    FROM employees e
    LEFT JOIN areas a ON e.area_id = a.area_id
    WHERE e.employee_id = ?
", [$employee_id]);

if (!$employee) {
    header("Location: ../login.php");
    exit;
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_picture'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_picture']['name'];
        $filetype = $_FILES['profile_picture']['type'];
        $filesize = $_FILES['profile_picture']['size'];
        
        // Get file extension
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Validate file
        if (!in_array($ext, $allowed)) {
            $_SESSION['error'] = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
        } elseif ($filesize > 5242880) { // 5MB limit
            $_SESSION['error'] = "File size must be less than 5MB.";
        } else {
            // Create uploads directory if it doesn't exist
            $upload_dir = '../uploads/profiles/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Delete old profile picture if exists
            if ($employee['profile_picture'] && file_exists('../' . $employee['profile_picture'])) {
                unlink('../' . $employee['profile_picture']);
            }
            
            // Generate unique filename
            $new_filename = 'employee_' . $employee_id . '_' . time() . '.' . $ext;
            $upload_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                // Update database
                $db_path = 'uploads/profiles/' . $new_filename;
                $updated = query("UPDATE employees SET profile_picture = ? WHERE employee_id = ?", [$db_path, $employee_id]);
                
                if ($updated) {
                    $_SESSION['success'] = "Profile picture updated successfully!";
                    
                    // Refresh employee data
                    $employee = getOne("
                        SELECT e.*, a.area_name 
                        FROM employees e
                        LEFT JOIN areas a ON e.area_id = a.area_id
                        WHERE e.employee_id = ?
                    ", [$employee_id]);
                } else {
                    $_SESSION['error'] = "Failed to update profile picture in database.";
                }
            } else {
                $_SESSION['error'] = "Failed to upload profile picture.";
            }
        }
    } else {
        $_SESSION['error'] = "Please select a valid image file.";
    }
    
    header("Location: my_profile.php");
    exit;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);
    
    // Validate inputs
    if (empty($full_name) || empty($email)) {
        $_SESSION['error'] = "Name and email are required!";
    } else {
        // Check if email is taken by another user
        $existing = getOne("SELECT employee_id FROM employees WHERE email = ? AND employee_id != ?", [$email, $employee_id]);
        
        if ($existing) {
            $_SESSION['error'] = "Email is already in use by another account!";
        } else {
            // Update profile
            $updated = query("
                UPDATE employees 
                SET full_name = ?, email = ?, phone_number = ?
                WHERE employee_id = ?
            ", [$full_name, $email, $phone_number, $employee_id]);
            
            if ($updated) {
                $_SESSION['success'] = "Profile updated successfully!";
                $_SESSION['full_name'] = $full_name; // Update session
                
                // Refresh employee data
                $employee = getOne("
                    SELECT e.*, a.area_name 
                    FROM employees e
                    LEFT JOIN areas a ON e.area_id = a.area_id
                    WHERE e.employee_id = ?
                ", [$employee_id]);
            } else {
                $_SESSION['error'] = "Failed to update profile!";
            }
        }
    }
    
    header("Location: my_profile.php");
    exit;
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = "All password fields are required!";
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $_SESSION['error'] = "New password must be at least 6 characters!";
    } elseif ($employee['password'] !== $current_password) {
        $_SESSION['error'] = "Current password is incorrect!";
    } else {
        // Update password
        $updated = query("UPDATE employees SET password = ? WHERE employee_id = ?", [$new_password, $employee_id]);
        
        if ($updated) {
            $_SESSION['success'] = "Password changed successfully!";
        } else {
            $_SESSION['error'] = "Failed to change password!";
        }
    }
    
    header("Location: my_profile.php");
    exit;
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Get current date info
$current_time = date('g:i A');
$current_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - EcoBin</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #FAF1E4;
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 30px;
            max-width: 1400px;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #435334;
        }

        .header-info {
            text-align: right;
        }

        .header-time {
            font-size: 14px;
            color: #666;
        }

        .header-date {
            font-size: 12px;
            color: #999;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Profile Layout */
        .profile-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 40px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            height: fit-content;
        }

        .profile-avatar-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #CEDEBD, #9db89a);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
            border: 5px solid #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: rgba(0,0,0,0.6);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: white;
            font-size: 14px;
            font-weight: 600;
            gap: 5px;
            transition: all 0.3s;
        }

        .profile-avatar-container:hover .avatar-overlay {
            display: flex;
        }

        #pictureInput {
            display: none;
        }

        .profile-name {
            font-size: 24px;
            font-weight: 700;
            color: #435334;
            margin-bottom: 5px;
        }

        .profile-role {
            color: #999;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .profile-info {
            text-align: left;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #f0f0f0;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
        }

        .info-item .icon {
            font-size: 18px;
            width: 30px;
            text-align: center;
        }

        .info-item strong {
            color: #435334;
            min-width: 80px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-on_leave {
            background: #fff3cd;
            color: #856404;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        /* Forms Container */
        .forms-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        /* Form Section */
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .form-section h2 {
            color: #435334;
            margin-bottom: 20px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: border 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #CEDEBD;
        }

        .form-group input:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
            color: #999;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #435334;
            color: white;
        }

        .btn-primary:hover {
            background: #354428;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 83, 52, 0.3);
        }

        .form-note {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            font-size: 13px;
            color: #666;
            margin-top: 20px;
            border-left: 4px solid #CEDEBD;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .profile-container {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .profile-avatar-container,
            .profile-avatar {
                width: 120px;
                height: 120px;
            }

            .profile-avatar {
                font-size: 48px;
            }

            .profile-name {
                font-size: 20px;
            }

            .form-section {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>👤 My Profile</h1>
            <div class="header-info">
                <div class="header-time"><?php echo $current_time; ?></div>
                <div class="header-date"><?php echo $current_date; ?></div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <span>✓</span>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <span>⚠</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Profile Container -->
        <div class="profile-container">
            <!-- Left Column: Profile Card -->
            <div class="profile-card">
                <div class="profile-avatar-container">
                    <div class="profile-avatar">
                        <?php if ($employee['profile_picture'] && file_exists('../' . $employee['profile_picture'])): ?>
                            <img src="../<?php echo htmlspecialchars($employee['profile_picture']); ?>" alt="Profile Picture">
                        <?php else: ?>
                            👨‍💼
                        <?php endif; ?>
                    </div>
                    <div class="avatar-overlay" onclick="document.getElementById('pictureInput').click()">
                        📷 Change Photo
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" id="pictureForm">
                    <input type="file" id="pictureInput" name="profile_picture" accept="image/*" onchange="this.form.submit()">
                    <input type="hidden" name="update_picture">
                </form>

                <div class="profile-name"><?php echo htmlspecialchars($employee['full_name']); ?></div>
                <div class="profile-role">Waste Collection Employee</div>
                <span class="status-badge status-<?php echo $employee['status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $employee['status'])); ?>
                </span>

                <div class="profile-info">
                    <div class="info-item">
                        <span class="icon">📧</span>
                        <div>
                            <strong>Email:</strong><br>
                            <?php echo htmlspecialchars($employee['email']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <span class="icon">📱</span>
                        <div>
                            <strong>Phone:</strong><br>
                            <?php echo htmlspecialchars($employee['phone_number'] ?? 'Not set'); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <span class="icon">📍</span>
                        <div>
                            <strong>Area:</strong><br>
                            <?php echo htmlspecialchars($employee['area_name'] ?? 'Not assigned'); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <span class="icon">👤</span>
                        <div>
                            <strong>Username:</strong><br>
                            <?php echo htmlspecialchars($employee['username']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <span class="icon">📅</span>
                        <div>
                            <strong>Joined:</strong><br>
                            <?php echo date('M j, Y', strtotime($employee['created_at'])); ?>
                        </div>
                    </div>
                    <?php if ($employee['last_login']): ?>
                        <div class="info-item">
                            <span class="icon">🕒</span>
                            <div>
                                <strong>Last Login:</strong><br>
                                <?php echo date('M j, Y g:i A', strtotime($employee['last_login'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Forms -->
            <div class="forms-container">
                <!-- Edit Profile Form -->
                <div class="form-section">
                    <h2>✏️ Edit Profile Information</h2>
                    <form method="POST" action="">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Full Name *</label>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($employee['full_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email Address *</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone_number" value="<?php echo htmlspecialchars($employee['phone_number'] ?? ''); ?>" placeholder="e.g., 0123456789">
                            </div>
                            <div class="form-group">
                                <label>Username (cannot be changed)</label>
                                <input type="text" value="<?php echo htmlspecialchars($employee['username']); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label>Assigned Area (cannot be changed)</label>
                                <input type="text" value="<?php echo htmlspecialchars($employee['area_name'] ?? 'Not assigned'); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label>Account Status</label>
                                <input type="text" value="<?php echo ucfirst(str_replace('_', ' ', $employee['status'])); ?>" disabled>
                            </div>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            💾 Save Changes
                        </button>
                        <div class="form-note">
                            <strong>Note:</strong> Username, assigned area, and account status can only be changed by administrators.
                        </div>
                    </form>
                </div>

                <!-- Change Password Form -->
                <div class="form-section">
                    <h2>🔒 Change Password</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Current Password *</label>
                            <input type="password" name="current_password" placeholder="Enter your current password" required>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>New Password * (minimum 6 characters)</label>
                                <input type="password" name="new_password" placeholder="Enter new password" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password *</label>
                                <input type="password" name="confirm_password" placeholder="Re-enter new password" required minlength="6">
                            </div>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary">
                            🔑 Change Password
                        </button>
                        <div class="form-note">
                            <strong>Password Requirements:</strong> At least 6 characters long. Use a strong password to protect your account.
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>