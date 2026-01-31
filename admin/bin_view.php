<?php


session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';


if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'bins';

$admin_id = $_SESSION['user_id'];
$bin_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$bin_id) {
    $_SESSION['error'] = "Invalid bin ID";
    header("Location: bins.php");
    exit;
}

$bin = getOne("SELECT b.*, a.area_name, a.block,
               d.device_code, d.device_status, d.last_ping, 
               d.signal_strength, d.device_mac_address, d.device_model
               FROM bins b 
               LEFT JOIN areas a ON b.area_id = a.area_id 
               LEFT JOIN iot_devices d ON b.device_id = d.device_id
               WHERE b.bin_id = ?", 
               [$bin_id]);

if (!$bin) {
    $_SESSION['error'] = "Bin not found";
    header("Location: bins.php");
    exit;
}

$readings = getAll("SELECT * FROM sensor_readings 
                    WHERE bin_id = ? 
                    ORDER BY recorded_at DESC 
                    LIMIT 20", 
                    [$bin_id]);

$tasks = getAll("SELECT t.*, e.full_name as employee_name 
                 FROM tasks t 
                 LEFT JOIN employees e ON t.assigned_to = e.employee_id 
                 WHERE t.triggered_by_bin = ? 
                 ORDER BY t.created_at DESC 
                 LIMIT 10", 
                 [$bin_id]);

$total_collections = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));
$pending_tasks = count(array_filter($tasks, fn($t) => $t['status'] === 'pending'));
$avg_fill = $readings ? array_sum(array_column($readings, 'fill_level')) / count($readings) : 0;
$avg_weight = $readings ? array_sum(array_column($readings, 'weight')) / count($readings) : 0;

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

function getSignalBars($rssi) {
    if (!$rssi) return '<span style="color: #95a5a6;">○○○○</span>';
    
    if ($rssi >= -50) return '<span style="color: #27ae60;">●●●●</span>';
    if ($rssi >= -60) return '<span style="color: #27ae60;">●●●○</span>';
    if ($rssi >= -70) return '<span style="color: #f39c12;">●●○○</span>';
    if ($rssi >= -80) return '<span style="color: #e74c3c;">●○○○</span>';
    return '<span style="color: #e74c3c;">○○○○</span>';
}

function getWeightStatus($weight, $maxWeight) {
    if (!$weight || !$maxWeight) return ['status' => 'Unknown', 'color' => '#95a5a6'];
    
    $percentage = ($weight / $maxWeight) * 100;
    
    if ($percentage >= 80) return ['status' => 'Heavy', 'color' => '#e74c3c'];
    if ($percentage >= 50) return ['status' => 'Normal', 'color' => '#27ae60'];
    if ($percentage >= 20) return ['status' => 'Light', 'color' => '#f39c12'];
    return ['status' => 'Very Light', 'color' => '#95a5a6'];
}

