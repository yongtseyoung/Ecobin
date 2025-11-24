<?php
/**
 * Employee Check-in/Check-out Page
 * GPS-based attendance tracking
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur'); // Malaysia timezone
require_once '../config/database.php';

// Check authentication - allow both admin and employee
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_type = $_SESSION['user_type'];
$user_name = $_SESSION['full_name'] ?? 'User';

// Set current page for sidebar
$current_page = 'attendance';

// Get employee ID
if ($user_type === 'employee') {
    $employee_id = $_SESSION['user_id'];
} else {
    // Admin can check in for any employee
    $employee_id = $_GET['employee_id'] ?? null;
    if (!$employee_id) {
        $_SESSION['error'] = "Please select an employee from the 'Check In/Out' dropdown in the Attendance page";
        header("Location: attendance.php");
        exit;
    }
}

// Get employee info
$employee = getOne("SELECT * FROM employees WHERE employee_id = ?", [$employee_id]);
if (!$employee) {
    $_SESSION['error'] = "Employee not found";
    header("Location: attendance.php");
    exit;
}

// Check today's attendance record
$today = date('Y-m-d');
$attendance_today = getOne("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?", [$employee_id, $today]);

$has_checked_in = $attendance_today && $attendance_today['check_in_time'];
$has_checked_out = $attendance_today && $attendance_today['check_out_time'];

// Get success/error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Current time
$current_time = date('g:i A');
$current_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check In/Out - EcoBin</title>
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

        /* Back Button */
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

        /* Main Container */
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

        /* Header */
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

        /* Current Time Card */
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

        /* Location Status */
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

        /* Alert Messages */
        .alert {
            padding: 18px 24px;
            border-radius: 15px;
            margin-bottom: 25px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
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

        /* Status Card */
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

        /* Action Buttons */
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

        /* Animations */
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

        /* Loading Spinner for Location */
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }

            .checkin-container {
                padding: 35px 25px;
            }

            .greeting-icon {
                font-size: 52px;
            }

            .header h1 {
                font-size: 26px;
            }

            .header .user-name {
                font-size: 19px;
            }

            .current-info {
                padding: 30px;
            }

            .current-info .time {
                font-size: 48px;
            }

            .current-info .date {
                font-size: 16px;
            }

            .status-info {
                grid-template-columns: 1fr;
            }

            .btn {
                padding: 20px 30px;
                font-size: 16px;
            }

            .icon-large {
                font-size: 24px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }

            .checkin-container {
                padding: 30px 20px;
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
                padding: 25px;
            }

            .current-info .time {
                font-size: 42px;
            }

            .current-info .date {
                font-size: 15px;
            }

            .status-item .value {
                font-size: 20px;
            }

            .btn {
                padding: 18px 24px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="checkin-wrapper">
            <a href="my_attendance.php" class="back-link">
                ← Back to My Attendance
            </a>

            <div class="checkin-container">
                <!-- Header -->
                <div class="header">
                    <div class="greeting-icon">👋</div>
                    <h1>Welcome!</h1>
                    <div class="user-name"><?php echo htmlspecialchars($employee['full_name']); ?></div>
                </div>

                <!-- Current Time -->
                <div class="current-info">
                    <div class="time" id="current-time"><?php echo $current_time; ?></div>
                    <div class="date"><?php echo $current_date; ?></div>
                </div>

                <!-- Alert Messages -->
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <span style="font-size: 20px;">✓</span>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <span style="font-size: 20px;">⚠</span>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Location Status -->
                <div class="location-status location-detecting" id="location-status">
                    <span class="spinner"></span>
                    <span>Detecting your location...</span>
                </div>

                <!-- Today's Status -->
                <?php if ($attendance_today): ?>
                    <div class="status-card">
                        <h3>
                            <span>📊</span>
                            <span>Today's Attendance Record</span>
                        </h3>
                        <div class="status-info">
                            <div class="status-item">
                                <label>Check In Time</label>
                                <div class="value <?php echo $attendance_today['check_in_time'] ? 'highlight' : ''; ?>">
                                    <?php echo $attendance_today['check_in_time'] ? date('g:i A', strtotime($attendance_today['check_in_time'])) : '--:--'; ?>
                                </div>
                            </div>
                            <div class="status-item">
                                <label>Check Out Time</label>
                                <div class="value <?php echo $attendance_today['check_out_time'] ? 'highlight' : ''; ?>">
                                    <?php echo $attendance_today['check_out_time'] ? date('g:i A', strtotime($attendance_today['check_out_time'])) : '--:--'; ?>
                                </div>
                            </div>
                            <div class="status-item">
                                <label>Status</label>
                                <div class="value"><?php echo ucfirst($attendance_today['status']); ?></div>
                            </div>
                            <div class="status-item">
                                <label>Work Hours</label>
                                <div class="value"><?php echo $attendance_today['work_hours'] ? number_format($attendance_today['work_hours'], 2) : '0.00'; ?> hrs</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <form id="checkin-form" method="POST" action="attendance_actions.php">
                        <input type="hidden" name="action" value="checkin">
                        <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
                        <input type="hidden" name="location" id="checkin-location">
                        
                        <button type="submit" class="btn btn-checkin" id="checkin-btn" <?php echo $has_checked_in ? 'disabled' : ''; ?>>
                            <span class="icon-large">✓</span>
                            <span>Check In Now</span>
                        </button>
                    </form>

                    <div class="divider">Or</div>

                    <form id="checkout-form" method="POST" action="attendance_actions.php">
                        <input type="hidden" name="action" value="checkout">
                        <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
                        <input type="hidden" name="location" id="checkout-location">
                        
                        <button type="submit" class="btn btn-checkout" id="checkout-btn" <?php echo (!$has_checked_in || $has_checked_out) ? 'disabled' : ''; ?>>
                            <span class="icon-large">🚪</span>
                            <span>Check Out</span>
                        </button>
                    </form>

                    <a href="<?php echo $user_type === 'admin' ? '../admin/attendance.php' : 'my_attendance.php'; ?>" class="btn btn-back">
                        <span>←</span>
                        <span>Back to <?php echo $user_type === 'admin' ? 'Attendance' : 'My Attendance'; ?></span>
                    </a>

                </div>
            </div>
        </div>
    </div>

    <script>
        // Update time every second
        setInterval(() => {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            document.getElementById('current-time').textContent = timeStr;
        }, 1000);

        // GPS Location tracking
        let currentLocation = null;
        const locationStatus = document.getElementById('location-status');

        // Get location on page load
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    currentLocation = `${position.coords.latitude},${position.coords.longitude}`;
                    document.getElementById('checkin-location').value = currentLocation;
                    document.getElementById('checkout-location').value = currentLocation;
                    
                    locationStatus.className = 'location-status location-success';
                    locationStatus.innerHTML = '<span style="font-size: 20px;">✓</span><span>Location detected successfully</span>';
                },
                (error) => {
                    locationStatus.className = 'location-status location-error';
                    locationStatus.innerHTML = '<span style="font-size: 20px;">⚠</span><span>Unable to detect location. Please enable GPS.</span>';
                    console.error('Location error:', error);
                }
            );
        } else {
            locationStatus.className = 'location-status location-error';
            locationStatus.innerHTML = '<span style="font-size: 20px;">⚠</span><span>Geolocation is not supported by your browser</span>';
        }

        // Validate forms before submission
        document.getElementById('checkin-form').addEventListener('submit', (e) => {
            if (!document.getElementById('checkin-location').value) {
                e.preventDefault();
                alert('⚠️ Location not detected!\n\nPlease enable GPS and refresh the page.');
            }
        });

        document.getElementById('checkout-form').addEventListener('submit', (e) => {
            if (!document.getElementById('checkout-location').value) {
                e.preventDefault();
                alert('⚠️ Location not detected!\n\nPlease enable GPS and refresh the page.');
            }
        });
    </script>
</body>
</html>