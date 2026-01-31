<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'Supply Requests';

$status_filter = $_GET['status'] ?? 'all';
$urgency_filter = $_GET['urgency'] ?? 'all';

$where = ["1=1"];
$params = [];

if ($status_filter !== 'all') {
    $where[] = "sr.status = ?";
    $params[] = $status_filter;
}

if ($urgency_filter !== 'all') {
    $where[] = "sr.urgency = ?";
    $params[] = $urgency_filter;
}

$where_clause = implode(" AND ", $where);

$supply_requests = getAll("
    SELECT 
        sr.*,
        e.full_name as employee_name,
        e.email as employee_email,
        i.item_name,
        i.unit,
        i.current_quantity,
        a.full_name as reviewer_name
    FROM supply_requests sr
    JOIN employees e ON sr.employee_id = e.employee_id
    JOIN inventory i ON sr.inventory_id = i.inventory_id
    LEFT JOIN admins a ON sr.reviewed_by = a.admin_id
    WHERE $where_clause
    ORDER BY 
        CASE sr.status 
            WHEN 'pending' THEN 1 
            WHEN 'approved' THEN 2 
            WHEN 'fulfilled' THEN 3 
            WHEN 'rejected' THEN 4 
        END,
        sr.urgency DESC,
        sr.requested_at DESC
", $params);

$total_requests = getOne("SELECT COUNT(*) as count FROM supply_requests")['count'];
$pending_requests = getOne("SELECT COUNT(*) as count FROM supply_requests WHERE status = 'pending'")['count'];
$fulfilled_requests = getOne("SELECT COUNT(*) as count FROM supply_requests WHERE status = 'fulfilled'")['count'];
$rejected_requests = getOne("SELECT COUNT(*) as count FROM supply_requests WHERE status = 'rejected'")['count'];

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supply Requests - EcoBin</title>
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

        .btn-success {
            background: #28a745;
            color: white;
            font-size: 13px;
            padding: 8px 16px;
        }

        .btn-danger {
            background: #dc3545;
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

        .filters select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }

        .requests-table {
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

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #cfe2ff;
            color: #084298;
        }

        .status-fulfilled {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .urgency-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }

        .urgency-low {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .urgency-medium {
            background: #fff3cd;
            color: #856404;
        }

        .urgency-high {
            background: #f8d7da;
            color: #721c24;
        }

        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
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

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
        }

        .modal-content h3 {
            color: #435334;
            margin-bottom: 20px;
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

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            min-height: 100px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

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
            <h1>Supply Requests</h1>
            <a href="inventory.php" class="btn btn-primary">← Back to Inventory</a>
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

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?php echo $total_requests; ?></div>
        <div class="stat-label">Total Requests</div>
    </div>

    <div class="stat-card">
        <div class="stat-value" style="color: #435334;"><?php echo $pending_requests; ?></div>
        <div class="stat-label">Pending</div>
    </div>

    <div class="stat-card">
        <div class="stat-value" style="color: #435334;"><?php echo $fulfilled_requests; ?></div>
        <div class="stat-label">Fulfilled</div>
    </div>

    <div class="stat-card">
        <div class="stat-value" style="color: #435334;"><?php echo $rejected_requests; ?></div>
        <div class="stat-label">Rejected</div>
    </div>
</div>

        <form method="GET" class="filters">
            <select name="status">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="fulfilled" <?php echo $status_filter === 'fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>

            <select name="urgency">
                <option value="all" <?php echo $urgency_filter === 'all' ? 'selected' : ''; ?>>All Urgency</option>
                <option value="low" <?php echo $urgency_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                <option value="medium" <?php echo $urgency_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="high" <?php echo $urgency_filter === 'high' ? 'selected' : ''; ?>>High</option>
            </select>

            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="supply_requests.php" class="btn" style="background: #e0e0e0;">Clear</a>
        </form>

        <div class="requests-table">
            <?php if (empty($supply_requests)): ?>
                <div class="empty-state">
                    <h3>No Supply Requests</h3>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Employee</th>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Available</th>
                            <th>Urgency</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($supply_requests as $request): ?>
                            <tr>
                                <td><strong>#<?php echo $request['request_id']; ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['employee_name']); ?></strong><br>
                                    <small style="color: #999;"><?php echo htmlspecialchars($request['employee_email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($request['item_name']); ?></td>
                                <td><strong><?php echo $request['quantity_requested']; ?> <?php echo htmlspecialchars($request['unit']); ?></strong></td>
                                <td>
                                    <span style="color: <?php echo $request['current_quantity'] >= $request['quantity_requested'] ? '#28a745' : '#dc3545'; ?>">
                                        <?php echo $request['current_quantity']; ?> <?php echo htmlspecialchars($request['unit']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $urgency_class = 'urgency-' . $request['urgency'];
                                    ?>
                                    <span class="urgency-badge <?php echo $urgency_class; ?>">
                                        <?php echo ucfirst($request['urgency']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status_class = 'status-' . $request['status'];
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($request['requested_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <button onclick="fulfillRequest(<?php echo $request['request_id']; ?>, '<?php echo htmlspecialchars($request['item_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($request['unit'], ENT_QUOTES); ?>')" class="btn btn-warning">📦 Fulfill Restock</button>
                                        <?php elseif ($request['status'] === 'fulfilled'): ?>
                                            <span style="color: #28a745; font-size: 12px; font-weight: 600;">
                                                ✓ Restocked <?php echo $request['quantity_restocked']; ?> <?php echo htmlspecialchars($request['unit']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 12px;">
                                                <?php echo $request['reviewer_name'] ? 'By ' . htmlspecialchars($request['reviewer_name']) : 'Processed'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <script>
function fulfillRequest(requestId, itemName, unit) {
    const quantity = prompt(`How many ${unit} of ${itemName} did you restock?`);
    
    if (quantity !== null && quantity !== '') {
        const qty = parseInt(quantity);
        
        if (qty <= 0 || isNaN(qty)) {
            alert('Please enter a valid quantity!');
            return;
        }
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'supply_actions.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'fulfill';
        
        const requestInput = document.createElement('input');
        requestInput.type = 'hidden';
        requestInput.name = 'request_id';
        requestInput.value = requestId;
        
        const quantityInput = document.createElement('input');
        quantityInput.type = 'hidden';
        quantityInput.name = 'quantity_restocked';
        quantityInput.value = qty;
        
        form.appendChild(actionInput);
        form.appendChild(requestInput);
        form.appendChild(quantityInput);
        document.body.appendChild(form);
        form.submit();
    }
}

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>