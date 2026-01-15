<?php
/**
 * Task Details View
 * View complete task information including timeline and notes
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$task_id = $_GET['id'] ?? 0;

// Get task details with related information
$task = getOne("SELECT t.*, 
                e.full_name as employee_name,
                e.username as employee_username,
                e.phone_number as employee_phone,
                a.area_name,
                a.block,
                b.bin_code,
                b.location_details as bin_location,
                b.current_fill_level,
                b.gps_latitude,
                b.gps_longitude,
                creator.full_name as creator_name
                FROM tasks t
                LEFT JOIN employees e ON t.assigned_to = e.employee_id
                LEFT JOIN areas a ON t.area_id = a.area_id
                LEFT JOIN bins b ON t.triggered_by_bin = b.bin_id
                LEFT JOIN admins creator ON t.created_by = creator.admin_id
                WHERE t.task_id = ?", [$task_id]);

if (!$task) {
    $_SESSION['error'] = "Task not found";
    header("Location: tasks.php");
    exit;
}

// Get task bins (for collection tasks with multiple bins)
$task_bins = getAll("SELECT tb.*, b.bin_code, b.location_details, b.current_fill_level
                     FROM task_bins tb
                     LEFT JOIN bins b ON tb.bin_id = b.bin_id
                     WHERE tb.task_id = ?", [$task_id]);

// Calculate task duration
$duration = null;
if ($task['started_at'] && $task['completed_at']) {
    $start = new DateTime($task['started_at']);
    $end = new DateTime($task['completed_at']);
    $interval = $start->diff($end);
    
    $duration = '';
    if ($interval->h > 0) $duration .= $interval->h . ' hrs ';
    $duration .= $interval->i . ' mins';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Details - EcoBin</title>
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
            align-items: flex-start;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #435334;
            margin-bottom: 10px;
        }

        .header-badges {
            display: flex;
            gap: 10px;
            margin-top: 10px;
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

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .card h2 {
            font-size: 20px;
            color: #435334;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 14px;
        }

        .info-value {
            color: #435334;
            font-size: 14px;
            font-weight: 600;
            text-align: right;
        }

        .status-badge {
            padding: 6px 14px;
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

        .priority-badge {
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
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

        .type-badge {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
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
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            background: #e8eaf6;
            color: #3f51b5;
        }

        .timeline {
            position: relative;
            padding-left: 40px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 30px;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -31px;
            top: 8px;
            width: 2px;
            height: 100%;
            background: #e0e0e0;
        }

        .timeline-item:last-child::before {
            display: none;
        }

        .timeline-icon {
            position: absolute;
            left: -40px;
            top: 0;
            width: 20px;
            height: 20px;
            background: white;
            border: 3px solid #435334;
            border-radius: 50%;
        }

        .timeline-icon.completed {
            background: #27ae60;
            border-color: #27ae60;
        }

        .timeline-content h4 {
            color: #435334;
            font-size: 15px;
            margin-bottom: 5px;
        }

        .timeline-content p {
            color: #666;
            font-size: 13px;
            line-height: 1.5;
        }

        .timeline-date {
            color: #999;
            font-size: 12px;
            margin-top: 5px;
        }

        .note-box {
            background: #f8f9fa;
            border-left: 4px solid #435334;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .note-box p {
            color: #555;
            line-height: 1.6;
            margin: 0;
        }

        .map-container {
            width: 100%;
            height: 250px;
            background: #e0e0e0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            margin-top: 15px;
        }

        .bin-list {
            list-style: none;
        }

        .bin-item {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .bin-item strong {
            color: #435334;
        }

        .bin-item small {
            color: #666;
            display: block;
            margin-top: 5px;
        }

        .stats-mini {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }

        .stat-mini {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-mini .value {
            font-size: 24px;
            font-weight: 700;
            color: #435334;
        }

        .stat-mini .label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="../assets/images/logo.png" alt="EcoBin Logo">
        </div>

        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item">
                <span class="icon">📊</span>
                <span>Dashboard</span>
            </a>
            <a href="users.php" class="nav-item">
                <span class="icon">👥</span>
                <span>User Management</span>
            </a>
            <a href="bins.php" class="nav-item">
                <span class="icon">🗑️</span>
                <span>Bin Monitoring</span>
            </a>
            <a href="attendance.php" class="nav-item">
                <span class="icon">✅</span>
                <span>Attendance</span>
            </a>
            <a href="tasks.php" class="nav-item active">
                <span class="icon">📋</span>
                <span>Tasks</span>
            </a>
            <a href="performance.php" class="nav-item">
                <span class="icon">📈</span>
                <span>Performance</span>
            </a>
            <a href="analytics.php" class="nav-item">
                <span class="icon">📊</span>
                <span>Analytics</span>
            </a>
            <a href="inventory.php" class="nav-item">
                <span class="icon">📦</span>
                <span>Inventory</span>
            </a>
            <a href="leave.php" class="nav-item">
                <span class="icon">📅</span>
                <span>Leave</span>
            </a>
            <a href="maintenance.php" class="nav-item">
                <span class="icon">🔧</span>
                <span>Maintenance</span>
            </a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1><?php echo htmlspecialchars($task['task_title']); ?></h1>
                <div class="header-badges">
                    <span class="type-badge type-<?php echo $task['task_type']; ?>">
                        <?php echo ucfirst($task['task_type']); ?>
                    </span>
                    <span class="priority-badge priority-<?php echo $task['priority']; ?>">
                        <?php echo ucfirst($task['priority']); ?> Priority
                    </span>
                    <span class="status-badge status-<?php echo $task['status']; ?>">
                        <?php echo ucwords(str_replace('_', ' ', $task['status'])); ?>
                    </span>
                    <?php if ($task['is_auto_generated']): ?>
                        <span class="auto-badge">🤖 Auto-Generated</span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <?php if ($task['status'] !== 'completed' && $task['status'] !== 'cancelled'): ?>
                    <a href="task_edit.php?id=<?php echo $task_id; ?>" class="btn btn-primary">
                        ✏️ Edit Task
                    </a>
                <?php endif; ?>
                <a href="tasks.php" class="btn btn-secondary">
                    ← Back to Tasks
                </a>
            </div>
        </div>

        <div class="content-grid">
            <div>
                <!-- Task Details Card -->
                <div class="card">
                    <h2>📋 Task Information</h2>
                    
                    <?php if ($task['description']): ?>
                        <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                            <p style="color: #555; line-height: 1.6; margin: 0;">
                                <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="info-row">
                        <span class="info-label">Task ID</span>
                        <span class="info-value">#<?php echo $task['task_id']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Type</span>
                        <span class="info-value"><?php echo ucfirst($task['task_type']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Assigned To</span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($task['employee_name'] ?? 'Unassigned'); ?>
                            <?php if ($task['employee_phone']): ?>
                                <br><small style="color: #666;"><?php echo htmlspecialchars($task['employee_phone']); ?></small>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Area</span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($task['area_name'] ?? 'N/A'); ?>
                            <?php if ($task['block']): ?>
                                (Block <?php echo htmlspecialchars($task['block']); ?>)
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($task['bin_code']): ?>
                        <div class="info-row">
                            <span class="info-label">Bin</span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($task['bin_code']); ?>
                                <?php if ($task['current_fill_level']): ?>
                                    <br><small style="color: #e67e22;"><?php echo $task['current_fill_level']; ?>% full</small>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Scheduled Date</span>
                        <span class="info-value">
                            <?php echo date('F j, Y', strtotime($task['scheduled_date'])); ?>
                            <?php if ($task['scheduled_time']): ?>
                                at <?php echo date('g:i A', strtotime($task['scheduled_time'])); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($duration): ?>
                        <div class="info-row">
                            <span class="info-label">Duration</span>
                            <span class="info-value"><?php echo $duration; ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Created By</span>
                        <span class="info-value">
                            <?php echo $task['created_by'] == 0 ? 'IoT System' : htmlspecialchars($task['creator_name'] ?? 'Admin'); ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Created At</span>
                        <span class="info-value"><?php echo date('F j, Y g:i A', strtotime($task['created_at'])); ?></span>
                    </div>
                </div>

                <!-- Task Timeline -->
                <div class="card">
                    <h2>⏱️ Task Timeline</h2>
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-icon completed"></div>
                            <div class="timeline-content">
                                <h4>Task Created</h4>
                                <p>
                                    <?php if ($task['is_auto_generated']): ?>
                                        Automatically generated by IoT system
                                    <?php else: ?>
                                        Manually created by <?php echo htmlspecialchars($task['creator_name'] ?? 'Admin'); ?>
                                    <?php endif; ?>
                                </p>
                                <div class="timeline-date">
                                    <?php echo date('F j, Y g:i A', strtotime($task['created_at'])); ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($task['started_at']): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon completed"></div>
                                <div class="timeline-content">
                                    <h4>Task Started</h4>
                                    <p>Employee began working on this task</p>
                                    <div class="timeline-date">
                                        <?php echo date('F j, Y g:i A', strtotime($task['started_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($task['completed_at']): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon completed"></div>
                                <div class="timeline-content">
                                    <h4>Task Completed</h4>
                                    <p>Task marked as completed</p>
                                    <div class="timeline-date">
                                        <?php echo date('F j, Y g:i A', strtotime($task['completed_at'])); ?>
                                    </div>
                                    <?php if ($task['completion_notes']): ?>
                                        <div class="note-box">
                                            <strong>Employee Notes:</strong>
                                            <p><?php echo nl2br(htmlspecialchars($task['completion_notes'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($task['status'] === 'pending'): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon"></div>
                                <div class="timeline-content">
                                    <h4>Awaiting Start</h4>
                                    <p>Task is pending and not yet started</p>
                                </div>
                            </div>
                        <?php elseif ($task['status'] === 'in_progress' && !$task['completed_at']): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon"></div>
                                <div class="timeline-content">
                                    <h4>In Progress</h4>
                                    <p>Employee is currently working on this task</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bin Collection Details -->
                <?php if (!empty($task_bins)): ?>
                    <div class="card">
                        <h2>🗑️ Bins for Collection</h2>
                        <ul class="bin-list">
                            <?php foreach ($task_bins as $tb): ?>
                                <li class="bin-item">
                                    <strong><?php echo htmlspecialchars($tb['bin_code']); ?></strong>
                                    <small><?php echo htmlspecialchars($tb['location_details']); ?></small>
                                    <?php if ($tb['weight_collected']): ?>
                                        <small style="color: #27ae60;">✓ Collected: <?php echo $tb['weight_collected']; ?> kg</small>
                                    <?php elseif ($tb['collection_status'] === 'skipped'): ?>
                                        <small style="color: #e74c3c;">⊗ Skipped</small>
                                    <?php else: ?>
                                        <small style="color: #f39c12;">⏳ Pending</small>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <!-- Quick Stats -->
                <?php if ($task['status'] === 'completed' && !empty($task_bins)): ?>
                    <div class="card">
                        <h2>📊 Collection Stats</h2>
                        <?php
                        $total_bins = count($task_bins);
                        $collected = count(array_filter($task_bins, fn($tb) => $tb['collection_status'] === 'collected'));
                        $total_weight = array_sum(array_column($task_bins, 'weight_collected'));
                        ?>
                        <div class="stats-mini">
                            <div class="stat-mini">
                                <div class="value"><?php echo $collected; ?>/<?php echo $total_bins; ?></div>
                                <div class="label">Bins Collected</div>
                            </div>
                            <div class="stat-mini">
                                <div class="value"><?php echo number_format($total_weight, 1); ?></div>
                                <div class="label">KG Collected</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- GPS Location -->
                <?php if ($task['gps_latitude'] && $task['gps_longitude']): ?>
                    <div class="card">
                        <h2>📍 Bin Location</h2>
                        <div style="margin-bottom: 10px;">
                            <strong>GPS Coordinates:</strong><br>
                            <small style="color: #666;">
                                Lat: <?php echo number_format($task['gps_latitude'], 6); ?><br>
                                Lng: <?php echo number_format($task['gps_longitude'], 6); ?>
                            </small>
                        </div>
                        <div class="map-container">
                            <div style="text-align: center;">
                                <div style="font-size: 48px; margin-bottom: 10px;">📍</div>
                                <div style="font-size: 14px;">GPS Location Available</div>
                                <div style="font-size: 12px; color: #999; margin-top: 5px;">
                                    <a href="https://www.google.com/maps?q=<?php echo $task['gps_latitude']; ?>,<?php echo $task['gps_longitude']; ?>" 
                                       target="_blank" 
                                       style="color: #435334; text-decoration: underline;">
                                        View on Google Maps
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <?php if ($task['status'] !== 'completed' && $task['status'] !== 'cancelled'): ?>
                    <div class="card">
                        <h2>⚡ Quick Actions</h2>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <a href="task_edit.php?id=<?php echo $task_id; ?>" class="btn btn-primary" style="width: 100%; justify-content: center;">
                                ✏️ Edit Task
                            </a>
                            <?php if ($task['status'] === 'pending'): ?>
                                <form method="POST" action="task_actions.php" style="width: 100%;">
                                    <input type="hidden" name="action" value="start">
                                    <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                                    <button type="submit" class="btn btn-secondary" style="width: 100%;">
                                        ▶️ Start Task
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($task['status'] === 'in_progress'): ?>
                                <a href="task_edit.php?id=<?php echo $task_id; ?>" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                                    ✓ Mark Complete
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>