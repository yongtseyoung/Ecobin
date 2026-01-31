<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'maintenance';

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'] ?? 'Admin';

$report_id = intval($_GET['id'] ?? 0);

if (!$report_id) {
    $_SESSION['error'] = "Invalid report ID";
    header("Location: maintenance_reports.php");
    exit;
}

$report = getOne("
    SELECT 
        mr.*,
        e.full_name as employee_name,
        e.email as employee_email,
        e.phone as employee_phone,
        a.full_name as assigned_admin_name
    FROM maintenance_reports mr
    JOIN employees e ON mr.employee_id = e.employee_id
    LEFT JOIN admins a ON mr.assigned_to = a.admin_id
    WHERE mr.report_id = ?
", [$report_id]);

if (!$report) {
    $_SESSION['error'] = "Report not found";
    header("Location: maintenance_reports.php");
    exit;
}

$admins = getAll("SELECT admin_id, full_name FROM admins ORDER BY full_name");

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Details #<?php echo $report_id; ?> - EcoBin</title>
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
            max-width: 1200px;
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

        .btn-warning {
            background: #ffc107;
            color: #000;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
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

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .card h2 {
            color: #435334;
            margin-bottom: 20px;
            font-size: 20px;
            border-bottom: 2px solid #CEDEBD;
            padding-bottom: 10px;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
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
            padding: 6px 14px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: 600;
            margin-left: 10px;
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

        .info-section {
            margin-bottom: 25px;
        }

        .info-section h3 {
            color: #435334;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 13px;
            color: #999;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 600;
            color: #435334;
        }

        .description-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #CEDEBD;
            margin-bottom: 20px;
        }

        .photo-box {
            margin-bottom: 20px;
        }

        .photo-box img {
            max-width: 100%;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.3s;
        }

        .photo-box img:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
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

        .action-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .action-section h3 {
            color: #435334;
            font-size: 16px;
            margin-bottom: 15px;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .action-buttons .btn {
            width: 100%;
            justify-content: center;
        }

        .timeline {
            margin-top: 20px;
        }

        .timeline-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #CEDEBD;
        }

        .timeline-item .time {
            font-size: 12px;
            color: #999;
            margin-bottom: 5px;
        }

        .timeline-item .event {
            font-size: 14px;
            color: #435334;
            font-weight: 600;
        }

        .admin-notes-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #2196f3;
            margin-bottom: 20px;
        }

        .category-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1976d2;
            margin-bottom: 10px;
        }

        @media (max-width: 968px) {
            .main-content {
                margin-left: 0;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1><i class="fa-solid fa-file-lines"></i> Report #<?php echo $report_id; ?></h1>
            <a href="maintenance_reports.php" class="btn btn-primary">
                <i class="fa-solid fa-arrow-left"></i> Back to Reports
            </a>
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

        <div class="content-grid">
            <div>
                <div class="card">
                    <h2><i class="fa-solid fa-circle-info"></i> Report Details</h2>

                    <div>
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
                            <?php echo ucfirst($report['priority']); ?> Priority
                        </span>
                    </div>

                    <div class="info-section">
                        <h3><i class="fa-solid fa-tools"></i> Issue Information</h3>
                        
                        <div class="info-item" style="margin-bottom: 15px;">
                            <div class="info-label">Issue Title</div>
                            <div class="info-value" style="font-size: 18px;"><?php echo htmlspecialchars($report['issue_title']); ?></div>
                        </div>

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

                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label"><i class="fa-solid fa-location-dot"></i> Location</div>
                                <div class="info-value"><?php echo htmlspecialchars($report['location']); ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label"><i class="fa-solid fa-calendar"></i> Reported Date</div>
                                <div class="info-value"><?php echo date('M j, Y g:i A', strtotime($report['reported_at'])); ?></div>
                            </div>
                        </div>

                        <div class="description-box">
                            <strong><i class="fa-solid fa-align-left"></i> Description:</strong><br>
                            <?php echo nl2br(htmlspecialchars($report['issue_description'])); ?>
                        </div>

                        <?php if ($report['photo_path']): ?>
                            <div class="photo-box">
                                <strong><i class="fa-solid fa-image"></i> Photo Evidence:</strong><br><br>
                                <a href="../<?php echo htmlspecialchars($report['photo_path']); ?>" target="_blank">
                                    <img src="../<?php echo htmlspecialchars($report['photo_path']); ?>" alt="Issue photo">
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="info-section">
                        <h3><i class="fa-solid fa-user"></i> Reporter Information</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Employee Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($report['employee_name']); ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($report['employee_email']); ?></div>
                            </div>

                            <?php if ($report['employee_phone']): ?>
                                <div class="info-item">
                                    <div class="info-label">Phone</div>
                                    <div class="info-value"><?php echo htmlspecialchars($report['employee_phone']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($report['admin_notes'])): ?>
                        <div class="admin-notes-box">
                            <strong><i class="fa-solid fa-comment"></i> Admin Notes:</strong><br>
                            <?php echo nl2br(htmlspecialchars($report['admin_notes'])); ?>
                        </div>
                    <?php endif; ?>

                    <form action="maintenance_actions.php" method="POST">
                        <input type="hidden" name="action" value="update_notes">
                        <input type="hidden" name="report_id" value="<?php echo $report_id; ?>">

                        <div class="form-group">
                            <label><i class="fa-solid fa-pen"></i> Update Admin Notes</label>
                            <textarea name="admin_notes" rows="4" placeholder="Add notes about this issue..."><?php echo htmlspecialchars($report['admin_notes'] ?? ''); ?></textarea>
                            <div class="hint">These notes will be visible to the employee</div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i> Save Notes
                        </button>
                    </form>
                </div>

                <div class="card" style="margin-top: 20px;">
                    <h2><i class="fa-solid fa-clock-rotate-left"></i> Timeline</h2>
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="time"><?php echo date('M j, Y g:i A', strtotime($report['reported_at'])); ?></div>
                            <div class="event"><i class="fa-solid fa-flag"></i> Report submitted by <?php echo htmlspecialchars($report['employee_name']); ?></div>
                        </div>

                        <?php if ($report['status'] !== 'pending'): ?>
                            <div class="timeline-item">
                                <div class="time"><?php echo date('M j, Y g:i A', strtotime($report['updated_at'])); ?></div>
                                <div class="event">
                                    <i class="fa-solid fa-rotate"></i> Status changed to 
                                    <strong><?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($report['assigned_to']): ?>
                            <div class="timeline-item">
                                <div class="time"><?php echo date('M j, Y g:i A', strtotime($report['updated_at'])); ?></div>
                                <div class="event">
                                    <i class="fa-solid fa-user-check"></i> Assigned to 
                                    <strong><?php echo htmlspecialchars($report['assigned_admin_name']); ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($report['resolved_at']): ?>
                            <div class="timeline-item" style="border-left-color: #28a745;">
                                <div class="time"><?php echo date('M j, Y g:i A', strtotime($report['resolved_at'])); ?></div>
                                <div class="event"><i class="fa-solid fa-circle-check"></i> Issue resolved</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div>
                <div class="card">
                    <h2><i class="fa-solid fa-sliders"></i> Actions</h2>

                    <form action="maintenance_actions.php" method="POST">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="report_id" value="<?php echo $report_id; ?>">

                        <div class="form-group">
                            <label><i class="fa-solid fa-signal"></i> Update Status</label>
                            <select name="status" required>
                                <option value="pending" <?php echo $report['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $report['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $report['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="cancelled" <?php echo $report['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fa-solid fa-refresh"></i> Update Status
                        </button>
                    </form>

                    <form action="maintenance_actions.php" method="POST" style="margin-top: 20px;">
                        <input type="hidden" name="action" value="assign">
                        <input type="hidden" name="report_id" value="<?php echo $report_id; ?>">

                        <div class="form-group">
                            <label><i class="fa-solid fa-user-shield"></i> Assign To</label>
                            <select name="assigned_to">
                                <option value="">Unassigned</option>
                                <?php foreach ($admins as $admin): ?>
                                    <option value="<?php echo $admin['admin_id']; ?>" 
                                            <?php echo $report['assigned_to'] == $admin['admin_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($admin['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fa-solid fa-user-check"></i> Assign
                        </button>
                    </form>

                    <div class="action-section">
                        <h3><i class="fa-solid fa-bolt"></i> Quick Actions</h3>
                        <div class="action-buttons">
                            <?php if ($report['status'] === 'pending'): ?>
                                <form action="maintenance_actions.php" method="POST">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="report_id" value="<?php echo $report_id; ?>">
                                    <input type="hidden" name="status" value="in_progress">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fa-solid fa-play"></i> Start Work
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($report['status'] === 'in_progress'): ?>
                                <form action="maintenance_actions.php" method="POST">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="report_id" value="<?php echo $report_id; ?>">
                                    <input type="hidden" name="status" value="resolved">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fa-solid fa-check"></i> Mark Resolved
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($report['status'] === 'resolved'): ?>
                                <form action="maintenance_actions.php" method="POST" onsubmit="return confirm('Delete this report permanently?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="report_id" value="<?php echo $report_id; ?>">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fa-solid fa-trash"></i> Delete Report
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
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