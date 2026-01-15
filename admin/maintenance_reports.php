<?php
/**
 * Admin Maintenance Reports
 * View and manage all maintenance reports from employees
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
$current_page = 'maintenance';

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'] ?? 'Admin';

// Get filters
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';
$category_filter = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where = ["1=1"];
$params = [];

if ($status_filter !== 'all') {
    $where[] = "mr.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter !== 'all') {
    $where[] = "mr.priority = ?";
    $params[] = $priority_filter;
}

if ($category_filter !== 'all') {
    $where[] = "mr.issue_category = ?";
    $params[] = $category_filter;
}

if (!empty($search)) {
    $where[] = "(mr.issue_title LIKE ? OR mr.location LIKE ? OR e.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(" AND ", $where);

// Get all maintenance reports
$reports = getAll("
    SELECT 
        mr.*,
        e.full_name as employee_name,
        e.email as employee_email,
        a.full_name as assigned_admin_name
    FROM maintenance_reports mr
    JOIN employees e ON mr.employee_id = e.employee_id
    LEFT JOIN admins a ON mr.assigned_to = a.admin_id
    WHERE $where_clause
    ORDER BY 
        CASE mr.status
            WHEN 'pending' THEN 1
            WHEN 'in_progress' THEN 2
            WHEN 'resolved' THEN 3
            WHEN 'cancelled' THEN 4
        END,
        CASE mr.priority
            WHEN 'high' THEN 1
            WHEN 'medium' THEN 2
            WHEN 'low' THEN 3
        END,
        mr.reported_at DESC
", $params);

// Get statistics
$total_reports = getOne("SELECT COUNT(*) as count FROM maintenance_reports")['count'];
$pending_reports = getOne("SELECT COUNT(*) as count FROM maintenance_reports WHERE status = 'pending'")['count'];
$in_progress_reports = getOne("SELECT COUNT(*) as count FROM maintenance_reports WHERE status = 'in_progress'")['count'];
$resolved_reports = getOne("SELECT COUNT(*) as count FROM maintenance_reports WHERE status = 'resolved'")['count'];
$high_priority = getOne("SELECT COUNT(*) as count FROM maintenance_reports WHERE priority = 'high' AND status IN ('pending', 'in_progress')")['count'];

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Reports - EcoBin</title>
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
        }

        .page-header h1 {
            font-size: 32px;
            color: #435334;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #666;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            animation: slideDown 0.3s ease;
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .filter-row:last-child {
            margin-bottom: 0;
        }

        .filters input[type="text"],
        .filters select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }

        .filters input[type="text"] {
            flex: 1;
            min-width: 200px;
        }

        .btn {
            padding: 10px 20px;
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

        .btn-warning {
            background: #ffc107;
            color: #000;
            padding: 8px 16px;
            font-size: 13px;
        }

        .btn-success {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            font-size: 13px;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            font-size: 13px;
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

        .report-card.high-priority {
            border-left: 4px solid #dc3545;
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
            flex-wrap: wrap;
        }

        .status-badge {
            display: inline-block;
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
            display: inline-block;
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
            display: inline-block;
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

        .report-photo {
            margin-bottom: 15px;
        }

        .report-photo img {
            max-width: 200px;
            max-height: 150px;
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
        }

        .report-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
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
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
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
            <h1>Maintenance Reports</h1>
            <p>Manage and track all maintenance issues and reports</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_reports; ?></div>
                <div class="stat-label">Total Reports</div>
            </div>

            <div class="stat-card">
                <div class="stat-value" style="color: #435334;"><?php echo $pending_reports; ?></div>
                <div class="stat-label">Pending</div>
            </div>

            <div class="stat-card">
                <div class="stat-value" style="color: #435334;"><?php echo $in_progress_reports; ?></div>
                <div class="stat-label">In Progress</div>
            </div>

            <div class="stat-card">
                <div class="stat-value" style="color: #435334;"><?php echo $resolved_reports; ?></div>
                <div class="stat-label">Resolved</div>
            </div>

            <div class="stat-card">
                <div class="stat-value" style="color: #435334;"><?php echo $high_priority; ?></div>
                <div class="stat-label">High Priority</div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="filters">
            <div class="filter-row">
                <input type="text" name="search" placeholder="Search by title, location, or employee..." value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="status">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>

                <select name="priority">
                    <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priority</option>
                    <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                </select>

                <select name="category">
                    <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Category</option>
                    <option value="bin_issue" <?php echo $category_filter === 'bin_issue' ? 'selected' : ''; ?>>Bin Issue</option>
                    <option value="equipment_issue" <?php echo $category_filter === 'equipment_issue' ? 'selected' : ''; ?>>Equipment Issue</option>
                    <option value="facility_issue" <?php echo $category_filter === 'facility_issue' ? 'selected' : ''; ?>>Facility Issue</option>
                    <option value="safety_hazard" <?php echo $category_filter === 'safety_hazard' ? 'selected' : ''; ?>>Safety Hazard</option>
                    <option value="other" <?php echo $category_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>

                <button type="submit" class="btn btn-primary"> Filter</button>
                <a href="maintenance_reports.php" class="btn" style="background: #e0e0e0;">Clear</a>
            </div>
        </form>

        <!-- Reports List -->
        <?php if (empty($reports)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-clipboard"></i>
                <h3>No Reports Found</h3>
                <p>No maintenance reports match your filters</p>
            </div>
        <?php else: ?>
            <?php foreach ($reports as $report): ?>
                <div class="report-card <?php echo $report['priority'] === 'high' ? 'high-priority' : ''; ?>">
                    <div class="report-header">
                        <div>
                            <div class="report-title">
                                <i class="fa-solid fa-tools"></i> <?php echo htmlspecialchars($report['issue_title']); ?>
                            </div>
                            <div class="report-id">
                                Report #<?php echo $report['report_id']; ?> • 
                                Reported by <strong><?php echo htmlspecialchars($report['employee_name']); ?></strong> • 
                                <?php echo date('M j, Y g:i A', strtotime($report['reported_at'])); ?>
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
                            <div class="detail-label"><i class="fa-solid fa-tag"></i> Category</div>
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
                            <div class="detail-label"><i class="fa-solid fa-location-dot"></i> Location</div>
                            <div class="detail-value"><?php echo htmlspecialchars($report['location']); ?></div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label"><i class="fa-solid fa-user"></i> Employee</div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($report['employee_name']); ?>
                                <br><small style="color: #999; font-weight: normal;"><?php echo htmlspecialchars($report['employee_email']); ?></small>
                            </div>
                        </div>

                        <?php if ($report['assigned_to']): ?>
                            <div class="detail-item">
                                <div class="detail-label"><i class="fa-solid fa-user-shield"></i> Assigned To</div>
                                <div class="detail-value"><?php echo htmlspecialchars($report['assigned_admin_name']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="report-description">
                        <strong><i class="fa-solid fa-align-left"></i> Description:</strong><br>
                        <?php echo nl2br(htmlspecialchars($report['issue_description'])); ?>
                    </div>

                    <?php if ($report['photo_path']): ?>
                        <div class="report-photo">
                            <a href="../<?php echo htmlspecialchars($report['photo_path']); ?>" target="_blank">
                                <img src="../<?php echo htmlspecialchars($report['photo_path']); ?>" alt="Issue photo">
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($report['admin_notes'])): ?>
                        <div class="admin-notes">
                            <div class="label"><i class="fa-solid fa-comment"></i> Admin Notes:</div>
                            <?php echo nl2br(htmlspecialchars($report['admin_notes'])); ?>
                        </div>
                    <?php endif; ?>

                    <div class="report-footer">
                        <div style="font-size: 12px; color: #999;">
                            <i class="fa-solid fa-clock"></i> Last updated: <?php echo date('M j, Y g:i A', strtotime($report['updated_at'])); ?>
                        </div>

                            <?php if ($report['status'] === 'pending'): ?>
                                <form action="maintenance_actions.php" method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                    <input type="hidden" name="status" value="in_progress">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fa-solid fa-play"></i> Start Work
                                    </button>
                                </form>
                            <?php elseif ($report['status'] === 'in_progress'): ?>
                                <form action="maintenance_actions.php" method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                    <input type="hidden" name="status" value="resolved">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fa-solid fa-check"></i> Mark Resolved
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($report['status'] === 'resolved'): ?>
                                <form action="maintenance_actions.php" method="POST" style="display: inline;" onsubmit="return confirm('Delete this resolved report?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </button>
                                </form>
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