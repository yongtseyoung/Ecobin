<?php
/**
 * EcoBin Admin Notifications Page
 * View all system notifications
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

// Get all notifications
try {
    $all_notifications = [];
    
    // 1. Pending Leave Requests (All time)
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
        ORDER BY lr.created_at DESC
    ");
    if ($leave_notifs) $all_notifications = array_merge($all_notifications, $leave_notifs);
    
    // 2. Pending Maintenance Reports (All time)
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
        ORDER BY 
            FIELD(mr.priority, 'high', 'medium', 'low'),
            mr.reported_at DESC
    ");
    if ($maintenance_notifs) $all_notifications = array_merge($all_notifications, $maintenance_notifs);
    
    // 3. Full Bins (All current full bins)
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
    ");
    if ($bin_notifs) $all_notifications = array_merge($all_notifications, $bin_notifs);
    
    // 4. Low Stock Inventory (All current low stock items)
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
    ");
    if ($inventory_notifs) $all_notifications = array_merge($all_notifications, $inventory_notifs);
    
    // 5. Out of Stock Inventory (All current out of stock items)
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
    ");
    if ($outofstock_notifs) $all_notifications = array_merge($all_notifications, $outofstock_notifs);
    
    // 6. Pending Supply Requests (All time)
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
        ORDER BY 
            FIELD(sr.urgency, 'high', 'medium', 'low'),
            sr.requested_at DESC
    ");
    if ($supply_notifs) $all_notifications = array_merge($all_notifications, $supply_notifs);
    
    // Sort all notifications by timestamp (most recent first)
    usort($all_notifications, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    // Separate into "New" (today) and "Earlier" (older)
    $new_notifications = [];
    $earlier_notifications = [];
    
    foreach ($all_notifications as $notif) {
        if (date('Y-m-d', strtotime($notif['timestamp'])) == date('Y-m-d')) {
            $new_notifications[] = $notif;
        } else {
            $earlier_notifications[] = $notif;
        }
    }
    
} catch (Exception $e) {
    error_log("Notifications page error: " . $e->getMessage());
    $new_notifications = [];
    $earlier_notifications = [];
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
    <title>Notifications - EcoBin Admin</title>
    
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
            max-width: 900px;
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

        /* Tabs */
        .tabs {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab {
            padding: 12px 20px;
            font-size: 15px;
            font-weight: 600;
            color: #666;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab:hover {
            color: #435334;
        }

        .tab.active {
            color: #435334;
            border-bottom-color: #435334;
        }

        /* Notifications Container */
        .notifications-wrapper {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        /* Section Header */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px 0;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #435334;
        }

        .see-all-btn {
            font-size: 13px;
            color: #27ae60;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .see-all-btn:hover {
            color: #1e8449;
        }

        /* Notification Item */
        .notification-item {
            display: flex;
            padding: 15px;
            margin-bottom: 10px;
            background: #fafafa;
            border-radius: 10px;
            border-left: 4px solid #CEDEBD;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: #ccc;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            color: #666;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 14px;
            color: #999;
        }

        /* Section Divider */
        .section-divider {
            margin: 30px 0;
        }

        /* Collapsed state */
        .notifications-list.collapsed {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .notifications-list {
            max-height: 10000px;
            transition: max-height 0.3s ease;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }
        }

/* Back Button */
        .back-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #CEDEBD;
            color: #435334;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #b8ceaa;
            transform: translateX(-3px);
        }

        .back-btn i {
            font-size: 13px;
        }

    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="main-content">
            <!-- Header -->
        <div class="page-header">
            <h1>Notifications</h1>
            <a href="dashboard.php" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active">All</button>
        </div>

        <!-- Notifications Container -->
        <div class="notifications-wrapper">
            <?php if (empty($new_notifications) && empty($earlier_notifications)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-bell-slash"></i>
                    <h3>No notifications</h3>
                    <p>You're all caught up! No pending notifications at this time.</p>
                </div>
            <?php else: ?>
                
                <!-- New Section (Today) -->
                <?php if (!empty($new_notifications)): ?>
                <div class="notification-section">
                    <div class="section-header">
                        <div class="section-title">New</div>
                        <button class="see-all-btn" onclick="toggleSection('new')">
                            See all (<?php echo count($new_notifications); ?>)
                        </button>
                    </div>
                    
                    <div id="new-notifications" class="notifications-list">
                        <?php 
                        $display_count = 0;
                        foreach ($new_notifications as $notif): 
                            $display_count++;
                        ?>
                            <?php
                                // Determine link based on type
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
                                
                                // Priority class
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
                            <a href="<?php echo $link; ?>" class="notification-item <?php echo $priorityClass; ?>">
                                <div class="notification-content-full">
                                    <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                    <div class="notification-desc"><?php echo htmlspecialchars($notif['description']); ?></div>
                                    <div class="notification-time">
                                        <i class="fa-regular fa-clock"></i>
                                        <?php 
                                            $time_diff = time() - strtotime($notif['timestamp']);
                                            if ($time_diff < 60) {
                                                echo 'Just now';
                                            } elseif ($time_diff < 3600) {
                                                echo floor($time_diff / 60) . ' min ago';
                                            } else {
                                                echo date('g:i A', strtotime($notif['timestamp']));
                                            }
                                        ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

<!-- Earlier Section (Last 7 days) -->
                <?php if (!empty($earlier_notifications)): ?>
                <div class="section-divider"></div>
                <div class="notification-section">
                    <div class="section-header">
                        <div class="section-title">Earlier</div>
                        <button class="see-all-btn" onclick="toggleSection('earlier')">
                            Show less
                        </button>
                    </div>
                    
                    <div id="earlier-notifications" class="notifications-list">
                        <?php foreach ($earlier_notifications as $notif): ?>
                            <?php
                                // Determine link based on type
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
                                
                                // Priority class
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
                            <a href="<?php echo $link; ?>" class="notification-item <?php echo $priorityClass; ?>">
                                <div class="notification-content-full">
                                    <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                    <div class="notification-desc"><?php echo htmlspecialchars($notif['description']); ?></div>
                                    <div class="notification-time">
                                        <i class="fa-regular fa-clock"></i>
                                        <?php echo date('M j, g:i A', strtotime($notif['timestamp'])); ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </main>

<script>
        function toggleSection(section) {
            const element = document.getElementById(section + '-notifications');
            const button = event.target;
            
            if (element.classList.contains('collapsed')) {
                // Expand to show all
                element.classList.remove('collapsed');
                button.textContent = 'Show less';
            } else {
                // Collapse to hide earlier notifications
                element.classList.add('collapsed');
                button.textContent = 'See all (' + <?php echo count($earlier_notifications); ?> + ')';
            }
        }
    </script>
</body>
</html>