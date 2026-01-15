<?php
/**
 * Employee Profile Page
 * View and edit employee profile information with profile picture upload and language settings
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';
require_once '../config/languages.php';

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

// Load language preference from database
$_SESSION['language'] = $employee['language'] ?? 'en';

// Handle language update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_language'])) {
    $language = $_POST['language'] ?? 'en';
    
    // Validate language
    if (!in_array($language, ['en', 'ms'])) {
        $language = 'en';
    }
    
    // Update database
    query("UPDATE employees SET language = ? WHERE employee_id = ?", [$language, $employee_id]);
    
    // Update session
    $_SESSION['language'] = $language;
    
    // Success message in selected language
    $messages = [
        'en' => 'Language updated successfully! The system interface has been changed.',
        'ms' => 'Bahasa berjaya dikemaskini! Antara muka sistem telah ditukar.'
    ];
    
    $_SESSION['success'] = $messages[$language];
    
    // Refresh employee data
    $employee = getOne("
        SELECT e.*, a.area_name 
        FROM employees e
        LEFT JOIN areas a ON e.area_id = a.area_id
        WHERE e.employee_id = ?
    ", [$employee_id]);
    
    header("Location: my_profile.php");
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
            $_SESSION['error'] = t('invalid_file_type');
        } elseif ($filesize > 5242880) { // 5MB limit
            $_SESSION['error'] = t('file_too_large');
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
                    $_SESSION['success'] = t('profile_picture_updated');
                    
                    // Refresh employee data
                    $employee = getOne("
                        SELECT e.*, a.area_name 
                        FROM employees e
                        LEFT JOIN areas a ON e.area_id = a.area_id
                        WHERE e.employee_id = ?
                    ", [$employee_id]);
                } else {
                    $_SESSION['error'] = t('operation_failed');
                }
            } else {
                $_SESSION['error'] = t('upload_failed');
            }
        }
    } else {
        $_SESSION['error'] = t('please_select_file');
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
        $_SESSION['error'] = t('please_fill_required_fields');
    } else {
        // Check if email is taken by another user
        $existing = getOne("SELECT employee_id FROM employees WHERE email = ? AND employee_id != ?", [$email, $employee_id]);
        
        if ($existing) {
            $_SESSION['error'] = t('email_already_used');
        } else {
            // Update profile
            $updated = query("
                UPDATE employees 
                SET full_name = ?, email = ?, phone_number = ?
                WHERE employee_id = ?
            ", [$full_name, $email, $phone_number, $employee_id]);
            
            if ($updated) {
                $_SESSION['success'] = t('profile_updated');
                $_SESSION['full_name'] = $full_name; // Update session
                
                // Refresh employee data
                $employee = getOne("
                    SELECT e.*, a.area_name 
                    FROM employees e
                    LEFT JOIN areas a ON e.area_id = a.area_id
                    WHERE e.employee_id = ?
                ", [$employee_id]);
            } else {
                $_SESSION['error'] = t('operation_failed');
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
        $_SESSION['error'] = t('all_fields_required');
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error'] = t('passwords_not_match');
    } elseif (strlen($new_password) < 6) {
        $_SESSION['error'] = t('password_min_length');
    } elseif ($employee['password'] !== $current_password) {
        $_SESSION['error'] = t('current_password_incorrect');
    } else {
        // Update password
        $updated = query("UPDATE employees SET password = ? WHERE employee_id = ?", [$new_password, $employee_id]);
        
        if ($updated) {
            $_SESSION['success'] = t('password_changed');
        } else {
            $_SESSION['error'] = t('operation_failed');
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
<html lang="<?php echo $_SESSION['language']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('my_profile'); ?> - EcoBin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
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
            display: flex;
            align-items: center;
            gap: 10px;
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
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-left: 4px solid #dc3545;
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
            color: #435334;
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
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
        }

        .info-item .icon {
            font-size: 18px;
            width: 30px;
            text-align: center;
            color: #435334;
            margin-top: 2px;
        }

        .info-item strong {
            color: #435334;
            display: block;
            margin-bottom: 2px;
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: border 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
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

        /* Language Settings */
        .language-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .language-card {
            border: 3px solid #e0e0e0;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .language-card:hover {
            border-color: #CEDEBD;
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .language-card.active {
            border-color: #435334;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .language-card.active::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 15px;
            right: 15px;
            width: 30px;
            height: 30px;
            background: #435334;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .language-flag {
            font-size: 64px;
            margin-bottom: 15px;
        }

        .language-name {
            font-size: 20px;
            font-weight: 600;
            color: #435334;
            margin-bottom: 5px;
        }

        .language-description {
            font-size: 13px;
            color: #666;
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
                margin-left: 0;
                padding: 80px 15px 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .header-info {
                text-align: left;
            }

            .alert {
                padding: 12px 15px;
                font-size: 13px;
            }

            .profile-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .profile-card {
                padding: 30px 20px;
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

            .profile-role {
                font-size: 13px;
            }

            .profile-info {
                margin-top: 20px;
                padding-top: 20px;
            }

            .info-item {
                font-size: 13px;
                margin-bottom: 12px;
            }

            .info-item .icon {
                font-size: 16px;
                width: 25px;
            }

            .status-badge {
                font-size: 11px;
                padding: 5px 12px;
            }

            .forms-container {
                gap: 20px;
            }

            .form-section {
                padding: 20px;
            }

            .form-section h2 {
                font-size: 18px;
                margin-bottom: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-group {
                margin-bottom: 15px;
            }

            .form-group label {
                font-size: 12px;
            }

            .form-group input,
            .form-group select {
                padding: 10px 12px;
                font-size: 13px;
            }

            .btn {
                width: 100%;
                justify-content: center;
                padding: 12px 24px;
            }

            .form-note {
                padding: 12px;
                font-size: 12px;
            }

            .language-options {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .language-card {
                padding: 25px;
            }

            .language-flag {
                font-size: 48px;
                margin-bottom: 12px;
            }

            .language-name {
                font-size: 18px;
            }

            .language-description {
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 70px 10px 15px;
            }

            .page-header h1 {
                font-size: 20px;
            }

            .header-time {
                font-size: 12px;
            }

            .header-date {
                font-size: 11px;
            }

            .alert {
                padding: 10px 12px;
                font-size: 12px;
            }

            .profile-container {
                gap: 15px;
            }

            .profile-card {
                padding: 25px 15px;
            }

            .profile-avatar-container,
            .profile-avatar {
                width: 100px;
                height: 100px;
            }

            .profile-avatar {
                font-size: 40px;
                border: 3px solid #fff;
            }

            .profile-name {
                font-size: 18px;
            }

            .profile-role {
                font-size: 12px;
                margin-bottom: 15px;
            }

            .status-badge {
                font-size: 10px;
                padding: 4px 10px;
            }

            .profile-info {
                margin-top: 15px;
                padding-top: 15px;
            }

            .info-item {
                font-size: 12px;
                margin-bottom: 10px;
                gap: 10px;
            }

            .info-item .icon {
                font-size: 14px;
                width: 22px;
            }

            .forms-container {
                gap: 15px;
            }

            .form-section {
                padding: 15px;
            }

            .form-section h2 {
                font-size: 16px;
                margin-bottom: 12px;
            }

            .form-section > p {
                font-size: 12px;
                margin-bottom: 15px;
            }

            .form-group {
                margin-bottom: 12px;
            }

            .form-group label {
                font-size: 11px;
                margin-bottom: 6px;
            }

            .form-group input,
            .form-group select {
                padding: 10px;
                font-size: 13px;
            }

            .btn {
                padding: 12px 20px;
                font-size: 13px;
            }

            .form-note {
                padding: 10px;
                font-size: 11px;
                margin-top: 15px;
            }

            .language-options {
                gap: 12px;
                margin-top: 15px;
                margin-bottom: 15px;
            }

            .language-card {
                padding: 20px;
            }

            .language-card.active::after {
                top: 10px;
                right: 10px;
                width: 25px;
                height: 25px;
                font-size: 12px;
            }

            .language-flag {
                font-size: 40px;
                margin-bottom: 10px;
            }

            .language-name {
                font-size: 16px;
            }

            .language-description {
                font-size: 11px;
            }

            .avatar-overlay {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <?php echo t('my_profile'); ?>
            </h1>
            <div class="header-info">
                <div class="header-time"><?php echo $current_time; ?></div>
                <div class="header-date"><?php echo $current_date; ?></div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-exclamation-triangle"></i>
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
                            <i class="fa-solid fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="avatar-overlay" onclick="document.getElementById('pictureInput').click()">
                        <i class="fa-solid fa-camera"></i>
                        <?php echo t('change_photo'); ?>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" id="pictureForm">
                    <input type="file" id="pictureInput" name="profile_picture" accept="image/*" onchange="this.form.submit()">
                    <input type="hidden" name="update_picture">
                </form>

                <div class="profile-name"><?php echo htmlspecialchars($employee['full_name']); ?></div>
                <div class="profile-role"><?php echo t('waste_collection_employee'); ?></div>
                <span class="status-badge status-<?php echo $employee['status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $employee['status'])); ?>
                </span>

                <div class="profile-info">
                    <div class="info-item">
                        <i class="fa-solid fa-envelope icon"></i>
                        <div>
                            <strong><?php echo t('email'); ?>:</strong>
                            <?php echo htmlspecialchars($employee['email']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fa-solid fa-phone icon"></i>
                        <div>
                            <strong><?php echo t('phone'); ?>:</strong>
                            <?php echo htmlspecialchars($employee['phone_number'] ?? t('not_set')); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fa-solid fa-location-dot icon"></i>
                        <div>
                            <strong><?php echo t('area'); ?>:</strong>
                            <?php echo htmlspecialchars($employee['area_name'] ?? t('not_assigned')); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fa-solid fa-user-tag icon"></i>
                        <div>
                            <strong><?php echo t('username'); ?>:</strong>
                            <?php echo htmlspecialchars($employee['username']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fa-solid fa-calendar-plus icon"></i>
                        <div>
                            <strong><?php echo t('joined'); ?>:</strong>
                            <?php echo date('M j, Y', strtotime($employee['created_at'])); ?>
                        </div>
                    </div>
                    <?php if ($employee['last_login']): ?>
                        <div class="info-item">
                            <i class="fa-solid fa-clock icon"></i>
                            <div>
                                <strong><?php echo t('last_login'); ?>:</strong>
                                <?php echo date('M j, Y g:i A', strtotime($employee['last_login'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Forms -->
            <div class="forms-container">
                <!-- Language Settings Form -->
                <div class="form-section">
                    <h2>
                        <i class="fa-solid fa-language"></i>
                        <?php echo t('language_settings'); ?>
                    </h2>
                    <p style="color: #666; margin-bottom: 20px;"><?php echo t('select_language'); ?></p>

                    <form action="" method="POST" id="languageForm">
                        <input type="hidden" name="update_language">
                        
                        <div class="language-options">
                            <!-- English -->
                            <div class="language-card <?php echo $employee['language'] === 'en' ? 'active' : ''; ?>" 
                                 onclick="selectLanguage('en')">
                                <div class="language-flag">🇬🇧</div>
                                <div class="language-name">English</div>
                                <div class="language-description">English Language</div>
                                <input type="radio" name="language" value="en" 
                                       <?php echo $employee['language'] === 'en' ? 'checked' : ''; ?> 
                                       style="display: none;">
                            </div>

                            <!-- Malay -->
                            <div class="language-card <?php echo $employee['language'] === 'ms' ? 'active' : ''; ?>" 
                                 onclick="selectLanguage('ms')">
                                <div class="language-flag">🇲🇾</div>
                                <div class="language-name">Bahasa Malaysia</div>
                                <div class="language-description">Bahasa Malaysia</div>
                                <input type="radio" name="language" value="ms" 
                                       <?php echo $employee['language'] === 'ms' ? 'checked' : ''; ?> 
                                       style="display: none;">
                            </div>
                        </div>
                    </form>

                <!-- Edit Profile Form -->
                <div class="form-section">
                    <h2>
                        <i class="fa-solid fa-user-edit"></i>
                        <?php echo t('edit_profile'); ?>
                    </h2>
                    <form method="POST" action="">
                        <div class="form-grid">
                            <div class="form-group">
                                <label><?php echo t('full_name'); ?> *</label>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($employee['full_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label><?php echo t('email'); ?> *</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label><?php echo t('phone'); ?></label>
                                <input type="text" name="phone_number" value="<?php echo htmlspecialchars($employee['phone_number'] ?? ''); ?>" placeholder="e.g., 0123456789">
                            </div>
                            <div class="form-group">
                                <label><?php echo t('username'); ?> (<?php echo t('cannot_be_changed'); ?>)</label>
                                <input type="text" value="<?php echo htmlspecialchars($employee['username']); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label><?php echo t('area'); ?> (<?php echo t('cannot_be_changed'); ?>)</label>
                                <input type="text" value="<?php echo htmlspecialchars($employee['area_name'] ?? t('not_assigned')); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label><?php echo t('status'); ?></label>
                                <input type="text" value="<?php echo ucfirst(str_replace('_', ' ', $employee['status'])); ?>" disabled>
                            </div>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <?php echo t('save'); ?>
                        </button>
                        <div class="form-note">
                            <strong><?php echo t('note'); ?>:</strong> <?php echo t('admin_only_fields'); ?>
                        </div>
                    </form>
                </div>

                <!-- Change Password Form -->
                <div class="form-section">
                    <h2>
                        <i class="fa-solid fa-key"></i>
                        <?php echo t('change_password'); ?>
                    </h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label><?php echo t('current_password'); ?> *</label>
                            <input type="password" name="current_password" placeholder="<?php echo t('enter_current_password'); ?>" required>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label><?php echo t('new_password'); ?> * (<?php echo t('minimum_6_characters'); ?>)</label>
                                <input type="password" name="new_password" placeholder="<?php echo t('enter_new_password'); ?>" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label><?php echo t('confirm_password'); ?> *</label>
                                <input type="password" name="confirm_password" placeholder="<?php echo t('reenter_password'); ?>" required minlength="6">
                            </div>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fa-solid fa-shield-halved"></i>
                            <?php echo t('change_password'); ?>
                        </button>
                        <div class="form-note">
                            <strong><?php echo t('password_requirements'); ?>:</strong> <?php echo t('password_requirement_text'); ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function selectLanguage(lang) {
            // Update radio button
            document.querySelector('input[value="' + lang + '"]').checked = true;
            
            // Update visual selection
            document.querySelectorAll('.language-card').forEach(card => {
                card.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // Auto-submit form
            document.getElementById('languageForm').submit();
        }

        // Auto-hide success message after 3 seconds
        <?php if ($success): ?>
        setTimeout(() => {
            const alert = document.querySelector('.alert-success');
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>