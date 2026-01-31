<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'dashboard';

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'] ?? 'Admin';

try {
    $total_admins = getOne("SELECT COUNT(*) as count FROM admins WHERE status = 'active'")['count'];
    $total_employees = getOne("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")['count'];
    $total_users = $total_admins + $total_employees;
    
try {
    $total_bins = getOne("SELECT COUNT(*) as count FROM bins")['count'] ?? 0;
    
    $online_bins_result = getOne("
        SELECT COUNT(DISTINCT b.bin_id) as count 
        FROM bins b
        INNER JOIN iot_devices d ON b.device_id = d.device_id
        WHERE d.last_ping IS NOT NULL 
        AND d.last_ping >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $online_bins = $online_bins_result['count'] ?? 0;
    
    $full_bins = getOne("SELECT COUNT(*) as count FROM bins WHERE current_fill_level >= 80")['count'] ?? 0;
    
} catch (Exception $e) {
    error_log("Dashboard bins query error: " . $e->getMessage());
    $total_bins = 0;
    $online_bins = 0;
    $full_bins = 0;
}
    
    $tasks_today = getOne("SELECT COUNT(*) as count FROM tasks WHERE DATE(scheduled_date) = CURDATE() AND status = 'completed'")['count'];
    $pending_tasks = getOne("SELECT COUNT(*) as count FROM tasks WHERE status = 'pending'")['count'];
    
    $maintenance_issues = getOne("SELECT COUNT(*) as count FROM maintenance_reports WHERE status = 'pending'")['count'] ?? 0;
    $leave_requests = getOne("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'")['count'] ?? 0;
    $supply_requests = getOne("SELECT COUNT(*) as count FROM supply_requests WHERE status = 'pending'")['count'] ?? 0;
    $full_bins = getOne("SELECT COUNT(*) as count FROM bins WHERE current_fill_level >= 80")['count'] ?? 0;
    $low_stock = getOne("SELECT COUNT(*) as count FROM inventory WHERE current_quantity <= minimum_quantity AND current_quantity > 0")['count'] ?? 0;
    $out_of_stock = getOne("SELECT COUNT(*) as count FROM inventory WHERE current_quantity = 0")['count'] ?? 0;
    $low_battery = getOne("SELECT COUNT(*) as count FROM bins WHERE battery_level < 20")['count'] ?? 0;
    
    $total_alerts = $maintenance_issues + $leave_requests + $supply_requests + $full_bins + $low_stock + $out_of_stock + $low_battery;
    
    $notifications = [];
    
    $leave_notifs = getAll("
        SELECT 
            'leave_request' as type,
            lr.leave_id as ref_id,
            CONCAT(e.full_name, ' requested ', lt.type_name, ' leave') as title,
            CONCAT(DATE_FORMAT(lr.start_date, '%b %d'), ' to ', DATE_FORMAT(lr.end_date, '%b %d')) as description,
            lr.created_at as timestamp,
            'medium' as priority
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.employee_id
        JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
        WHERE lr.status = 'pending'
        AND DATE(lr.created_at) = CURDATE()
        ORDER BY lr.created_at DESC
    ");
    if ($leave_notifs) $notifications = array_merge($notifications, $leave_notifs);
    
    $maintenance_notifs = getAll("
        SELECT 
            'maintenance' as type,
            mr.report_id as ref_id,
            mr.issue_title as title,
            CONCAT(
                REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                    mr.issue_category,
                    'bin_issue', 'Bin Issue'),
                    'equipment_issue', 'Equipment Issue'),
                    'facility_issue', 'Facility Issue'),
                    'safety_hazard', 'Safety Hazard'),
                    'other', 'Other'
                ),
                ' - ', 
                mr.location
            ) as description,
            mr.reported_at as timestamp,
            mr.priority
        FROM maintenance_reports mr
        WHERE mr.status = 'pending'
        AND DATE(mr.reported_at) = CURDATE()
        ORDER BY 
            FIELD(mr.priority, 'high', 'medium', 'low'),
            mr.reported_at DESC
    ");
    if ($maintenance_notifs) $notifications = array_merge($notifications, $maintenance_notifs);
    
    $bin_notifs = getAll("
        SELECT 
            'bin_full' as type,
            b.bin_id as ref_id,
            CONCAT('Bin ', b.bin_code, ' is full') as title,
            CONCAT(a.area_name, ' - Floor ', b.floor_number, ' (', ROUND(b.current_fill_level), '% full)') as description,
            b.last_updated as timestamp,
            'high' as priority
        FROM bins b
        JOIN areas a ON b.area_id = a.area_id
        WHERE b.current_fill_level >= 80
        ORDER BY b.current_fill_level DESC
        LIMIT 10
    ");
    if ($bin_notifs) $notifications = array_merge($notifications, $bin_notifs);
    
    $inventory_notifs = getAll("
        SELECT 
            'inventory_low' as type,
            i.inventory_id as ref_id,
            CONCAT(i.item_name, ' is low in stock') as title,
            CONCAT('Only ', i.current_quantity, ' ', i.unit, ' remaining (min: ', i.minimum_quantity, ')') as description,
            i.updated_at as timestamp,
            'medium' as priority
        FROM inventory i
        WHERE i.current_quantity <= i.minimum_quantity
        AND i.current_quantity > 0
        ORDER BY (i.current_quantity / NULLIF(i.minimum_quantity, 0)) ASC
        LIMIT 10
    ");
    if ($inventory_notifs) $notifications = array_merge($notifications, $inventory_notifs);
    
    $outofstock_notifs = getAll("
        SELECT 
            'inventory_out' as type,
            i.inventory_id as ref_id,
            CONCAT(i.item_name, ' is out of stock') as title,
            CONCAT(i.item_category, ' - ', COALESCE(i.storage_location, 'No location')) as description,
            i.updated_at as timestamp,
            'high' as priority
        FROM inventory i
        WHERE i.current_quantity = 0
        ORDER BY i.updated_at DESC
        LIMIT 10
    ");
    if ($outofstock_notifs) $notifications = array_merge($notifications, $outofstock_notifs);
    
    $supply_notifs = getAll("
        SELECT 
            'supply_request' as type,
            sr.request_id as ref_id,
            CONCAT(e.full_name, ' requested supplies') as title,
            CONCAT(i.item_name, ' - Qty: ', sr.quantity_requested, ' (', sr.urgency, ' urgency)') as description,
            sr.requested_at as timestamp,
            CASE 
                WHEN sr.urgency = 'high' THEN 'high'
                WHEN sr.urgency = 'medium' THEN 'medium'
                ELSE 'low'
            END as priority
        FROM supply_requests sr
        JOIN employees e ON sr.employee_id = e.employee_id
        JOIN inventory i ON sr.inventory_id = i.inventory_id
        WHERE sr.status = 'pending'
        AND DATE(sr.requested_at) = CURDATE()
        ORDER BY 
            FIELD(sr.urgency, 'high', 'medium', 'low'),
            sr.requested_at DESC
    ");
    if ($supply_notifs) $notifications = array_merge($notifications, $supply_notifs);
    
    usort($notifications, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    $present_today = getOne("SELECT COUNT(DISTINCT employee_id) as count FROM attendance WHERE DATE(attendance_date) = CURDATE() AND check_in_time IS NOT NULL")['count'] ?? 0;
    
    $top_performers = getAll("
        SELECT e.full_name, COUNT(t.task_id) as completed_tasks 
        FROM employees e 
        LEFT JOIN tasks t ON e.employee_id = t.assigned_to AND t.status = 'completed' AND MONTH(t.completed_at) = MONTH(CURDATE())
        GROUP BY e.employee_id 
        ORDER BY completed_tasks DESC 
        LIMIT 3
    ");
    
} catch (Exception $e) {
    $total_admins = $total_admins ?? 0;
    $total_employees = $total_employees ?? 0;
    $total_users = $total_admins + $total_employees;
    $total_bins = 0;
    $online_bins = 0;
    $tasks_today = 0;
    $total_alerts = 0;
    $notifications = [];
    $present_today = 0;
    $top_performers = [];
}

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

        .notifications-container {
            max-height: 600px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .notifications-container::-webkit-scrollbar {
            width: 6px;
        }

        .notifications-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .notifications-container::-webkit-scrollbar-thumb {
            background: #CEDEBD;
            border-radius: 10px;
        }

        .notifications-container::-webkit-scrollbar-thumb:hover {
            background: #9db89a;
        }

        .no-notifications {
            text-align: center;
            padding: 40px 20px;
        }

        .notification-item {
            display: flex;
            padding: 15px;
            margin-bottom: 10px;
            background: #fafafa;
            border-radius: 10px;
            border-left: 4px solid #CEDEBD;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .notification-item:hover {
            background: #f0f0f0;
            transform: translateX(5px);
        }

        .notification-item.priority-high {
            border-left-color: #e74c3c;
            background: #ffebee;
        }

        .notification-item.priority-high:hover {
            background: #ffcdd2;
        }

        .notification-item.priority-medium {
            border-left-color: #f39c12;
            background: #fff8e1;
        }

        .notification-item.priority-medium:hover {
            background: #ffecb3;
        }

        .notification-item.priority-low {
            border-left-color: #3498db;
        }

        .notification-content-full {
            flex: 1;
            min-width: 0;
        }

        .notification-title {
            font-size: 14px;
            font-weight: 600;
            color: #435334;
            margin-bottom: 4px;
            line-height: 1.4;
        }

        .notification-desc {
            font-size: 13px;
            color: #666;
            margin-bottom: 6px;
            line-height: 1.4;
        }

        .notification-time {
            font-size: 11px;
            color: #999;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .notification-time i {
            font-size: 10px;
        }

        .alert-badge {
            background: #e74c3c;
            color: white;
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 10px;
            font-weight: 600;
        }

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
        <div class="page-header">
            <h1>Dashboard</h1>
            <div class="header-info">
                <div class="header-time"><?php echo $current_time; ?></div>
                <div class="header-date"><?php echo $current_date; ?></div>
            </div>
        </div>

        <div class="welcome-card">
            <h2>  Welcome back, <?php echo htmlspecialchars(explode(' ', $admin_name)[0]); ?>!<i class="fa-regular fa-hand-wave" style="color: #435334;"></i> </h2>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-users" style="color: #435334;"></i></div>
                <div class="stat-value"><?php echo $total_users; ?></div>
                <div class="stat-label">Active Users</div>
                <div class="stat-change">
                <i class="fa-solid fa-user-tie" style="color: #82437dff;"></i> <?php echo $total_admins; ?> admins + 
                <i class="fa-solid fa-user-gear" style="color: #d5d179ff;"></i><?php echo $total_employees; ?> employees
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-trash-can" style="color: #435334;"></i></div>
                <div class="stat-value"><?php echo $online_bins; ?>/<?php echo $total_bins; ?></div>
                <div class="stat-label">Bins Online</div>
                <div class="stat-change"><?php echo $full_bins; ?> bins need collection</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-circle-check" style="color: #435334;"></i></div>
                <div class="stat-value"><?php echo $tasks_today; ?></div>
                <div class="stat-label">Tasks Completed Today</div>
                <div class="stat-change"><?php echo $pending_tasks; ?> pending tasks</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation" style="color: #435334;"></i></div>
                <div class="stat-value"><?php echo $total_alerts; ?></div>
                <div class="stat-label">Alerts</div>
                <div class="stat-change">
                    <?php if ($total_alerts > 0): ?>
                    <?php else: ?>
                        All clear!
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fa-solid fa-bell" style="color: #435334; margin-right: 8px;"></i>
                        Notifications
                    </h3>
                    <a href="notifications.php" class="card-link">View All →</a>
                </div>
                
                <div class="notifications-container">
                    <?php if (empty($notifications)): ?>
                        <div class="no-notifications">
                            <i class="fa-solid fa-circle-check" style="color: #27ae60; font-size: 48px; margin-bottom: 10px;"></i>
                            <p style="color: #999;">No notifications today. All clear!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notif): ?>
                            <?php
                                $link = '#';
                                
                                switch($notif['type']) {
                                    case 'leave_request':
                                        $link = 'leave.php';
                                        break;
                                    case 'maintenance':
                                        $link = 'maintenance_reports.php';
                                        break;
                                    case 'bin_full':
                                        $link = 'bins.php';
                                        break;
                                    case 'inventory_low':
                                    case 'inventory_out':
                                        $link = 'inventory.php';
                                        break;
                                    case 'supply_request':
                                        $link = 'supply_requests.php';
                                        break;
                                }
                                
                                $priorityClass = '';
                                switch($notif['priority']) {
                                    case 'high':
                                        $priorityClass = 'priority-high';
                                        break;
                                    case 'medium':
                                        $priorityClass = 'priority-medium';
                                        break;
                                    case 'low':
                                        $priorityClass = 'priority-low';
                                        break;
                                }
                            ?>
                            <a href="<?php echo $link; ?>" class="notification-item <?php echo $priorityClass; ?>" style="text-decoration: none; color: inherit;">
                                <div class="notification-content-full">
                                    <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                    <div class="notification-desc"><?php echo htmlspecialchars($notif['description']); ?></div>
                                    <div class="notification-time">
                                        <i class="fa-regular fa-clock"></i>
                                        <?php 
                                            $time_diff = time() - strtotime($notif['timestamp']);
                                            $notif_date = date('M j', strtotime($notif['timestamp']));
                                            $today_date = date('M j');
                                            
                                            if ($time_diff < 60) {
                                                echo 'Just now';
                                            } elseif ($time_diff < 3600) {
                                                echo floor($time_diff / 60) . ' min ago';
                                            } elseif ($notif_date == $today_date) {
                                                echo 'Today at ' . date('g:i A', strtotime($notif['timestamp']));
                                            } else {
                                                echo date('M j, g:i A', strtotime($notif['timestamp']));
                                            }
                                        ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">Quick Actions</h3>
                    </div>
                    <div class="quick-actions">
                        <a href="task_create.php" class="action-btn"><i class="fa-solid fa-plus" style="color: #435334;"></i> New Task</a>
                        <a href="user_add.php" class="action-btn"><i class="fa-solid fa-user-plus" style="color: #435334;"></i> Add User</a>
                        <a href="maintenance_reports.php" class="action-btn"><i class="fa-solid fa-triangle-exclamation" style="color: #435334;"></i> View Issues</a>
                        <a href="analytics.php" class="action-btn"><i class="fa-solid fa-chart-column" style="color: #435334;"></i> Waste Analytics</a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Top Performers (This Month)</h3>
                        <a href="performance.php" class="card-link">View All →</a>
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

                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">Attendance Today</h3>
                        <a href="attendance.php" class="card-link">View →</a>
                    </div>
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 48px; font-weight: 700; color: #435334;">
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