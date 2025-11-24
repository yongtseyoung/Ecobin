<?php
/**
 * Attendance System - Main Page
 * View and manage employee attendance records
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur'); // Malaysia timezone
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Set current page for sidebar
$current_page = 'attendance';

$admin_name = $_SESSION['full_name'] ?? 'Admin';

// Get filter parameters
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_employee = $_GET['employee'] ?? '';
$selected_status = $_GET['status'] ?? '';

// Get success/error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Build query
$query = "SELECT a.*, e.full_name, e.username, ar.area_name
          FROM attendance a
          JOIN employees e ON a.employee_id = e.employee_id
          LEFT JOIN areas ar ON e.area_id = ar.area_id
          WHERE a.attendance_date = ?";
$params = [$selected_date];

if ($selected_employee) {
    $query .= " AND a.employee_id = ?";
    $params[] = $selected_employee;
}

if ($selected_status) {
    $query .= " AND a.status = ?";
    $params[] = $selected_status;
}

$query .= " ORDER BY a.check_in_time ASC";

// Get attendance records
$attendance_records = getAll($query, $params);

// Get all employees for filter dropdown
$employees = getAll("SELECT employee_id, full_name FROM employees WHERE status = 'active' ORDER BY full_name");

// Calculate statistics for selected date
$total_employees = getOne("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")['count'];
$present_count = count(array_filter($attendance_records, fn($r) => in_array($r['status'], ['present', 'late'])));
$late_count = count(array_filter($attendance_records, fn($r) => $r['status'] === 'late'));
$absent_count = $total_employees - $present_count;

// Get employees who haven't checked in yet
$checked_in_ids = array_column($attendance_records, 'employee_id');
$absent_employees = [];
if ($selected_date === date('Y-m-d')) {
    $placeholders = $checked_in_ids ? str_repeat('?,', count($checked_in_ids) - 1) . '?' : '';
    $absent_query = "SELECT employee_id, full_name FROM employees WHERE status = 'active'";
    if ($placeholders) {
        $absent_query .= " AND employee_id NOT IN ($placeholders)";
        $absent_employees = getAll($absent_query, $checked_in_ids);
    } else {
        $absent_employees = getAll($absent_query);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance System - EcoBin</title>
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

        /* Main Content */
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

        .header-actions {
            display: flex;
            gap: 10px;
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

        .btn-primary:hover {
            background: #354428;
        }

        .btn-secondary {
            background: #CEDEBD;
            color: #435334;
        }

        .btn-secondary:hover {
            background: #b8ceaa;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
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

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-card h3 {
            font-size: 14px;
            color: #999;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #435334;
        }

        .stat-card.present .value {
            color: #27ae60;
        }

        .stat-card.late .value {
            color: #f39c12;
        }

        .stat-card.absent .value {
            color: #e74c3c;
        }

        /* Filters */
        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filters form {
            display: grid;
            grid-template-columns: repeat(4, 1fr) auto;
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 14px;
            font-weight: 600;
            color: #435334;
            margin-bottom: 8px;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            color: #435334;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #e0e0e0;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        tr:hover {
            background: #fafafa;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
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

        .status-half_day {
            background: #d1ecf1;
            color: #0c5460;
        }

        .location-link {
            color: #435334;
            text-decoration: none;
            font-size: 12px;
        }

        .location-link:hover {
            text-decoration: underline;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        /* Responsive */
        @media (max-width: 968px) {
            .main-content {
                margin-left: 70px;
            }
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            .filters form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>✅ Attendance System</h1>
            <div class="header-actions">
                <a href="attendance_report.php" class="btn btn-secondary">
                    📊 View Reports
                </a>
                <a href="attendance_manual.php" class="btn btn-secondary">
                    ✏️ Manual Entry
                </a>
                <div style="position: relative;">
                    <button onclick="showEmployeeSelector()" class="btn btn-primary" style="cursor: pointer;">
                        ✓ Check In/Out (Select Employee)
                    </button>
                    <!-- Employee Selector Dropdown -->
                    <div id="employeeSelector" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 10px; background: white; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.2); padding: 20px; min-width: 300px; z-index: 1000;">
                        <h3 style="margin-bottom: 10px; color: #435334;">👤 Select Employee</h3>
                        <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                            Choose an employee to view their check-in/check-out page
                        </p>
                        <div style="margin-bottom: 15px;">
                            <input type="text" id="employeeSearch" placeholder="Search employee..." 
                                   style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px;"
                                   onkeyup="filterEmployees()">
                        </div>
                        <div id="employeeList" style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($employees as $emp): ?>
                                <a href="attendance_checkin.php?employee_id=<?php echo $emp['employee_id']; ?>" 
                                   class="employee-item"
                                   style="display: block; padding: 12px; margin-bottom: 5px; background: #f8f9fa; border-radius: 8px; text-decoration: none; color: #435334; transition: all 0.2s;"
                                   onmouseover="this.style.background='#CEDEBD'" 
                                   onmouseout="this.style.background='#f8f9fa'">
                                    <strong><?php echo htmlspecialchars($emp['full_name']); ?></strong>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <button onclick="hideEmployeeSelector()" 
                                style="width: 100%; margin-top: 10px; padding: 10px; background: #f0f0f0; border: none; border-radius: 8px; cursor: pointer;">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function showEmployeeSelector() {
                document.getElementById('employeeSelector').style.display = 'block';
                document.getElementById('employeeSearch').focus();
            }

            function hideEmployeeSelector() {
                document.getElementById('employeeSelector').style.display = 'none';
            }

            function filterEmployees() {
                const searchText = document.getElementById('employeeSearch').value.toLowerCase();
                const items = document.querySelectorAll('.employee-item');
                
                items.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    if (text.includes(searchText)) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                const selector = document.getElementById('employeeSelector');
                const button = event.target.closest('.btn-primary');
                
                if (!selector.contains(event.target) && !button) {
                    hideEmployeeSelector();
                }
            });
        </script>

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

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Employees</h3>
                <div class="value"><?php echo $total_employees; ?></div>
            </div>
            <div class="stat-card present">
                <h3>Present</h3>
                <div class="value"><?php echo $present_count; ?></div>
            </div>
            <div class="stat-card late">
                <h3>Late</h3>
                <div class="value"><?php echo $late_count; ?></div>
            </div>
            <div class="stat-card absent">
                <h3>Absent</h3>
                <div class="value"><?php echo $absent_count; ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="filter-group">
                    <label>Date</label>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($selected_date); ?>">
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
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="present" <?php echo $selected_status === 'present' ? 'selected' : ''; ?>>Present</option>
                        <option value="late" <?php echo $selected_status === 'late' ? 'selected' : ''; ?>>Late</option>
                        <option value="absent" <?php echo $selected_status === 'absent' ? 'selected' : ''; ?>>Absent</option>
                        <option value="half_day" <?php echo $selected_status === 'half_day' ? 'selected' : ''; ?>>Half Day</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </form>
        </div>

        <!-- Attendance Table -->
        <div class="table-container">
            <?php if (empty($attendance_records)): ?>
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <h3>No attendance records found</h3>
                    <p>No employees have checked in for this date</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Work Hours</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($record['full_name']); ?></strong><br>
                                    <small style="color: #999;"><?php echo htmlspecialchars($record['area_name'] ?? 'No Area'); ?></small>
                                </td>
                                <td>
                                    <?php echo $record['check_in_time'] ? date('g:i A', strtotime($record['check_in_time'])) : '--:--'; ?>
                                </td>
                                <td>
                                    <?php echo $record['check_out_time'] ? date('g:i A', strtotime($record['check_out_time'])) : '--:--'; ?>
                                </td>
                                <td>
                                    <?php echo $record['work_hours'] ? number_format($record['work_hours'], 2) . ' hrs' : '0.00 hrs'; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $record['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $record['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($record['check_in_location']): ?>
                                        <a href="https://www.google.com/maps?q=<?php echo urlencode($record['check_in_location']); ?>" 
                                           target="_blank" 
                                           class="location-link">
                                            📍 View Map
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #999;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Show absent employees for today -->
            <?php if ($selected_date === date('Y-m-d') && !empty($absent_employees)): ?>
                <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 10px;">
                    <h3 style="color: #856404; margin-bottom: 10px;">⚠ Not Checked In Yet:</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                        <?php foreach ($absent_employees as $absent): ?>
                            <span style="padding: 6px 12px; background: white; border-radius: 8px; font-size: 13px;">
                                <?php echo htmlspecialchars($absent['full_name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>