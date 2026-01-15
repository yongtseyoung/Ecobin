<?php
/**
 * Task Reports & Analytics
 * Performance metrics and task statistics
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get date range filter
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Set current page for sidebar
$current_page = 'tasks';

// Overall statistics
$total_tasks = getOne("SELECT COUNT(*) as count FROM tasks WHERE DATE(created_at) BETWEEN ? AND ?", [$start_date, $end_date])['count'];
$completed_tasks = getOne("SELECT COUNT(*) as count FROM tasks WHERE status = 'completed' AND DATE(completed_at) BETWEEN ? AND ?", [$start_date, $end_date])['count'];
$pending_tasks = getOne("SELECT COUNT(*) as count FROM tasks WHERE status = 'pending'")['count'];
$in_progress_tasks = getOne("SELECT COUNT(*) as count FROM tasks WHERE status = 'in_progress'")['count'];
$auto_generated = getOne("SELECT COUNT(*) as count FROM tasks WHERE is_auto_generated = 1 AND DATE(created_at) BETWEEN ? AND ?", [$start_date, $end_date])['count'];

// Completion rate
$completion_rate = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100, 1) : 0;

// Tasks by type
$by_type = getAll("SELECT task_type, COUNT(*) as count 
                   FROM tasks 
                   WHERE DATE(created_at) BETWEEN ? AND ?
                   GROUP BY task_type 
                   ORDER BY count DESC", [$start_date, $end_date]);

// Tasks by priority
$by_priority = getAll("SELECT priority, COUNT(*) as count 
                       FROM tasks 
                       WHERE DATE(created_at) BETWEEN ? AND ?
                       GROUP BY priority 
                       ORDER BY FIELD(priority, 'urgent', 'high', 'medium', 'low')", [$start_date, $end_date]);

// Employee performance
$employee_stats = getAll("SELECT 
                          e.full_name,
                          COUNT(t.task_id) as total_tasks,
                          SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed,
                          SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) as pending,
                          SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                          AVG(CASE 
                              WHEN t.completed_at IS NOT NULL AND t.started_at IS NOT NULL 
                              THEN TIMESTAMPDIFF(MINUTE, t.started_at, t.completed_at) 
                              ELSE NULL 
                          END) as avg_completion_time
                          FROM employees e
                          LEFT JOIN tasks t ON e.employee_id = t.assigned_to 
                              AND DATE(t.created_at) BETWEEN ? AND ?
                          WHERE e.status = 'active'
                          GROUP BY e.employee_id, e.full_name
                          ORDER BY completed DESC", [$start_date, $end_date]);

// Recent completed tasks
$recent_completed = getAll("SELECT t.*, e.full_name as employee_name
                            FROM tasks t
                            LEFT JOIN employees e ON t.assigned_to = e.employee_id
                            WHERE t.status = 'completed'
                            AND DATE(t.completed_at) BETWEEN ? AND ?
                            ORDER BY t.completed_at DESC
                            LIMIT 10", [$start_date, $end_date]);

// Tasks by area
$by_area = getAll("SELECT 
                   a.area_name,
                   a.block,
                   COUNT(t.task_id) as task_count,
                   SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed
                   FROM areas a
                   LEFT JOIN tasks t ON a.area_id = t.area_id 
                       AND DATE(t.created_at) BETWEEN ? AND ?
                   GROUP BY a.area_id, a.area_name, a.block
                   HAVING task_count > 0
                   ORDER BY task_count DESC", [$start_date, $end_date]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Reports - EcoBin</title>
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
            background: #CEDEBD;
            color: #435334;
        }

        .btn-primary:hover {
            background: #b8ceaa;
        }

        .btn-secondary {
            background: #435334;
            color: white;
        }

        .btn-secondary:hover {
            background: #354428;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filters form {
            display: flex;
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            flex: 1;
        }

        .filter-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #435334;
            margin-bottom: 8px;
        }

        .filter-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stat-card .value {
            font-size: 42px;
            font-weight: 700;
            color: #435334;
            margin-bottom: 8px;
        }

        .stat-card .label {
            font-size: 13px;
            color: #999;
            text-transform: uppercase;
        }

        .stat-card.primary .value { color: #435334; }
        .stat-card.success .value { color: #435334; }
        .stat-card.warning .value { color: #435334; }
        .stat-card.info .value { color: #435334; }
        .stat-card.purple .value { color: #435334; }

        .report-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .report-section h2 {
            font-size: 20px;
            color: #435334;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            height: 8px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #27ae60;
            transition: width 0.3s;
        }

        .chart-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .chart-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
        }

        .chart-item h4 {
            color: #435334;
            margin-bottom: 15px;
        }

        .bar-chart {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .bar-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bar-label {
            min-width: 100px;
            font-size: 13px;
            color: #666;
        }

        .bar {
            flex: 1;
            height: 24px;
            background: #435334;
            border-radius: 5px;
            position: relative;
        }

        .bar-value {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-size: 12px;
            font-weight: 600;
        }

        /* Print Styles */
        @media print {
            body {
                background: white;
            }

            .sidebar,
            .page-header,
            .filters,
            .header-actions,
            .btn {
                display: none !important;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .report-section {
                box-shadow: none;
                page-break-inside: avoid;
            }

            .stat-card {
                box-shadow: none;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
            }

            .header-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Task Reports & Analytics</h1>
            <div class="header-actions">
                <button onclick="window.print()" class="btn btn-primary">
                    Print Report
                </button>
                <a href="tasks.php" class="btn btn-secondary">
                    ← Back to Tasks
                </a>
            </div>
        </div>

        <div class="filters">
            <form method="GET">
                <div class="filter-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="filter-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-secondary" style="width: 100%;">Apply</button>
                </div>
            </form>
        </div>

        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="value"><?php echo $total_tasks; ?></div>
                <div class="label">Total Tasks</div>
            </div>
            <div class="stat-card success">
                <div class="value"><?php echo $completed_tasks; ?></div>
                <div class="label">Completed</div>
            </div>
            <div class="stat-card warning">
                <div class="value"><?php echo $pending_tasks; ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-card info">
                <div class="value"><?php echo $in_progress_tasks; ?></div>
                <div class="label">In Progress</div>
            </div>
            <div class="stat-card purple">
                <div class="value"><?php echo $auto_generated; ?></div>
                <div class="label">Auto-Generated</div>
            </div>
            <div class="stat-card success">
                <div class="value"><?php echo $completion_rate; ?>%</div>
                <div class="label">Completion Rate</div>
            </div>
        </div>

        <div class="report-section">
            <h2>Task Distribution</h2>
            <div class="chart-container">
                <div class="chart-item">
                    <h4>By Type</h4>
                    <div class="bar-chart">
                        <?php foreach ($by_type as $item): ?>
                            <?php $percentage = $total_tasks > 0 ? ($item['count'] / $total_tasks) * 100 : 0; ?>
                            <div class="bar-item">
                                <div class="bar-label"><?php echo ucfirst($item['task_type']); ?></div>
                                <div class="bar" style="width: <?php echo $percentage; ?>%;">
                                    <div class="bar-value"><?php echo $item['count']; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="chart-item">
                    <h4>By Priority</h4>
                    <div class="bar-chart">
                        <?php foreach ($by_priority as $item): ?>
                            <?php $percentage = $total_tasks > 0 ? ($item['count'] / $total_tasks) * 100 : 0; ?>
                            <div class="bar-item">
                                <div class="bar-label"><?php echo ucfirst($item['priority']); ?></div>
                                <div class="bar" style="width: <?php echo $percentage; ?>%; background: 
                                    <?php echo $item['priority'] === 'urgent' ? '#e74c3c' : 
                                               ($item['priority'] === 'high' ? '#f39c12' : 
                                               ($item['priority'] === 'medium' ? '#3498db' : '#27ae60')); ?>">
                                    <div class="bar-value"><?php echo $item['count']; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="report-section">
            <h2>Employee Performance</h2>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Total Tasks</th>
                        <th>Completed</th>
                        <th>In Progress</th>
                        <th>Pending</th>
                        <th>Completion Rate</th>
                        <th>Avg Time (mins)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employee_stats as $emp): ?>
                        <?php $rate = $emp['total_tasks'] > 0 ? round(($emp['completed'] / $emp['total_tasks']) * 100) : 0; ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($emp['full_name']); ?></strong></td>
                            <td><?php echo $emp['total_tasks']; ?></td>
                            <td style="color: #27ae60; font-weight: 600;"><?php echo $emp['completed']; ?></td>
                            <td style="color: #b32c2c;"><?php echo $emp['in_progress']; ?></td>
                            <td style="color: #f39c12;"><?php echo $emp['pending']; ?></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $rate; ?>%"></div>
                                </div>
                                <small><?php echo $rate; ?>%</small>
                            </td>
                            <td>
                                <?php echo $emp['avg_completion_time'] ? round($emp['avg_completion_time']) : 'N/A'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($by_area)): ?>
            <div class="report-section">
                <h2>Tasks by Area</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Area</th>
                            <th>Block</th>
                            <th>Total Tasks</th>
                            <th>Completed</th>
                            <th>Completion Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($by_area as $area): ?>
                            <?php $rate = $area['task_count'] > 0 ? round(($area['completed'] / $area['task_count']) * 100) : 0; ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($area['area_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($area['block'] ?? 'N/A'); ?></td>
                                <td><?php echo $area['task_count']; ?></td>
                                <td style="color: #27ae60; font-weight: 600;"><?php echo $area['completed']; ?></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $rate; ?>%"></div>
                                    </div>
                                    <small><?php echo $rate; ?>%</small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($recent_completed)): ?>
            <div class="report-section">
                <h2>Recently Completed Tasks</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Completed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_completed as $task): ?>
                            <tr>
                                <td>
                                    <a href="task_view.php?id=<?php echo $task['task_id']; ?>" style="color: #435334; text-decoration: none;">
                                        <strong><?php echo htmlspecialchars($task['task_title']); ?></strong>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($task['employee_name']); ?></td>
                                <td><?php echo ucfirst($task['task_type']); ?></td>
                                <td><?php echo date('M j, g:i A', strtotime($task['completed_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>