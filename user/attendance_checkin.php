<?php


session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';
require_once '../config/languages.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_type = $_SESSION['user_type'];
$user_name = $_SESSION['full_name'] ?? 'User';

$current_page = 'attendance';

if ($user_type === 'employee') {
    $employee_id = $_SESSION['user_id'];
} else {
    $employee_id = $_GET['employee_id'] ?? null;
    if (!$employee_id) {
        $_SESSION['error'] = t('please_select_employee');
        header("Location: attendance.php");
        exit;
    }
}

$employee = getOne("SELECT * FROM employees WHERE employee_id = ?", [$employee_id]);
if (!$employee) {
    $_SESSION['error'] = t('employee_not_found');
    header("Location: attendance.php");
    exit;
}

$_SESSION['language'] = $employee['language'] ?? 'en';

$today = date('Y-m-d');
$attendance_today = getOne("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?", [$employee_id, $today]);

$has_checked_in = $attendance_today && $attendance_today['check_in_time'];
$has_checked_out = $attendance_today && $attendance_today['check_out_time'];

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$current_time = date('g:i A');
$current_date = date('l, F j, Y');

$current_hour = (int)date('H');
$current_minute = (int)date('i');

$status_message = '';
$status_class = '';
$requires_reason = false;

if (!$has_checked_in) {
    if ($current_hour < 8 || ($current_hour == 8 && $current_minute <= 30)) {
        $status_message = t('status_on_time');
        $status_class = 'alert-success';
        $requires_reason = false;
    } 
    elseif ($current_hour < 12) {
        $status_message = t('status_late_arrival');
        $status_class = 'alert-warning';
        $requires_reason = true;
    } 
    else {
        $status_message = t('status_very_late');
        $status_class = 'alert-danger';
        $requires_reason = true;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('check_in_out'); ?> - EcoBin</title>
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
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .checkin-wrapper {
        max-width: 600px;
        width: 100%;
    }

    .icon-main {
        color: #435334;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #435334;
        text-decoration: none;
        font-weight: 600;
        margin-bottom: 20px;
        padding: 10px 15px;
        border-radius: 10px;
        transition: all 0.3s;
    }

    .back-link:hover {
        background: white;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .checkin-container {
        background: white;
        border-radius: 25px;
        padding: 50px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        position: relative;
        overflow: hidden;
    }

    .checkin-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(90deg, #435334, #27ae60, #CEDEBD);
    }

    .header {
        text-align: center;
        margin-bottom: 35px;
    }

    .greeting-icon {
        font-size: 64px;
        margin-bottom: 15px;
        animation: wave 1.5s ease-in-out infinite;
    }

    @keyframes wave {
        0%, 100% { transform: rotate(0deg); }
        25% { transform: rotate(20deg); }
        75% { transform: rotate(-20deg); }
    }

    .header h1 {
        font-size: 32px;
        color: #435334;
        margin-bottom: 8px;
        font-weight: 700;
    }

    .header .user-name {
        font-size: 22px;
        color: #27ae60;
        font-weight: 600;
    }

    .current-info {
        background: linear-gradient(135deg, #435334 0%, #5a6f4a 100%);
        padding: 40px;
        border-radius: 20px;
        margin-bottom: 30px;
        text-align: center;
        box-shadow: 0 8px 25px rgba(67, 83, 52, 0.3);
        position: relative;
        overflow: hidden;
    }

    .current-info::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(206, 222, 189, 0.1) 0%, transparent 70%);
        animation: pulse 3s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); opacity: 0.5; }
        50% { transform: scale(1.1); opacity: 0.8; }
    }

    .current-info .time {
        font-size: 56px;
        font-weight: 700;
        color: white;
        margin-bottom: 10px;
        text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        position: relative;
        z-index: 1;
    }

    .current-info .date {
        font-size: 18px;
        color: #CEDEBD;
        font-weight: 500;
        position: relative;
        z-index: 1;
    }

    .location-status {
        padding: 18px 24px;
        border-radius: 15px;
        margin-bottom: 25px;
        text-align: center;
        font-size: 15px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: all 0.3s;
    }

    .location-detecting {
        background: #fff3cd;
        color: #856404;
        border: 2px solid #ffeaa7;
    }

    .location-success {
        background: #d4edda;
        color: #155724;
        border: 2px solid #c3e6cb;
        animation: slideIn 0.5s ease-out;
    }

    .location-error {
        background: #f8d7da;
        color: #721c24;
        border: 2px solid #f5c6cb;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .alert {
        padding: 18px 24px;
        border-radius: 15px;
        margin-bottom: 25px;
        font-size: 16px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        text-align: center;
        animation: slideIn 0.5s ease-out;
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 2px solid #c3e6cb;
    }

    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 2px solid #f5c6cb;
    }

    .alert-warning {
        background: #fff3cd;
        color: #856404;
        border: 2px solid #ffeaa7;
    }

    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border: 2px solid #f5c6cb;
    }

    .status-card {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 25px;
        border-radius: 20px;
        margin-bottom: 30px;
        border: 2px solid #CEDEBD;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }

    .status-card h3 {
        font-size: 16px;
        color: #435334;
        margin-bottom: 20px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .status-info {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .status-item {
        background: white;
        padding: 20px;
        border-radius: 15px;
        border: 2px solid #e0e0e0;
        transition: all 0.3s;
    }

    .status-item:hover {
        border-color: #CEDEBD;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .status-item label {
        font-size: 11px;
        color: #999;
        display: block;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
    }

    .status-item .value {
        font-size: 22px;
        font-weight: 700;
        color: #435334;
    }

    .status-item .value.highlight {
        color: #27ae60;
    }

    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .btn {
        padding: 22px 40px;
        border: none;
        border-radius: 15px;
        font-size: 18px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        width: 100%;
        position: relative;
        overflow: hidden;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .btn::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }

    .btn:hover::before {
        width: 300px;
        height: 300px;
    }

    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn:disabled:hover {
        transform: none;
    }

    .btn span {
        position: relative;
        z-index: 1;
    }

    .btn-checkin {
        background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        color: white;
        box-shadow: 0 6px 20px rgba(39, 174, 96, 0.3);
    }

    .btn-checkin:hover:not(:disabled) {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(39, 174, 96, 0.4);
    }

    .btn-checkout {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        color: white;
        box-shadow: 0 6px 20px rgba(231, 76, 60, 0.3);
    }

    .btn-checkout:hover:not(:disabled) {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(231, 76, 60, 0.4);
    }

    .btn-back {
        background: #435334;
        color: white;
        padding: 14px 24px;
        font-size: 15px;
        box-shadow: 0 4px 15px rgba(67, 83, 52, 0.2);
        text-decoration: none;
    }

    .btn-back:hover {
        background: #354428;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(67, 83, 52, 0.3);
    }

    .divider {
        text-align: center;
        margin: 25px 0;
        color: #999;
        font-size: 14px;
        font-weight: 600;
        position: relative;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .divider::before,
    .divider::after {
        content: '';
        position: absolute;
        top: 50%;
        width: 38%;
        height: 2px;
        background: linear-gradient(to right, transparent, #e0e0e0, transparent);
    }

    .divider::before {
        left: 0;
    }

    .divider::after {
        right: 0;
    }

    .icon-large {
        font-size: 28px;
    }

    .reason-field {
        margin-bottom: 20px;
        animation: slideIn 0.5s ease-out;
    }

    .reason-field label {
        display: block;
        margin-bottom: 10px;
        color: #435334;
        font-weight: 700;
        font-size: 15px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .reason-field .required-badge {
        color: #e74c3c;
        font-size: 13px;
        font-weight: 600;
        margin-left: 5px;
    }

    .reason-field textarea {
        width: 100%;
        padding: 15px;
        border: 2px solid #CEDEBD;
        border-radius: 15px;
        font-family: inherit;
        font-size: 14px;
        resize: vertical;
        min-height: 100px;
        transition: all 0.3s;
        background: #f8f9fa;
    }

    .reason-field textarea:focus {
        outline: none;
        border-color: #27ae60;
        background: white;
        box-shadow: 0 4px 15px rgba(39, 174, 96, 0.1);
    }

    .reason-field .help-text {
        font-size: 13px;
        color: #666;
        margin-top: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 500;
    }

    .reason-field .help-text.warning {
        color: #856404;
    }

    .reason-field .help-text.danger {
        color: #721c24;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .checkin-container {
        animation: fadeInUp 0.6s ease-out;
    }

    .spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(0,0,0,0.1);
        border-left-color: #856404;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            padding: 80px 20px 20px;
        }

        .checkin-wrapper {
            max-width: 100%;
        }

        .checkin-container {
            padding: 30px 20px;
            border-radius: 20px;
        }

        .greeting-icon {
            font-size: 48px;
        }

        .header h1 {
            font-size: 24px;
        }

        .header .user-name {
            font-size: 18px;
        }

        .current-info {
            padding: 25px 20px;
        }

        .current-info .time {
            font-size: 42px;
        }

        .current-info .date {
            font-size: 14px;
        }

        .status-info {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .status-item {
            padding: 15px;
        }

        .status-item .value {
            font-size: 20px;
        }

        .status-card h3 {
            font-size: 14px;
        }

        .alert {
            padding: 15px 18px;
            font-size: 14px;
            flex-wrap: wrap;
        }

        .location-status {
            padding: 15px 18px;
            font-size: 14px;
        }

        .btn {
            padding: 18px 30px;
            font-size: 16px;
        }

        .btn-back {
            padding: 14px 20px;
            font-size: 14px;
        }

        .icon-large {
            font-size: 22px;
        }

        .reason-field label {
            font-size: 13px;
        }

        .reason-field textarea {
            padding: 12px;
            font-size: 13px;
            min-height: 80px;
        }

        .reason-field .help-text {
            font-size: 12px;
        }

        .divider {
            font-size: 12px;
        }
    }

    @media (max-width: 480px) {
        .main-content {
            padding: 70px 15px 15px;
        }

        .checkin-container {
            padding: 25px 15px;
        }

        .greeting-icon {
            font-size: 40px;
        }

        .header h1 {
            font-size: 20px;
        }

        .header .user-name {
            font-size: 16px;
        }

        .current-info {
            padding: 20px 15px;
        }

        .current-info .time {
            font-size: 36px;
        }

        .current-info .date {
            font-size: 13px;
        }

        .status-item {
            padding: 12px;
        }

        .status-item label {
            font-size: 10px;
        }

        .status-item .value {
            font-size: 18px;
        }

        .btn {
            padding: 16px 24px;
            font-size: 14px;
            letter-spacing: 0.5px;
        }

        .btn-back {
            padding: 12px 18px;
            font-size: 13px;
        }

        .icon-large {
            font-size: 20px;
        }
    }
</style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="checkin-wrapper">

            <div class="checkin-container">
                <div class="header">
                    <h1><?php echo t('welcome'); ?>!</h1>
                    <div class="user-name"><?php echo htmlspecialchars($employee['full_name']); ?></div>
                </div>

                <div class="current-info">
                    <div class="time" id="current-time"><?php echo $current_time; ?></div>
                    <div class="date"><?php echo $current_date; ?></div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fa-solid fa-circle-check" style="font-size: 20px;"></i>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fa-solid fa-triangle-exclamation" style="font-size: 20px;"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!$has_checked_in && $status_message): ?>
                    <div class="alert <?php echo $status_class; ?>">
                        <?php 
                        if ($status_class === 'alert-success') {
                            echo '<i class="fa-solid fa-circle-check" style="font-size: 20px;"></i>';
                        } elseif ($status_class === 'alert-warning') {
                            echo '<i class="fa-solid fa-triangle-exclamation" style="font-size: 20px;"></i>';
                        } else {
                            echo '<i class="fa-solid fa-circle-xmark" style="font-size: 20px;"></i>';
                        }
                        ?>
                        <span><?php echo $status_message; ?></span>
                    </div>
                <?php endif; ?>

                <div class="location-status location-detecting" id="location-status">
                    <span class="spinner"></span>
                    <span><?php echo t('detecting_location'); ?></span>
                </div>

                <?php if ($attendance_today): ?>
                    <div class="status-card">
                        <h3>
                            <i class="fa-solid fa-chart-column icon-main"></i>
                            <span><?php echo t('todays_attendance_record'); ?></span>
                        </h3>
                        <div class="status-info">
                            <div class="status-item">
                                <label><?php echo t('check_in_time'); ?></label>
                                <div class="value <?php echo $attendance_today['check_in_time'] ? 'highlight' : ''; ?>">
                                    <?php echo $attendance_today['check_in_time'] ? date('g:i A', strtotime($attendance_today['check_in_time'])) : '--:--'; ?>
                                </div>
                            </div>
                            <div class="status-item">
                                <label><?php echo t('check_out_time'); ?></label>
                                <div class="value <?php echo $attendance_today['check_out_time'] ? 'highlight' : ''; ?>">
                                    <?php echo $attendance_today['check_out_time'] ? date('g:i A', strtotime($attendance_today['check_out_time'])) : '--:--'; ?>
                                </div>
                            </div>
                            <div class="status-item">
                                <label><?php echo t('status'); ?></label>
                                <div class="value"><?php echo ucfirst(str_replace('_', ' ', $attendance_today['status'])); ?></div>
                            </div>
                            <div class="status-item">
                                <label><?php echo t('work_hours'); ?></label>
                                <div class="value"><?php echo $attendance_today['work_hours'] ? number_format($attendance_today['work_hours'], 2) : '0.00'; ?> <?php echo t('hours'); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="action-buttons">
                    <form id="checkin-form" method="POST" action="attendance_actions.php">
                        <input type="hidden" name="action" value="checkin">
                        <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
                        <input type="hidden" name="location" id="checkin-location">
                        
                        <?php if (!$has_checked_in && $requires_reason): ?>
                            <div class="reason-field">
                                <label>
                                    <?php echo t('reason_late_arrival'); ?>
                                    <?php if ($current_hour >= 12): ?>
                                        <span class="required-badge">(<?php echo t('required'); ?>)</span>
                                    <?php else: ?>
                                        <span class="required-badge">(<?php echo t('optional'); ?>)</span>
                                    <?php endif; ?>
                                </label>
                                <textarea 
                                    name="late_reason" 
                                    placeholder="<?php echo t('explain_late_reason'); ?>"
                                    <?php echo ($current_hour >= 12) ? 'required' : ''; ?>
                                ></textarea>
                                <small class="help-text <?php echo ($current_hour >= 12) ? 'danger' : 'warning'; ?>">
                                    <?php if ($current_hour >= 12): ?>
                                        <i class="fa-solid fa-triangle-exclamation"></i> <?php echo t('checkin_after_noon_warning'); ?>
                                    <?php else: ?>
                                        <i class="fa-solid fa-lightbulb"></i> <?php echo t('late_reason_help'); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-checkin" id="checkin-btn" <?php echo $has_checked_in ? 'disabled' : ''; ?>>
                            <span class="icon-large"><i class="fa-solid fa-right-to-bracket"></i></span>
                            <span><?php echo t('check_in_now'); ?></span>
                        </button>
                    </form>

                    <div class="divider"><?php echo t('or'); ?></div>

                    <form id="checkout-form" method="POST" action="attendance_actions.php">
                        <input type="hidden" name="action" value="checkout">
                        <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
                        <input type="hidden" name="location" id="checkout-location">
                        
                        <button type="submit" class="btn btn-checkout" id="checkout-btn" <?php echo (!$has_checked_in || $has_checked_out) ? 'disabled' : ''; ?>>
                            <span class="icon-large"><i class="fa-solid fa-right-from-bracket"></i></span>
                            <span><?php echo t('check_out_now'); ?></span>
                        </button>
                    </form>

                    <a href="<?php echo $user_type === 'admin' ? '../admin/attendance.php' : 'my_attendance.php'; ?>" class="btn btn-back">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span><?php echo t('back_to'); ?> <?php echo $user_type === 'admin' ? t('attendance') : t('my_attendance'); ?></span>
                    </a>

                </div>
            </div>
        </div>
    </div>

    <script>
        setInterval(() => {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            document.getElementById('current-time').textContent = timeStr;
        }, 1000);

        let currentLocation = null;
        const locationStatus = document.getElementById('location-status');

        const translations = {
            locationDetected: "<?php echo t('location_detected'); ?>",
            locationError: "<?php echo t('location_error'); ?>",
            geoNotSupported: "<?php echo t('geolocation_not_supported'); ?>",
            locationNotDetected: "<?php echo t('location_not_detected_alert'); ?>",
            enableGPS: "<?php echo t('enable_gps_refresh'); ?>"
        };

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    currentLocation = `${position.coords.latitude},${position.coords.longitude}`;
                    document.getElementById('checkin-location').value = currentLocation;
                    document.getElementById('checkout-location').value = currentLocation;
                    
                    locationStatus.className = 'location-status location-success';
                    locationStatus.innerHTML = '<i class="fa-solid fa-circle-check" style="font-size: 20px;"></i><span>' + translations.locationDetected + '</span>';
                },
                (error) => {
                    locationStatus.className = 'location-status location-error';
                    locationStatus.innerHTML = '<i class="fa-solid fa-triangle-exclamation" style="font-size: 20px;"></i><span>' + translations.locationError + '</span>';
                    console.error('Location error:', error);
                }
            );
        } else {
            locationStatus.className = 'location-status location-error';
            locationStatus.innerHTML = '<i class="fa-solid fa-triangle-exclamation" style="font-size: 20px;"></i><span>' + translations.geoNotSupported + '</span>';
        }

        document.getElementById('checkin-form').addEventListener('submit', (e) => {
            if (!document.getElementById('checkin-location').value) {
                e.preventDefault();
                alert('⚠️ ' + translations.locationNotDetected + '\n\n' + translations.enableGPS);
            }
        });

        document.getElementById('checkout-form').addEventListener('submit', (e) => {
            if (!document.getElementById('checkout-location').value) {
                e.preventDefault();
                alert('⚠️ ' + translations.locationNotDetected + '\n\n' + translations.enableGPS);
            }
        });
    </script>
</body>
</html>