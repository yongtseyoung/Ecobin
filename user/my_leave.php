<?php
/**
 * Employee Leave Dashboard
 * View leave requests, balances, and history
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

$employee_id = $_SESSION['user_id'];
$employee_name = $_SESSION['full_name'] ?? 'Employee';
$current_year = date('Y');

// Get employee details and load language preference
$employee = getOne("SELECT e.*, a.area_name 
                    FROM employees e 
                    LEFT JOIN areas a ON e.area_id = a.area_id 
                    WHERE e.employee_id = ?", 
                    [$employee_id]);


// Set current page for sidebar
$current_page = 'leave';

// Get filter
$status_filter = $_GET['status'] ?? 'all';

// Get leave requests
$query = "SELECT lr.*, lt.type_name, lt.color_code,
          a.full_name as reviewed_by_name
          FROM leave_requests lr
          JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
          LEFT JOIN admins a ON lr.reviewed_by = a.admin_id
          WHERE lr.employee_id = ?";

$params = [$employee_id];

if ($status_filter !== 'all') {
    $query .= " AND lr.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY lr.created_at DESC";

$leave_requests = getAll($query, $params);

// Get leave balances
$leave_balances = getAll("SELECT lb.*, lt.type_name, lt.color_code, lt.max_days_per_year
                          FROM leave_balances lb
                          JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id
                          WHERE lb.employee_id = ? AND lb.year = ?
                          ORDER BY lt.type_name",
                          [$employee_id, $current_year]);

// Calculate stats
$total_requests = count($leave_requests);
$pending = count(array_filter($leave_requests, fn($r) => $r['status'] === 'pending'));
$approved = count(array_filter($leave_requests, fn($r) => $r['status'] === 'approved'));
$rejected = count(array_filter($leave_requests, fn($r) => $r['status'] === 'rejected'));

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('my_leave'); ?> - EcoBin</title>
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

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card .icon {
            font-size: 32px;
            margin-bottom: 10px;
            color: #435334;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #435334;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
        }

        .stat-card.pending .value { color: #f39c12; }
        .stat-card.pending .icon { color: #f39c12; }
        .stat-card.approved .value { color: #27ae60; }
        .stat-card.approved .icon { color: #27ae60; }
        .stat-card.rejected .value { color: #e74c3c; }
        .stat-card.rejected .icon { color: #e74c3c; }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
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
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }

        .filter-tab {
            padding: 8px 16px;
            border: none;
            background: none;
            color: #666;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            text-decoration: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-tab:hover {
            background: #f8f9fa;
        }

        .filter-tab.active {
            color: #435334;
            background: #CEDEBD;
        }

        .leave-item {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .leave-item:hover {
            border-color: #CEDEBD;
            box-shadow: 0 3px 10px rgba(67, 83, 52, 0.1);
        }

        .leave-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .leave-type {
            font-weight: 600;
            color: #435334;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .leave-type-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }

        .leave-dates {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .leave-dates strong {
            color: #435334;
        }

        .leave-reason {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #555;
            margin-bottom: 10px;
        }

        .leave-reason strong {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 5px;
        }

        .leave-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }

        .leave-meta {
            font-size: 12px;
            color: #999;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .leave-meta i {
            margin-right: 5px;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-cancelled { background: #e2e3e5; color: #383d41; }

        .balance-card {
            padding: 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid;
            transition: transform 0.3s;
        }

        .balance-card:hover {
            transform: translateX(5px);
        }

        .balance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .balance-type {
            font-weight: 600;
            color: #435334;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .balance-remaining {
            font-size: 24px;
            font-weight: 700;
            color: #435334;
        }

        .balance-detail {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .progress-bar {
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-fill {
            height: 100%;
            background: #27ae60;
            border-radius: 4px;
            transition: width 0.3s;
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

        .review-notes {
            background: #e3f2fd;
            border-left: 3px solid #2196f3;
            padding: 12px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 13px;
        }

        .review-notes strong {
            color: #1976d2;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 5px;
        }

/* Responsive */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 80px 15px 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .stat-card {
                padding: 18px;
            }

            .stat-card .icon {
                font-size: 28px;
                margin-bottom: 8px;
            }

            .stat-card .value {
                font-size: 28px;
            }

            .stat-card .label {
                font-size: 11px;
            }

            .card {
                padding: 20px;
            }

            .card h2 {
                font-size: 18px;
            }

            .filter-tabs {
                flex-wrap: wrap;
                gap: 8px;
            }

            .filter-tab {
                padding: 6px 12px;
                font-size: 12px;
            }

            .leave-item {
                padding: 15px;
            }

            .leave-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            .leave-type {
                font-size: 15px;
            }

            .leave-dates {
                font-size: 13px;
                flex-wrap: wrap;
            }

            .leave-reason {
                padding: 10px;
                font-size: 12px;
            }

            .leave-footer {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            .leave-footer form {
                width: 100%;
            }

            .leave-footer .btn {
                width: 100%;
            }

            .leave-meta {
                font-size: 11px;
            }

            .balance-card {
                padding: 12px;
            }

            .balance-type {
                font-size: 14px;
            }

            .balance-remaining {
                font-size: 20px;
            }

            .balance-detail {
                font-size: 11px;
            }

            .alert {
                padding: 12px 15px;
                font-size: 14px;
            }

            .review-notes {
                padding: 10px;
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 70px 10px 15px;
            }

            .page-header {
                margin-bottom: 20px;
            }

            .page-header h1 {
                font-size: 20px;
            }

            .btn {
                padding: 10px 18px;
                font-size: 13px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
                margin-bottom: 20px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card .icon {
                font-size: 24px;
                margin-bottom: 8px;
            }

            .stat-card .value {
                font-size: 24px;
            }

            .stat-card .label {
                font-size: 10px;
            }

            .card {
                padding: 15px;
                margin-bottom: 20px;
            }

            .card h2 {
                font-size: 16px;
                margin-bottom: 15px;
            }

            .filter-tabs {
                gap: 6px;
                padding-bottom: 8px;
            }

            .filter-tab {
                padding: 6px 10px;
                font-size: 11px;
            }

            .leave-item {
                padding: 12px;
                margin-bottom: 12px;
            }

            .leave-header {
                gap: 8px;
            }

            .leave-type {
                font-size: 14px;
            }

            .leave-type-dot {
                width: 10px;
                height: 10px;
            }

            .badge {
                padding: 4px 8px;
                font-size: 10px;
            }

            .leave-dates {
                font-size: 12px;
            }

            .leave-dates strong {
                font-size: 12px;
            }

            .leave-reason {
                padding: 10px;
                font-size: 11px;
            }

            .leave-reason strong {
                font-size: 12px;
            }

            .leave-footer {
                padding-top: 12px;
                gap: 8px;
            }

            .leave-meta {
                font-size: 10px;
                gap: 2px;
            }

            .btn-small {
                padding: 6px 12px;
                font-size: 11px;
            }

            .balance-card {
                padding: 10px;
                margin-bottom: 10px;
            }

            .balance-type {
                font-size: 13px;
            }

            .balance-remaining {
                font-size: 18px;
            }

            .balance-detail {
                font-size: 10px;
            }

            .progress-bar {
                height: 6px;
                margin-top: 6px;
            }

            .alert {
                padding: 10px 12px;
                font-size: 13px;
            }

            .review-notes {
                padding: 8px;
                font-size: 11px;
                margin-top: 8px;
            }

            .review-notes strong {
                font-size: 12px;
            }

            .empty-state {
                padding: 40px 15px;
            }

            .empty-state .icon {
                font-size: 48px;
                margin-bottom: 15px;
            }

            .empty-state h3 {
                font-size: 14px;
            }
        }    
        </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>
                <?php echo t('my_leave'); ?>
            </h1>
            <a href="leave_request.php" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i>
                <?php echo t('request_leave'); ?>
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

        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-clipboard-list"></i></div>
                <div class="value"><?php echo $total_requests; ?></div>
                <div class="label"><?php echo t('total_requests'); ?></div>
            </div>
            <div class="stat-card pending">
                <div class="icon"><i class="fa-solid fa-hourglass-half"></i></div>
                <div class="value"><?php echo $pending; ?></div>
                <div class="label"><?php echo t('pending'); ?></div>
            </div>
            <div class="stat-card approved">
                <div class="icon"><i class="fa-solid fa-circle-check"></i></div>
                <div class="value"><?php echo $approved; ?></div>
                <div class="label"><?php echo t('approved'); ?></div>
            </div>
            <div class="stat-card rejected">
                <div class="icon"><i class="fa-solid fa-circle-xmark"></i></div>
                <div class="value"><?php echo $rejected; ?></div>
                <div class="label"><?php echo t('rejected'); ?></div>
            </div>
        </div>

        <div class="content-grid">
            <div>
                <div class="card">
                    <h2>
                        <?php echo t('leave_requests'); ?>
                    </h2>

                    <div class="filter-tabs">
                        <a href="my_leave.php?status=all" class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                            <?php echo t('all'); ?>
                        </a>
                        <a href="my_leave.php?status=pending" class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                            <?php echo t('pending'); ?>
                        </a>
                        <a href="my_leave.php?status=approved" class="filter-tab <?php echo $status_filter === 'approved' ? 'active' : ''; ?>">
                            <?php echo t('approved'); ?>
                        </a>
                        <a href="my_leave.php?status=rejected" class="filter-tab <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">
                            <?php echo t('rejected'); ?>
                        </a>
                    </div>

                    <?php if (empty($leave_requests)): ?>
                        <div class="empty-state">
                            <div class="icon"><i class="fa-solid fa-inbox"></i></div>
                            <h3><?php echo t('no_leave_requests_found'); ?></h3>
                            <br>
                        </div>
                    <?php else: ?>
                        <?php foreach ($leave_requests as $leave): ?>
                            <div class="leave-item">
                                <div class="leave-header">
                                    <div class="leave-type">
                                        <span class="leave-type-dot" style="background: <?php echo $leave['color_code']; ?>"></span>
                                        <?php echo htmlspecialchars($leave['type_name']); ?>
                                    </div>
                                    <span class="badge status-<?php echo $leave['status']; ?>">
                                        <?php 
                                        if ($leave['status'] === 'approved') {
                                            echo '<i class="fa-solid fa-check"></i>';
                                        } elseif ($leave['status'] === 'rejected') {
                                            echo '<i class="fa-solid fa-xmark"></i>';
                                        } elseif ($leave['status'] === 'cancelled') {
                                            echo '<i class="fa-solid fa-ban"></i>';
                                        } else {
                                            echo '<i class="fa-solid fa-hourglass-half"></i>';
                                        }
                                        ?>
                                        <?php echo ucfirst($leave['status']); ?>
                                    </span>
                                </div>

                                <div class="leave-dates">
                                    <strong><?php echo date('M d, Y', strtotime($leave['start_date'])); ?></strong>
                                    <i class="fa-solid fa-arrow-right"></i>
                                    <strong><?php echo date('M d, Y', strtotime($leave['end_date'])); ?></strong>
                                    <span>(<?php echo $leave['total_days']; ?> <?php echo $leave['total_days'] > 1 ? t('days') : t('day'); ?>)</span>
                                </div>

                                <div class="leave-reason">
                                    <strong>
                                        <?php echo t('reason'); ?>:
                                    </strong>
                                    <?php echo htmlspecialchars($leave['reason']); ?>
                                </div>

                                <?php if ($leave['review_notes']): ?>
                                    <div class="review-notes">
                                        <strong>
                                            <?php echo t('admin_response'); ?>:
                                        </strong>
                                        <?php echo htmlspecialchars($leave['review_notes']); ?>
                                        <?php if ($leave['reviewed_by_name']): ?>
                                            <br><small>- <?php echo htmlspecialchars($leave['reviewed_by_name']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="leave-footer">
                                    <div class="leave-meta">
                                        <span>
                                            <?php echo t('applied'); ?>: <?php echo date('M d, Y H:i', strtotime($leave['created_at'])); ?>
                                        </span>
                                        <?php if ($leave['reviewed_at']): ?>
                                            <span>
                                                <?php echo t('reviewed'); ?>: <?php echo date('M d, Y H:i', strtotime($leave['reviewed_at'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($leave['status'] === 'pending'): ?>
                                        <form method="POST" action="leave_actions.php" style="display: inline;" 
                                              onsubmit="return confirm('<?php echo t('cancel_leave_confirm'); ?>')">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="leave_id" value="<?php echo $leave['leave_id']; ?>">
                                            <button type="submit" class="btn btn-small" style="background: #e74c3c; color: white;">
                                                <i class="fa-solid fa-ban"></i>
                                                <?php echo t('cancel_request'); ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="card">
                    <h2>
                        <?php echo t('leave_balance'); ?> (<?php echo $current_year; ?>)
                    </h2>
                    <?php if (empty($leave_balances)): ?>
                        <p style="color: #999; text-align: center; padding: 20px;">
                            <i class="fa-solid fa-inbox" style="font-size: 48px; display: block; margin-bottom: 10px;"></i>
                            <?php echo t('no_leave_balance_data'); ?>
                        </p>
                    <?php else: ?>
                        <?php foreach ($leave_balances as $balance): ?>
                            <?php
                            $usage_percent = ($balance['total_days'] > 0) 
                                ? ($balance['used_days'] / $balance['total_days']) * 100 
                                : 0;
                            ?>
                            <div class="balance-card" style="border-left-color: <?php echo $balance['color_code']; ?>">
                                <div class="balance-header">
                                    <div class="balance-type">
                                        <?php echo htmlspecialchars($balance['type_name']); ?>
                                    </div>
                                    <div class="balance-remaining"><?php echo $balance['remaining_days']; ?></div>
                                </div>
                                <div class="balance-detail">
                                    <?php echo t('used'); ?>: <?php echo $balance['used_days']; ?> / <?php echo $balance['total_days']; ?> <?php echo t('days'); ?>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $usage_percent; ?>%; background: <?php echo $balance['color_code']; ?>"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>