<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'inventory';

$inventory_id = intval($_GET['id'] ?? 0);

if (!$inventory_id) {
    $_SESSION['error'] = "Invalid inventory item ID";
    header("Location: inventory.php");
    exit;
}

$item = getOne("SELECT * FROM inventory WHERE inventory_id = ?", [$inventory_id]);

if (!$item) {
    $_SESSION['error'] = "Inventory item not found";
    header("Location: inventory.php");
    exit;
}

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Inventory Item - EcoBin</title>
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
            max-width: 1000px;
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
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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

        .form-group label .required {
            color: #dc3545;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #435334;
        }

        .form-group .hint {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #435334;
            color: white;
        }

        .btn-primary:hover {
            background: #354428;
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .info-box h4 {
            color: #1976d2;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .info-box p {
            color: #555;
            font-size: 13px;
            margin: 0;
        }

        .stock-actions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .stock-actions h3 {
            color: #435334;
            font-size: 16px;
            margin-bottom: 15px;
        }

        .stock-action-row {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .stock-action-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .stock-action-row {
                flex-direction: column;
            }

            .stock-action-row .form-group {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Edit Inventory Item</h1>
            <p>Update item details and manage stock levels</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error">
                ⚠ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form action="inventory_actions.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="inventory_id" value="<?php echo $item['inventory_id']; ?>">

                <div class="form-group">
                    <label>Item Name <span class="required">*</span></label>
                    <input type="text" name="item_name" required value="<?php echo htmlspecialchars($item['item_name']); ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Category <span class="required">*</span></label>
                        <select name="item_category" required>
                            <option value="consumable" <?php echo $item['item_category'] === 'consumable' ? 'selected' : ''; ?>>Consumable</option>
                            <option value="equipment" <?php echo $item['item_category'] === 'equipment' ? 'selected' : ''; ?>>Equipment</option>
                            <option value="safety_gear" <?php echo $item['item_category'] === 'safety_gear' ? 'selected' : ''; ?>>Safety Gear</option>
                            <option value="other" <?php echo $item['item_category'] === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Storage Location</label>
                        <input type="text" name="storage_location" value="<?php echo htmlspecialchars($item['storage_location'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Current Quantity <span class="required">*</span></label>
                        <input type="number" name="current_quantity" id="currentQuantity" required min="0" step="1" value="<?php echo $item['current_quantity']; ?>">
                    </div>

                    <div class="form-group">
                        <label>Minimum Quantity (Reorder Level) <span class="required">*</span></label>
                        <input type="number" name="minimum_quantity" id="minimumQuantity" required min="0" step="1" value="<?php echo $item['minimum_quantity']; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Unit <span class="required">*</span></label>
                        <input type="text" name="unit" required value="<?php echo htmlspecialchars($item['unit']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Status <span class="required">*</span></label>
                        <select name="status" id="statusSelect" required>
                            <option value="in_stock" <?php echo $item['status'] === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                            <option value="low_stock" <?php echo $item['status'] === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="out_of_stock" <?php echo $item['status'] === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Item</button>
                    <a href="inventory.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>

            <div class="stock-actions">
                <h3>Quick Restock </h3>

                <div class="stock-action-row">
                    <form action="inventory_actions.php" method="POST" style="flex: 1; display: flex; gap: 10px; align-items: flex-end;">
                        <input type="hidden" name="action" value="restock">
                        <input type="hidden" name="inventory_id" value="<?php echo $item['inventory_id']; ?>">
                        
                        <div class="form-group" style="flex: 1;">
                            <label>Add Stock</label>
                            <input type="number" name="quantity" min="1" step="1" placeholder="Enter quantity to add" required>
                        </div>
                        
                        <button type="submit" class="btn btn-success">Add Stock</button>
                    </form>

                    <form action="inventory_actions.php" method="POST" style="flex: 1; display: flex; gap: 10px; align-items: flex-end;">
                        <input type="hidden" name="action" value="stock_out">
                        <input type="hidden" name="inventory_id" value="<?php echo $item['inventory_id']; ?>">
                        
                        <div class="form-group" style="flex: 1;">
                            <label>Remove Stock</label>
                            <input type="number" name="quantity" min="1" max="<?php echo $item['current_quantity']; ?>" step="1" placeholder="Enter quantity to remove" required>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">Remove Stock</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        const currentQty = document.getElementById('currentQuantity');
        const minQty = document.getElementById('minimumQuantity');
        const statusSelect = document.getElementById('statusSelect');

        function updateStatus() {
            const current = parseInt(currentQty.value) || 0;
            const minimum = parseInt(minQty.value) || 0;

            if (current === 0) {
                statusSelect.value = 'out_of_stock';
                currentQty.style.borderColor = '#dc3545';
            } else if (current <= minimum) {
                statusSelect.value = 'low_stock';
                currentQty.style.borderColor = '#ffc107';
            } else {
                statusSelect.value = 'in_stock';
                currentQty.style.borderColor = '#28a745';
            }
        }

        currentQty.addEventListener('input', updateStatus);
        minQty.addEventListener('input', updateStatus);

        updateStatus();
    </script>
</body>
</html>