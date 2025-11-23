<?php
/**
 * Bin Details View
 * Shows detailed information about a specific bin
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
$bin_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$bin_id) {
    $_SESSION['error'] = "Invalid bin ID";
    header("Location: bins.php");
    exit;
}

// Get bin details
$bin = getOne("SELECT b.*, a.area_name, a.block 
               FROM bins b 
               LEFT JOIN areas a ON b.area_id = a.area_id 
               WHERE b.bin_id = ?", 
               [$bin_id]);

if (!$bin) {
    $_SESSION['error'] = "Bin not found";
    header("Location: bins.php");
    exit;
}

// Get recent sensor readings (last 20)
$readings = getAll("SELECT * FROM sensor_readings 
                    WHERE bin_id = ? 
                    ORDER BY recorded_at DESC 
                    LIMIT 20", 
                    [$bin_id]);

// Get collection history (tasks)
$tasks = getAll("SELECT t.*, e.full_name as employee_name 
                 FROM tasks t 
                 LEFT JOIN employees e ON t.assigned_to = e.employee_id 
                 WHERE t.triggered_by_bin = ? 
                 ORDER BY t.created_at DESC 
                 LIMIT 10", 
                 [$bin_id]);

// Get maintenance records (skip if table doesn't exist yet)
$maintenance = [];

// Calculate statistics
$total_collections = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));
$pending_tasks = count(array_filter($tasks, fn($t) => $t['status'] === 'pending'));
$avg_fill = $readings ? array_sum(array_column($readings, 'fill_level')) / count($readings) : 0;

// Determine status color
$status_class = 'normal';
$status_text = 'Normal';
if ($bin['current_fill_level'] >= 80) {
    $status_class = 'full';
    $status_text = 'Full - Needs Collection';
} elseif ($bin['current_fill_level'] >= 60) {
    $status_class = 'medium';
    $status_text = 'Medium';
} elseif ($bin['current_fill_level'] < 30) {
    $status_class = 'empty';
    $status_text = 'Empty';
}

$fill_color = '#27ae60';
if ($bin['current_fill_level'] >= 80) $fill_color = '#e74c3c';
elseif ($bin['current_fill_level'] >= 60) $fill_color = '#f39c12';
elseif ($bin['current_fill_level'] >= 30) $fill_color = '#3498db';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bin Details - <?php echo htmlspecialchars($bin['bin_code']); ?></title>
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
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #435334;
        }

        .back-link {
            color: #435334;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .bin-header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .bin-header-top {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 30px;
        }

        .bin-title {
            flex: 1;
        }

        .bin-title h2 {
            font-size: 28px;
            color: #435334;
            margin-bottom: 10px;
        }

        .bin-title .location {
            font-size: 16px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-badge {
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }

        .status-badge.full {
            background: #fee;
            color: #c00;
        }

        .status-badge.medium {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.normal {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-badge.empty {
            background: #d4edda;
            color: #155724;
        }

        .fill-display {
            text-align: center;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .fill-percentage {
            font-size: 72px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .fill-label {
            font-size: 16px;
            color: #666;
            margin-bottom: 20px;
        }

        .fill-bar {
            height: 30px;
            background: #e0e0e0;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }

        .fill-bar-inner {
            height: 100%;
            border-radius: 15px;
            transition: width 0.3s ease;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
        }

        .info-item .label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .info-item .value {
            font-size: 16px;
            font-weight: 600;
            color: #435334;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .card h3 {
            color: #435334;
            margin-bottom: 20px;
            font-size: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #435334;
            border-bottom: 2px solid #e0e0e0;
            font-size: 13px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-in_progress { background: #cce5ff; color: #004085; }
        .badge-completed { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #435334;
            color: white;
        }

        .btn-secondary {
            background: #CEDEBD;
            color: #435334;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
            <a href="bins.php" class="nav-item active">
                <span class="icon">🗑️</span>
                <span>Bin Monitoring</span>
            </a>
            <a href="attendance.php" class="nav-item">
                <span class="icon">✅</span>
                <span>Attendance</span>
            </a>
            <a href="tasks.php" class="nav-item">
                <span class="icon">📋</span>
                <span>Tasks</span>
            </a>
            <a href="performance.php" class="nav-item">
                <span class="icon">📈</span>
                <span>Employee Performance</span>
            </a>
            <a href="analytics.php" class="nav-item">
                <span class="icon">📊</span>
                <span>Waste Analytics</span>
            </a>
            <a href="inventory.php" class="nav-item">
                <span class="icon">📦</span>
                <span>Inventory</span>
            </a>
            <a href="leave.php" class="nav-item">
                <span class="icon">📅</span>
                <span>Leave Management</span>
            </a>
            <a href="maintenance.php" class="nav-item">
                <span class="icon">🔧</span>
                <span>Maintenance & Issues</span>
            </a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <a href="bins.php" class="back-link">
                ← Back to Bins
            </a>
        </div>

        <div class="bin-header">
            <div class="bin-header-top">
                <div class="bin-title">
                    <h2><?php echo htmlspecialchars($bin['bin_code']); ?></h2>
                    <div class="location">
                        📍 <?php echo htmlspecialchars($bin['location_details']); ?>
                        <?php if ($bin['area_name']): ?>
                            • <?php echo htmlspecialchars($bin['area_name']); ?>
                            <?php if ($bin['block']): ?>
                                (Block <?php echo htmlspecialchars($bin['block']); ?>)
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="status-badge <?php echo $status_class; ?>">
                    <?php echo $status_text; ?>
                </span>
            </div>

            <div class="fill-display">
                <div class="fill-percentage" style="color: <?php echo $fill_color; ?>">
                    <?php echo number_format($bin['current_fill_level'], 1); ?>%
                </div>
                <div class="fill-label">Current Fill Level</div>
                <div class="fill-bar">
                    <div class="fill-bar-inner" style="width: <?php echo $bin['current_fill_level']; ?>%; background: <?php echo $fill_color; ?>"></div>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Capacity</div>
                    <div class="value"><?php echo $bin['bin_capacity']; ?> Liters</div>
                </div>
                <div class="info-item">
                    <div class="label">Floor Number</div>
                    <div class="value">Floor <?php echo $bin['floor_number'] ?? 'N/A'; ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Battery Level</div>
                    <div class="value"><?php echo $bin['battery_level'] ?? 'N/A'; ?>%</div>
                </div>
                <div class="info-item">
                    <div class="label">Lid Status</div>
                    <div class="value"><?php echo $bin['lid_status'] === 'open' ? '🔓 Open' : '🔒 Closed'; ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Last Updated</div>
                    <div class="value">
                        <?php 
                        if ($bin['last_updated']) {
                            $date = new DateTime($bin['last_updated']);
                            echo $date->format('M d, Y H:i');
                        } else {
                            echo 'Never';
                        }
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">GPS Coordinates</div>
                    <div class="value">
                        <?php if ($bin['gps_latitude'] && $bin['gps_longitude']): ?>
                            <?php echo number_format($bin['gps_latitude'], 6); ?>, <?php echo number_format($bin['gps_longitude'], 6); ?>
                        <?php else: ?>
                            Not available
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="value"><?php echo $total_collections; ?></div>
                <div class="label">Total Collections</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo $pending_tasks; ?></div>
                <div class="label">Pending Tasks</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo number_format($avg_fill, 1); ?>%</div>
                <div class="label">Avg Fill Level</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo count($readings); ?></div>
                <div class="label">Recent Readings</div>
            </div>
        </div>

        <div class="card">
            <h3>📋 Collection History</h3>
            <?php if (empty($tasks)): ?>
                <div class="empty-state">
                    <p>No collection tasks recorded yet</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Task ID</th>
                            <th>Title</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td><strong>#<?php echo $task['task_id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($task['task_title']); ?></td>
                                <td><?php echo htmlspecialchars($task['employee_name'] ?? 'Unassigned'); ?></td>
                                <td><span class="badge badge-<?php echo $task['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?></span></td>
                                <td><span style="text-transform: uppercase; font-size: 11px; font-weight: 600;"><?php echo $task['priority']; ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($task['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if (!empty($readings)): ?>
        <div class="card">
            <h3>📊 Recent Sensor Readings</h3>
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Fill Level</th>
                        <th>Distance</th>
                        <th>Battery</th>
                        <th>Temperature</th>
                        <th>Lid Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($readings as $reading): ?>
                        <tr>
                            <td><?php echo date('M d, Y H:i', strtotime($reading['recorded_at'])); ?></td>
                            <td><?php echo number_format($reading['fill_level'], 1); ?>%</td>
                            <td><?php echo number_format($reading['distance'], 1); ?> cm</td>
                            <td><?php echo number_format($reading['battery_voltage'], 2); ?>V</td>
                            <td><?php echo $reading['temperature'] ? number_format($reading['temperature'], 1) . '°C' : 'N/A'; ?></td>
                            <td><?php echo $reading['lid_status'] === 'open' ? '🔓' : '🔒'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="action-buttons">
            <a href="bins.php" class="btn btn-secondary">← Back to Bins</a>
        </div>
    </main>
</body>
</html>