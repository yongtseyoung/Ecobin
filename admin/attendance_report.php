<?php


session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'attendance';


$admin_name = $_SESSION['full_name'] ?? 'Admin';

$selected_month = $_GET['month'] ?? date('Y-m');
$selected_employee = $_GET['employee'] ?? '';

list($year, $month) = explode('-', $selected_month);

$employees = getAll("SELECT employee_id, full_name FROM employees WHERE status = 'active' ORDER BY full_name");

$start_date = "$year-$month-01";
$end_date = date('Y-m-t', strtotime($start_date));

$query = "SELECT a.*, e.full_name, e.username, ar.area_name
          FROM attendance a
          JOIN employees e ON a.employee_id = e.employee_id
          LEFT JOIN areas ar ON e.area_id = ar.area_id
          WHERE a.attendance_date BETWEEN ? AND ?";
$params = [$start_date, $end_date];

if ($selected_employee) {
    $query .= " AND a.employee_id = ?";
    $params[] = $selected_employee;
}

$query .= " ORDER BY a.attendance_date DESC, e.full_name ASC";

$attendance_records = getAll($query, $params);

$total_working_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$total_records = count($attendance_records);

$present_count = count(array_filter($attendance_records, fn($r) => $r['status'] === 'present'));
$late_count = count(array_filter($attendance_records, fn($r) => $r['status'] === 'late'));
$absent_count = count(array_filter($attendance_records, fn($r) => $r['status'] === 'absent'));
$half_day_count = count(array_filter($attendance_records, fn($r) => $r['status'] === 'half_day'));

$total_work_hours = array_sum(array_column($attendance_records, 'work_hours'));
$average_work_hours = $total_records > 0 ? $total_work_hours / $total_records : 0;

$employee_stats = [];
foreach ($attendance_records as $record) {
    $emp_id = $record['employee_id'];
    if (!isset($employee_stats[$emp_id])) {
        $employee_stats[$emp_id] = [
            'name' => $record['full_name'],
            'area' => $record['area_name'] ?? 'N/A',
            'total_days' => 0,
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'half_day' => 0,
            'total_hours' => 0
        ];
    }
    
    $employee_stats[$emp_id]['total_days']++;
    $employee_stats[$emp_id][$record['status']]++;
    $employee_stats[$emp_id]['total_hours'] += $record['work_hours'] ?? 0;
}

foreach ($employee_stats as &$stat) {
    $stat['attendance_rate'] = round(($stat['present'] + $stat['late']) / $total_working_days * 100, 1);
}
unset($stat);

