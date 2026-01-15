<?php
/**
 * Inventory Item History
 * Shows all transactions for a specific item
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check authentication - admins only
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'inventory';
$inventory_id = intval($_GET['id'] ?? 0);

if (!$inventory_id) {
    $_SESSION['error'] = "Invalid item ID";
    header("Location: inventory.php");
    exit;
}

// Get item details
$item = getOne("SELECT * FROM inventory WHERE inventory_id = ?", [$inventory_id]);
if (!$item) {
    $_SESSION['error'] = "Item not found";
    header("Location: inventory.php");
    exit;
}

// Get all transactions for this item
$transactions = getAll("
    SELECT 
        it.*,
        e.full_name as employee_name,
        e.email as employee_email
    FROM inventory_transactions it
    LEFT JOIN employees e ON it.employee_id = e.employee_id
    WHERE it.inventory_id = ?
    ORDER BY it.created_at DESC
", [$inventory_id]);

// Get statistics
$total_taken = getOne("SELECT COALESCE(SUM(quantity), 0) as total FROM inventory_transactions WHERE inventory_id = ? AND transaction_type = 'take'", [$inventory_id])['total'];
$total_restocked = getOne("SELECT COALESCE(SUM(quantity), 0) as total FROM inventory_transactions WHERE inventory_id = ? AND transaction_type = 'restock'", [$inventory_id])['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Item History - <?php echo htmlspecialchars($item['item_name']); ?> - EcoBin</title>
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

        .item-info {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .item-info h2 {
            color: #435334;
            margin-bottom: 15px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 12px;
            color: #999;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 18px;
            font-weight: 600;
            color: #435334;
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

        .transactions-table {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        .transactions-table h3 {
            color: #435334;
            margin-bottom: 20px;
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

        .type-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .type-take {
            background: #fff3cd;
            color: #856404;
        }

        .type-restock {
            background: #d4edda;
            color: #155724;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Item History</h1>
            <a href="inventory.php" class="btn btn-primary">← Back to Inventory</a>
        </div>

        <!-- Item Info -->
        <div class="item-info">
            <h2><?php echo htmlspecialchars($item['item_name']); ?></h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Current Quantity</div>
                    <div class="info-value"><?php echo $item['current_quantity']; ?> <?php echo htmlspecialchars($item['unit']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Category</div>
                    <div class="info-value"><?php echo ucfirst(str_replace('_', ' ', $item['item_category'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Storage Location</div>
                    <div class="info-value"><?php echo htmlspecialchars($item['storage_location'] ?: '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value"><?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?></div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" style="color: #dc3545;"><?php echo $total_taken; ?></div>
                <div class="stat-label">Total Taken</div>
            </div>

            <div class="stat-card">
                <div class="stat-value" style="color: #28a745;"><?php echo $total_restocked; ?></div>
                <div class="stat-label">Total Restocked</div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?php echo count($transactions); ?></div>
                <div class="stat-label">Total Transactions</div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="transactions-table">
            <h3>Transaction History</h3>
            
            <?php if (empty($transactions)): ?>
                <div class="empty-state">
                    <div style="font-size: 64px; margin-bottom: 20px;">📋</div>
                    <h3>No Transactions Yet</h3>
                    <p>No one has taken or restocked this item</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Before</th>
                            <th>After</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo date('M j, Y g:i A', strtotime($transaction['created_at'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($transaction['employee_name'] ?: 'System'); ?></strong>
                                    <?php if ($transaction['employee_email']): ?>
                                        <br><small style="color: #999;"><?php echo htmlspecialchars($transaction['employee_email']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="type-badge type-<?php echo $transaction['transaction_type']; ?>">
                                        <?php echo ucfirst($transaction['transaction_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: <?php echo $transaction['transaction_type'] === 'take' ? '#dc3545' : '#28a745'; ?>">
                                        <?php echo $transaction['transaction_type'] === 'take' ? '-' : '+'; ?><?php echo $transaction['quantity']; ?>
                                    </strong>
                                </td>
                                <td><?php echo $transaction['previous_quantity']; ?></td>
                                <td><?php echo $transaction['new_quantity']; ?></td>
                                <td><?php echo htmlspecialchars($transaction['reason'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>