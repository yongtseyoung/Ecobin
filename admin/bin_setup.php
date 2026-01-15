<?php
/**
 * Quick Bin Setup
 * Add bins to the database quickly for testing
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'add_bin') {
        $bin_code = trim($_POST['bin_code']);
        $location_details = trim($_POST['location_details']);
        $area_id = $_POST['area_id'] ?: null;
        $bin_capacity = floatval($_POST['bin_capacity']);
        $floor_number = intval($_POST['floor_number']);
        
        try {
            query("INSERT INTO bins (bin_code, location_details, area_id, bin_capacity, 
                   floor_number, current_fill_level, status, created_at) 
                   VALUES (?, ?, ?, ?, ?, 0, 'normal', NOW())", 
                   [$bin_code, $location_details, $area_id, $bin_capacity, $floor_number]);
            
            $_SESSION['success'] = "Bin added successfully!";
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        header("Location: bin_setup.php");
        exit;
    }
    
    if ($_POST['action'] === 'quick_setup') {
        try {
            // Get an area (or create default one)
            $area = getOne("SELECT area_id FROM areas LIMIT 1");
            $area_id = $area ? $area['area_id'] : null;
            
            if (!$area_id) {
                // Create default area
                query("INSERT INTO areas (area_name, block, description, created_at) VALUES ('Main Campus', 'A', 'Default testing area', NOW())");
                $area_id = lastInsertId();
            }
            
            // Add 5 sample bins with floor numbers
            $sample_bins = [
                ['BIN-A1', 'Library Entrance', 50, 1],
                ['BIN-A2', 'Cafeteria Main Hall', 60, 1],
                ['BIN-B1', 'Student Center', 50, 2],
                ['BIN-B2', 'Lecture Hall Block B', 55, 2],
                ['BIN-C1', 'Sports Complex', 70, 1],
            ];
            
            $count = 0;
            foreach ($sample_bins as $bin) {
                // Check if bin already exists
                $exists = getOne("SELECT bin_id FROM bins WHERE bin_code = ?", [$bin[0]]);
                
                if (!$exists) {
                    query("INSERT INTO bins (bin_code, location_details, area_id, bin_capacity, 
                           floor_number, current_fill_level, status, created_at) 
                           VALUES (?, ?, ?, ?, ?, 0, 'normal', NOW())", 
                           [$bin[0], $bin[1], $area_id, $bin[2], $bin[3]]);
                    $count++;
                }
            }
            
            $_SESSION['success'] = "$count sample bin(s) added successfully!";
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        header("Location: bin_setup.php");
        exit;
    }
}

// Get existing bins
$bins = getAll("SELECT b.*, a.area_name 
                FROM bins b 
                LEFT JOIN areas a ON b.area_id = a.area_id 
                ORDER BY b.bin_code");

// Get areas
$areas = getAll("SELECT * FROM areas ORDER BY area_name");

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bin Setup - EcoBin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #FAF1E4;
            padding: 30px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: #435334;
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
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
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .quick-setup {
            background: #e3f2fd;
            border: 2px solid #2196f3;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .quick-setup h2 {
            color: #1976d2;
            margin-bottom: 15px;
        }

        .quick-setup p {
            color: #555;
            margin-bottom: 15px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .card h2 {
            color: #435334;
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #435334;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #435334;
            color: white;
        }

        .btn-primary:hover {
            background: #354428;
        }

        .btn-large {
            padding: 20px 40px;
            font-size: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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
            background: #e8f5e9;
            color: #2e7d32;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #435334;
            text-decoration: none;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .success-box {
            background: #d4edda;
            border: 2px solid #c3e6cb;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }

        .success-box h2 {
            color: #155724;
            margin-bottom: 15px;
        }

        .success-box p {
            color: #155724;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗑️ Bin Setup</h1>
            <p>Add bins to your system for testing and monitoring</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <span>✅</span>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <span>⚠️</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($bins)): ?>
            <div class="quick-setup">
                <h2>🚀 Quick Start</h2>
                <p>No bins found in the system. Would you like to add 5 sample bins for testing?</p>
                <p><strong>This will create:</strong></p>
                <ul style="margin: 15px 0 20px 20px; color: #555;">
                    <li>BIN-A1 - Library Entrance, Floor 1 (50L)</li>
                    <li>BIN-A2 - Cafeteria Main Hall, Floor 1 (60L)</li>
                    <li>BIN-B1 - Student Center, Floor 2 (50L)</li>
                    <li>BIN-B2 - Lecture Hall Block B, Floor 2 (55L)</li>
                    <li>BIN-C1 - Sports Complex, Floor 1 (70L)</li>
                </ul>
                <form method="POST">
                    <input type="hidden" name="action" value="quick_setup">
                    <button type="submit" class="btn btn-primary btn-large">
                        ✨ Create 5 Sample Bins
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="success-box">
                <h2>✅ Bins Ready!</h2>
                <p>You have <?php echo count($bins); ?> bin(s) in the system.</p>
                <a href="bin_simulator.php" class="btn btn-primary btn-large">
                    🧪 Go to Bin Simulator →
                </a>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>➕ Add New Bin</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_bin">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Bin Code *</label>
                        <input type="text" name="bin_code" required placeholder="e.g., BIN-A1">
                    </div>
                    
                    <div class="form-group">
                        <label>Location *</label>
                        <input type="text" name="location_details" required placeholder="e.g., Library Entrance">
                    </div>
                    
                    <div class="form-group">
                        <label>Floor Number *</label>
                        <input type="number" name="floor_number" required value="1" min="0" max="20">
                    </div>
                    
                    <div class="form-group">
                        <label>Area</label>
                        <select name="area_id">
                            <option value="">No specific area</option>
                            <?php foreach ($areas as $area): ?>
                                <option value="<?php echo $area['area_id']; ?>">
                                    <?php echo htmlspecialchars($area['area_name']); ?>
                                    <?php if ($area['block']): ?>
                                        (Block <?php echo htmlspecialchars($area['block']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Capacity (Liters) *</label>
                        <input type="number" name="bin_capacity" required value="50" min="10" max="200">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    ➕ Add Bin
                </button>
            </form>
        </div>

        <?php if (!empty($bins)): ?>
            <div class="card">
                <h2>📋 Existing Bins (<?php echo count($bins); ?>)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Bin Code</th>
                            <th>Location</th>
                            <th>Floor</th>
                            <th>Area</th>
                            <th>Capacity</th>
                            <th>Fill Level</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bins as $bin): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($bin['bin_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($bin['location_details']); ?></td>
                                <td><?php echo $bin['floor_number'] ?? 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($bin['area_name'] ?? 'N/A'); ?></td>
                                <td><?php echo $bin['bin_capacity']; ?>L</td>
                                <td><?php echo number_format($bin['current_fill_level'], 1); ?>%</td>
                                <td><span class="badge"><?php echo ucfirst($bin['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
</body>
</html>