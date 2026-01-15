<?php
/**
 * Employee Attendance Dashboard
 * View attendance history and statistics
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';
require_once '../config/languages.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['user_id'];
$employee = getOne("SELECT e.*, a.area_name FROM employees e LEFT JOIN areas a ON e.area_id = a.area_id WHERE e.employee_id = ?", [$employee_id]);

// Load language preference
$_SESSION['language'] = $employee['language'] ?? 'en';

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

// Calculate monthly stats - Only present, late, absent
$total_days = count($attendance_records);
$present_days = count(array_filter($attendance_records, fn($a) => $a['status'] === 'present'));
$late_days = count(array_filter($attendance_records, fn($a) => $a['status'] === 'late'));
$absent_days = count(array_filter($attendance_records, fn($a) => $a['status'] === 'absent'));
$total_hours = array_sum(array_column($attendance_records, 'work_hours'));

// Calculate attendance rate (present + late = attended)
$working_days = getWorkingDaysInMonth($month_filter);
$attended_days = $present_days + $late_days;
$attendance_rate = $working_days > 0 ? ($attended_days / $working_days) * 100 : 0;

// Get this week's stats
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$week_attendance = getAll(
    "SELECT * FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?",
    [$employee_id, $week_start, $week_end]
);

// Week stats - Only present + late
$week_present = count(array_filter($week_attendance, fn($a) => in_array($a['status'], ['present', 'late'])));
$week_hours = array_sum(array_column($week_attendance, 'work_hours'));

// Check today's status
$today = date('Y-m-d');
$today_attendance = getOne("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?", [$employee_id, $today]);

// Check current time for warnings
$current_hour = (int)date('H');
$current_minute = (int)date('i');

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
<html lang="<?php echo $_SESSION['language']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('my_attendance'); ?> - EcoBin</title>
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
        }

        .icon-main {
            color: #435334;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #435334;
            display: flex;
            align-items: center;
            gap: 10px;
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
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
        }

        /* Time Status Notice */
        .time-status-notice {
            padding: 15px 20px;
            border-radius: 10px;
            margin-top: 15px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
        }

        .time-status-notice.ontime {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }

        .time-status-notice.late {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffc107;
        }

        .time-status-notice.very_late {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }

        /* Current Time Display */
        .current-time-display {
            background: #f8f9fa;
            padding: 12px 20px;
            border-radius: 10px;
            margin-top: 10px;
            text-align: center;
            font-size: 13px;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .current-time-display strong {
            color: #435334;
            font-size: 16px;
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
            background: #ffffffff;
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
            display: flex;
            align-items: center;
            gap: 10px;
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
            display: inline-flex;
            align-items: center;
            gap: 5px;
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

        .today-status-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .today-status-card h4 {
            color: #435334;
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .status-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border: 2px solid #e0e0e0;
        }

        .status-item label {
            font-size: 11px;
            color: #666;
            display: block;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .status-item .value {
            font-size: 18px;
            font-weight: 600;
            color: #435334;
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
                margin-left: 0;
                padding: 80px 15px 20px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .quick-action-card {
                padding: 20px;
            }

            .action-btn {
                padding: 15px 30px;
                font-size: 16px;
                width: 100%;
            }

            .current-time-display {
                font-size: 12px;
                flex-direction: column;
                gap: 4px;
            }

            .current-time-display strong {
                font-size: 14px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-icon {
                font-size: 32px;
                margin-bottom: 10px;
            }

            .stat-value {
                font-size: 28px;
            }

            .stat-label {
                font-size: 11px;
            }

            .table-card {
                border-radius: 15px;
            }

            .table-header {
                padding: 20px;
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }

            .table-header h3 {
                font-size: 16px;
            }

            .month-selector {
                width: 100%;
            }

            .attendance-table {
                font-size: 12px;
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .attendance-table thead,
            .attendance-table tbody,
            .attendance-table tr {
                display: table;
                width: 100%;
                table-layout: fixed;
            }

            .attendance-table th,
            .attendance-table td {
                padding: 10px 8px;
            }

            .sidebar-cards {
                gap: 15px;
            }

            .sidebar-card,
            .today-status-card {
                padding: 20px;
            }

            .sidebar-card h3,
            .today-status-card h4 {
                font-size: 14px;
            }

            .status-grid {
                gap: 10px;
            }

            .status-item {
                padding: 12px;
            }

            .status-item label {
                font-size: 10px;
            }

            .status-item .value {
                font-size: 16px;
            }

            .week-stat-item {
                padding: 12px;
            }

            .week-stat-item .label {
                font-size: 12px;
            }

            .week-stat-item .value {
                font-size: 18px;
            }

            .time-status-notice {
                padding: 12px 15px;
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 70px 10px 15px;
            }

            .page-header {
                margin-bottom: 20px;
            }

            .page-header h1 {
                font-size: 20px;
            }

            .quick-action-card {
                padding: 15px;
                margin-bottom: 20px;
            }

            .action-btn {
                padding: 14px 24px;
                font-size: 14px;
            }

            .action-btn .icon {
                font-size: 20px;
            }

            .current-time-display {
                padding: 10px 15px;
                font-size: 11px;
            }

            .current-time-display strong {
                font-size: 13px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
                margin-bottom: 20px;
            }

            .stat-card {
                padding: 18px;
            }

            .stat-icon {
                font-size: 28px;
                margin-bottom: 8px;
            }

            .stat-value {
                font-size: 24px;
            }

            .stat-label {
                font-size: 10px;
            }

            .table-card {
                border-radius: 12px;
            }

            .table-header {
                padding: 15px;
            }

            .table-header h3 {
                font-size: 14px;
            }

            .month-selector {
                padding: 8px 12px;
                font-size: 13px;
            }

            .attendance-table {
                font-size: 11px;
            }

            .attendance-table th,
            .attendance-table td {
                padding: 8px 6px;
            }

            .status-badge {
                padding: 4px 8px;
                font-size: 10px;
            }

            .sidebar-card,
            .today-status-card {
                padding: 15px;
                border-radius: 15px;
            }

            .sidebar-card h3,
            .today-status-card h4 {
                font-size: 13px;
                margin-bottom: 12px;
            }

            .status-grid {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .status-item {
                padding: 10px;
            }

            .status-item label {
                font-size: 9px;
                margin-bottom: 4px;
            }

            .status-item .value {
                font-size: 14px;
            }

            .week-stats {
                gap: 10px;
            }

            .week-stat-item {
                padding: 10px;
            }

            .week-stat-item .label {
                font-size: 11px;
            }

            .week-stat-item .value {
                font-size: 16px;
            }

            .time-status-notice {
                padding: 10px 12px;
                font-size: 12px;
            }

            .empty-state {
                padding: 40px 15px;
            }

            .empty-state-icon {
                font-size: 48px;
                margin-bottom: 15px;
            }

            .empty-state h3 {
                font-size: 14px;
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
                <?php echo t('my_attendance'); ?>
            </h1>
        </div>

        <!-- Quick Action -->
        <div class="quick-action-card">
            <a href="attendance_checkin.php" class="action-btn">
                <i class="fa-solid fa-right-to-bracket icon"></i>
                <span><?php echo t('go_to_check_in_out'); ?></span>
            </a>
            <!-- Current Time -->
            <div class="current-time-display">
                <?php echo t('current_time'); ?>: <strong><?php echo date('g:i A'); ?></strong> | <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <!-- Monthly Stats -->
        <div class="stats-grid">
            <div class="stat-card highlight">
                <div class="stat-icon"><i class="fa-solid fa-calendar-check icon-main"></i></div>
                <div class="stat-value"><?php echo $present_days; ?></div>
                <div class="stat-label"><?php echo t('present_days'); ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-clock icon-main"></i></div>
                <div class="stat-value"><?php echo $late_days; ?></div>
                <div class="stat-label"><?php echo t('late_arrivals'); ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-circle-xmark icon-main"></i></div>
                <div class="stat-value"><?php echo $absent_days; ?></div>
                <div class="stat-label"><?php echo t('absent_days'); ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-hourglass-half icon-main"></i></div>
                <div class="stat-value"><?php echo number_format($total_hours, 1); ?>h</div>
                <div class="stat-label"><?php echo t('total_hours'); ?></div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Attendance Records Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3>
                        <?php echo t('attendance_records'); ?>
                    </h3>
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
                        <h3><?php echo t('no_records_found'); ?></h3>
                    </div>
                <?php else: ?>
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th><?php echo t('date'); ?></th>
                                <th><?php echo t('check_in'); ?></th>
                                <th><?php echo t('check_out'); ?></th>
                                <th><?php echo t('hours'); ?></th>
                                <th><?php echo t('status'); ?></th>
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
                                            <?php 
                                            if ($record['status'] === 'present') {
                                                echo '<i class="fa-solid fa-check"></i>';
                                            } elseif ($record['status'] === 'late') {
                                                echo '<i class="fa-solid fa-clock"></i>';
                                            } else {
                                                echo '<i class="fa-solid fa-xmark"></i>';
                                            }
                                            ?>
                                            <?php echo ucfirst(str_replace('_', ' ', $record['status'])); ?>
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

                <!-- Today's Status -->
                <?php if ($today_attendance): ?>
                    <div class="today-status-card">
                        <h4>
                            <i class="fa-solid fa-calendar-day"></i>
                            <?php echo t('todays_status'); ?>
                        </h4>
                        <div class="status-grid">
                            <div class="status-item">
                                <label><i class="fa-solid fa-right-to-bracket"></i> <?php echo t('check_in'); ?></label>
                                <div class="value">
                                    <?php echo $today_attendance['check_in_time'] ? date('g:i A', strtotime($today_attendance['check_in_time'])) : '--:--'; ?>
                                </div>
                            </div>
                            <div class="status-item">
                                <label><i class="fa-solid fa-right-from-bracket"></i> <?php echo t('check_out'); ?></label>
                                <div class="value">
                                    <?php echo $today_attendance['check_out_time'] ? date('g:i A', strtotime($today_attendance['check_out_time'])) : '--:--'; ?>
                                </div>
                            </div>
                            <div class="status-item">
                                <label><i class="fa-solid fa-info-circle"></i> <?php echo t('status'); ?></label>
                                <div class="value" style="font-size: 14px;">
                                    <?php echo ucfirst(str_replace('_', ' ', $today_attendance['status'])); ?>
                                </div>
                            </div>
                            <div class="status-item">
                                <label><i class="fa-solid fa-clock"></i> <?php echo t('hours'); ?></label>
                                <div class="value">
                                    <?php echo $today_attendance['work_hours'] ? number_format($today_attendance['work_hours'], 1) : '0.0'; ?>h
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="sidebar-card" style="background: #fff3cd; border: 2px solid #ffc107;">
                        <h3 style="color: #856404;">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <?php echo t('not_checked_in'); ?>
                        </h3>
                        <p style="color: #856404; margin-bottom: 0;">
                            <?php echo t('havent_checked_in_today'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>