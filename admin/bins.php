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
          d.device_code, d.device_status, d.last_ping, d.signal_strength, d.device_mac_address,
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

// A bin is offline if last_ping is NULL or older than 5 minutes
$offline_bins = count(array_filter($bins, function($b) {
    if (!$b['last_ping']) return true; // No ping yet = offline
    $last_ping_time = strtotime($b['last_ping']);
    $five_minutes_ago = time() - (5 * 60);
    return $last_ping_time < $five_minutes_ago; // Ping too old = offline
}));

$low_battery = count(array_filter($bins, fn($b) => $b['battery_level'] < 20));

// Calculate total weight
$total_weight = array_sum(array_column($bins, 'current_weight'));

// Get messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Function to get signal strength bars
function getSignalBars($rssi) {
    if (!$rssi) return '<span style="color: #95a5a6;"><i class="fa-solid fa-signal"></i> No Signal</span>';
    
    if ($rssi >= -50) return '<span style="color: #27ae60;"><i class="fa-solid fa-signal"></i> Excellent</span>';
    if ($rssi >= -60) return '<span style="color: #27ae60;"><i class="fa-solid fa-signal"></i> Good</span>';
    if ($rssi >= -70) return '<span style="color: #f39c12;"><i class="fa-solid fa-signal"></i> Fair</span>';
    if ($rssi >= -80) return '<span style="color: #e74c3c;"><i class="fa-solid fa-signal"></i> Weak</span>';
    return '<span style="color: #e74c3c;"><i class="fa-solid fa-signal"></i> Poor</span>';
}

// Function to get weight status
function getWeightStatus($weight, $maxWeight) {
    if (!$weight || !$maxWeight) return ['status' => 'Unknown', 'color' => '#95a5a6'];
    
    $percentage = ($weight / $maxWeight) * 100;
    
    if ($percentage >= 80) return ['status' => 'Heavy', 'color' => '#e74c3c'];
    if ($percentage >= 50) return ['status' => 'Normal', 'color' => '#27ae60'];
    if ($percentage >= 20) return ['status' => 'Light', 'color' => '#f39c12'];
    return ['status' => 'Very Light', 'color' => '#95a5a6'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bin Monitoring - EcoBin</title>
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
        }

        .icon-main {
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

        .btn-small {
            padding: 8px 16px;
            font-size: 12px;
        }

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); /* responsive columns */
    gap: 15px;
    margin-bottom: 30px;
    align-items: stretch; /* ensures all cards have same height */
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    text-align: center;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    height: 100%; /* ensures stretching inside grid */
}

.stat-card .value {
    font-size: 32px;
    font-weight: 700;
    color: #435334; /* All numbers same color */
    margin-bottom: 5px;
    line-height: 1;
}

.stat-card .label {
    font-size: 12px;
    color: #999;
    text-transform: uppercase;
    line-height: 1.4;
    margin-top: 8px;
}

