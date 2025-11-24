<?php
/**
 * Employee Attendance Dashboard
 * View attendance history and statistics
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['user_id'];
$employee = getOne("SELECT e.*, a.area_name FROM employees e LEFT JOIN areas a ON e.area_id = a.area_id WHERE e.employee_id = ?", [$employee_id]);

// Set current page for sidebar
$current_page = 'attendance';

// Get filter
$month_filter = $_GET['month'] ?? date('Y-m');

// Get attendance records for selected month
$attendance_records = getAll(
    "SELECT * FROM attendance 
     WHERE employee_id = ? 
     AND DATE_FORMAT(attendance_date, '%Y-%m') = ?
     ORDER BY attendance_date DESC",
    [$employee_id, $month_filter]
);

// Calculate monthly stats
$total_days = count($attendance_records);
$present_days = count(array_filter($attendance_records, fn($a) => $a['status'] === 'present'));
$late_days = count(array_filter($attendance_records, fn($a) => $a['status'] === 'late'));
$absent_days = count(array_filter($attendance_records, fn($a) => $a['status'] === 'absent'));
$total_hours = array_sum(array_column($attendance_records, 'work_hours'));

// Calculate attendance rate
$working_days = getWorkingDaysInMonth($month_filter);
$attendance_rate = $working_days > 0 ? ($present_days / $working_days) * 100 : 0;

// Get this week's stats
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$week_attendance = getAll(
    "SELECT * FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?",
    [$employee_id, $week_start, $week_end]
);

$week_present = count(array_filter($week_attendance, fn($a) => $a['status'] !== 'absent'));
$week_hours = array_sum(array_column($week_attendance, 'work_hours'));

// Check today's status
$today = date('Y-m-d');
$today_attendance = getOne("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?", [$employee_id, $today]);

$current_time = date('g:i A');
$current_date = date('l, F j, Y');

// Function to get working days in a month
function getWorkingDaysInMonth($month) {
    $start = new DateTime($month . '-01');
    $end = new DateTime($start->format('Y-m-t'));
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
    
    $working_days = 0;
    foreach ($period as $date) {
        if ($date->format('N') < 6) { // Monday to Friday
            $working_days++;
        }
    }
    return $working_days;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - EcoBin</title>
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

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #435334 0%, #5a6f4a 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome-content h2 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .welcome-content p {
            font-size: 16px;
            opacity: 0.9;
        }

        .welcome-content .employee-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            margin-top: 10px;
        }

        .welcome-icon {
            font-size: 80px;
            opacity: 0.3;
        }

        /* Quick Action Card */
        .quick-action-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-align: center;
        }

        .quick-action-card h3 {
            color: #435334;
            font-size: 20px;
            margin-bottom: 20px;
        }

        .action-btn {
            display: inline-block;
            padding: 18px 40px;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            text-decoration: none;
            border-radius: 15px;
            font-size: 18px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
            transition: all 0.3s;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
        }

        .action-btn .icon {
            font-size: 24px;
            margin-right: 10px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #435334;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card.highlight {
            background: linear-gradient(135deg, #CEDEBD, #9db89a);
        }

        .stat-card.highlight .stat-value {
            color: #435334;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        /* Attendance Table Card */
        .table-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table-header {
            padding: 25px;
            background: #FAF1E4;
            border-bottom: 2px solid #CEDEBD;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            color: #435334;
            font-size: 18px;
        }

        .month-selector {
            padding: 8px 16px;
            border: 2px solid #CEDEBD;
            border-radius: 10px;
            font-size: 14px;
            color: #435334;
            background: white;
            cursor: pointer;
        }

        .month-selector:focus {
            outline: none;
            border-color: #435334;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-size: 12px;
            color: #435334;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .attendance-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .attendance-table tr:hover {
            background: #fafafa;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-present {
            background: #d4edda;
            color: #155724;
        }

        .status-late {
            background: #fff3cd;
            color: #856404;
        }

        .status-absent {
            background: #f8d7da;
            color: #721c24;
        }

        /* Sidebar Cards */
        .sidebar-cards {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .sidebar-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .sidebar-card h3 {
            color: #435334;
            font-size: 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .week-stats {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .week-stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            border-left: 4px solid #CEDEBD;
        }

        .week-stat-item .label {
            font-size: 13px;
            color: #666;
            font-weight: 600;
        }

        .week-stat-item .value {
            font-size: 20px;
            font-weight: 700;
            color: #435334;
        }

        .week-stat-item.highlight {
            border-left-color: #27ae60;
            background: #d4edda;
        }

        /* Today Status Card */
        .today-status-card {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            padding: 20px;
            border-radius: 15px;
            border-left: 4px solid #2196f3;
        }

        .today-status-card h4 {
            color: #1565c0;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .status-item {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .status-item label {
            font-size: 11px;
            color: #666;
            display: block;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .status-item .value {
            font-size: 18px;
            font-weight: 600;
            color: #1565c0;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #435334;
            margin-bottom: 10px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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

            .welcome-banner {
                flex-direction: column;
                text-align: center;
                padding: 30px 20px;
            }

            .welcome-content h2 {
                font-size: 24px;
            }

            .welcome-icon {
                font-size: 60px;
                margin-top: 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .attendance-table {
                font-size: 12px;
            }

            .attendance-table th,
            .attendance-table td {
                padding: 10px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }

            .quick-action-card {
                padding: 20px;
            }

            .action-btn {
                padding: 15px 30px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>📅 My Attendance</h1>
            <div class="header-info">
                <div class="header-time"><?php echo $current_time; ?></div>
                <div class="header-date"><?php echo $current_date; ?></div>
            </div>
        </div>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-content">
                <h2>Welcome, <?php echo htmlspecialchars(explode(' ', $employee['full_name'])[0]); ?>! 👋</h2>
                <p>Track your attendance and work hours</p>
                <?php if ($employee['area_name']): ?>
                    <div class="employee-badge">📍 <?php echo htmlspecialchars($employee['area_name']); ?></div>
                <?php endif; ?>
            </div>
            <div class="welcome-icon">📊</div>
        </div>

        <!-- Quick Action -->
        <div class="quick-action-card">
            <h3>⏱️ Mark Your Attendance</h3>
            <a href="attendance_checkin.php" class="action-btn">
                <span class="icon">✓</span>
                <span>Go to Check In/Out</span>
            </a>
        </div>

        <!-- Monthly Stats -->
        <div class="stats-grid">
            <div class="stat-card highlight">
                <div class="stat-icon">📅</div>
                <div class="stat-value"><?php echo $present_days; ?></div>
                <div class="stat-label">Present Days</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">⏰</div>
                <div class="stat-value"><?php echo $late_days; ?></div>
                <div class="stat-label">Late Arrivals</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">❌</div>
                <div class="stat-value"><?php echo $absent_days; ?></div>
                <div class="stat-label">Absent Days</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">⏱️</div>
                <div class="stat-value"><?php echo number_format($total_hours, 1); ?>h</div>
                <div class="stat-label">Total Hours</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Attendance Records Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3>📋 Attendance Records</h3>
                    <select class="month-selector" onchange="window.location.href='?month='+this.value">
                        <?php
                        for ($i = 0; $i < 6; $i++) {
                            $month = date('Y-m', strtotime("-$i months"));
                            $selected = $month === $month_filter ? 'selected' : '';
                            echo "<option value='$month' $selected>" . date('F Y', strtotime($month)) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <?php if (empty($attendance_records)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <h3>No Records Found</h3>
                        <p>No attendance records for this month</p>
                    </div>
                <?php else: ?>
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Hours</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $record): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></strong><br>
                                        <small style="color: #999;"><?php echo date('l', strtotime($record['attendance_date'])); ?></small>
                                    </td>
                                    <td><?php echo $record['check_in_time'] ? date('g:i A', strtotime($record['check_in_time'])) : '--:--'; ?></td>
                                    <td><?php echo $record['check_out_time'] ? date('g:i A', strtotime($record['check_out_time'])) : '--:--'; ?></td>
                                    <td><?php echo $record['work_hours'] ? number_format($record['work_hours'], 2) . 'h' : '0.00h'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $record['status']; ?>">
                                            <?php echo ucfirst($record['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="sidebar-cards">
                <!-- This Week Stats -->
                <div class="sidebar-card">
                    <h3>📅 This Week</h3>
                    <div class="week-stats">
                        <div class="week-stat-item highlight">
                            <div class="label">Days Present</div>
                            <div class="value"><?php echo $week_present; ?>/5</div>
                        </div>
                        <div class="week-stat-item">
                            <div class="label">Total Hours</div>
                            <div class="value"><?php echo number_format($week_hours, 1); ?>h</div>
                        </div>
                        <div class="week-stat-item">
                            <div class="label">Attendance Rate</div>
                            <div class="value"><?php echo number_format($attendance_rate, 1); ?>%</div>
                        </div>
                    </div>
                </div>

                <!-- Today's Status -->
                <?php if ($today_attendance): ?>
                    <div class="today-status-card">
                        <h4>📊 Today's Status</h4>
                        <div class="status-grid">
                            <div class="status-item">
                                <label>Check In</label>
                                <div class="value">
                                    <?php echo $today_attendance['check_in_time'] ? date('g:i A', strtotime($today_attendance['check_in_time'])) : '--:--'; ?>
                                </div>
                            </div>
                            <div class="status-item">
                                <label>Check Out</label>
                                <div class="value">
                                    <?php echo $today_attendance['check_out_time'] ? date('g:i A', strtotime($today_attendance['check_out_time'])) : '--:--'; ?>
                                </div>
                            </div>
                            <div class="status-item">
                                <label>Status</label>
                                <div class="value" style="font-size: 14px;">
                                    <?php echo ucfirst($today_attendance['status']); ?>
                                </div>
                            </div>
                            <div class="status-item">
                                <label>Hours</label>
                                <div class="value">
                                    <?php echo $today_attendance['work_hours'] ? number_format($today_attendance['work_hours'], 1) : '0.0'; ?>h
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="sidebar-card" style="background: #fff3cd; border: 2px solid #ffc107;">
                        <h3 style="color: #856404;">⚠️ Not Checked In</h3>
                        <p style="color: #856404; margin-bottom: 15px;">You haven't checked in today yet.</p>
                        <a href="attendance_checkin.php" class="action-btn" style="font-size: 14px; padding: 12px 24px;">
                            Check In Now
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>