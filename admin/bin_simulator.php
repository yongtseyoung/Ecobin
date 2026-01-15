<?php
/**
 * Bin Sensor Simulator
 * Test the webhook and auto-task creation without ESP32 hardware
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get all bins
$bins = getAll("SELECT b.*, a.area_name 
                FROM bins b 
                LEFT JOIN areas a ON b.area_id = a.area_id 
                ORDER BY b.bin_code");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bin_id = $_POST['bin_id'] ?? 0;
    $fill_level = floatval($_POST['fill_level'] ?? 0);
    $battery = floatval($_POST['battery'] ?? 80);
    $lid_status = $_POST['lid_status'] ?? 'closed';
    
    // Get bin details
    $bin = getOne("SELECT * FROM bins WHERE bin_id = ?", [$bin_id]);
    
    if ($bin) {
        // Simulate ultrasonic distance (inverse of fill level)
        $distance = round(100 - $fill_level, 2);
        
        // Simulate GPS (slight variation around UMS coordinates)
        $gps_lat = 5.9804 + (rand(-100, 100) / 10000);
        $gps_lng = 116.0735 + (rand(-100, 100) / 10000);
        
        // Create simulation data
        $data = [
            'bin_id' => $bin_id,
            'fill_level' => $fill_level,
            'distance' => $distance,
            'battery_percentage' => $battery,
            'battery_voltage' => round($battery * 0.084, 2), // Simulate voltage
            'gps_latitude' => $gps_lat,
            'gps_longitude' => $gps_lng,
            'lid_status' => $lid_status,
            'last_opened' => $lid_status === 'open' ? date('Y-m-d H:i:s') : null,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Send to webhook using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/Ecobin/admin/bin_sensor_webhook.php');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Store response for display
        $_SESSION['simulation_response'] = $response;
        $_SESSION['simulation_code'] = $http_code;
        $_SESSION['simulation_data'] = json_encode($data, JSON_PRETTY_PRINT);
        
        header("Location: bin_simulator.php?success=1");
        exit;
    }
}

$success = isset($_GET['success']);
$simulation_response = $_SESSION['simulation_response'] ?? '';
$simulation_code = $_SESSION['simulation_code'] ?? 0;
$simulation_data = $_SESSION['simulation_data'] ?? '';
unset($_SESSION['simulation_response'], $_SESSION['simulation_code'], $_SESSION['simulation_data']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bin Simulator - EcoBin</title>
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
            width: 90px;
            height: 90px;
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
            max-width: 1200px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #435334;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #666;
            font-size: 14px;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .info-box h3 {
            color: #1976d2;
            margin-bottom: 10px;
        }

        .info-box ul {
            margin-left: 20px;
            color: #555;
        }

        .info-box li {
            margin: 5px 0;
        }

        .simulator-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #435334;
            margin-bottom: 8px;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
        }

        .slider-container {
            margin-top: 10px;
        }

        .slider {
            width: 100%;
            height: 8px;
            border-radius: 5px;
            background: #e0e0e0;
            outline: none;
            -webkit-appearance: none;
        }

        .slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #435334;
            cursor: pointer;
        }

        .slider-value {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }

        .slider-display {
            font-size: 24px;
            font-weight: 700;
            color: #435334;
            text-align: center;
            margin: 10px 0;
        }

        .fill-low { color: #27ae60; }
        .fill-medium { color: #3498db; }
        .fill-high { color: #f39c12; }
        .fill-full { color: #e74c3c; }

        .preset-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .btn-preset {
            padding: 12px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-preset:hover {
            border-color: #435334;
            background: #f8f9fa;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
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

        .response-box {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }

        .response-box h4 {
            color: #435334;
            margin-bottom: 10px;
        }

        .response-box pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 12px;
            line-height: 1.6;
        }

        .success-banner {
            background: #d4edda;
            border: 2px solid #c3e6cb;
            color: #155724;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .radio-option input {
            width: auto;
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
            <a href="tasks.php" class="nav-item">
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
            <h1>🧪 Bin Sensor Simulator</h1>
            <p>Test the auto-task creation system without ESP32 hardware</p>
        </div>

        <div class="info-box">
            <h3>💡 How to Use This Simulator</h3>
            <ul>
                <li><strong>Step 1:</strong> Select a bin from the dropdown</li>
                <li><strong>Step 2:</strong> Adjust fill level (use presets or slider)</li>
                <li><strong>Step 3:</strong> Click "Send Simulation"</li>
                <li><strong>Step 4:</strong> Check bins.php to see updated fill level</li>
                <li><strong>Step 5:</strong> If fill ≥80%, check tasks.php for auto-created task!</li>
            </ul>
        </div>

        <?php if ($success): ?>
            <div class="success-banner">
                <span style="font-size: 24px;">✅</span>
                <div>
                    <strong>Simulation Sent Successfully!</strong><br>
                    <small>HTTP Response Code: <?php echo $simulation_code; ?></small>
                </div>
            </div>
        <?php endif; ?>

        <div class="simulator-grid">
            <div class="card">
                <h2>📤 Send Simulated Data</h2>

                <form method="POST">
                    <div class="form-group">
                        <label>Select Bin *</label>
                        <select name="bin_id" id="binSelect" required>
                            <option value="">-- Choose a bin --</option>
                            <?php foreach ($bins as $bin): ?>
                                <option value="<?php echo $bin['bin_id']; ?>">
                                    <?php echo htmlspecialchars($bin['bin_code']); ?> - 
                                    <?php echo htmlspecialchars($bin['location_details']); ?>
                                    <?php if ($bin['area_name']): ?>
                                        (<?php echo htmlspecialchars($bin['area_name']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Quick Presets</label>
                        <div class="preset-buttons">
                            <button type="button" class="btn-preset" onclick="setFillLevel(10)">
                                🟢 Empty (10%)
                            </button>
                            <button type="button" class="btn-preset" onclick="setFillLevel(50)">
                                🔵 Medium (50%)
                            </button>
                            <button type="button" class="btn-preset" onclick="setFillLevel(85)">
                                🟡 Full (85%)
                            </button>
                            <button type="button" class="btn-preset" onclick="setFillLevel(95)">
                                🔴 Overflow (95%)
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Fill Level (%)</label>
                        <div class="slider-display" id="fillDisplay">50%</div>
                        <div class="slider-container">
                            <input type="range" name="fill_level" id="fillSlider" 
                                   min="0" max="100" value="50" class="slider">
                            <div class="slider-value">
                                <span>0%</span>
                                <span>50%</span>
                                <span>100%</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Battery Level (%)</label>
                        <input type="range" name="battery" id="batterySlider" 
                               min="0" max="100" value="80" class="slider">
                        <div class="slider-value">
                            <span>0%</span>
                            <span id="batteryDisplay">80%</span>
                            <span>100%</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Lid Status</label>
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" name="lid_status" value="closed" id="lidClosed" checked>
                                <label for="lidClosed" style="margin: 0;">🔒 Closed</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" name="lid_status" value="open" id="lidOpen">
                                <label for="lidOpen" style="margin: 0;">🔓 Open</label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        📤 Send Simulation
                    </button>
                </form>

                <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 10px;">
                    <strong>💡 Tip:</strong> Set fill level to 85% or higher to trigger auto-task creation!
                </div>
            </div>

            <div class="card">
                <h2>📊 Response & Results</h2>

                <?php if ($success && $simulation_response): ?>
                    <div class="response-box">
                        <h4>✅ Webhook Response (HTTP <?php echo $simulation_code; ?>):</h4>
                        <pre><?php echo htmlspecialchars($simulation_response); ?></pre>
                    </div>

                    <div class="response-box">
                        <h4>📤 Data Sent:</h4>
                        <pre><?php echo htmlspecialchars($simulation_data); ?></pre>
                    </div>

                    <?php
                    $response_data = json_decode($simulation_response, true);
                    if ($response_data && isset($response_data['task_created']) && $response_data['task_created']): ?>
                        <div style="background: #d4edda; padding: 15px; border-radius: 10px; margin-top: 15px;">
                            <strong style="color: #155724;">🎉 SUCCESS!</strong><br>
                            <span style="color: #155724;">
                                Auto-task created! Task ID: <?php echo $response_data['task_id']; ?><br>
                                <a href="tasks.php" style="color: #155724; text-decoration: underline;">
                                    View in Tasks Page →
                                </a>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 20px;">
                        <a href="bins.php" class="btn btn-secondary">
                            🗑️ View Bins Dashboard
                        </a>
                    </div>
                <?php else: ?>
                    <p style="color: #999; text-align: center; padding: 60px 20px;">
                        📊<br><br>
                        Response will appear here after sending simulation
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        const fillSlider = document.getElementById('fillSlider');
        const fillDisplay = document.getElementById('fillDisplay');
        const batterySlider = document.getElementById('batterySlider');
        const batteryDisplay = document.getElementById('batteryDisplay');

        fillSlider.addEventListener('input', function() {
            const value = this.value;
            fillDisplay.textContent = value + '%';
            
            // Change color based on fill level
            fillDisplay.className = 'slider-display';
            if (value >= 80) {
                fillDisplay.classList.add('fill-full');
            } else if (value >= 60) {
                fillDisplay.classList.add('fill-high');
            } else if (value >= 30) {
                fillDisplay.classList.add('fill-medium');
            } else {
                fillDisplay.classList.add('fill-low');
            }
        });

        batterySlider.addEventListener('input', function() {
            batteryDisplay.textContent = this.value + '%';
        });

        function setFillLevel(level) {
            fillSlider.value = level;
            fillSlider.dispatchEvent(new Event('input'));
        }
    </script>
</body>
</html>