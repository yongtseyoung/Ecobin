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

// Get employee ID
if ($user_type === 'employee') {
    $employee_id = $_SESSION['user_id'];
} else {
    // Admin can check in for any employee
    $employee_id = $_GET['employee_id'] ?? null;
    if (!$employee_id) {
        $_SESSION['error'] = "Please select an employee";
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
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .checkin-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 28px;
            color: #435334;
            margin-bottom: 10px;
        }

        .header .user-name {
            font-size: 20px;
            color: #27ae60;
            font-weight: 600;
        }

        .current-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }

        .current-info .time {
            font-size: 48px;
            font-weight: 700;
            color: #435334;
            margin-bottom: 10px;
        }

        .current-info .date {
            font-size: 16px;
            color: #666;
        }

        .status-card {
            background: #CEDEBD;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .status-card h3 {
            font-size: 14px;
            color: #435334;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .status-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .status-item {
            background: white;
            padding: 15px;
            border-radius: 10px;
        }

        .status-item label {
            font-size: 12px;
            color: #999;
            display: block;
            margin-bottom: 5px;
        }

        .status-item .value {
            font-size: 18px;
            font-weight: 600;
            color: #435334;
        }

        .location-status {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }

        .location-detecting {
            background: #fff3cd;
            color: #856404;
        }

        .location-success {
            background: #d4edda;
            color: #155724;
        }

        .location-error {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .btn {
            padding: 18px;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-checkin {
            background: #27ae60;
            color: white;
        }

        .btn-checkin:hover:not(:disabled) {
            background: #229954;
            transform: translateY(-2px);
        }

        .btn-checkout {
            background: #e74c3c;
            color: white;
        }

        .btn-checkout:hover:not(:disabled) {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .btn-back {
            background: #f0f0f0;
            color: #435334;
            padding: 12px;
            font-size: 14px;
        }

        .btn-back:hover {
            background: #e0e0e0;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }

        .divider {
            text-align: center;
            margin: 20px 0;
            color: #999;
            font-size: 14px;
        }

        @media (max-width: 600px) {
            .checkin-container {
                padding: 30px 20px;
            }
            .current-info .time {
                font-size: 36px;
            }
        }
    </style>
</head>
<body>
    <div class="checkin-container">
        <!-- Header -->
        <div class="header">
            <h1>👋 Welcome!</h1>
            <div class="user-name"><?php echo htmlspecialchars($employee['full_name']); ?></div>
        </div>

        <!-- Current Time -->
        <div class="current-info">
            <div class="time" id="current-time"><?php echo $current_time; ?></div>
            <div class="date"><?php echo $current_date; ?></div>
        </div>

        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success">✓ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">⚠ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Location Status -->
        <div class="location-status location-detecting" id="location-status">
            📍 Detecting location...
        </div>

        <!-- Today's Status -->
        <?php if ($attendance_today): ?>
            <div class="status-card">
                <h3>Today's Record</h3>
                <div class="status-info">
                    <div class="status-item">
                        <label>Check In</label>
                        <div class="value">
                            <?php echo $attendance_today['check_in_time'] ? date('g:i A', strtotime($attendance_today['check_in_time'])) : '--:--'; ?>
                        </div>
                    </div>
                    <div class="status-item">
                        <label>Check Out</label>
                        <div class="value">
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
                    <span>✓</span>
                    <span>Check In Now</span>
                </button>
            </form>

            <div class="divider">OR</div>

            <form id="checkout-form" method="POST" action="attendance_actions.php">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
                <input type="hidden" name="location" id="checkout-location">
                
                <button type="submit" class="btn btn-checkout" id="checkout-btn" <?php echo (!$has_checked_in || $has_checked_out) ? 'disabled' : ''; ?>>
                    <span>🚪</span>
                    <span>Check Out</span>
                </button>
            </form>

            <a href="<?php echo $user_type === 'admin' ? 'attendance.php' : 'dashboard.php'; ?>" class="btn btn-back">
                ← Back
            </a>
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
        const checkinBtn = document.getElementById('checkin-btn');
        const checkoutBtn = document.getElementById('checkout-btn');

        // Get location on page load
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    currentLocation = `${position.coords.latitude},${position.coords.longitude}`;
                    document.getElementById('checkin-location').value = currentLocation;
                    document.getElementById('checkout-location').value = currentLocation;
                    
                    locationStatus.className = 'location-status location-success';
                    locationStatus.textContent = '📍 Location detected successfully';
                },
                (error) => {
                    locationStatus.className = 'location-status location-error';
                    locationStatus.textContent = '⚠ Unable to detect location. Please enable GPS.';
                    console.error('Location error:', error);
                }
            );
        } else {
            locationStatus.className = 'location-status location-error';
            locationStatus.textContent = '⚠ Geolocation is not supported by your browser';
        }

        // Validate forms before submission
        document.getElementById('checkin-form').addEventListener('submit', (e) => {
            if (!document.getElementById('checkin-location').value) {
                e.preventDefault();
                alert('Location not detected. Please enable GPS and refresh the page.');
            }
        });

        document.getElementById('checkout-form').addEventListener('submit', (e) => {
            if (!document.getElementById('checkout-location').value) {
                e.preventDefault();
                alert('Location not detected. Please enable GPS and refresh the page.');
            }
        });
    </script>
</body>
</html>