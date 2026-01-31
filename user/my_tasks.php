<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';
require_once '../config/languages.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['user_id'];
$employee_name = $_SESSION['full_name'] ?? 'Employee';

$employee = getOne("SELECT e.*, a.area_name 
                    FROM employees e 
                    LEFT JOIN areas a ON e.area_id = a.area_id 
                    WHERE e.employee_id = ?", 
                    [$employee_id]);


$current_page = 'tasks';

$filter = $_GET['filter'] ?? 'active';

$query = "SELECT t.*, 
          a.area_name,
          b.bin_code,
          b.location_details as bin_location,
          b.current_fill_level,
          b.gps_latitude,
          b.gps_longitude
          FROM tasks t
          LEFT JOIN areas a ON t.area_id = a.area_id
          LEFT JOIN bins b ON t.triggered_by_bin = b.bin_id
          WHERE t.assigned_to = ?";

$params = [$employee_id];

if ($filter === 'today') {
    $query .= " AND DATE(t.scheduled_date) = CURDATE()";
    $query .= " ORDER BY t.created_at DESC";
} elseif ($filter === 'active') {
    $query .= " AND t.status IN ('pending', 'in_progress')";
    $query .= " ORDER BY 
                FIELD(t.priority, 'urgent', 'high', 'medium', 'low'),
                FIELD(t.status, 'in_progress', 'pending'),
                t.scheduled_date ASC";
} else {
    $query .= " ORDER BY t.created_at DESC";
}

$tasks = getAll($query, $params);

