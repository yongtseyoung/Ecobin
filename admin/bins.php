<?php
/**
 * Bins Dashboard - Real-time Bin Monitoring
 * View all bins with live status, fill levels, and battery status
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Set current page for sidebar
$current_page = 'bins';

// Get filter parameters
$filter_area = $_GET['area'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_fill = $_GET['fill'] ?? ''; // low, medium, high, full

// Build query
$query = "SELECT b.*, 
          a.area_name, a.block,
          d.device_code, d.device_status, d.last_ping,
          (SELECT COUNT(*) FROM tasks t 
           WHERE t.triggered_by_bin = b.bin_id 
           AND t.status IN ('pending', 'in_progress')) as active_tasks
          FROM bins b
          LEFT JOIN areas a ON b.area_id = a.area_id
          LEFT JOIN iot_devices d ON b.device_id = d.device_id
          WHERE 1=1";

$params = [];

if ($filter_area) {
    $query .= " AND b.area_id = ?";
    $params[] = $filter_area;
}

if ($filter_status) {
    $query .= " AND b.status = ?";
    $params[] = $filter_status;
}

if ($filter_fill === 'full') {
    $query .= " AND b.current_fill_level >= 80";
} elseif ($filter_fill === 'high') {
    $query .= " AND b.current_fill_level >= 60 AND b.current_fill_level < 80";
} elseif ($filter_fill === 'medium') {
    $query .= " AND b.current_fill_level >= 30 AND b.current_fill_level < 60";
} elseif ($filter_fill === 'low') {
    $query .= " AND b.current_fill_level < 30";
}

$query .= " ORDER BY b.current_fill_level DESC, b.bin_code ASC";

$bins = getAll($query, $params);

// Get areas for filter
$areas = getAll("SELECT area_id, area_name, block FROM areas ORDER BY area_name");

// Calculate statistics
$total_bins = count($bins);
$full_bins = count(array_filter($bins, fn($b) => $b['current_fill_level'] >= 80));
$medium_bins = count(array_filter($bins, fn($b) => $b['current_fill_level'] >= 30 && $b['current_fill_level'] < 80));
$empty_bins = count(array_filter($bins, fn($b) => $b['current_fill_level'] < 30));
$offline_bins = count(array_filter($bins, fn($b) => $b['device_status'] === 'offline'));
$low_battery = count(array_filter($bins, fn($b) => $b['battery_level'] < 20));

// Get messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bin Monitoring - EcoBin</title>
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

        .stats-row {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
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

        .stat-card.alert .value { color: #e74c3c; }
        .stat-card.warning .value { color: #f39c12; }
        .stat-card.success .value { color: #27ae60; }
        .stat-card.offline .value { color: #95a5a6; }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filters form {
            display: grid;
            grid-template-columns: repeat(4, 1fr) auto;
            gap: 15px;
            align-items: end;
        }

        .filter-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #435334;
            margin-bottom: 8px;
        }

        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }

        .bins-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .bin-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: relative;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .bin-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .bin-card.full {
            border-left: 5px solid #e74c3c;
        }

        .bin-card.high {
            border-left: 5px solid #f39c12;
        }

        .bin-card.normal {
            border-left: 5px solid #27ae60;
        }

        .bin-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .bin-code {
            font-size: 20px;
            font-weight: 700;
            color: #435334;
        }

        .bin-status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-normal { background: #d4edda; color: #155724; }
        .status-full { background: #f8d7da; color: #721c24; }
        .status-offline { background: #e2e3e5; color: #383d41; }
        .status-maintenance { background: #fff3cd; color: #856404; }

        .bin-location {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
        }

        .fill-level-container {
            margin-bottom: 15px;
        }

        .fill-label {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .fill-label strong {
            color: #435334;
        }

        .fill-bar {
            width: 100%;
            height: 12px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }

        .fill-progress {
            height: 100%;
            transition: width 0.3s;
            border-radius: 10px;
        }

        .fill-empty { background: #27ae60; }
        .fill-medium { background: #3498db; }
        .fill-high { background: #f39c12; }
        .fill-full { background: #e74c3c; }

        .bin-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .detail-item {
            font-size: 12px;
        }

        .detail-item strong {
            display: block;
            color: #435334;
            margin-bottom: 3px;
        }

        .detail-item span {
            color: #666;
        }

        .battery-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .battery-low {
            color: #e74c3c;
        }

        .battery-good {
            color: #27ae60;
        }

        .bin-actions {
            display: flex;
            gap: 8px;
        }

        .btn-small {
            flex: 1;
            padding: 8px 12px;
            font-size: 12px;
            text-align: center;
        }

        .device-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .dot-online { background: #27ae60; }
        .dot-offline { background: #95a5a6; }

        .task-indicator {
            background: #fff3cd;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            color: #856404;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .refresh-notice {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .refresh-notice button {
            margin-left: auto;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <h1>🗑️ Bin Monitoring</h1>
            <div style="display: flex; gap: 10px;">
                <a href="bin_simulator.php" class="btn btn-secondary">
                    🧪 Test Simulator
                </a>
                <button onclick="location.reload()" class="btn btn-primary">
                    🔄 Refresh
                </button>
            </div>
        </div>

        <div class="refresh-notice">
            <span>💡</span>
            <span>Real-time monitoring: Data updates automatically when ESP32 sends new readings</span>
            <button onclick="location.reload()" class="btn btn-secondary btn-small">
                Refresh Now
            </button>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="value"><?php echo $total_bins; ?></div>
                <div class="label">Total Bins</div>
            </div>
            <div class="stat-card alert">
                <div class="value"><?php echo $full_bins; ?></div>
                <div class="label">Full (≥80%)</div>
            </div>
            <div class="stat-card warning">
                <div class="value"><?php echo $medium_bins; ?></div>
                <div class="label">Medium (30-80%)</div>
            </div>
            <div class="stat-card success">
                <div class="value"><?php echo $empty_bins; ?></div>
                <div class="label">Empty (<30%)</div>
            </div>
            <div class="stat-card offline">
                <div class="value"><?php echo $offline_bins; ?></div>
                <div class="label">Offline</div>
            </div>
            <div class="stat-card alert">
                <div class="value"><?php echo $low_battery; ?></div>
                <div class="label">Low Battery</div>
            </div>
        </div>

        <div class="filters">
            <form method="GET">
                <div class="filter-group">
                    <label>Area</label>
                    <select name="area">
                        <option value="">All Areas</option>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?php echo $area['area_id']; ?>" 
                                    <?php echo $filter_area == $area['area_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($area['area_name']); ?>
                                <?php if ($area['block']): ?>
                                    (Block <?php echo htmlspecialchars($area['block']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="normal" <?php echo $filter_status === 'normal' ? 'selected' : ''; ?>>Normal</option>
                        <option value="full" <?php echo $filter_status === 'full' ? 'selected' : ''; ?>>Full</option>
                        <option value="offline" <?php echo $filter_status === 'offline' ? 'selected' : ''; ?>>Offline</option>
                        <option value="maintenance" <?php echo $filter_status === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Fill Level</label>
                    <select name="fill">
                        <option value="">All Levels</option>
                        <option value="full" <?php echo $filter_fill === 'full' ? 'selected' : ''; ?>>Full (≥80%)</option>
                        <option value="high" <?php echo $filter_fill === 'high' ? 'selected' : ''; ?>>High (60-80%)</option>
                        <option value="medium" <?php echo $filter_fill === 'medium' ? 'selected' : ''; ?>>Medium (30-60%)</option>
                        <option value="low" <?php echo $filter_fill === 'low' ? 'selected' : ''; ?>>Empty (<30%)</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Filter</button>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <a href="bins.php" class="btn btn-secondary" style="width: 100%; justify-content: center;">Clear</a>
                </div>
            </form>
        </div>

        <?php if (empty($bins)): ?>
            <div class="empty-state">
                <div class="icon">🗑️</div>
                <h3>No bins found</h3>
                <p>No bins match your filter criteria</p>
            </div>
        <?php else: ?>
            <div class="bins-grid">
                <?php foreach ($bins as $bin): ?>
                    <?php
                    $fill = $bin['current_fill_level'];
                    $card_class = $fill >= 80 ? 'full' : ($fill >= 60 ? 'high' : 'normal');
                    $fill_class = $fill >= 80 ? 'fill-full' : ($fill >= 60 ? 'fill-high' : ($fill >= 30 ? 'fill-medium' : 'fill-empty'));
                    ?>
                    <div class="bin-card <?php echo $card_class; ?>">
                        <div class="bin-header">
                            <div class="bin-code"><?php echo htmlspecialchars($bin['bin_code']); ?></div>
                            <span class="bin-status-badge status-<?php echo $bin['status']; ?>">
                                <?php echo ucfirst($bin['status']); ?>
                            </span>
                        </div>

                        <div class="bin-location">
                            📍 <?php echo htmlspecialchars($bin['location_details']); ?>
                            <?php if ($bin['area_name']): ?>
                                <br><small>Area: <?php echo htmlspecialchars($bin['area_name']); ?>
                                <?php if ($bin['block']): ?>
                                    (Block <?php echo htmlspecialchars($bin['block']); ?>)
                                <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <?php if ($bin['active_tasks'] > 0): ?>
                            <div class="task-indicator">
                                <span>📋</span>
                                <span><strong><?php echo $bin['active_tasks']; ?></strong> active collection task(s)</span>
                            </div>
                        <?php endif; ?>

                        <div class="fill-level-container">
                            <div class="fill-label">
                                <strong>Fill Level</strong>
                                <span style="font-weight: 600; font-size: 16px; color: <?php echo $fill >= 80 ? '#e74c3c' : ($fill >= 60 ? '#f39c12' : '#27ae60'); ?>">
                                    <?php echo number_format($fill, 1); ?>%
                                </span>
                            </div>
                            <div class="fill-bar">
                                <div class="fill-progress <?php echo $fill_class; ?>" style="width: <?php echo min($fill, 100); ?>%"></div>
                            </div>
                        </div>

                        <div class="bin-details">
                            <div class="detail-item">
                                <strong>Battery</strong>
                                <span class="battery-indicator <?php echo $bin['battery_level'] < 20 ? 'battery-low' : 'battery-good'; ?>">
                                    🔋 <?php echo number_format($bin['battery_level'], 0); ?>%
                                </span>
                            </div>
                            <div class="detail-item">
                                <strong>Device</strong>
                                <span class="device-status">
                                    <span class="status-dot <?php echo $bin['device_status'] === 'online' ? 'dot-online' : 'dot-offline'; ?>"></span>
                                    <?php echo ucfirst($bin['device_status'] ?? 'offline'); ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <strong>Lid Status</strong>
                                <span><?php echo $bin['lid_status'] === 'open' ? '🔓 Open' : '🔒 Closed'; ?></span>
                            </div>
                            <div class="detail-item">
                                <strong>Last Update</strong>
                                <span>
                                    <?php 
                                    if ($bin['last_updated']) {
                                        $diff = time() - strtotime($bin['last_updated']);
                                        if ($diff < 60) echo 'Just now';
                                        elseif ($diff < 3600) echo round($diff / 60) . ' mins ago';
                                        elseif ($diff < 86400) echo round($diff / 3600) . ' hrs ago';
                                        else echo date('M j', strtotime($bin['last_updated']));
                                    } else {
                                        echo 'Never';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>

                        <div class="bin-actions">
                            <a href="bin_view.php?id=<?php echo $bin['bin_id']; ?>" class="btn btn-primary btn-small">
                                👁️ View Details
                            </a>
                            <?php if ($bin['gps_latitude'] && $bin['gps_longitude']): ?>
                                <a href="https://www.google.com/maps?q=<?php echo $bin['gps_latitude']; ?>,<?php echo $bin['gps_longitude']; ?>" 
                                   target="_blank" 
                                   class="btn btn-secondary btn-small">
                                    📍 Map
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>