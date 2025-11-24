<?php
/**
 * EcoBin Admin Dashboard
 * Main overview and summary of all system modules
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Set current page for sidebar
$current_page = 'dashboard';

// Get admin info
$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'] ?? 'Admin';

// Get real-time statistics
try {
    // User Stats
    $total_admins = getOne("SELECT COUNT(*) as count FROM admins WHERE status = 'active'")['count'];
    $total_employees = getOne("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")['count'];
    $total_users = $total_admins + $total_employees;
    
    // Bin Stats
    $total_bins = getOne("SELECT COUNT(*) as count FROM bins")['count'];
    $online_bins = getOne("SELECT COUNT(*) as count FROM bins WHERE status = 'online'")['count'] ?? 0;
    $full_bins = getOne("SELECT COUNT(*) as count FROM bins WHERE current_fill_level >= 80")['count'] ?? 0;
    
    // Task Stats
    $tasks_today = getOne("SELECT COUNT(*) as count FROM tasks WHERE DATE(scheduled_date) = CURDATE() AND status = 'completed'")['count'];
    $pending_tasks = getOne("SELECT COUNT(*) as count FROM tasks WHERE status = 'pending'")['count'];
    
    // Alert Stats
    $maintenance_issues = getOne("SELECT COUNT(*) as count FROM maintenance_reports WHERE status = 'pending'")['count'] ?? 0;
    $leave_requests = getOne("SELECT COUNT(*) as count FROM leave_applications WHERE status = 'pending'")['count'] ?? 0;
    $low_battery = getOne("SELECT COUNT(*) as count FROM bins WHERE battery_level < 20")['count'] ?? 0;
    $total_alerts = $maintenance_issues + $leave_requests + $low_battery;
    
    // Recent Activities
    $recent_tasks = getAll("
        SELECT t.*, e.full_name as employee_name, a.area_name 
        FROM tasks t 
        LEFT JOIN employees e ON t.assigned_to = e.employee_id 
        LEFT JOIN areas a ON t.area_id = a.area_id 
        ORDER BY t.created_at DESC 
        LIMIT 5
    ");
    
    // Today's Attendance
    $present_today = getOne("SELECT COUNT(DISTINCT employee_id) as count FROM attendance WHERE DATE(attendance_date) = CURDATE() AND check_in_time IS NOT NULL")['count'] ?? 0;
    
    // Employee Performance (this month)
    $top_performers = getAll("
        SELECT e.full_name, COUNT(t.task_id) as completed_tasks 
        FROM employees e 
        LEFT JOIN tasks t ON e.employee_id = t.assigned_to AND t.status = 'completed' AND MONTH(t.completed_at) = MONTH(CURDATE())
        GROUP BY e.employee_id 
        ORDER BY completed_tasks DESC 
        LIMIT 3
    ");
    
} catch (Exception $e) {
    // Default values if queries fail
    $total_admins = $total_admins ?? 0;
    $total_employees = $total_employees ?? 0;
    $total_users = $total_admins + $total_employees;
    $total_bins = 0;
    $online_bins = 0;
    $tasks_today = 0;
    $total_alerts = 0;
    $recent_tasks = [];
    $present_today = 0;
    $top_performers = [];
}

// Get current date info
$current_time = date('g:i A');
$current_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EcoBin Admin</title>
    
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

        /* Header */
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

        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, #CEDEBD, #9db89a);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            color: #435334;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .welcome-card h2 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .welcome-card p {
            font-size: 15px;
            opacity: 0.9;
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
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 36px;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: #435334;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-change {
            font-size: 12px;
            color: #27ae60;
            margin-top: 5px;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #435334;
        }

        .card-link {
            font-size: 13px;
            color: #27ae60;
            text-decoration: none;
        }

        /* Activity Item */
        .activity-item {
            padding: 15px;
            border-left: 3px solid #CEDEBD;
            margin-bottom: 15px;
            background: #fafafa;
            border-radius: 5px;
        }

        .activity-title {
            font-size: 14px;
            font-weight: 600;
            color: #435334;
            margin-bottom: 5px;
        }

        .activity-desc {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }

        .activity-time {
            font-size: 11px;
            color: #999;
        }

        /* Alert Badge */
        .alert-badge {
            background: #e74c3c;
            color: white;
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 10px;
            font-weight: 600;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .action-btn {
            padding: 15px;
            background: #CEDEBD;
            border: none;
            border-radius: 10px;
            color: #435334;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .action-btn:hover {
            background: #b8ceaa;
            transform: translateY(-2px);
        }

        /* Performance List */
        .performance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #fafafa;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .performance-name {
            font-size: 14px;
            color: #435334;
            font-weight: 500;
        }

        .performance-score {
            font-size: 16px;
            font-weight: 700;
            color: #27ae60;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <!-- Header -->
        <div class="page-header">
            <h1>Dashboard</h1>
            <div class="header-info">
                <div class="header-time"><?php echo $current_time; ?></div>
                <div class="header-date"><?php echo $current_date; ?></div>
            </div>
        </div>

        <!-- Welcome Card -->
        <div class="welcome-card">
            <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $admin_name)[0]); ?>! 👋</h2>
            <p>Here's what's happening with your waste management system today.</p>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo $total_users; ?></div>
                <div class="stat-label">Active Users</div>
                <div class="stat-change">
                    👤 <?php echo $total_admins; ?> admins + 
                    👷 <?php echo $total_employees; ?> employees
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🗑️</div>
                <div class="stat-value"><?php echo $online_bins; ?>/<?php echo $total_bins; ?></div>
                <div class="stat-label">Bins Online</div>
                <div class="stat-change"><?php echo $full_bins; ?> bins need collection</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?php echo $tasks_today; ?></div>
                <div class="stat-label">Tasks Completed Today</div>
                <div class="stat-change"><?php echo $pending_tasks; ?> pending tasks</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">⚠️</div>
                <div class="stat-value"><?php echo $total_alerts; ?></div>
                <div class="stat-label">Pending Alerts</div>
                <div class="stat-change">
                    <?php if ($total_alerts > 0): ?>
                        <span class="alert-badge">Needs Attention</span>
                    <?php else: ?>
                        All clear!
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Activity</h3>
                    <a href="tasks.php" class="card-link">View All →</a>
                </div>
                
                <?php if (empty($recent_tasks)): ?>
                    <p style="color: #999; text-align: center; padding: 20px;">No recent activities</p>
                <?php else: ?>
                    <?php foreach ($recent_tasks as $task): ?>
                        <div class="activity-item">
                            <div class="activity-title"><?php echo htmlspecialchars($task['task_title']); ?></div>
                            <div class="activity-desc">
                                Assigned to: <?php echo htmlspecialchars($task['employee_name'] ?? 'Unassigned'); ?> 
                                | Area: <?php echo htmlspecialchars($task['area_name'] ?? 'N/A'); ?>
                            </div>
                            <div class="activity-time">
                                <?php echo date('M j, g:i A', strtotime($task['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Quick Actions -->
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">Quick Actions</h3>
                    </div>
                    <div class="quick-actions">
                        <a href="task_create.php" class="action-btn">
                            <span>➕</span> New Task
                        </a>
                        <a href="user_add.php" class="action-btn">
                            <span>👤</span> Add User
                        </a>
                        <a href="maintenance.php" class="action-btn">
                            <span>⚠️</span> View Alerts
                        </a>
                        <a href="analytics.php" class="action-btn">
                            <span>📊</span> Waste Analytics
                        </a>
                    </div>
                </div>

                <!-- Top Performers -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Top Performers (This Month)</h3>
                        <a href="admin_performance_overview.php" class="card-link">View All →</a>
                    </div>
                    
                    <?php if (empty($top_performers)): ?>
                        <p style="color: #999; text-align: center; padding: 20px;">No data yet</p>
                    <?php else: ?>
                        <?php foreach ($top_performers as $performer): ?>
                            <div class="performance-item">
                                <span class="performance-name">
                                    <?php echo htmlspecialchars($performer['full_name']); ?>
                                </span>
                                <span class="performance-score">
                                    <?php echo $performer['completed_tasks']; ?> tasks
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Attendance Today -->
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">Attendance Today</h3>
                        <a href="attendance.php" class="card-link">View →</a>
                    </div>
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 48px; font-weight: 700; color: #27ae60;">
                            <?php echo $present_today; ?>/<?php echo $total_employees; ?>
                        </div>
                        <div style="color: #999; font-size: 14px; margin-top: 10px;">
                            Employees Present
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>