$month_name = date('F Y', strtotime($start_date));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - EcoBin</title>
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
        }

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

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #435334;
            color: white;
        }

        .btn-secondary {
            background: #CEDEBD;
            color: #435334;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filters form {
            display: grid;
            grid-template-columns: repeat(3, 1fr) auto;
            gap: 15px;
            align-items: end;
        }

        .filter-group label {
            font-size: 14px;
            font-weight: 600;
            color: #435334;
            margin-bottom: 8px;
            display: block;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-box .value {
            font-size: 32px;
            font-weight: 700;
            color: #435334;
            margin-bottom: 5px;
        }

        .stat-box .label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
        }

        .stat-box.present .value {
            color: #27ae60;
        }

        .stat-box.late .value {
            color: #f39c12;
        }

        .stat-box.absent .value {
            color: #e74c3c;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow-x: auto;
            margin-bottom: 30px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h2 {
            font-size: 20px;
            color: #435334;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            color: #435334;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #e0e0e0;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        tr:hover {
            background: #fafafa;
        }

        .progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #27ae60, #2ecc71);
            transition: width 0.3s ease;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 11px;
            font-weight: 600;
            color: #435334;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        @media print {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Attendance Reports</h1>
            <div style="display: flex; gap: 10px;">
                <button onclick="window.print()" class="btn btn-secondary">
                    Print Report
                </button>
                <a href="attendance.php" class="btn btn-primary">
                    ← Back to Attendance
                </a>
            </div>
        </div>

        <div class="filters">
            <form method="GET" action="">
                <div class="filter-group">
                    <label>Month</label>
                    <input type="month" name="month" value="<?php echo $selected_month; ?>" max="<?php echo date('Y-m'); ?>">
                </div>
                <div class="filter-group">
                    <label>Employee</label>
                    <select name="employee">
                        <option value="">All Employees</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['employee_id']; ?>" 
                                    <?php echo $selected_employee == $emp['employee_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Generate Report</button>
                </div>
            </form>
        </div>

        <div style="text-align: center; margin-bottom: 30px;">
            <h2 style="color: #435334; font-size: 24px;"><?php echo $month_name; ?> Attendance Report</h2>
            <?php if ($selected_employee): ?>
                <p style="color: #666; margin-top: 5px;">
                    Employee: <?php echo htmlspecialchars($employees[array_search($selected_employee, array_column($employees, 'employee_id'))]['full_name'] ?? 'Unknown'); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="stats-grid">
            <div class="stat-box">
                <div class="value"><?php echo $total_working_days; ?></div>
                <div class="label">Working Days</div>
            </div>
            <div class="stat-box">
                <div class="value"><?php echo $total_records; ?></div>
                <div class="label">Total Records</div>
            </div>
            <div class="stat-box present">
                <div class="value"><?php echo $present_count; ?></div>
                <div class="label">Present</div>
            </div>
            <div class="stat-box late">
                <div class="value"><?php echo $late_count; ?></div>
                <div class="label">Late</div>
            </div>
            <div class="stat-box absent">
                <div class="value"><?php echo $absent_count; ?></div>
                <div class="label">Absent</div>
            </div>
            <div class="stat-box">
                <div class="value"><?php echo number_format($total_work_hours, 1); ?></div>
                <div class="label">Total Hours</div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h2>Employee Summary</h2>
            </div>
            
            <?php if (empty($employee_stats)): ?>
                <div class="empty-state">
                    <div style="font-size: 48px; margin-bottom: 10px;">📊</div>
                    <h3>No attendance data</h3>
                    <p>No records found for <?php echo $month_name; ?></p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Area</th>
                            <th>Days Worked</th>
                            <th>Present</th>
                            <th>Late</th>
                            <th>Half Day</th>
                            <th>Total Hours</th>
                            <th>Avg Hours/Day</th>
                            <th>Attendance Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employee_stats as $stat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($stat['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($stat['area']); ?></td>
                                <td><?php echo $stat['total_days']; ?></td>
                                <td style="color: #27ae60; font-weight: 600;"><?php echo $stat['present']; ?></td>
                                <td style="color: #f39c12; font-weight: 600;"><?php echo $stat['late']; ?></td>
                                <td style="color: #3498db; font-weight: 600;"><?php echo $stat['half_day']; ?></td>
                                <td><?php echo number_format($stat['total_hours'], 2); ?> hrs</td>
                                <td><?php echo number_format($stat['total_hours'] / max($stat['total_days'], 1), 2); ?> hrs</td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $stat['attendance_rate']; ?>%;"></div>
                                        <div class="progress-text"><?php echo $stat['attendance_rate']; ?>%</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h2>Detailed Records</h2>
            </div>
            
            <?php if (empty($attendance_records)): ?>
                <div class="empty-state">
                    <div style="font-size: 48px; margin-bottom: 10px;">📋</div>
                    <h3>No records found</h3>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($record['attendance_date'])); ?></td>
                                <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                                <td><?php echo $record['check_in_time'] ? date('g:i A', strtotime($record['check_in_time'])) : '--:--'; ?></td>
                                <td><?php echo $record['check_out_time'] ? date('g:i A', strtotime($record['check_out_time'])) : '--:--'; ?></td>
                                <td><?php echo number_format($record['work_hours'] ?? 0, 2); ?> hrs</td>
                                <td>
                                    <span style="padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;
                                        background: <?php 
                                            echo $record['status'] === 'present' ? '#d4edda' : 
                                                ($record['status'] === 'late' ? '#fff3cd' : 
                                                ($record['status'] === 'half_day' ? '#d1ecf1' : '#f8d7da')); 
                                        ?>;
                                        color: <?php 
                                            echo $record['status'] === 'present' ? '#155724' : 
                                                ($record['status'] === 'late' ? '#856404' : 
                                                ($record['status'] === 'half_day' ? '#0c5460' : '#721c24')); 
                                        ?>;">
                                        <?php echo ucfirst(str_replace('_', ' ', $record['status'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>