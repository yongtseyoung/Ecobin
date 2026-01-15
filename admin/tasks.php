<?php
/**
 * Task Management System - Main Dashboard
 * Shows all tasks (manual + auto-generated from IoT sensors)
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Set current page for sidebar
$current_page = 'tasks';

$admin_name = $_SESSION['full_name'] ?? 'Admin';

// Get filter parameters
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_employee = $_GET['employee'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_priority = $_GET['priority'] ?? '';
$filter_source = $_GET['source'] ?? ''; // auto or manual

// Build query
$query = "SELECT t.*, 
          e.full_name as employee_name, 
          e.username as employee_username,
          a.area_name,
          b.bin_code,
          b.current_fill_level,
          creator.full_name as creator_name
          FROM tasks t
          LEFT JOIN employees e ON t.assigned_to = e.employee_id
          LEFT JOIN areas a ON t.area_id = a.area_id
          LEFT JOIN bins b ON t.triggered_by_bin = b.bin_id
          LEFT JOIN admins creator ON t.created_by = creator.admin_id
          WHERE 1=1";

$params = [];

// Apply filters
if ($filter_date) {
    $query .= " AND DATE(t.scheduled_date) = ?";
    $params[] = $filter_date;
}

if ($filter_employee) {
    $query .= " AND t.assigned_to = ?";
    $params[] = $filter_employee;
}

if ($filter_status) {
    $query .= " AND t.status = ?";
    $params[] = $filter_status;
}

if ($filter_type) {
    $query .= " AND t.task_type = ?";
    $params[] = $filter_type;
}

if ($filter_priority) {
    $query .= " AND t.priority = ?";
    $params[] = $filter_priority;
}

if ($filter_source === 'auto') {
    $query .= " AND t.is_auto_generated = 1";
} elseif ($filter_source === 'manual') {
    $query .= " AND t.is_auto_generated = 0";
}

$query .= " ORDER BY 
            FIELD(t.priority, 'urgent', 'high', 'medium', 'low'),
            FIELD(t.status, 'pending', 'in_progress', 'completed', 'cancelled'),
            t.scheduled_date ASC,
            t.created_at DESC";

$tasks = getAll($query, $params);

// Get all employees for filter
$employees = getAll("SELECT employee_id, full_name FROM employees WHERE status = 'active' ORDER BY full_name");

// Calculate statistics
$total_tasks = count($tasks);
$pending_tasks = count(array_filter($tasks, fn($t) => $t['status'] === 'pending'));
$in_progress_tasks = count(array_filter($tasks, fn($t) => $t['status'] === 'in_progress'));
$completed_tasks = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));
$auto_generated_tasks = count(array_filter($tasks, fn($t) => $t['is_auto_generated'] == 1));
$manual_tasks = count(array_filter($tasks, fn($t) => $t['is_auto_generated'] == 0));

// Get messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management - EcoBin</title>
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

        .stats-row {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #435334;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
        }

        .stat-card.pending .value {
            color: #f39c12;
        }

        .stat-card.progress .value {
            color: #3498db;
        }

        .stat-card.completed .value {
            color: #27ae60;
        }

        .stat-card.auto .value {
            color: #9b59b6;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filters form {
            display: grid;
            grid-template-columns: repeat(7, 1fr) auto;
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

        .priority-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-urgent {
            background: #fee;
            color: #c00;
        }

        .priority-high {
            background: #fff3cd;
            color: #856404;
        }

        .priority-medium {
            background: #d1ecf1;
            color: #0c5460;
        }

        .priority-low {
            background: #d4edda;
            color: #155724;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-in_progress {
            background: #cce5ff;
            color: #004085;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .type-badge {
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
        }

        .type-collection {
            background: #e3f2fd;
            color: #1976d2;
        }

        .type-maintenance {
            background: #fff3e0;
            color: #e65100;
        }

        .type-inspection {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .type-emergency {
            background: #ffebee;
            color: #c62828;
        }

        .auto-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            background: #e8eaf6;
            color: #3f51b5;
        }

        .actions-btns {
            display: flex;
            gap: 5px;
        }

        .btn-icon {
            padding: 8px 12px;
            font-size: 12px;
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
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <h1>Task Management</h1>
            <div class="header-actions">
                <a href="task_reports.php" class="btn btn-secondary">
                    Reports
                </a>
                <a href="task_create.php" class="btn btn-primary">
                    + Create Task
                </a>
            </div>
        </div>

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

        <div class="stats-row">
            <div class="stat-card">
                <div class="value"><?php echo $total_tasks; ?></div>
                <div class="label">Total Tasks</div>
            </div>
            <div class="stat-card pending">
                <div class="value"><?php echo $pending_tasks; ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-card progress">
                <div class="value"><?php echo $in_progress_tasks; ?></div>
                <div class="label">In Progress</div>
            </div>
            <div class="stat-card completed">
                <div class="value"><?php echo $completed_tasks; ?></div>
                <div class="label">Completed</div>
            </div>
            <div class="stat-card auto">
                <div class="value"><?php echo $auto_generated_tasks; ?></div>
                <div class="label">Auto</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo $manual_tasks; ?></div>
                <div class="label">Manual</div>
            </div>
        </div>

        <div class="filters">
            <form method="GET" action="">
                <div class="filter-group">
                    <label>Date</label>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">
                </div>
                <div class="filter-group">
                    <label>Employee</label>
                    <select name="employee">
                        <option value="">All Employees</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['employee_id']; ?>" 
                                    <?php echo $filter_employee == $emp['employee_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Type</label>
                    <select name="type">
                        <option value="">All Types</option>
                        <option value="collection" <?php echo $filter_type === 'collection' ? 'selected' : ''; ?>>Collection</option>
                        <option value="maintenance" <?php echo $filter_type === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="inspection" <?php echo $filter_type === 'inspection' ? 'selected' : ''; ?>>Inspection</option>
                        <option value="emergency" <?php echo $filter_type === 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Priority</label>
                    <select name="priority">
                        <option value="">All Priorities</option>
                        <option value="urgent" <?php echo $filter_priority === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                        <option value="high" <?php echo $filter_priority === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="medium" <?php echo $filter_priority === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="low" <?php echo $filter_priority === 'low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Source</label>
                    <select name="source">
                        <option value="">All Sources</option>
                        <option value="auto" <?php echo $filter_source === 'auto' ? 'selected' : ''; ?>>🤖 Auto (IoT)</option>
                        <option value="manual" <?php echo $filter_source === 'manual' ? 'selected' : ''; ?>>✍️ Manual</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Filter</button>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <a href="tasks.php" class="btn btn-secondary" style="width: 100%; justify-content: center;">Clear</a>
                </div>
            </form>
        </div>

        <div class="table-container">
            <?php if (empty($tasks)): ?>
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <h3>No tasks found</h3>
                    <p>No tasks match your filters for the selected date</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Type</th>
                            <th>Priority</th>
                            <th>Assigned To</th>
                            <th>Area</th>
                            <th>Status</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($task['task_title']); ?></strong>
                                    <?php if ($task['is_auto_generated']): ?>
                                        <br><span class="auto-badge">AUTO</span>
                                    <?php endif; ?>
                                    <?php if ($task['bin_code']): ?>
                                        <br><small style="color: #666;">Bin: <?php echo htmlspecialchars($task['bin_code']); ?> 
                                        <?php if ($task['current_fill_level']): ?>
                                            (<?php echo $task['current_fill_level']; ?>% full)
                                        <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="type-badge type-<?php echo $task['task_type']; ?>">
                                        <?php echo ucfirst($task['task_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="priority-badge priority-<?php echo $task['priority']; ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($task['employee_name']): ?>
                                        <?php echo htmlspecialchars($task['employee_name']); ?>
                                    <?php else: ?>
                                        <span style="color: #999;">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($task['area_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $task['status']; ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $task['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($task['scheduled_date']): ?>
                                        <?php 
                                        $due_date = date('M j, Y', strtotime($task['scheduled_date']));
                                        $is_overdue = strtotime($task['scheduled_date']) < time() && $task['status'] !== 'completed';
                                        echo $is_overdue ? "<span style='color: red; font-weight: 600;'>$due_date</span>" : $due_date;
                                        ?>
                                    <?php else: ?>
                                        <span style="color: #999;">N/A</span>
                                    <?php endif; ?>
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