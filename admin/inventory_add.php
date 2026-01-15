<?php
/**
 * Add New Inventory Item
 * Form for admins to add new items to inventory
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check authentication - admins only
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Set current page for sidebar
$current_page = 'inventory';

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Inventory Item - EcoBin</title>
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

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Add New Inventory Item</h1>
            <p>Add a new item to the inventory system</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error">
                ⚠ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form action="inventory_actions.php" method="POST">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label>Item Name <span class="required">*</span></label>
                    <input type="text" name="item_name" required placeholder="e.g., Garbage Bags, Safety Gloves">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Category <span class="required">*</span></label>
                        <select name="item_category" required>
                            <option value="">Select category</option>
                            <option value="consumable">Consumable</option>
                            <option value="equipment">Equipment</option>
                            <option value="safety_gear">Safety Gear</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Storage Location</label>
                        <input type="text" name="storage_location" placeholder="e.g., Warehouse A, Shelf 2">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Current Quantity <span class="required">*</span></label>
                        <input type="number" name="current_quantity" required min="0" step="1" value="0">
                    </div>

                    <div class="form-group">
                        <label>Minimum Quantity (Reorder Level) <span class="required">*</span></label>
                        <input type="number" name="minimum_quantity" required min="0" step="1" value="10">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Unit <span class="required">*</span></label>
                        <input type="text" name="unit" required placeholder="e.g., boxes, pieces, liters">
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="in_stock">In Stock</option>
                            <option value="low_stock">Low Stock</option>
                            <option value="out_of_stock">Out of Stock</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">✓ Add Item</button>
                    <a href="inventory.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Auto-calculate status based on quantity
        const currentQty = document.querySelector('input[name="current_quantity"]');
        const minQty = document.querySelector('input[name="minimum_quantity"]');

        function updateStatus() {
            const current = parseInt(currentQty.value) || 0;
            const minimum = parseInt(minQty.value) || 0;

            // Visual feedback (optional)
            if (current === 0) {
                currentQty.style.borderColor = '#dc3545';
            } else if (current <= minimum) {
                currentQty.style.borderColor = '#ffc107';
            } else {
                currentQty.style.borderColor = '#28a745';
            }
        }

        currentQty.addEventListener('input', updateStatus);
        minQty.addEventListener('input', updateStatus);
    </script>
</body>
</html>