<?php
/**
 * Employee My Reports
 * View submitted maintenance reports and track status
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
$current_page = 'maintenance';

$employee_id = $_SESSION['user_id'];
$employee_name = $_SESSION['full_name'] ?? 'Employee';

// Get employee details and load language preference
$employee = getOne("SELECT e.*, a.area_name 
                    FROM employees e 
                    LEFT JOIN areas a ON e.area_id = a.area_id 
                    WHERE e.employee_id = ?", 
                    [$employee_id]);

// Get filter
$status_filter = $_GET['status'] ?? 'all';

// Build query
$where = ["employee_id = ?"];
$params = [$employee_id];

if ($status_filter !== 'all') {
    $where[] = "mr.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(" AND ", $where);

// Get employee's reports
$my_reports = getAll("
    SELECT 
        mr.*,
        a.full_name as assigned_admin_name
    FROM maintenance_reports mr
    LEFT JOIN admins a ON mr.assigned_to = a.admin_id
    WHERE $where_clause
    ORDER BY mr.reported_at DESC
", $params);

// Get statistics
$total_reports = getOne("SELECT COUNT(*) as count FROM maintenance_reports WHERE employee_id = ?", [$employee_id])['count'];
$pending_reports = getOne("SELECT COUNT(*) as count FROM maintenance_reports WHERE employee_id = ? AND status = 'pending'", [$employee_id])['count'];
$in_progress_reports = getOne("SELECT COUNT(*) as count FROM maintenance_reports WHERE employee_id = ? AND status = 'in_progress'", [$employee_id])['count'];
$resolved_reports = getOne("SELECT COUNT(*) as count FROM maintenance_reports WHERE employee_id = ? AND status = 'resolved'", [$employee_id])['count'];

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('my_reports'); ?> - EcoBin</title>
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

        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            font-size: 13px;
        }

        .btn-danger:hover {
            background: #c82333;
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

        .stat-icon i {
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

        .report-card {
            background: white;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .report-card:hover {
            border-color: #CEDEBD;
            box-shadow: 0 3px 10px rgba(67, 83, 52, 0.1);
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .report-title {
            font-size: 18px;
            font-weight: 600;
            color: #435334;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .report-id {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .report-badges {
            display: flex;
            gap: 10px;
            align-items: center;
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

        .status-in_progress {
            background: #cfe2ff;
            color: #084298;
        }

        .status-resolved {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }

        .priority-low {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }

        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }

        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1976d2;
        }

        .report-details {
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
            font-size: 14px;
            font-weight: 600;
            color: #435334;
        }

        .report-description {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #555;
            margin-bottom: 15px;
            border-left: 3px solid #CEDEBD;
        }

        .report-description strong {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 5px;
        }

        .report-photo {
            margin-bottom: 15px;
        }

        .report-photo img {
            max-width: 300px;
            max-height: 200px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.3s;
        }

        .report-photo img:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .admin-notes {
            background: #e3f2fd;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #555;
            border-left: 3px solid #2196f3;
            margin-bottom: 15px;
        }

        .admin-notes .label {
            font-weight: 600;
            color: #1976d2;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .report-footer {
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

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #ccc;
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

            .alert {
                padding: 12px 15px;
                font-size: 13px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-icon {
                font-size: 28px;
                margin-bottom: 8px;
            }

            .stat-value {
                font-size: 28px;
            }

            .stat-label {
                font-size: 12px;
            }

            .filters {
                flex-direction: column;
                padding: 15px;
            }

            .filters select {
                width: 100%;
            }

            .filters .btn {
                width: 100%;
            }

            .report-card {
                padding: 15px;
            }

            .report-header {
                flex-direction: column;
                gap: 12px;
            }

            .report-title {
                font-size: 16px;
            }

            .report-id {
                font-size: 11px;
            }

            .report-badges {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
            }

            .status-badge {
                padding: 5px 12px;
                font-size: 11px;
            }

            .priority-badge,
            .category-badge {
                padding: 3px 8px;
                font-size: 10px;
            }

            .report-details {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .detail-label {
                font-size: 11px;
            }

            .detail-value {
                font-size: 13px;
            }

            .report-description {
                padding: 10px;
                font-size: 12px;
            }

            .report-photo img {
                max-width: 100%;
                height: auto;
            }

            .admin-notes {
                padding: 10px;
                font-size: 12px;
            }

            .report-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                font-size: 11px;
            }

            .report-footer .btn {
                width: 100%;
            }

            .empty-state {
                padding: 40px 15px;
            }

            .empty-state i {
                font-size: 48px;
            }

            .empty-state h3 {
                font-size: 16px;
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

            .alert {
                padding: 10px 12px;
                font-size: 12px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .stat-card {
                padding: 18px;
            }

            .stat-icon {
                font-size: 24px;
                margin-bottom: 8px;
            }

            .stat-value {
                font-size: 24px;
            }

            .stat-label {
                font-size: 11px;
            }

            .filters {
                padding: 12px;
            }

            .filters select {
                padding: 10px;
                font-size: 13px;
            }

            .report-card {
                padding: 12px;
                margin-bottom: 12px;
            }

            .report-header {
                gap: 10px;
            }

            .report-title {
                font-size: 15px;
            }

            .report-id {
                font-size: 10px;
                margin-top: 4px;
            }

            .report-badges {
                gap: 6px;
            }

            .status-badge {
                padding: 4px 10px;
                font-size: 10px;
            }

            .priority-badge,
            .category-badge {
                padding: 3px 6px;
                font-size: 9px;
            }

            .report-details {
                gap: 10px;
                margin-bottom: 12px;
            }

            .detail-label {
                font-size: 10px;
                margin-bottom: 4px;
            }

            .detail-label i {
                font-size: 12px;
            }

            .detail-value {
                font-size: 12px;
            }

            .report-description {
                padding: 10px;
                font-size: 11px;
                margin-bottom: 12px;
            }

            .report-description strong {
                font-size: 12px;
                margin-bottom: 4px;
            }

            .report-photo {
                margin-bottom: 12px;
            }

            .report-photo img {
                max-width: 100%;
                border-radius: 8px;
            }

            .admin-notes {
                padding: 10px;
                font-size: 11px;
                margin-bottom: 12px;
            }

            .admin-notes .label {
                font-size: 12px;
                margin-bottom: 4px;
            }

            .report-footer {
                padding-top: 12px;
                gap: 8px;
                font-size: 10px;
            }

            .report-footer strong {
                font-size: 11px;
            }

            .btn-danger {
                padding: 8px 12px;
                font-size: 12px;
            }

            .empty-state {
                padding: 30px 10px;
            }

            .empty-state i {
                font-size: 40px;
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
                <?php echo t('my_issue_reports'); ?>
            </h1>
            <a href="report_issue.php" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i>
                <?php echo t('new_report'); ?>
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

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                <div class="stat-value"><?php echo $total_reports; ?></div>
                <div class="stat-label"><?php echo t('total_reports'); ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                <div class="stat-value" style="color: #ffc107;"><?php echo $pending_reports; ?></div>
                <div class="stat-label"><?php echo t('pending'); ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-spinner"></i></div>
                <div class="stat-value" style="color: #2196f3;"><?php echo $in_progress_reports; ?></div>
                <div class="stat-label"><?php echo t('in_progress'); ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                <div class="stat-value" style="color: #28a745;"><?php echo $resolved_reports; ?></div>
                <div class="stat-label"><?php echo t('resolved'); ?></div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="filters">
            <select name="status">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>><?php echo t('all_status'); ?></option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>><?php echo t('pending'); ?></option>
                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>><?php echo t('in_progress'); ?></option>
                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>><?php echo t('resolved'); ?></option>
                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>><?php echo t('cancelled'); ?></option>
            </select>

            <button type="submit" class="btn btn-primary">
                <?php echo t('filter'); ?>
            </button>
            <a href="my_reports.php" class="btn" style="background: #e0e0e0;">
                <?php echo t('clear'); ?>
            </a>
        </form>

        <!-- Reports List -->
        <?php if (empty($my_reports)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-clipboard"></i>
                <h3><?php echo t('no_reports_yet'); ?></h3>
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($my_reports as $report): ?>
                <div class="report-card">
                    <div class="report-header">
                        <div>
                            <div class="report-title">
                                <i class="fa-solid fa-tools"></i>
                                <?php echo htmlspecialchars($report['issue_title']); ?>
                            </div>
                            <div class="report-id">
                                <?php echo t('report'); ?> #<?php echo $report['report_id']; ?> • <?php echo date('M j, Y', strtotime($report['reported_at'])); ?>
                            </div>
                        </div>
                        <div class="report-badges">
                            <span class="status-badge status-<?php echo $report['status']; ?>">
                                <?php
                                $status_icons = [
                                    'pending' => 'fa-hourglass-half',
                                    'in_progress' => 'fa-spinner',
                                    'resolved' => 'fa-circle-check',
                                    'cancelled' => 'fa-times-circle'
                                ];
                                ?>
                                <i class="fa-solid <?php echo $status_icons[$report['status']]; ?>"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                            </span>
                            <span class="priority-badge priority-<?php echo $report['priority']; ?>">
                                <?php
                                $priority_icons = [
                                    'low' => 'fa-arrow-down',
                                    'medium' => 'fa-minus',
                                    'high' => 'fa-arrow-up'
                                ];
                                ?>
                                <i class="fa-solid <?php echo $priority_icons[$report['priority']]; ?>"></i>
                                <?php echo ucfirst($report['priority']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="report-details">
                        <div class="detail-item">
                            <div class="detail-label">
                                <i class="fa-solid fa-tag"></i>
                                <?php echo t('category'); ?>
                            </div>
                            <div class="detail-value">
                                <span class="category-badge">
                                    <?php
                                    $category_icons = [
                                        'bin_issue' => 'fa-trash',
                                        'equipment_issue' => 'fa-toolbox',
                                        'facility_issue' => 'fa-building',
                                        'safety_hazard' => 'fa-triangle-exclamation',
                                        'other' => 'fa-box'
                                    ];
                                    ?>
                                    <i class="fa-solid <?php echo $category_icons[$report['issue_category']]; ?>"></i>
                                    <?php echo ucfirst(str_replace('_', ' ', $report['issue_category'])); ?>
                                </span>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">
                                <i class="fa-solid fa-location-dot"></i>
                                <?php echo t('location'); ?>
                            </div>
                            <div class="detail-value"><?php echo htmlspecialchars($report['location']); ?></div>
                        </div>

                        <?php if ($report['assigned_to']): ?>
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fa-solid fa-user"></i>
                                    <?php echo t('assigned_to'); ?>
                                </div>
                                <div class="detail-value"><?php echo htmlspecialchars($report['assigned_admin_name']); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($report['resolved_at']): ?>
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fa-solid fa-clock"></i>
                                    <?php echo t('resolved_date'); ?>
                                </div>
                                <div class="detail-value"><?php echo date('M j, Y', strtotime($report['resolved_at'])); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="report-description">
                        <strong>
                            <i class="fa-solid fa-align-left"></i>
                            <?php echo t('description'); ?>:
                        </strong>
                        <?php echo nl2br(htmlspecialchars($report['issue_description'])); ?>
                    </div>

                    <?php if ($report['photo_path']): ?>
                        <div class="report-photo">
                            <a href="../<?php echo htmlspecialchars($report['photo_path']); ?>" target="_blank">
                                <img src="../<?php echo htmlspecialchars($report['photo_path']); ?>" alt="<?php echo t('issue_photo'); ?>">
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($report['admin_notes'])): ?>
                        <div class="admin-notes">
                            <div class="label">
                                <i class="fa-solid fa-comment"></i>
                                <?php echo t('admin_notes'); ?>:
                            </div>
                            <?php echo nl2br(htmlspecialchars($report['admin_notes'])); ?>
                        </div>
                    <?php endif; ?>

                    <div class="report-footer">
                        <div>
                            <i class="fa-solid fa-calendar"></i>
                            <?php echo t('last_updated'); ?>: <?php echo date('M j, Y g:i A', strtotime($report['updated_at'])); ?>
                        </div>

                        <div>
                            <?php if ($report['status'] === 'pending'): ?>
                                <form action="report_actions.php" method="POST" style="display: inline;" onsubmit="return confirm('<?php echo t('cancel_report_confirm'); ?>');">
                                    <input type="hidden" name="action" value="cancel_report">
                                    <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fa-solid fa-times"></i>
                                        <?php echo t('cancel_report'); ?>
                                    </button>
                                </form>
                            <?php elseif ($report['status'] === 'resolved'): ?>
                                <strong style="color: #28a745;">
                                    <i class="fa-solid fa-check-circle"></i>
                                    <?php echo t('issue_resolved'); ?>
                                </strong>
                            <?php elseif ($report['status'] === 'in_progress'): ?>
                                <strong style="color: #2196f3;">
                                    <i class="fa-solid fa-spinner fa-spin"></i>
                                    <?php echo t('being_worked_on'); ?>
                                </strong>
                            <?php elseif ($report['status'] === 'cancelled'): ?>
                                <strong style="color: #dc3545;">
                                    <i class="fa-solid fa-ban"></i>
                                    <?php echo t('cancelled'); ?>
                                </strong>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <script>
        // Auto-hide success message after 5 seconds
        <?php if ($success): ?>
        setTimeout(() => {
            const alert = document.querySelector('.alert-success');
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>