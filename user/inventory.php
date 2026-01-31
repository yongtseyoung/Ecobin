<?php


session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';
require_once '../config/languages.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'inventory';

$employee_id = $_SESSION['user_id'];
$employee_name = $_SESSION['full_name'] ?? 'Employee';

$employee = getOne("SELECT e.*, a.area_name 
                    FROM employees e 
                    LEFT JOIN areas a ON e.area_id = a.area_id 
                    WHERE e.employee_id = ?", 
                    [$employee_id]);

$category_filter = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? '';

$where = ["1=1"];
$params = [];

if ($category_filter !== 'all') {
    $where[] = "item_category = ?";
    $params[] = $category_filter;
}

if (!empty($search)) {
    $where[] = "(item_name LIKE ? OR storage_location LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(" AND ", $where);

$inventory_items = getAll("SELECT * FROM inventory WHERE $where_clause ORDER BY item_name", $params);

$my_transactions = getAll("
    SELECT 
        it.*,
        i.item_name,
        i.unit
    FROM inventory_transactions it
    JOIN inventory i ON it.inventory_id = i.inventory_id
    WHERE it.employee_id = ? AND it.transaction_type = 'take'
    ORDER BY it.created_at DESC
    LIMIT 10
", [$employee_id]);

$total_items = getOne("SELECT COUNT(*) as count FROM inventory")['count'];
$in_stock = getOne("SELECT COUNT(*) as count FROM inventory WHERE status = 'in_stock'")['count'];
$low_stock = getOne("SELECT COUNT(*) as count FROM inventory WHERE status = 'low_stock'")['count'];

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
$success_type = $_SESSION['success_type'] ?? '';
$highlight_item = $_SESSION['highlight_item'] ?? 0;
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['success_type'], $_SESSION['highlight_item']);
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('inventory'); ?> - EcoBin</title>
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
            display: flex;
            align-items: center;
            gap: 10px;
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
            transition: all 0.3s;
        }

        .btn-primary {
            background: #435334;
            color: white;
        }

        .btn-primary:hover {
            background: #354428;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #28a745;
            color: white;
            font-size: 13px;
            padding: 8px 16px;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-info {
            background: #2196f3;
            color: white;
            font-size: 13px;
            padding: 8px 16px;
        }

        .btn-info:hover {
            background: #0b7dda;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .icon-main {
            color: #435334;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            animation: slideDown 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
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

        .alert-icon {
            font-size: 20px;
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
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
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

        tr.highlight {
            background: #fff3cd !important;
            animation: highlight 2s ease;
        }

        @keyframes highlight {
            0% { background: #fff3cd; }
            100% { background: white; }
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
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

        .action-buttons {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .take-supply-form {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .take-supply-form input {
            width: 60px;
            padding: 6px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            text-align: center;
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

        .history-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .history-section h2 {
            color: #435334;
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .transaction-item {
            padding: 15px;
            border-left: 4px solid #CEDEBD;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .transaction-details {
            flex: 1;
        }

        .transaction-item strong {
            color: #435334;
            font-size: 15px;
        }

        .transaction-date {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .transaction-quantity {
            font-size: 18px;
            font-weight: 700;
            color: #435334;
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
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-content h3 {
            color: #435334;
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group .hint {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .info-text {
            background: #e3f2fd;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #1976d2;
            margin-bottom: 15px;
            border-left: 3px solid #2196f3;
        }

@media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 80px 15px 20px;
            }

            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-icon {
                font-size: 28px;
            }

            .stat-value {
                font-size: 28px;
            }

            .filters {
                flex-direction: column;
                align-items: stretch;
            }

            .filters input[type="text"] {
                width: 100%;
                min-width: unset;
            }

            .filters select {
                width: 100%;
            }

            .filters .btn {
                width: 100%;
                justify-content: center;
            }

            .inventory-table {
                padding: 15px;
                overflow-x: auto;
            }

            table {
                min-width: 800px;
            }

            th, td {
                padding: 10px 8px;
                font-size: 12px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 8px;
            }

            .take-supply-form {
                flex-direction: row;
                width: 100%;
            }

            .take-supply-form input {
                flex: 1;
            }

            .btn-sm {
                white-space: nowrap;
            }

            .history-section {
                padding: 20px;
            }

            .history-section h2 {
                font-size: 18px;
            }

            .transaction-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .transaction-quantity {
                font-size: 16px;
            }

            .modal-content {
                padding: 20px;
                width: 95%;
            }

            .modal-content h3 {
                font-size: 18px;
            }

            .modal-actions {
                flex-direction: column;
            }

            .modal-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 70px 10px 15px;
            }

            .page-header h1 {
                font-size: 20px;
            }

            .btn {
                padding: 10px 18px;
                font-size: 13px;
            }

            .stats-grid {
                gap: 12px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-icon {
                font-size: 24px;
                margin-bottom: 8px;
            }

            .stat-value {
                font-size: 24px;
            }

            .stat-label {
                font-size: 12px;
            }

            .filters {
                padding: 15px;
                gap: 10px;
            }

            .filters input,
            .filters select {
                padding: 10px;
                font-size: 13px;
            }

            .inventory-table {
                padding: 10px;
            }

            table {
                font-size: 11px;
            }

            th, td {
                padding: 8px 6px;
            }

            .status-badge,
            .category-badge {
                font-size: 10px;
                padding: 3px 8px;
            }

            .take-supply-form input {
                width: 50px;
                padding: 5px;
                font-size: 12px;
            }

            .btn-sm {
                padding: 5px 10px;
                font-size: 11px;
            }

            .alert {
                padding: 12px 15px;
                font-size: 13px;
            }

            .alert-icon {
                font-size: 16px;
            }

            .history-section {
                padding: 15px;
            }

            .history-section h2 {
                font-size: 16px;
            }

            .transaction-item {
                padding: 12px;
            }

            .transaction-item strong {
                font-size: 14px;
            }

            .transaction-date {
                font-size: 11px;
            }

            .transaction-quantity {
                font-size: 14px;
            }

            .modal-content {
                padding: 15px;
            }

            .form-group label {
                font-size: 13px;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 10px;
                font-size: 13px;
            }

            .info-text {
                padding: 10px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>
                <?php echo t('inventory'); ?>
            </h1>
            <a href="supply_requests.php?tab=my-requests" class="btn btn-primary">
                <i class="fa-solid fa-clipboard-list"></i>
                <?php echo t('view_my_requests'); ?>
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success" id="successAlert">
                <i class="fa-solid fa-circle-check alert-icon"></i>
                <div>
                    <strong><?php echo htmlspecialchars($success); ?></strong>
                    <?php if ($success_type === 'request'): ?>
                        <br><small><?php echo t('check_requests_page'); ?> <a href="supply_requests.php?tab=my-requests" style="color: #155724; font-weight: 600; text-decoration: underline;"><?php echo t('my_requests'); ?></a></small>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-triangle-exclamation alert-icon"></i>
                <strong><?php echo htmlspecialchars($error); ?></strong>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-box icon-main"></i></div>
                <div class="stat-value"><?php echo $total_items; ?></div>
                <div class="stat-label"><?php echo t('total_items'); ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-check-circle" style="color:#28a745;"></i></div>
                <div class="stat-value" style="color: #435334;"><?php echo $in_stock; ?></div>
                <div class="stat-label"><?php echo t('available'); ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation" style="color:#ffc107;"></i></div>
                <div class="stat-value" style="color: #435334;"><?php echo $low_stock; ?></div>
                <div class="stat-label"><?php echo t('low_stock'); ?></div>
            </div>
        </div>

        <form method="GET" class="filters">
            <input type="text" name="search" placeholder="<?php echo t('search_items'); ?>..." value="<?php echo htmlspecialchars($search); ?>">
            
            <select name="category">
                <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>><?php echo t('all_categories'); ?></option>
                <option value="consumable" <?php echo $category_filter === 'consumable' ? 'selected' : ''; ?>><?php echo t('consumable'); ?></option>
                <option value="equipment" <?php echo $category_filter === 'equipment' ? 'selected' : ''; ?>><?php echo t('equipment'); ?></option>
                <option value="safety_gear" <?php echo $category_filter === 'safety_gear' ? 'selected' : ''; ?>><?php echo t('safety_gear'); ?></option>
                <option value="other" <?php echo $category_filter === 'other' ? 'selected' : ''; ?>><?php echo t('other'); ?></option>
            </select>

            <button type="submit" class="btn btn-primary">
                <?php echo t('filter'); ?>
            </button>
            <a href="inventory.php" class="btn" style="background: #e0e0e0;">
                <?php echo t('clear'); ?>
            </a>
        </form>

        <div class="inventory-table">
            <?php if (empty($inventory_items)): ?>
                <div class="empty-state">
                    <h3><?php echo t('no_items_found'); ?></h3>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th><?php echo t('item_name'); ?></th>
                            <th><?php echo t('category'); ?></th>
                            <th><?php echo t('storage_location'); ?></th>
                            <th><?php echo t('quantity'); ?></th>
                            <th><?php echo t('min_quantity'); ?></th>
                            <th><?php echo t('unit'); ?></th>
                            <th><?php echo t('status'); ?></th>
                            <th><?php echo t('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory_items as $item): ?>
                            <tr <?php echo ($highlight_item == $item['inventory_id']) ? 'class="highlight"' : ''; ?> id="item-<?php echo $item['inventory_id']; ?>">
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
                                    $status_icon = '';
                                    if ($item['status'] === 'in_stock') {
                                        $status_icon = '<i class="fa-solid fa-check"></i>';
                                    } elseif ($item['status'] === 'low_stock') {
                                        $status_icon = '<i class="fa-solid fa-triangle-exclamation"></i>';
                                    } else {
                                        $status_icon = '<i class="fa-solid fa-xmark"></i>';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_icon . ' ' . $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($item['status'] !== 'out_of_stock'): ?>
                                            <form action="inventory_take.php" method="POST" class="take-supply-form" onsubmit="return confirmTake(this, <?php echo $item['current_quantity']; ?>)">
                                                <input type="hidden" name="action" value="take">
                                                <input type="hidden" name="inventory_id" value="<?php echo $item['inventory_id']; ?>">
                                                <input type="number" name="quantity" min="1" max="<?php echo $item['current_quantity']; ?>" placeholder="<?php echo t('qty'); ?>" required>
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <?php echo t('take'); ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #dc3545; font-size: 12px; margin-right: 10px;">
                                                <?php echo t('out_of_stock'); ?>
                                            </span>
                                        <?php endif; ?>

                                        <button onclick="openRequestModal(<?php echo $item['inventory_id']; ?>, '<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>', <?php echo $item['current_quantity']; ?>, '<?php echo htmlspecialchars($item['unit'], ENT_QUOTES); ?>')" class="btn btn-info btn-sm">
                                            <?php echo t('request'); ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if (!empty($my_transactions)): ?>
            <div class="history-section">
                <h2>
                    <?php echo t('my_recent_transactions'); ?>
                </h2>
                <?php foreach ($my_transactions as $transaction): ?>
                    <div class="transaction-item">
                        <div class="transaction-details">
                            <strong><?php echo htmlspecialchars($transaction['item_name']); ?></strong>
                            <div class="transaction-date">
                                <i class="fa-solid fa-calendar"></i>
                                <?php echo date('M j, Y g:i A', strtotime($transaction['created_at'])); ?>
                            </div>
                        </div>
                        <div class="transaction-quantity">
                            -<?php echo $transaction['quantity']; ?> <?php echo htmlspecialchars($transaction['unit']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <div id="requestModal" class="modal">
        <div class="modal-content">
            <h3>
                <i class="fa-solid fa-clipboard-list"></i>
                <?php echo t('request_restock'); ?>
            </h3>
    
            <form action="supply_request_actions.php" method="POST">
                <input type="hidden" name="action" value="request">
                <input type="hidden" name="inventory_id" id="modal_inventory_id">
                <input type="hidden" name="from_inventory" value="1">

                <div class="info-text">
                    <strong id="modal_item_name"></strong><br>
                    <?php echo t('currently_available'); ?>: <strong><span id="modal_available">0</span> <span id="modal_unit"><?php echo t('units'); ?></span></strong><br>
                </div>

                <div class="form-group">
                    <label>
                        <?php echo t('quantity_to_restock'); ?> <span style="color: #dc3545;">*</span>
                    </label>
                    <input type="number" name="quantity_requested" id="modal_quantity" required min="1" placeholder="<?php echo t('enter_quantity_restock'); ?>">
                </div>

                <div class="form-group">
                    <label><?php echo t('urgency'); ?> <span style="color: #dc3545;">*</span></label>
                    <select name="urgency" required>
                        <option value="low"><?php echo t('low'); ?></option>
                        <option value="medium" selected><?php echo t('medium'); ?></option>
                        <option value="high"><?php echo t('high'); ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label><?php echo t('reason_optional_recommended'); ?></label>
                    <textarea name="employee_reason" rows="3" placeholder="<?php echo t('why_need_this'); ?>"></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" onclick="closeRequestModal()" class="btn" style="background: #e0e0e0;">
                        <i class="fa-solid fa-xmark"></i>
                        <?php echo t('cancel'); ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-paper-plane"></i>
                        <?php echo t('submit_request'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const translations = {
            onlyAvailable: "<?php echo t('only_units_available'); ?>",
            takeConfirm: "<?php echo t('take_confirm'); ?>",
            unit: "<?php echo t('unit'); ?>",
            units: "<?php echo t('units'); ?>"
        };

        <?php if ($success): ?>
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
        setTimeout(() => {
            const alert = document.getElementById('successAlert');
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
        <?php endif; ?>

        <?php if ($highlight_item): ?>
        setTimeout(() => {
            const item = document.getElementById('item-<?php echo $highlight_item; ?>');
            if (item) {
                item.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 300);
        <?php endif; ?>

        function confirmTake(form, availableStock) {
            const quantity = parseInt(form.querySelector('input[name="quantity"]').value);
            const itemName = form.closest('tr').querySelector('strong').textContent;
            
            if (quantity > availableStock) {
                alert(translations.onlyAvailable.replace('{quantity}', availableStock));
                return false;
            }
            
            const unitText = quantity === 1 ? translations.unit : translations.units;
            return confirm(translations.takeConfirm.replace('{quantity}', quantity).replace('{unit}', unitText).replace('{item}', itemName));
        }

        function openRequestModal(inventoryId, itemName, available, unit) {
            document.getElementById('modal_inventory_id').value = inventoryId;
            document.getElementById('modal_item_name').textContent = itemName;
            document.getElementById('modal_available').textContent = available;
            document.getElementById('modal_unit').textContent = unit;
            document.getElementById('requestModal').classList.add('active');
        }

        function closeRequestModal() {
            document.getElementById('requestModal').classList.remove('active');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('requestModal');
            if (event.target === modal) {
                closeRequestModal();
            }
        }
    </script>
</body>
</html>