/* Remove the color overrides */
.stat-card.alert .value,
.stat-card.warning .value,
.stat-card.success .value,
.stat-card.offline .value,
.stat-card.info .value {
    color: #435334; /* All numbers same color */
}

        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            background: #2ecc71;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

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
            font-size: 12px;
            font-weight: 600;
            color: #666;
            margin-bottom: 8px;
        }

        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }

        .bins-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .bin-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border-left: 5px solid #27ae60;
        }

        .bin-card.full {
            border-left-color: #e74c3c;
        }

        .bin-card.high {
            border-left-color: #f39c12;
        }

        .bin-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .bin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .bin-code {
            font-size: 20px;
            font-weight: 700;
            color: #435334;
        }

        .bin-status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-normal {
            background: #d4edda;
            color: #155724;
        }

        .status-full {
            background: #f8d7da;
            color: #721c24;
        }

        .status-needs_maintenance {
            background: #fff3cd;
            color: #856404;
        }

        .status-offline {
            background: #e2e3e5;
            color: #383d41;
        }

        .bin-location {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .task-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fff3cd;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #856404;
        }

        .fill-level-container {
            margin-bottom: 20px;
        }

        .fill-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
            color: #666;
        }

        .fill-bar {
            height: 12px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }

        .fill-progress {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .fill-empty { background: linear-gradient(90deg, #27ae60, #2ecc71); }
        .fill-medium { background: linear-gradient(90deg, #f39c12, #f1c40f); }
        .fill-high { background: linear-gradient(90deg, #e67e22, #f39c12); }
        .fill-full { background: linear-gradient(90deg, #e74c3c, #c0392b); }

        .bin-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }

        .detail-item {
            font-size: 13px;
        }

        .detail-item strong {
            display: block;
            color: #999;
            font-size: 11px;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .detail-item span {
            color: #435334;
            font-weight: 600;
        }

        .battery-indicator {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .battery-low {
            color: #e74c3c !important;
        }

        .battery-good {
            color: #27ae60 !important;
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
            align-items: center;
            justify-content: space-between;
        }

        .weight-value {
            font-size: 16px;
            font-weight: 700;
        }

        .weight-status {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 600;
            background: #f0f0f0;
        }

        .signal-bars {
            font-size: 13px;
        }

        .bin-actions {
            display: flex;
            gap: 10px;
        }

        .bin-actions .btn {
            flex: 1;
            justify-content: center;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
        }

        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 20px;
            color: #ccc;
        }

        .empty-state h3 {
            color: #435334;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .filters form {
                grid-template-columns: 1fr;
            }

            .bins-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Bin Monitoring</h1>
            <a href="create_bin.php" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Add New Bin
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

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
            <div class="stat-card info">
                <div class="value"><?php echo number_format($total_weight, 1); ?></div>
                <div class="label">Total Weight (kg)</div>
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
                <div class="icon"><i class="fa-solid fa-trash-can"></i></div>
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
                    $weightStatus = getWeightStatus($bin['current_weight'], $bin['max_weight']);
                    ?>
                    <div class="bin-card <?php echo $card_class; ?>">
                        <div class="bin-header">
                            <div class="bin-code"><?php echo htmlspecialchars($bin['bin_code']); ?></div>
                            <span class="bin-status-badge status-<?php echo $bin['status']; ?>">
                                <?php echo ucfirst($bin['status']); ?>
                            </span>
                        </div>

                        <div class="bin-location">
                            <i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($bin['location_details']); ?>
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
                                <i class="fa-solid fa-clipboard-list"></i>
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
                            <div class="detail-item" style="grid-column: 1 / -1;">
                                <strong>Current Weight</strong>
                                <div class="weight-display">
                                    <span class="weight-value"><?php echo number_format($bin['current_weight'], 2); ?> kg</span>
                                    <span class="weight-status" style="color: <?php echo $weightStatus['color']; ?>">
                                        <?php echo $weightStatus['status']; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <strong>Battery</strong>
                                <span class="battery-indicator <?php echo $bin['battery_level'] < 20 ? 'battery-low' : 'battery-good'; ?>">
                                    <i class="fa-solid fa-battery-<?php echo $bin['battery_level'] < 20 ? 'quarter' : ($bin['battery_level'] < 50 ? 'half' : 'full'); ?>"></i> <?php echo number_format($bin['battery_level'], 0); ?>%
                                </span>
                            </div>
                            
                            <div class="detail-item">
                                <strong>Device Status</strong>
                                <span class="device-status">
                                    <?php 
                                    // Check if device sent heartbeat in last 5 minutes
                                    $is_online = false;
                                    if ($bin['last_ping']) {
                                        $last_ping_time = strtotime($bin['last_ping']);
                                        $five_minutes_ago = time() - (5 * 60);
                                        $is_online = $last_ping_time >= $five_minutes_ago;
                                    }
                                    ?>
                                    <span class="status-dot <?php echo $is_online ? 'dot-online' : 'dot-offline'; ?>"></span>
                                    <?php echo $is_online ? 'Online' : 'Offline'; ?>
                                </span>
                            </div>
                            
                            <div class="detail-item">
                                <strong>WiFi Signal</strong>
                                <span class="signal-bars">
                                    <?php echo getSignalBars($bin['signal_strength']); ?>
                                </span>
                            </div>
                            
                            <div class="detail-item">
                                <strong>Lid Status</strong>
                                <span>
                                    <?php if ($bin['lid_status'] === 'open'): ?>
                                        <i class="fa-solid fa-lock-open"></i> Open
                                    <?php else: ?>
                                        <i class="fa-solid fa-lock"></i> Closed
                                    <?php endif; ?>
                                </span>
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
                            
                            <div class="detail-item">
                                <strong>Last Weight Reading</strong>
                                <span>
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
                                </span>
                            </div>
                        </div>

                        <div class="bin-actions">
                            <a href="bin_view.php?id=<?php echo $bin['bin_id']; ?>" class="btn btn-primary btn-small">
                                <i class="fa-solid fa-eye"></i> View Details
                            </a>
                            <?php if ($bin['gps_latitude'] && $bin['gps_longitude']): ?>
                                <a href="https://www.google.com/maps?q=<?php echo $bin['gps_latitude']; ?>,<?php echo $bin['gps_longitude']; ?>" 
                                   target="_blank" 
                                   class="btn btn-secondary btn-small">
                                    <i class="fa-solid fa-map-location-dot"></i> Map
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>