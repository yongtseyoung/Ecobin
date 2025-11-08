<?php
/**
 * Employee Leave Request Form
 * Allows employees to request leave
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check authentication - employees only
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['user_id'];
$employee_name = $_SESSION['full_name'] ?? 'Employee';
$current_year = date('Y');

// Get leave types
$leave_types = getAll("SELECT * FROM leave_types WHERE is_active = TRUE ORDER BY type_name");

// Get leave balances for current year
$leave_balances = getAll("SELECT lb.*, lt.type_name, lt.color_code 
                          FROM leave_balances lb
                          JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id
                          WHERE lb.employee_id = ? AND lb.year = ?
                          ORDER BY lt.type_name",
                          [$employee_id, $current_year]);

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Leave - EcoBin</title>
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

        .sidebar {
            width: 250px;
            background: #435334;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-logo {
            width: 120px;
            height: 120px;
            background: #CEDEBD;
            border-radius: 50%;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .sidebar-logo img {
            width: 90px;
            height: 90px;
            object-fit: contain;
        }

        .nav-menu {
            padding: 0 15px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin-bottom: 5px;
            border-radius: 10px;
            text-decoration: none;
            color: white;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-item.active {
            background: white;
            color: #435334;
            font-weight: 600;
        }

        .nav-item .icon {
            margin-right: 12px;
            font-size: 18px;
        }

        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 30px;
            max-width: 1400px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #435334;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #666;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
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

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .card h2 {
            color: #435334;
            font-size: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #435334;
            margin-bottom: 8px;
        }

        .form-group label .required {
            color: #e74c3c;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #435334;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #435334;
            color: white;
        }

        .btn-primary:hover {
            background: #354428;
        }

        .btn-secondary {
            background: #CEDEBD;
            color: #435334;
        }

        .balance-card {
            padding: 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid;
        }

        .balance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .balance-type {
            font-weight: 600;
            color: #435334;
            font-size: 15px;
        }

        .balance-remaining {
            font-size: 24px;
            font-weight: 700;
            color: #435334;
        }

        .balance-detail {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .info-box h4 {
            color: #1976d2;
            margin-bottom: 8px;
        }

        .info-box ul {
            margin-left: 20px;
            color: #555;
            font-size: 13px;
        }

        .info-box li {
            margin: 5px 0;
        }

        .days-calculator {
            background: #fff3cd;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            text-align: center;
        }

        .days-calculator .days {
            font-size: 32px;
            font-weight: 700;
            color: #856404;
        }

        .days-calculator .label {
            font-size: 13px;
            color: #856404;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="../assets/images/logo.png" alt="EcoBin Logo">
        </div>

        <nav class="nav-menu">
            <a href="employee_dashboard.php" class="nav-item">
                <span class="icon">🏠</span>
                <span>Dashboard</span>
            </a>
            <a href="my_tasks.php" class="nav-item">
                <span class="icon">📋</span>
                <span>My Tasks</span>
            </a>
            <a href="my_attendance.php" class="nav-item">
                <span class="icon">✅</span>
                <span>Attendance</span>
            </a>
            <a href="my_leave.php" class="nav-item active">
                <span class="icon">🏖️</span>
                <span>Leave</span>
            </a>
            <a href="my_performance.php" class="nav-item">
                <span class="icon">📈</span>
                <span>Performance</span>
            </a>
            <a href="my_profile.php" class="nav-item">
                <span class="icon">👤</span>
                <span>Profile</span>
            </a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1>🏖️ Request Leave</h1>
            <p>Submit a leave request for approval</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✓ <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                ⚠ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <div>
                <div class="card">
                    <h2>📝 Leave Request Form</h2>

                    <form action="leave_actions.php" method="POST">
                        <input type="hidden" name="action" value="request">

                        <div class="form-group">
                            <label>Leave Type <span class="required">*</span></label>
                            <select name="leave_type_id" id="leaveType" required>
                                <option value="">Select leave type</option>
                                <?php foreach ($leave_types as $type): ?>
                                    <option value="<?php echo $type['leave_type_id']; ?>">
                                        <?php echo htmlspecialchars($type['type_name']); ?>
                                        <?php if ($type['description']): ?>
                                            - <?php echo htmlspecialchars($type['description']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Start Date <span class="required">*</span></label>
                                <input type="date" name="start_date" id="startDate" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>End Date <span class="required">*</span></label>
                                <input type="date" name="end_date" id="endDate" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div id="daysCalculator" class="days-calculator" style="display: none;">
                            <div class="days" id="totalDays">0</div>
                            <div class="label">Total Days Requested</div>
                        </div>

                        <div class="form-group">
                            <label>Reason <span class="required">*</span></label>
                            <textarea name="reason" required placeholder="Please provide details for your leave request..."></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Emergency Contact Name</label>
                                <input type="text" name="emergency_contact" placeholder="Contact person during leave">
                            </div>

                            <div class="form-group">
                                <label>Emergency Contact Phone</label>
                                <input type="tel" name="emergency_phone" placeholder="+60 XX-XXX XXXX">
                            </div>
                        </div>

                        <div style="display: flex; gap: 15px; margin-top: 30px;">
                            <button type="submit" class="btn btn-primary">
                                ✓ Submit Request
                            </button>
                            <a href="my_leave.php" class="btn btn-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div>
                <div class="card">
                    <h2>📊 Leave Balance (<?php echo $current_year; ?>)</h2>
                    <?php if (empty($leave_balances)): ?>
                        <p style="color: #999; text-align: center; padding: 20px;">
                            No leave balance data available
                        </p>
                    <?php else: ?>
                        <?php foreach ($leave_balances as $balance): ?>
                            <div class="balance-card" style="border-left-color: <?php echo $balance['color_code']; ?>">
                                <div class="balance-header">
                                    <div class="balance-type"><?php echo htmlspecialchars($balance['type_name']); ?></div>
                                    <div class="balance-remaining"><?php echo $balance['remaining_days']; ?></div>
                                </div>
                                <div class="balance-detail">
                                    Used: <?php echo $balance['used_days']; ?> / <?php echo $balance['total_days']; ?> days
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="info-box">
                    <h4>📌 Important Notes</h4>
                    <ul>
                        <li>Submit leave requests at least 3 days in advance</li>
                        <li>Emergency leave can be submitted anytime</li>
                        <li>Medical certificates required for sick leave > 2 days</li>
                        <li>Check with your supervisor before requesting</li>
                        <li>All leave requests require approval</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Calculate total days between dates
        const startDate = document.getElementById('startDate');
        const endDate = document.getElementById('endDate');
        const totalDaysEl = document.getElementById('totalDays');
        const calculator = document.getElementById('daysCalculator');

        function calculateDays() {
            if (startDate.value && endDate.value) {
                const start = new Date(startDate.value);
                const end = new Date(endDate.value);
                
                if (end >= start) {
                    const diffTime = Math.abs(end - start);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 to include both days
                    
                    totalDaysEl.textContent = diffDays;
                    calculator.style.display = 'block';
                } else {
                    calculator.style.display = 'none';
                }
            } else {
                calculator.style.display = 'none';
            }
        }

        startDate.addEventListener('change', calculateDays);
        endDate.addEventListener('change', calculateDays);

        // Update end date min value when start date changes
        startDate.addEventListener('change', function() {
            endDate.min = this.value;
        });
    </script>
</body>
</html>