$weightStatus = getWeightStatus($bin['current_weight'], $bin['max_weight']);
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
            font-size: 14px;
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

        .status-badge.normal {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.medium {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.full {
            background: #f8d7da;
            color: #721c24;
        }

        .status-badge.empty {
            background: #d1ecf1;
            color: #0c5460;
        }

        .fill-display {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .fill-percentage {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .fill-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }

        .fill-bar {
            height: 20px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }

        .fill-bar-inner {
            height: 100%;
            transition: width 0.3s ease;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .info-item .label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .info-item .value {
            font-size: 18px;
            font-weight: 600;
            color: #435334;
        }

        .device-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .device-section h3 {
            color: #435334;
            font-size: 18px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .device-info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .device-info-item {
            background: white;
            padding: 15px;
            border-radius: 10px;
        }

        .device-info-item .label {
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .device-info-item .value {
            font-size: 14px;
            font-weight: 600;
            color: #435334;
        }

        .device-status {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .dot-online {
            background: #27ae60;
            box-shadow: 0 0 5px #27ae60;
        }

        .dot-offline {
            background: #95a5a6;
        }

        .weight-display {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }


        .weight-value {
            font-size: 20px;
            font-weight: 700;
        }

        .weight-status {
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 10px;
            font-weight: 600;
            background: #f0f0f0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: 700;
            color: #435334;
            margin-bottom: 8px;
        }

        .stat-card .label {
            font-size: 13px;
            color: #999;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .card h3 {
            color: #435334;
            font-size: 20px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            border-bottom: 2px solid #e0e0e0;
        }

        tbody td {
            padding: 15px 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-in_progress {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-completed {
            background: #d4edda;
            color: #155724;
        }

        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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

        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }

            .info-grid,
            .device-info-grid,
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
            <a href="bins.php" class="back-link">
                ← Back to Bins
            </a>
        </div>

        <div class="bin-header">
            <div class="bin-header-top">
                <div class="bin-title">
                    <h2><?php echo htmlspecialchars($bin['bin_code']); ?></h2>
                    <div class="location">
                        <?php echo htmlspecialchars($bin['location_details']); ?>
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
                    <div class="label">Bin Capacity</div>
                    <div class="value"><?php echo $bin['bin_capacity']; ?> Liters</div>
                </div>
                <div class="info-item">
                    <div class="label">Floor Number</div>
                    <div class="value">Floor <?php echo $bin['floor_number'] ?? 'N/A'; ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Lid Status</div>
                    <div class="value"><?php echo $bin['lid_status'] === 'open' ? 'Open' : 'Closed'; ?></div>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Current Weight</div>
                    <div class="weight-display">
                        <span class="weight-value"><?php echo number_format($bin['current_weight'], 2); ?> kg</span>
                        <span class="weight-status" style="color: <?php echo $weightStatus['color']; ?>">
                            <?php echo $weightStatus['status']; ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">Max Weight</div>
                    <div class="value"><?php echo number_format($bin['max_weight'], 2); ?> kg</div>
                </div>
                <div class="info-item">
                    <div class="label">Battery Level</div>
                    <div class="value" style="color: <?php echo $bin['battery_level'] < 20 ? '#e74c3c' : '#27ae60'; ?>">
                        <?php echo number_format($bin['battery_level'], 0); ?>%
                    </div>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Last Updated</div>
                    <div class="value">
                        <?php 
                        if ($bin['last_updated']) {
                            $diff = time() - strtotime($bin['last_updated']);
                            if ($diff < 60) echo 'Just now';
                            elseif ($diff < 3600) echo round($diff / 60) . ' mins ago';
                            elseif ($diff < 86400) echo round($diff / 3600) . ' hrs ago';
                            else echo date('M j, Y', strtotime($bin['last_updated']));
                        } else {
                            echo 'Never';
                        }
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">Last Weight Reading</div>
                    <div class="value">
                        <?php 
                        if ($bin['last_weight_reading']) {
                            $diff = time() - strtotime($bin['last_weight_reading']);
                            if ($diff < 60) echo 'Just now';
                            elseif ($diff < 3600) echo round($diff / 60) . ' mins ago';
                            elseif ($diff < 86400) echo round($diff / 3600) . ' hrs ago';
                            else echo date('M j', strtotime($bin['last_weight_reading']));
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
                            <a href="https://www.google.com/maps?q=<?php echo $bin['gps_latitude']; ?>,<?php echo $bin['gps_longitude']; ?>" 
                               target="_blank" 
                               style="color: #435334; text-decoration: none;">
                                View Map
                            </a>
                        <?php else: ?>
                            Not available
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($bin['device_id']): ?>
        <div class="device-section">
            <h3>IoT Device Information</h3>
            <div class="device-info-grid">
                <div class="device-info-item">
                    <div class="label">Device Status</div>
                    <div class="value">
                        <span class="device-status">
                            <span class="status-dot <?php echo $bin['device_status'] === 'online' ? 'dot-online' : 'dot-offline'; ?>"></span>
                            <?php echo ucfirst($bin['device_status'] ?? 'offline'); ?>
                        </span>
                    </div>
                </div>
                <div class="device-info-item">
                    <div class="label">MAC Address</div>
                    <div class="value" style="font-size: 12px; font-family: monospace;">
                        <?php echo htmlspecialchars($bin['device_mac_address'] ?? 'N/A'); ?>
                    </div>
                </div>
                <div class="device-info-item">
                    <div class="label">WiFi Signal</div>
                    <div class="value">
                        <?php echo getSignalBars($bin['signal_strength']); ?>
                        <?php if ($bin['signal_strength']): ?>
                            <small style="color: #999;">(<?php echo $bin['signal_strength']; ?> dBm)</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="device-info-item">
                    <div class="label">Last Ping</div>
                    <div class="value" style="font-size: 13px;">
                        <?php 
                        if ($bin['last_ping']) {
                            $diff = time() - strtotime($bin['last_ping']);
                            if ($diff < 60) echo 'Just now';
                            elseif ($diff < 3600) echo round($diff / 60) . ' mins ago';
                            elseif ($diff < 86400) echo round($diff / 3600) . ' hrs ago';
                            else echo date('M j', strtotime($bin['last_ping']));
                        } else {
                            echo 'Never';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

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
                <div class="value"><?php echo number_format($avg_weight, 2); ?> kg</div>
                <div class="label">Avg Weight</div>
            </div>
        </div>

        <div class="card">
            <h3>Collection History</h3>
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
            <h3>Recent Sensor Readings</h3>
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Fill Level</th>
                        <th>Weight</th>
                        <th>Distance</th>
                        <th>Battery</th>
                        <th>Signal</th>
                        <th>Lid Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($readings as $reading): ?>
                        <tr>
                            <td><?php echo date('M d, Y H:i', strtotime($reading['recorded_at'])); ?></td>
                            <td><strong><?php echo number_format($reading['fill_level'], 1); ?>%</strong></td>
                            <td><?php echo $reading['weight'] ? number_format($reading['weight'], 2) . ' kg' : 'N/A'; ?></td>
                            <td><?php echo $reading['distance'] ? number_format($reading['distance'], 1) . ' cm' : 'N/A'; ?></td>
                            <td><?php echo $reading['battery_voltage'] ? number_format($reading['battery_voltage'], 1) . '%' : 'N/A'; ?></td>
                            <td><?php echo getSignalBars($reading['signal_quality']); ?></td>
                            <td><?php echo $reading['lid_status'] === 'open' ? 'Open' : 'Closed'; ?></td>
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