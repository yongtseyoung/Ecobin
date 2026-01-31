<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'bins';

$areas = getAll("SELECT area_id, area_name, block FROM areas ORDER BY area_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bin_code = trim($_POST['bin_code']);
    $area_id = $_POST['area_id'];
    $floor_number = $_POST['floor_number'];
    $location_details = trim($_POST['location_details']);
    $bin_capacity = $_POST['bin_capacity'];
    $max_weight = $_POST['max_weight'];
    
    $errors = [];
    
    if (empty($bin_code)) {
        $errors[] = "Bin code is required";
    } else {
        $existing = getOne("SELECT bin_id FROM bins WHERE bin_code = ?", [$bin_code]);
        if ($existing) {
            $errors[] = "Bin code already exists. Please use a different code.";
        }
    }
    
    if (empty($area_id)) {
        $errors[] = "Area is required";
    }
    
    if (empty($floor_number) || $floor_number < 1 || $floor_number > 10) {
        $errors[] = "Floor number must be between 1 and 10";
    }
    
    if (empty($location_details)) {
        $errors[] = "Location details are required";
    }
    
    if (empty($bin_capacity) || $bin_capacity <= 0) {
        $errors[] = "Bin capacity must be greater than 0";
    }
    
    if (empty($max_weight) || $max_weight <= 0) {
        $errors[] = "Max weight must be greater than 0";
    }
    
    if (empty($errors)) {
        try {
            query("
                INSERT INTO bins 
                (area_id, bin_code, floor_number, location_details, bin_capacity, 
                 max_weight, current_fill_level, current_weight, battery_level, 
                 status, lid_status)
                VALUES (?, ?, ?, ?, ?, ?, 0, 0, 100, 'normal', 'closed')
            ", [
                $area_id,
                $bin_code,
                $floor_number,
                $location_details,
                $bin_capacity,
                $max_weight
            ]);
            
            $_SESSION['success'] = "Bin '$bin_code' created successfully! ESP32 can now connect using this bin code.";
            header("Location: bins.php");
            exit;
            
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <title>Create New Bin - EcoBin</title>
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

        .breadcrumb {
            display: flex;
            gap: 10px;
            align-items: center;
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
        }

        .breadcrumb a {
            color: #435334;
            text-decoration: none;
            font-weight: 600;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .form-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 800px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-error ul {
            margin: 10px 0 0 20px;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            color: #435334;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #CEDEBD;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 600;
            color: #435334;
            margin-bottom: 8px;
        }

        .form-group label .required {
            color: #e74c3c;
            margin-left: 3px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #435334;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group small {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        .btn {
            padding: 12px 30px;
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

        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #435334;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .info-box h4 {
            color: #435334;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .info-box ul {
            margin-left: 20px;
            font-size: 13px;
            color: #666;
            line-height: 1.8;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }

            .form-container {
                padding: 25px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="breadcrumb">
            <a href="bins.php">← Back to Bins</a>
            <span>/</span>
            <span>Create New Bin</span>
        </div>

        <div class="page-header">
            <h1>Create New Bin</h1>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>Please fix the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" action="">
                <div class="form-section">
                    <h3>Bin Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>
                                Bin Code
                                <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   name="bin_code" 
                                   value="<?php echo htmlspecialchars($_POST['bin_code'] ?? ''); ?>"
                                   placeholder="e.g., BIN-ESP-32, BIN-A1-F1" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label>
                                Area/Block
                                <span class="required">*</span>
                            </label>
                            <select name="area_id" required>
                                <option value="">Select Area</option>
                                <?php foreach ($areas as $area): ?>
                                    <option value="<?php echo $area['area_id']; ?>"
                                            <?php echo (isset($_POST['area_id']) && $_POST['area_id'] == $area['area_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($area['area_name']); ?>
                                        <?php if ($area['block']): ?>
                                            (Block <?php echo htmlspecialchars($area['block']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>
                                Floor Number
                                <span class="required">*</span>
                            </label>
                            <input type="number" 
                                   name="floor_number" 
                                   value="<?php echo htmlspecialchars($_POST['floor_number'] ?? '1'); ?>"
                                   min="1" 
                                   max="10" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label>
                                Bin Capacity (Liters)
                                <span class="required">*</span>
                            </label>
                            <input type="number" 
                                   name="bin_capacity" 
                                   value="<?php echo htmlspecialchars($_POST['bin_capacity'] ?? '50'); ?>"
                                   step="0.01" 
                                   min="0.01" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label>
                                Max Weight (kg)
                                <span class="required">*</span>
                            </label>
                            <input type="number" 
                                   name="max_weight" 
                                   value="<?php echo htmlspecialchars($_POST['max_weight'] ?? '50'); ?>"
                                   step="0.01" 
                                   min="0.01" 
                                   required>
                        </div>

                        <div class="form-group full-width">
                            <label>
                                Location Details
                                <span class="required">*</span>
                            </label>
                            <textarea name="location_details" 
                                      placeholder="e.g., Near the main entrance, beside the vending machine"
                                      required><?php echo htmlspecialchars($_POST['location_details'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="bins.php" class="btn btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Create Bin
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>