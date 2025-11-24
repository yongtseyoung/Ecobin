<?php
/**
 * Employee Leave Dashboard
 * View leave requests, balances, and history
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check authentication - employees only
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['user_id'];
$employee_name = $_SESSION['full_name'] ?? 'Employee';
$current_year = date('Y');

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Leave - EcoBin</title>
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

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
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
        .stat-card.approved .value { color: #27ae60; }
        .stat-card.rejected .value { color: #e74c3c; }

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
        }

        .badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
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
                margin-left: 70px;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>🏖️ My Leave</h1>
            <a href="leave_request.php" class="btn btn-primary">
                ➕ Request Leave
            </a>
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
                <div class="value"><?php echo $total_requests; ?></div>
                <div class="label">Total Requests</div>
            </div>
            <div class="stat-card pending">
                <div class="value"><?php echo $pending; ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-card approved">
                <div class="value"><?php echo $approved; ?></div>
                <div class="label">Approved</div>
            </div>
            <div class="stat-card rejected">
                <div class="value"><?php echo $rejected; ?></div>
                <div class="label">Rejected</div>
            </div>
        </div>

        <div class="content-grid">
            <div>
                <div class="card">
                    <h2>📋 Leave Requests</h2>

                    <div class="filter-tabs">
                        <a href="my_leave.php?status=all" class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                            All
                        </a>
                        <a href="my_leave.php?status=pending" class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                            Pending
                        </a>
                        <a href="my_leave.php?status=approved" class="filter-tab <?php echo $status_filter === 'approved' ? 'active' : ''; ?>">
                            Approved
                        </a>
                        <a href="my_leave.php?status=rejected" class="filter-tab <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">
                            Rejected
                        </a>
                    </div>

                    <?php if (empty($leave_requests)): ?>
                        <div class="empty-state">
                            <div class="icon">📭</div>
                            <h3>No leave requests found</h3>
                            <p>You haven't requested any leave yet</p>
                            <br>
                            <a href="leave_request.php" class="btn btn-primary">Request Leave Now</a>
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
                                        <?php echo ucfirst($leave['status']); ?>
                                    </span>
                                </div>

                                <div class="leave-dates">
                                    <strong>📅 <?php echo date('M d, Y', strtotime($leave['start_date'])); ?></strong>
                                    to
                                    <strong><?php echo date('M d, Y', strtotime($leave['end_date'])); ?></strong>
                                    (<?php echo $leave['total_days']; ?> day<?php echo $leave['total_days'] > 1 ? 's' : ''; ?>)
                                </div>

                                <div class="leave-reason">
                                    <strong>Reason:</strong> <?php echo htmlspecialchars($leave['reason']); ?>
                                </div>

                                <?php if ($leave['review_notes']): ?>
                                    <div class="review-notes">
                                        <strong>Admin Response:</strong> <?php echo htmlspecialchars($leave['review_notes']); ?>
                                        <?php if ($leave['reviewed_by_name']): ?>
                                            <br><small>- <?php echo htmlspecialchars($leave['reviewed_by_name']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="leave-footer">
                                    <div class="leave-meta">
                                        Applied: <?php echo date('M d, Y H:i', strtotime($leave['created_at'])); ?>
                                        <?php if ($leave['reviewed_at']): ?>
                                            <br>Reviewed: <?php echo date('M d, Y H:i', strtotime($leave['reviewed_at'])); ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($leave['status'] === 'pending'): ?>
                                        <form method="POST" action="leave_actions.php" style="display: inline;" 
                                              onsubmit="return confirm('Cancel this leave request?')">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="leave_id" value="<?php echo $leave['leave_id']; ?>">
                                            <button type="submit" class="btn btn-small" style="background: #e74c3c; color: white;">
                                                Cancel Request
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
                    <h2>📊 Leave Balance (<?php echo $current_year; ?>)</h2>
                    <?php if (empty($leave_balances)): ?>
                        <p style="color: #999; text-align: center; padding: 20px;">
                            No leave balance data available
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
                                    <div class="balance-type"><?php echo htmlspecialchars($balance['type_name']); ?></div>
                                    <div class="balance-remaining"><?php echo $balance['remaining_days']; ?></div>
                                </div>
                                <div class="balance-detail">
                                    Used: <?php echo $balance['used_days']; ?> / <?php echo $balance['total_days']; ?> days
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