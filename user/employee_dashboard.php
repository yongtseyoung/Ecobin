<?php
/**
 * Employee Dashboard
 * Main dashboard for employees to view their tasks, performance, and stats
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

// Get employee details
$employee = getOne("SELECT e.*, a.area_name 
                    FROM employees e 
                    LEFT JOIN areas a ON e.area_id = a.area_id 
                    WHERE e.employee_id = ?", 
                    [$employee_id]);

// Get today's date
$today = date('Y-m-d');
$current_month = date('Y-m');

// Get task statistics
$total_tasks = getOne("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ?", [$employee_id])['count'];
$pending_tasks = getOne("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'pending'", [$employee_id])['count'];
$in_progress_tasks = getOne("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'in_progress'", [$employee_id])['count'];
$completed_today = getOne("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'completed' AND DATE(completed_at) = ?", [$employee_id, $today])['count'];
$completed_month = getOne("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'completed' AND DATE(completed_at) LIKE ?", [$employee_id, $current_month.'%'])['count'];

// Get today's tasks
$todays_tasks = getAll("SELECT t.*, b.bin_code, b.location_details 
                        FROM tasks t 
                        LEFT JOIN bins b ON t.triggered_by_bin = b.bin_id 
                        WHERE t.assigned_to = ? 
                        AND DATE(t.scheduled_date) = ? 
                        ORDER BY 
                            FIELD(t.priority, 'urgent', 'high', 'medium', 'low'),
                            FIELD(t.status, 'in_progress', 'pending', 'completed', 'cancelled')",
                        [$employee_id, $today]);

// Get recent completed tasks
$recent_completed = getAll("SELECT t.*, b.bin_code 
                           FROM tasks t 
                           LEFT JOIN bins b ON t.triggered_by_bin = b.bin_id 
                           WHERE t.assigned_to = ? 
                           AND t.status = 'completed' 
                           ORDER BY t.completed_at DESC 
                           LIMIT 5",
                           [$employee_id]);

// Get attendance for this month
$attendance_records = getAll("SELECT * FROM attendance 
                              WHERE employee_id = ? 
                              AND DATE(check_in_time) LIKE ? 
                              ORDER BY check_in_time DESC",
                              [$employee_id, $current_month.'%']);

$present_days = count(array_filter($attendance_records, fn($a) => $a['status'] === 'present'));
$late_days = count(array_filter($attendance_records, fn($a) => $a['status'] === 'late'));

// Calculate performance score (simple calculation)
$performance_score = 0;
if ($total_tasks > 0) {
    $completed_all = getOne("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'completed'", [$employee_id])['count'];
    $performance_score = round(($completed_all / $total_tasks) * 100, 1);
}

// Get urgent/high priority tasks
$urgent_tasks = getAll("SELECT t.*, b.bin_code, b.location_details 
                        FROM tasks t 
                        LEFT JOIN bins b ON t.triggered_by_bin = b.bin_id 
                        WHERE t.assigned_to = ? 
                        AND t.status IN ('pending', 'in_progress')
                        AND t.priority IN ('urgent', 'high')
                        ORDER BY FIELD(t.priority, 'urgent', 'high'), t.scheduled_date",
                        [$employee_id]);

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EcoBin</title>
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

        .user-profile {
            text-align: center;
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .user-profile h3 {
            font-size: 16px;
            margin-bottom: 5px;
        }

        .user-profile p {
            font-size: 12px;
            opacity: 0.8;
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

        .welcome-banner {
            background: linear-gradient(135deg, #435334 0%, #5a6f4a 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .welcome-banner h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .welcome-banner p {
            opacity: 0.9;
            font-size: 16px;
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
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .stat-card .icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: 700;
            color: #435334;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card.pending .value { color: #f39c12; }
        .stat-card.progress .value { color: #3498db; }
        .stat-card.completed .value { color: #27ae60; }
        .stat-card.performance .value { color: #9b59b6; }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .card h2 {
            color: #435334;
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .task-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .task-item {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 15px;
            transition: all 0.3s ease;
        }

        .task-item:hover {
            border-color: #CEDEBD;
            box-shadow: 0 3px 10px rgba(67, 83, 52, 0.1);
        }

        .task-item.urgent {
            border-color: #fee;
            background: #fff8f8;
        }

        .task-item.high {
            border-color: #fff3cd;
            background: #fffef8;
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }

        .task-title {
            font-weight: 600;
            color: #435334;
            font-size: 15px;
            margin-bottom: 5px;
        }

        .task-meta {
            font-size: 12px;
            color: #666;
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }

        .task-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .priority-urgent { background: #fee; color: #c00; }
        .priority-high { background: #fff3cd; color: #856404; }
        .priority-medium { background: #d1ecf1; color: #0c5460; }
        .priority-low { background: #d4edda; color: #155724; }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-in_progress { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #435334;
            color: white;
        }

        .btn-primary:hover {
            background: #354428;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .quick-stats {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .quick-stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .quick-stat-item .label {
            font-size: 13px;
            color: #666;
        }

        .quick-stat-item .value {
            font-size: 20px;
            font-weight: 700;
            color: #435334;
        }

        .progress-ring {
            width: 120px;
            height: 120px;
            margin: 0 auto 15px;
        }

        .progress-circle {
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }

            .sidebar-logo {
                width: 50px;
                height: 50px;
            }

            .user-profile,
            .nav-item span:not(.icon) {
                display: none;
            }

            .main-content {
                margin-left: 70px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="../assets/images/logo.png" alt="EcoBin Logo">
        </div>

        <div class="user-profile">
            <h3><?php echo htmlspecialchars($employee_name); ?></h3>
            <p><?php echo htmlspecialchars($employee['area_name'] ?? 'No Area'); ?></p>
        </div>

        <nav class="nav-menu">
            <a href="employee_dashboard.php" class="nav-item active">
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
            <a href="my_performance.php" class="nav-item">
                <span class="icon">📈</span>
                <span>Performance</span>
            </a>
            <a href="my_schedule.php" class="nav-item">
                <span class="icon">📅</span>
                <span>Schedule</span>
            </a>
            <a href="my_leave.php" class="nav-item">
                <span class="icon">🏖️</span>
                <span>Leave</span>
            </a>
            <a href="my_profile.php" class="nav-item">
                <span class="icon">👤</span>
                <span>Profile</span>
            </a>
            <a href="../auth/logout.php" class="nav-item" style="margin-top: 20px; opacity: 0.7;">
                <span class="icon">🚪</span>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="welcome-banner">
            <h1>👋 Welcome back, <?php echo htmlspecialchars(explode(' ', $employee_name)[0]); ?>!</h1>
            <p>Here's what's happening with your tasks today</p>
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

        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="icon">⏳</div>
                <div class="value"><?php echo $pending_tasks; ?></div>
                <div class="label">Pending Tasks</div>
            </div>

            <div class="stat-card progress">
                <div class="icon">🚀</div>
                <div class="value"><?php echo $in_progress_tasks; ?></div>
                <div class="label">In Progress</div>
            </div>

            <div class="stat-card completed">
                <div class="icon">✅</div>
                <div class="value"><?php echo $completed_today; ?></div>
                <div class="label">Completed Today</div>
            </div>

            <div class="stat-card completed">
                <div class="icon">📊</div>
                <div class="value"><?php echo $completed_month; ?></div>
                <div class="label">This Month</div>
            </div>

            <div class="stat-card performance">
                <div class="icon">⭐</div>
                <div class="value"><?php echo $performance_score; ?>%</div>
                <div class="label">Performance</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <h2>📅 Today's Tasks (<?php echo date('M d, Y'); ?>)</h2>
                <?php if (empty($todays_tasks)): ?>
                    <div class="empty-state">
                        <div class="icon">🎉</div>
                        <h3>No tasks for today!</h3>
                        <p>Enjoy your day or check upcoming tasks</p>
                    </div>
                <?php else: ?>
                    <div class="task-list">
                        <?php foreach ($todays_tasks as $task): ?>
                            <div class="task-item <?php echo $task['priority']; ?>">
                                <div class="task-header">
                                    <div>
                                        <div class="task-title"><?php echo htmlspecialchars($task['task_title']); ?></div>
                                        <?php if ($task['bin_code']): ?>
                                            <div style="font-size: 12px; color: #666; margin-top: 3px;">
                                                🗑️ <?php echo htmlspecialchars($task['bin_code']); ?> - <?php echo htmlspecialchars($task['location_details']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span class="badge priority-<?php echo $task['priority']; ?>">
                                            <?php echo strtoupper($task['priority']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="task-meta">
                                    <span>📋 <?php echo ucfirst($task['task_type']); ?></span>
                                    <span class="badge status-<?php echo $task['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                    </span>
                                    <?php if ($task['status'] === 'pending'): ?>
                                        <a href="my_tasks.php" class="btn btn-primary btn-small">Start Task →</a>
                                    <?php elseif ($task['status'] === 'in_progress'): ?>
                                        <a href="my_tasks.php" class="btn btn-primary btn-small">Complete →</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <?php if (!empty($urgent_tasks)): ?>
                    <div class="card" style="margin-bottom: 20px;">
                        <h2>🚨 Urgent Tasks</h2>
                        <div class="task-list">
                            <?php foreach (array_slice($urgent_tasks, 0, 3) as $task): ?>
                                <div class="task-item <?php echo $task['priority']; ?>">
                                    <div class="task-title"><?php echo htmlspecialchars($task['task_title']); ?></div>
                                    <?php if ($task['bin_code']): ?>
                                        <div style="font-size: 11px; color: #666; margin-top: 3px;">
                                            <?php echo htmlspecialchars($task['bin_code']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="margin-top: 8px;">
                                        <span class="badge priority-<?php echo $task['priority']; ?>">
                                            <?php echo strtoupper($task['priority']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($urgent_tasks) > 3): ?>
                            <div style="text-align: center; margin-top: 15px;">
                                <a href="my_tasks.php" class="btn btn-primary btn-small">
                                    View All (<?php echo count($urgent_tasks); ?>)
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <h2>📊 Quick Stats</h2>
                    <div class="quick-stats">
                        <div class="quick-stat-item">
                            <span class="label">Present Days (This Month)</span>
                            <span class="value"><?php echo $present_days; ?></span>
                        </div>
                        <div class="quick-stat-item">
                            <span class="label">Late Arrivals</span>
                            <span class="value"><?php echo $late_days; ?></span>
                        </div>
                        <div class="quick-stat-item">
                            <span class="label">Total Tasks</span>
                            <span class="value"><?php echo $total_tasks; ?></span>
                        </div>
                        <div class="quick-stat-item">
                            <span class="label">Completion Rate</span>
                            <span class="value"><?php echo $performance_score; ?>%</span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($recent_completed)): ?>
                    <div class="card" style="margin-top: 20px;">
                        <h2>✅ Recently Completed</h2>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <?php foreach (array_slice($recent_completed, 0, 5) as $task): ?>
                                <div style="padding: 10px; background: #f8f9fa; border-radius: 8px; font-size: 13px;">
                                    <div style="font-weight: 600; color: #435334; margin-bottom: 3px;">
                                        <?php echo htmlspecialchars($task['task_title']); ?>
                                    </div>
                                    <div style="color: #666; font-size: 11px;">
                                        <?php echo date('M d, H:i', strtotime($task['completed_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>