<?php
/**
 * Employee Supply Requests
 * View and manage supply requests that require admin approval
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';
require_once '../config/languages.php';

// Check authentication - employees only
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

// Set current page for sidebar
$current_page = 'inventory';

$employee_id = $_SESSION['user_id'];
$employee_name = $_SESSION['full_name'] ?? 'Employee';

// Get employee details and load language preference
$employee = getOne("SELECT e.*, a.area_name 
                    FROM employees e 
                    LEFT JOIN areas a ON e.area_id = a.area_id 
                    WHERE e.employee_id = ?", 
                    [$employee_id]);


// Get active tab
$active_tab = $_GET['tab'] ?? 'my-requests';

// Get available inventory items for request form
$available_items = getAll("SELECT * FROM inventory WHERE status IN ('in_stock', 'low_stock') ORDER BY item_name");

// Get filter for requests
$status_filter = $_GET['status'] ?? 'all';

// Build query for my requests
$where = ["sr.employee_id = ?"];
$params = [$employee_id];

if ($status_filter !== 'all') {
    $where[] = "sr.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(" AND ", $where);

// Get employee's supply requests (exclude self-service takes)
$my_requests = getAll("
    SELECT 
        sr.*,
        i.item_name,
        i.unit,
        i.current_quantity,
        a.full_name as reviewer_name
    FROM supply_requests sr
    JOIN inventory i ON sr.inventory_id = i.inventory_id
    LEFT JOIN admins a ON sr.reviewed_by = a.admin_id
    WHERE $where_clause AND (sr.employee_reason IS NULL OR sr.employee_reason != 'Self-service take')
    ORDER BY sr.requested_at DESC
", $params);

// Get statistics
$total_requests = getOne("SELECT COUNT(*) as count FROM supply_requests WHERE employee_id = ? AND (employee_reason IS NULL OR employee_reason != 'Self-service take')", [$employee_id])['count'];
$pending_requests = getOne("SELECT COUNT(*) as count FROM supply_requests WHERE employee_id = ? AND status = 'pending' AND (employee_reason IS NULL OR employee_reason != 'Self-service take')", [$employee_id])['count'];
$approved_requests = getOne("SELECT COUNT(*) as count FROM supply_requests WHERE employee_id = ? AND status = 'approved' AND (employee_reason IS NULL OR employee_reason != 'Self-service take')", [$employee_id])['count'];
$fulfilled_requests = getOne("SELECT COUNT(*) as count FROM supply_requests WHERE employee_id = ? AND status = 'fulfilled' AND (employee_reason IS NULL OR employee_reason != 'Self-service take')", [$employee_id])['count'];

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('supply_requests'); ?> - EcoBin</title>
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
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #435334;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header p {
            color: #666;
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
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Tab Navigation */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            background: white;
            padding: 10px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .tab-btn {
            flex: 1;
            padding: 15px 20px;
            border: none;
            background: transparent;
            color: #666;
            font-size: 15px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .tab-btn:hover {
            background: #f8f9fa;
        }

        .tab-btn.active {
            background: #435334;
            color: white;
        }

        .tab-btn .badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Request Form Styles */
        .request-form-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 800px;
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #435334;
            color: white;
        }

        .btn-primary:hover {
            background: #354428;
            transform: translateY(-2px);
        }

        .stock-info-box {
            background: #fff3cd;
            padding: 15px;
            border-radius: 10px;
            margin-top: 10px;
            display: none;
        }

        .stock-info-box.show {
            display: block;
        }

        .stock-info-box .label {
            font-size: 12px;
            color: #856404;
            margin-bottom: 5px;
        }

        .stock-info-box .value {
            font-size: 24px;
            font-weight: 700;
            color: #856404;
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

        .info-box ul {
            margin-left: 20px;
            color: #555;
            font-size: 13px;
        }

        .info-box li {
            margin: 5px 0;
        }

        /* My Requests Styles */
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
            color: #435334;
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

        .request-card {
            background: white;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .request-card:hover {
            border-color: #CEDEBD;
            box-shadow: 0 3px 10px rgba(67, 83, 52, 0.1);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .request-title {
            font-size: 18px;
            font-weight: 600;
            color: #435334;
        }

        .request-id {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 14px;
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
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
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

        .request-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 12px;
            color: #999;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .detail-value {
            font-size: 15px;
            font-weight: 600;
            color: #435334;
        }

        .request-reason {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #555;
            margin-bottom: 15px;
            border-left: 3px solid #CEDEBD;
        }

        .request-reason .label {
            font-weight: 600;
            color: #435334;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .admin-response {
            background: #e3f2fd;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #555;
            border-left: 3px solid #2196f3;
        }

        .admin-response .label {
            font-weight: 600;
            color: #1976d2;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .request-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
            font-size: 12px;
            color: #999;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            background: white;
            border-radius: 15px;
        }

        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .tabs {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1>
                    <i class="fa-solid fa-clipboard-list"></i>
                    <?php echo t('supply_requests'); ?>
                </h1>
            </div>
            <a href="inventory.php" class="btn btn-primary">
                <i class="fa-solid fa-arrow-left"></i>
                <?php echo t('back_to_inventory'); ?>
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="tabs">
            <button class="tab-btn <?php echo $active_tab === 'request' ? 'active' : ''; ?>" onclick="switchTab('request')">
                <i class="fa-solid fa-plus"></i>
                <?php echo t('new_request'); ?>
            </button>
            <button class="tab-btn <?php echo $active_tab === 'my-requests' ? 'active' : ''; ?>" onclick="switchTab('my-requests')">
                <i class="fa-solid fa-clipboard-list"></i>
                <?php echo t('my_requests'); ?>
                <?php if ($pending_requests > 0): ?>
                    <span class="badge"><?php echo $pending_requests; ?></span>
                <?php endif; ?>
            </button>
        </div>

        <!-- Tab 1: New Request Form -->
        <div id="request" class="tab-content <?php echo $active_tab === 'request' ? 'active' : ''; ?>">
            <div class="request-form-card">
                <h2 style="color: #435334; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-pen-to-square"></i>
                    <?php echo t('submit_supply_request'); ?>
                </h2>

                <form action="supply_request_actions.php" method="POST">
                    <input type="hidden" name="action" value="request">

                    <div class="form-group">
                        <label>
                            <?php echo t('select_item'); ?> <span class="required">*</span>
                        </label>
                        <select name="inventory_id" id="itemSelect" required onchange="updateStockInfo()">
                            <option value=""><?php echo t('choose_item'); ?>...</option>
                            <?php foreach ($available_items as $item): ?>
                                <option value="<?php echo $item['inventory_id']; ?>" 
                                        data-quantity="<?php echo $item['current_quantity']; ?>"
                                        data-unit="<?php echo htmlspecialchars($item['unit']); ?>"
                                        data-name="<?php echo htmlspecialchars($item['item_name']); ?>">
                                    <?php echo htmlspecialchars($item['item_name']); ?> 
                                    (<?php echo $item['current_quantity']; ?> <?php echo htmlspecialchars($item['unit']); ?> <?php echo t('available'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="stockInfo" class="stock-info-box">
                        <div class="label"><?php echo t('available_stock'); ?></div>
                        <div class="value"><span id="stockQuantity">0</span> <span id="stockUnit"><?php echo t('units'); ?></span></div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <?php echo t('quantity_requested'); ?> <span class="required">*</span>
                            </label>
                            <input type="number" name="quantity_requested" id="quantityInput" required min="1" step="1" placeholder="<?php echo t('enter_quantity'); ?>">
                        </div>

                        <div class="form-group">
                            <label>
                                <?php echo t('urgency'); ?> <span class="required">*</span>
                            </label>
                            <select name="urgency" required>
                                <option value="low"><?php echo t('urgency_low_desc'); ?></option>
                                <option value="medium" selected><?php echo t('urgency_medium_desc'); ?></option>
                                <option value="high"><?php echo t('urgency_high_desc'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <?php echo t('reason_optional_recommended'); ?>
                        </label>
                        <textarea name="employee_reason" rows="4" placeholder="<?php echo t('reason_placeholder'); ?>"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-paper-plane"></i>
                        <?php echo t('submit_request'); ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Tab 2: My Requests -->
        <div id="my-requests" class="tab-content <?php echo $active_tab === 'my-requests' ? 'active' : ''; ?>">
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                    <div class="stat-value"><?php echo $total_requests; ?></div>
                    <div class="stat-label"><?php echo t('total_requests'); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                    <div class="stat-value" style="color: #737373ff;"><?php echo $pending_requests; ?></div>
                    <div class="stat-label"><?php echo t('pending'); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="stat-value" style="color: #737373ff;"><?php echo $approved_requests; ?></div>
                    <div class="stat-label"><?php echo t('approved'); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-star"></i></div>
                    <div class="stat-value" style="color: #737373ff;"><?php echo $fulfilled_requests; ?></div>
                    <div class="stat-label"><?php echo t('fulfilled'); ?></div>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" class="filters">
                <input type="hidden" name="tab" value="my-requests">
                <select name="status">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>><?php echo t('all_requests'); ?></option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>><?php echo t('pending'); ?></option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>><?php echo t('approved'); ?></option>
                    <option value="fulfilled" <?php echo $status_filter === 'fulfilled' ? 'selected' : ''; ?>><?php echo t('fulfilled'); ?></option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>><?php echo t('rejected'); ?></option>
                </select>

                <button type="submit" class="btn btn-primary">
                    <?php echo t('filter'); ?>
                </button>
                <a href="supply_requests.php?tab=my-requests" class="btn" style="background: #e0e0e0;">
                    <?php echo t('clear'); ?>
                </a>
            </form>

            <!-- Requests List -->
            <?php if (empty($my_requests)): ?>
                <div class="empty-state">
                    <div class="icon"><i class="fa-solid fa-box-open icon-main"></i></div>
                    <h3><?php echo t('no_supply_requests_yet'); ?></h3>
                    <p><?php echo t('havent_submitted_requests'); ?></p>
                    <button class="btn btn-primary" style="margin-top: 20px;" onclick="switchTab('request')">
                        <i class="fa-solid fa-plus"></i>
                        <?php echo t('create_first_request'); ?>
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($my_requests as $request): ?>
                    <div class="request-card">
                        <div class="request-header">
                            <div>
                                <div class="request-title">
                                    <?php echo htmlspecialchars($request['item_name']); ?>
                                    <span class="urgency-badge urgency-<?php echo $request['urgency']; ?>">
                                        <?php 
                                        if ($request['urgency'] === 'high') {
                                            echo '<i class="fa-solid fa-circle-exclamation"></i>';
                                        } elseif ($request['urgency'] === 'medium') {
                                            echo '<i class="fa-solid fa-circle"></i>';
                                        } else {
                                            echo '<i class="fa-solid fa-circle-dot"></i>';
                                        }
                                        ?>
                                        <?php echo ucfirst($request['urgency']); ?> <?php echo t('priority'); ?>
                                    </span>
                                </div>
                                <div class="request-id"><?php echo t('request'); ?> #<?php echo $request['request_id']; ?></div>
                            </div>
                            <span class="status-badge status-<?php echo $request['status']; ?>">
                                <?php 
                                if ($request['status'] === 'fulfilled') {
                                    echo '<i class="fa-solid fa-check"></i>';
                                } elseif ($request['status'] === 'approved') {
                                    echo '<i class="fa-solid fa-thumbs-up"></i>';
                                } elseif ($request['status'] === 'rejected') {
                                    echo '<i class="fa-solid fa-xmark"></i>';
                                } else {
                                    echo '<i class="fa-solid fa-hourglass-half"></i>';
                                }
                                ?>
                                <?php echo ucfirst($request['status']); ?>
                            </span>
                        </div>

                        <div class="request-details">
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fa-solid fa-hashtag"></i>
                                    <?php echo t('quantity_requested'); ?>
                                </div>
                                <div class="detail-value"><?php echo $request['quantity_requested']; ?> <?php echo htmlspecialchars($request['unit']); ?></div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fa-solid fa-box"></i>
                                    <?php echo t('current_stock'); ?>
                                </div>
                                <div class="detail-value" style="color: <?php echo $request['current_quantity'] >= $request['quantity_requested'] ? '#28a745' : '#dc3545'; ?>">
                                    <?php echo $request['current_quantity']; ?> <?php echo htmlspecialchars($request['unit']); ?>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fa-solid fa-calendar"></i>
                                    <?php echo t('requested_date'); ?>
                                </div>
                                <div class="detail-value"><?php echo date('M j, Y', strtotime($request['requested_at'])); ?></div>
                            </div>

                            <?php if ($request['reviewed_at']): ?>
                                <div class="detail-item">
                                    <div class="detail-label">
                                        <i class="fa-solid fa-calendar-check"></i>
                                        <?php echo t('processed_date'); ?>
                                    </div>
                                    <div class="detail-value"><?php echo date('M j, Y', strtotime($request['reviewed_at'])); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($request['employee_reason'])): ?>
                            <div class="request-reason">
                                <div class="label">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                    <?php echo t('your_reason'); ?>:
                                </div>
                                <?php echo nl2br(htmlspecialchars($request['employee_reason'])); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($request['admin_response'])): ?>
                            <div class="admin-response">
                                <div class="label">
                                    <i class="fa-solid fa-comment-dots"></i>
                                    <?php echo t('admin_response'); ?>:
                                </div>
                                <?php echo nl2br(htmlspecialchars($request['admin_response'])); ?>
                            </div>
                        <?php endif; ?>

                        <div class="request-footer">
                            <div>
                                <?php if ($request['reviewer_name']): ?>
                                    <i class="fa-solid fa-user-check"></i>
                                    <?php echo t('reviewed_by'); ?>: <strong><?php echo htmlspecialchars($request['reviewer_name']); ?></strong>
                                <?php else: ?>
                                    <i class="fa-solid fa-hourglass-half"></i>
                                    <?php echo t('awaiting_admin_review'); ?>
                                <?php endif; ?>
                            </div>

                            <div>
                                <?php if ($request['status'] === 'fulfilled'): ?>
                                    <i class="fa-solid fa-circle-check"></i> <strong style="color: #28a745;"><?php echo t('item_restocked'); ?></strong>
                                <?php elseif ($request['status'] === 'approved'): ?>
                                    <i class="fa-solid fa-hourglass-half"></i> <strong style="color: #2196f3;"><?php echo t('awaiting_fulfillment'); ?></strong>
                                <?php elseif ($request['status'] === 'pending'): ?>
                                    <i class="fa-solid fa-hourglass-half"></i> <strong style="color: #ffc107;"><?php echo t('pending'); ?></strong>
                                <?php elseif ($request['status'] === 'rejected'): ?>
                                    <i class="fa-solid fa-circle-xmark"></i> <strong style="color: #dc3545;"><?php echo t('request_rejected'); ?></strong>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Translations for JavaScript
        const translations = {
            availableStock: "<?php echo t('available_stock'); ?>",
            units: "<?php echo t('units'); ?>"
        };

        // Tab switching
        function switchTab(tabName) {
            window.history.pushState({}, '', '?tab=' + tabName);
            
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            
            document.getElementById(tabName).classList.add('active');
            document.querySelector(`[onclick="switchTab('${tabName}')"]`).classList.add('active');
        }

        // Update stock info
        function updateStockInfo() {
            const select = document.getElementById('itemSelect');
            const option = select.options[select.selectedIndex];
            const stockInfo = document.getElementById('stockInfo');
            const quantityInput = document.getElementById('quantityInput');
    
            if (option.value) {
                const quantity = option.getAttribute('data-quantity');
                const unit = option.getAttribute('data-unit');
        
                document.getElementById('stockQuantity').textContent = quantity;
                document.getElementById('stockUnit').textContent = unit;
                stockInfo.classList.add('show');

                // Change color based on stock level
                if (parseInt(quantity) === 0) {
                    stockInfo.style.background = '#f8d7da';
                    stockInfo.querySelector('.label').style.color = '#721c24';
                    stockInfo.querySelector('.value').style.color = '#721c24';
                } else if (parseInt(quantity) < 10) {
                    stockInfo.style.background = '#fff3cd';
                    stockInfo.querySelector('.label').style.color = '#856404';
                    stockInfo.querySelector('.value').style.color = '#856404';
                } else {
                    stockInfo.style.background = '#d4edda';
                    stockInfo.querySelector('.label').style.color = '#155724';
                    stockInfo.querySelector('.value').style.color = '#155724';
                }
            } else {
                stockInfo.classList.remove('show');
            }
        }
    </script>
</body>
</html>