$total_tasks = count($tasks);
$pending = count(array_filter($tasks, fn($t) => $t['status'] === 'pending'));
$in_progress = count(array_filter($tasks, fn($t) => $t['status'] === 'in_progress'));
$today_tasks = count(array_filter($tasks, fn($t) => date('Y-m-d', strtotime($t['scheduled_date'])) === date('Y-m-d')));

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$current_time = date('g:i A');
$current_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('my_tasks'); ?> - EcoBin</title>
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

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #435334;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-info {
            text-align: right;
        }

        .header-time {
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
            justify-content: flex-end;
        }

        .header-date {
            font-size: 12px;
            color: #999;
            display: flex;
            align-items: center;
            gap: 5px;
            justify-content: flex-end;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card .icon {
            font-size: 32px;
            color: #435334;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: 700;
            color: #435334;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card.today .value {
            color: #435334;
        }

        .stat-card.progress .value {
            color: #435334;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
        }

        .filter-tab {
            flex: 1;
            padding: 12px 20px;
            border: 2px solid #CEDEBD;
            background: white;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            color: #435334;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .filter-tab:hover {
            border-color: #435334;
            background: #FAF1E4;
        }

        .filter-tab.active {
            background: #435334;
            color: white;
            border-color: #435334;
        }

        .tasks-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .tasks-header {
            padding: 25px;
            border-bottom: 2px solid #f0f0f0;
        }

        .tasks-header h3 {
            color: #435334;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .task-card {
            padding: 25px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }

        .task-card:hover {
            background: #fafafa;
        }

        .task-card:last-child {
            border-bottom: none;
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .task-title {
            font-size: 18px;
            font-weight: 600;
            color: #435334;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .task-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .priority-urgent { background: #fee; color: #c00; }
        .priority-high { background: #fff3cd; color: #856404; }
        .priority-medium { background: #d1ecf1; color: #0c5460; }
        .priority-low { background: #d4edda; color: #155724; }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-in_progress { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }

        .auto-badge {
            background: #e8eaf6;
            color: #3f51b5;
        }

        .task-details {
            font-size: 14px;
            color: #666;
            line-height: 1.8;
            margin-bottom: 20px;
        }

        .task-details > div {
            margin-bottom: 8px;
            display: flex;
            align-items: flex-start;
            gap: 5px;
        }

        .task-details strong {
            color: #435334;
            margin-right: 5px;
        }

        .task-description {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 12px;
            color: #555;
            border-left: 3px solid #CEDEBD;
        }

        .task-actions {
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
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-complete {
            background: #27ae60;
            color: white;
            flex: 1;
        }

        .btn-complete:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 20px;
            color: #CEDEBD;
        }

        .empty-state h3 {
            color: #435334;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
            font-size: 15px;
        }

        .complete-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .complete-modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
        }

        .modal-content h3 {
            color: #435334;
            font-size: 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-content textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #CEDEBD;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            min-height: 120px;
            margin-bottom: 20px;
            resize: vertical;
        }

        .modal-content textarea:focus {
            outline: none;
            border-color: #435334;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #666;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        .map-link {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .map-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 80px 15px 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .header-info {
                text-align: left;
            }

            .header-time,
            .header-date {
                font-size: 12px;
            }

            .alert {
                padding: 12px 15px;
                font-size: 13px;
            }

            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
            }

            .stat-card {
                padding: 20px 15px;
            }

            .stat-card .icon {
                font-size: 28px;
                margin-bottom: 8px;
            }

            .stat-card .value {
                font-size: 28px;
            }

            .stat-card .label {
                font-size: 11px;
            }

            .filter-section {
                padding: 15px;
            }

            .filter-tabs {
                flex-direction: column;
                gap: 8px;
            }

            .filter-tab {
                width: 100%;
                padding: 12px 16px;
                font-size: 13px;
            }

            .tasks-container {
                border-radius: 12px;
            }

            .tasks-header {
                padding: 20px;
            }

            .tasks-header h3 {
                font-size: 16px;
            }

            .task-card {
                padding: 20px;
            }

            .task-title {
                font-size: 16px;
            }

            .task-badges {
                gap: 6px;
            }

            .badge {
                padding: 5px 10px;
                font-size: 10px;
            }

            .task-details {
                font-size: 13px;
            }

            .task-description {
                padding: 10px;
                font-size: 13px;
            }

            .task-header {
                flex-direction: column;
            }

            .task-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                padding: 12px 20px;
            }

            .modal-content {
                padding: 25px;
                margin: 10px;
            }

            .modal-content h3 {
                font-size: 20px;
            }

            .modal-content p {
                font-size: 13px;
            }

            .modal-actions {
                flex-direction: column;
            }

            .empty-state {
                padding: 40px 15px;
            }

            .empty-state .icon {
                font-size: 48px;
            }

            .empty-state h3 {
                font-size: 20px;
            }

            .empty-state p {
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 70px 10px 15px;
            }

            .page-header h1 {
                font-size: 20px;
            }

            .header-time,
            .header-date {
                font-size: 11px;
            }

            .alert {
                padding: 10px 12px;
                font-size: 12px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .stat-card {
                padding: 18px;
            }

            .stat-card .icon {
                font-size: 24px;
                margin-bottom: 8px;
            }

            .stat-card .value {
                font-size: 24px;
            }

            .stat-card .label {
                font-size: 10px;
            }

            .filter-section {
                padding: 12px;
                margin-bottom: 15px;
            }

            .filter-tabs {
                gap: 8px;
            }

            .filter-tab {
                padding: 10px 12px;
                font-size: 12px;
            }

            .tasks-header {
                padding: 15px;
            }

            .tasks-header h3 {
                font-size: 14px;
            }

            .task-card {
                padding: 15px;
            }

            .task-title {
                font-size: 15px;
                margin-bottom: 8px;
            }

            .task-badges {
                gap: 5px;
                margin-bottom: 12px;
            }

            .badge {
                padding: 4px 8px;
                font-size: 9px;
            }

            .task-description {
                padding: 10px;
                font-size: 12px;
                margin-bottom: 10px;
            }

            .task-details {
                font-size: 12px;
                line-height: 1.6;
                margin-bottom: 15px;
            }

            .task-details > div {
                margin-bottom: 6px;
            }

            .task-details i {
                font-size: 12px;
            }

            .map-link {
                font-size: 12px;
            }

            .btn {
                padding: 12px 18px;
                font-size: 13px;
            }

            .modal-content {
                padding: 20px;
                max-width: calc(100% - 20px);
            }

            .modal-content h3 {
                font-size: 18px;
                margin-bottom: 15px;
            }

            .modal-content p {
                font-size: 12px;
                margin-bottom: 15px;
            }

            .modal-content textarea {
                padding: 12px;
                font-size: 13px;
                min-height: 100px;
                margin-bottom: 15px;
            }

            .modal-actions {
                gap: 8px;
            }

            .modal-actions .btn {
                padding: 10px 16px;
                font-size: 13px;
            }

            .empty-state {
                padding: 30px 10px;
            }

            .empty-state .icon {
                font-size: 40px;
                margin-bottom: 15px;
            }

            .empty-state h3 {
                font-size: 18px;
                margin-bottom: 8px;
            }

            .empty-state p {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>
                <?php echo t('my_tasks'); ?>
            </h1>
            <div class="header-info">
                <div class="header-time">
                    <i class="fa-solid fa-clock"></i>
                    <?php echo $current_time; ?>
                </div>
                <div class="header-date">
                    <i class="fa-solid fa-calendar"></i>
                    <?php echo $current_date; ?>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card today">
                <div class="icon"><i class="fa-solid fa-calendar-day"></i></div>
                <div class="value"><?php echo $today_tasks; ?></div>
                <div class="label"><?php echo t('todays_tasks'); ?></div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-hourglass-half"></i></div>
                <div class="value"><?php echo $pending; ?></div>
                <div class="label"><?php echo t('pending'); ?></div>
            </div>
            <div class="stat-card progress">
                <div class="icon"><i class="fa-solid fa-spinner"></i></div>
                <div class="value"><?php echo $in_progress; ?></div>
                <div class="label"><?php echo t('in_progress'); ?></div>
            </div>
        </div>

        <div class="filter-section">
            <div class="filter-tabs">
                <a href="?filter=active" class="filter-tab <?php echo $filter === 'active' ? 'active' : ''; ?>">
                    <?php echo t('active_tasks'); ?>
                </a>
                <a href="?filter=today" class="filter-tab <?php echo $filter === 'today' ? 'active' : ''; ?>">
                    <?php echo t('today'); ?>
                </a>
                <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <?php echo t('tasks_history'); ?>
                </a>
            </div>
        </div>

        <div class="tasks-container">
            <div class="tasks-header">
                <h3>
                    <?php echo t('task_list'); ?> (<?php echo count($tasks); ?> <?php echo t('tasks'); ?>)
                </h3>
            </div>

            <?php if (empty($tasks)): ?>
                <div class="empty-state">
                    <h3><?php echo t('all_clear'); ?>!</h3>
                    <p>
    <?php 
    if ($filter === 'active') {
        echo t('no_active_tasks');
    } elseif ($filter === 'today') {
        echo t('no_today_tasks');
    } else {
        echo t('no_tasks_found');
    }
    ?>
</p>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <div class="task-card">
                        <div class="task-title">
                            <i class="fa-solid fa-check-circle"></i>
                            <?php echo htmlspecialchars($task['task_title']); ?>
                        </div>

                        <div class="task-badges">
                            <span class="badge priority-<?php echo $task['priority']; ?>">
                                <i class="fa-solid fa-flag"></i>
                                <?php echo strtoupper($task['priority']); ?>
                            </span>
                            <span class="badge status-<?php echo $task['status']; ?>">
                                <?php 
                                if ($task['status'] === 'pending') {
                                    echo '<i class="fa-solid fa-hourglass-half"></i>';
                                } elseif ($task['status'] === 'in_progress') {
                                    echo '<i class="fa-solid fa-spinner"></i>';
                                } else {
                                    echo '<i class="fa-solid fa-check"></i>';
                                }
                                ?>
                                <?php echo strtoupper(str_replace('_', ' ', $task['status'])); ?>
                            </span>
                            <?php if ($task['is_auto_generated']): ?>
                                <span class="badge auto-badge">
                                    <i class="fa-solid fa-robot"></i>
                                    <?php echo t('auto'); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($task['description']): ?>
                            <div class="task-description">
                                <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                            </div>
                        <?php endif; ?>

                        <div class="task-details">
                            <div>
                                <i class="fa-solid fa-tag"></i>
                                <strong><?php echo t('type'); ?>:</strong> <?php echo ucfirst($task['task_type']); ?>
                            </div>
                            <div>
                                <i class="fa-solid fa-map-marker-alt"></i>
                                <strong><?php echo t('area'); ?>:</strong> <?php echo htmlspecialchars($task['area_name'] ?? 'N/A'); ?>
                            </div>
                            
                            <?php if ($task['bin_code']): ?>
                                <div>
                                    <i class="fa-solid fa-trash"></i>
                                    <strong><?php echo t('bin'); ?>:</strong> <?php echo htmlspecialchars($task['bin_code']); ?>
                                    <?php if ($task['current_fill_level']): ?>
                                        (<?php echo $task['current_fill_level']; ?>% <?php echo t('full'); ?>)
                                    <?php endif; ?>
                                </div>
                                <?php if ($task['bin_location']): ?>
                                    <div>
                                        <i class="fa-solid fa-location-dot"></i>
                                        <strong><?php echo t('location'); ?>:</strong> <?php echo htmlspecialchars($task['bin_location']); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <div>
                                <i class="fa-solid fa-calendar-check"></i>
                                <strong><?php echo t('due'); ?>:</strong> 
                                <?php 
                                $due_date = date('M j, Y', strtotime($task['scheduled_date']));
                                $is_today = date('Y-m-d', strtotime($task['scheduled_date'])) === date('Y-m-d');
                                $is_overdue = strtotime($task['scheduled_date']) < time() && $task['status'] !== 'completed';
                                
                                if ($is_today) {
                                    echo "<span style='color: #3498db; font-weight: 600;'>" . t('today') . "</span>";
                                } elseif ($is_overdue) {
                                    echo "<span style='color: #e74c3c; font-weight: 600;'>$due_date (" . t('overdue') . ")</span>";
                                } else {
                                    echo $due_date;
                                }
                                
                                if ($task['scheduled_time']) {
                                    echo ' ' . t('at') . ' ' . date('g:i A', strtotime($task['scheduled_time']));
                                }
                                ?>
                            </div>

                            <?php if ($task['gps_latitude'] && $task['gps_longitude']): ?>
                                <div>
                                    <a href="https://www.google.com/maps?q=<?php echo $task['gps_latitude']; ?>,<?php echo $task['gps_longitude']; ?>" 
                                       target="_blank" 
                                       class="map-link">
                                        <i class="fa-solid fa-map-location-dot"></i>
                                        <?php echo t('view_on_map'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="task-actions">
                            <?php if ($task['status'] === 'pending' || $task['status'] === 'in_progress'): ?>
                                <button onclick="openCompleteModal(<?php echo $task['task_id']; ?>)" 
                                        class="btn btn-complete">
                                    <i class="fa-solid fa-check"></i>
                                    <?php echo t('complete_task'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="completeModal" class="complete-modal">
        <div class="modal-content">
            <h3>
                <i class="fa-solid fa-check-circle"></i>
                <?php echo t('complete_task'); ?>
            </h3>
            <p style="color: #666; margin-bottom: 20px;"><?php echo t('add_completion_notes'); ?></p>
            <form method="POST" action="task_actions.php">
                <input type="hidden" name="action" value="employee_complete">
                <input type="hidden" name="task_id" id="modalTaskId">
                <textarea name="completion_notes" placeholder="<?php echo t('any_issues_placeholder'); ?>"></textarea>
                <div class="modal-actions">
                    <button type="button" onclick="closeCompleteModal()" class="btn btn-secondary" style="flex: 1;">
                        <i class="fa-solid fa-xmark"></i>
                        <?php echo t('cancel'); ?>
                    </button>
                    <button type="submit" class="btn btn-complete" style="flex: 1;">
                        <i class="fa-solid fa-check"></i>
                        <?php echo t('complete_task'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCompleteModal(taskId) {
            document.getElementById('modalTaskId').value = taskId;
            document.getElementById('completeModal').classList.add('active');
        }

        function closeCompleteModal() {
            document.getElementById('completeModal').classList.remove('active');
        }

        document.getElementById('completeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCompleteModal();
            }
        });
    </script>
</body>
</html>