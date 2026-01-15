<?php
/**
 * Inventory Management Dashboard
 * Admin view of all inventory items with stock levels
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

// Get filters
$category_filter = $_GET['category'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where = ["1=1"];
$params = [];

if ($category_filter !== 'all') {
    $where[] = "item_category = ?";
    $params[] = $category_filter;
}

if ($status_filter !== 'all') {
    $where[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where[] = "(item_name LIKE ? OR storage_location LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(" AND ", $where);

// Get inventory items
$inventory_items = getAll("SELECT * FROM inventory WHERE $where_clause ORDER BY item_name", $params);

// Get statistics
$total_items = getOne("SELECT COUNT(*) as count FROM inventory")['count'];
$low_stock = getOne("SELECT COUNT(*) as count FROM inventory WHERE status = 'low_stock'")['count'];
$out_of_stock = getOne("SELECT COUNT(*) as count FROM inventory WHERE status = 'out_of_stock'")['count'];
$in_stock = getOne("SELECT COUNT(*) as count FROM inventory WHERE status = 'in_stock'")['count'];

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - EcoBin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

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
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #435334;
            color: white;
        }

        .btn-primary:hover {
            background: #354428;
        }

        .btn-success {
            background: #28a745;
            color: white;
            font-size: 13px;
            padding: 8px 16px;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
            font-size: 13px;
            padding: 8px 16px;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            font-size: 13px;
            padding: 8px 16px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #435334;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #999;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filters select,
        .filters input {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }

        .filters input[type="text"] {
            flex: 1;
            min-width: 200px;
        }

        .inventory-table {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            border-bottom: 2px solid #f0f0f0;
        }

        td {
            padding: 15px 12px;
            border-bottom: 1px solid #f5f5f5;
            font-size: 14px;
        }

        tr:hover {
            background: #fafafa;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-in-stock {
            background: #d4edda;
            color: #155724;
        }

        .status-low-stock {
            background: #fff3cd;
            color: #856404;
        }

        .status-out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }

        .category-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            background: #e8f5e9;
            color: #2e7d32;
        }

        .actions {
            display: flex;
            gap: 5px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
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

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters {
                flex-direction: column;
            }

            .filters input[type="text"] {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Inventory Management</h1>
            <a href="inventory_add.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add New Item</a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✓ <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                ⚠ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_items; ?></div>
                <div class="stat-label">Total Items</div>
            </div>

            <div class="stat-card">
                <div class="stat-value" style="color: #28a745;"><?php echo $in_stock; ?></div>
                <div class="stat-label">In Stock</div>
            </div>

            <div class="stat-card">
                <div class="stat-value" style="color: #ffc107;"><?php echo $low_stock; ?></div>
                <div class="stat-label">Low Stock Alerts</div>
            </div>

            <div class="stat-card">
                <div class="stat-value" style="color: #dc3545;"><?php echo $out_of_stock; ?></div>
                <div class="stat-label">Out of Stock</div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="filters">
            <input type="text" name="search" placeholder="Search items..." value="<?php echo htmlspecialchars($search); ?>">
            
            <select name="category">
                <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                <option value="consumable" <?php echo $category_filter === 'consumable' ? 'selected' : ''; ?>>Consumable</option>
                <option value="equipment" <?php echo $category_filter === 'equipment' ? 'selected' : ''; ?>>Equipment</option>
                <option value="safety_gear" <?php echo $category_filter === 'safety_gear' ? 'selected' : ''; ?>>Safety Gear</option>
                <option value="other" <?php echo $category_filter === 'other' ? 'selected' : ''; ?>>Other</option>
            </select>

            <select name="status">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="in_stock" <?php echo $status_filter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                <option value="low_stock" <?php echo $status_filter === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                <option value="out_of_stock" <?php echo $status_filter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
            </select>

            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="inventory.php" class="btn" style="background: #e0e0e0;">Clear</a>
        </form>

        <!-- Inventory Table -->
        <div class="inventory-table">
            <?php if (empty($inventory_items)): ?>
                <div class="empty-state">
                    <h3>No Items Found</h3>
                    <a href="inventory_add.php" class="btn btn-primary" style="margin-top: 20px;">➕ Add Item</a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Storage Location</th>
                            <th>Quantity</th>
                            <th>Min. Quantity</th>
                            <th>Unit</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory_items as $item): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                <td>
                                    <span class="category-badge">
                                        <?php echo ucfirst(str_replace('_', ' ', $item['item_category'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($item['storage_location'] ?: '-'); ?></td>
                                <td>
                                    <strong style="font-size: 16px; color: <?php echo $item['current_quantity'] <= $item['minimum_quantity'] ? '#dc3545' : '#435334'; ?>">
                                        <?php echo $item['current_quantity']; ?>
                                    </strong>
                                </td>
                                <td><?php echo $item['minimum_quantity']; ?></td>
                                <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                <td>
                                    <?php
                                    $status_class = 'status-' . str_replace('_', '-', $item['status']);
                                    $status_text = ucfirst(str_replace('_', ' ', $item['status']));
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="inventory_history.php?id=<?php echo $item['inventory_id']; ?>" class="btn btn-sm" style="background: #2196f3; color: white;">History</a>
                                        <a href="inventory_edit.php?id=<?php echo $item['inventory_id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                        <form action="inventory_actions.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="inventory_id" value="<?php echo $item['inventory_id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>