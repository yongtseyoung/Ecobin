<?php
/**
 * Employee Dashboard
 * Main dashboard for employees to view their tasks, performance, and stats
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';
require_once '../config/languages.php';
require_once '../config/performance_calculator.php';

// Check authentication - employees only
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

// Set current page for sidebar
$current_page = 'dashboard';

$employee_id = $_SESSION['user_id'];
$employee_name = $_SESSION['full_name'] ?? 'Employee';

// Get employee details and load language preference
$employee = getOne("SELECT e.*, a.area_name 
                    FROM employees e 
                    LEFT JOIN areas a ON e.area_id = a.area_id 
                    WHERE e.employee_id = ?", 
                    [$employee_id]);

// Load language preference
$_SESSION['language'] = $employee['language'] ?? 'en';

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

// Calculate performance score using the same PerformanceCalculator as My Performance page
$calculator = new PerformanceCalculator($employee_id, 'current_month');
$performance_metrics = $calculator->calculatePerformance();
$performance_score = round($performance_metrics['performance_score'], 1);

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
<html lang="<?php echo $_SESSION['language']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('dashboard'); ?> - EcoBin</title>

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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .welcome-banner p {
            opacity: 0.9;
            font-size: 16px;
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
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-left: 4px solid #dc3545;
        }

        .icon-main {
            color: #435334;
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

        .stat-card.pending .value { color: #435334; }
        .stat-card.progress .value { color: #435334; }
        .stat-card.completed .value { color: #435334; }
        .stat-card.performance .value { color: #435334; }

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
            align-items: center;
            flex-wrap: wrap;
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
            display: inline-flex;
            align-items: center;
            gap: 5px;
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

@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 80px 15px 20px;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }

    .stat-card {
        padding: 20px 15px;
    }

    .stat-card .icon {
        font-size: 32px;
        margin-bottom: 10px;
    }

    .stat-card .value {
        font-size: 28px;
    }

    .stat-card .label {
        font-size: 11px;
    }

    .welcome-banner {
        padding: 20px;
    }

    .welcome-banner h1 {
        font-size: 22px;
    }

    .content-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .card {
        padding: 20px;
    }

    .card h2 {
        font-size: 18px;
    }

    .task-item {
        padding: 12px;
    }

    .task-title {
        font-size: 14px;
    }

    .task-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .quick-stat-item {
        padding: 12px;
    }

    .quick-stat-item .label {
        font-size: 12px;
    }

    .quick-stat-item .value {
        font-size: 18px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .welcome-banner h1 {
        font-size: 20px;
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }

    .task-header {
        flex-direction: column;
        gap: 10px;
    }

    .btn-small {
        width: 100%;
        justify-content: center;
    }
}
</style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="welcome-banner">
            <h1>
                <?php echo t('welcome_back'); ?>, <?php echo htmlspecialchars(explode(' ', $employee_name)[0]); ?>!
            </h1>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="icon"><i class="fa-solid fa-hourglass-half" style="color: #435334;"></i></div>
                <div class="value"><?php echo $pending_tasks; ?></div>
                <div class="label"><?php echo t('pending_tasks'); ?></div>
            </div>

            <div class="stat-card completed">
                <div class="icon"><i class="fa-solid fa-circle-check" style="color: #435334;"></i></div>
                <div class="value"><?php echo $completed_today; ?></div>
                <div class="label"><?php echo t('completed_today'); ?></div>
            </div>

            <div class="stat-card completed">
                <div class="icon"><i class="fa-solid fa-chart-column" style="color: #435334;"></i></div>
                <div class="value"><?php echo $completed_month; ?></div>
                <div class="label"><?php echo t('completed_this_month'); ?></div>
            </div>

            <div class="stat-card performance">
                <div class="icon"><i class="fa-solid fa-star" style="color: #435334;"></i></div>
                <div class="value"><?php echo $performance_score; ?>%</div>
                <div class="label"><?php echo t('performance'); ?></div>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <h2>
                    <?php echo t('todays_tasks'); ?> (<?php echo date('M d, Y'); ?>)
                </h2>
                <?php if (empty($todays_tasks)): ?>
                    <div class="empty-state">
                        <h3><?php echo t('no_tasks_today'); ?>!</h3>
                        <p><?php echo t('enjoy_your_day'); ?></p>
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
                                                <i class="fa-solid fa-trash-can"></i>
                                                <?php echo htmlspecialchars($task['bin_code']); ?> - <?php echo htmlspecialchars($task['location_details']); ?>
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
                                    <span>
                                        <i class="fa-solid fa-clipboard-list"></i>
                                        <?php echo ucfirst($task['task_type']); ?>
                                    </span>
                                    <span class="badge status-<?php echo $task['status']; ?>">
                                        <?php echo t($task['status']); ?>
                                    </span>
                                    <?php if ($task['status'] === 'pending'): ?>
                                        <a href="my_tasks.php" class="btn btn-primary btn-small">
                                            <?php echo t('start_task'); ?> <i class="fa-solid fa-arrow-right"></i>
                                        </a>
                                    <?php elseif ($task['status'] === 'in_progress'): ?>
                                        <a href="my_tasks.php" class="btn btn-primary btn-small">
                                            <?php echo t('complete_task'); ?> <i class="fa-solid fa-arrow-right"></i>
                                        </a>
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
                        <h2>
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <?php echo t('urgent_tasks'); ?>
                        </h2>
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
                                    <?php echo t('view_all'); ?> (<?php echo count($urgent_tasks); ?>)
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <h2>
                        <?php echo t('quick_stats'); ?>
                    </h2>
                    <div class="quick-stats">
                        <div class="quick-stat-item">
                            <span class="label"><?php echo t('present_days_month'); ?></span>
                            <span class="value"><?php echo $present_days; ?></span>
                        </div>
                        <div class="quick-stat-item">
                            <span class="label"><?php echo t('late_arrivals'); ?></span>
                            <span class="value"><?php echo $late_days; ?></span>
                        </div>
                        <div class="quick-stat-item">
                            <span class="label"><?php echo t('total_tasks'); ?></span>
                            <span class="value"><?php echo $total_tasks; ?></span>
                        </div>
                        <div class="quick-stat-item">
                            <span class="label"><?php echo t('completion_rate'); ?></span>
                            <span class="value"><?php echo round($performance_metrics['completion_rate'], 1); ?>%</span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($recent_completed)): ?>
                    <div class="card" style="margin-top: 20px;">
                        <h2>
                            <?php echo t('recently_completed'); ?>
                        </h2>
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