<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'leave';

$admin_id = $_SESSION['user_id'];
$current_year = date('Y');

$status_filter = $_GET['status'] ?? 'pending';
$employee_filter = $_GET['employee'] ?? 'all';

$query = "SELECT lr.*, 
          e.full_name as employee_name,
          lt.type_name, lt.color_code,
          a.full_name as reviewed_by_name
          FROM leave_requests lr
          JOIN employees e ON lr.employee_id = e.employee_id
          JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
          LEFT JOIN admins a ON lr.reviewed_by = a.admin_id
          WHERE 1=1";

$params = [];

if ($status_filter !== 'all') {
    $query .= " AND lr.status = ?";
    $params[] = $status_filter;
}

if ($employee_filter !== 'all') {
    $query .= " AND lr.employee_id = ?";
    $params[] = $employee_filter;
}

$query .= " ORDER BY 
            FIELD(lr.status, 'pending', 'approved', 'rejected', 'cancelled'),
            lr.created_at DESC";

$leave_requests = getAll($query, $params);

$employees = getAll("SELECT employee_id, full_name FROM employees WHERE status = 'active' ORDER BY full_name");

$total = count(getAll("SELECT leave_id FROM leave_requests"));
$pending = count(getAll("SELECT leave_id FROM leave_requests WHERE status = 'pending'"));
$approved_today = count(getAll("SELECT leave_id FROM leave_requests WHERE status = 'approved' AND DATE(reviewed_at) = CURDATE()"));
$ongoing = count(getAll("SELECT leave_id FROM leave_requests WHERE status = 'approved' AND CURDATE() BETWEEN start_date AND end_date"));

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - EcoBin</title>
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
        .nav-menu {
            padding: 0 15px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin-bottom: 5px;
            border-radius: 10px;
            text-decoration: none;
            color: white;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-item.active {
            background: white;
            color: #435334;
            font-weight: 600;
        }

        .nav-item .icon {
            margin-right: 12px;
            font-size: 18px;
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
            background: #27ae60;
            color: white;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
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
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
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
        .stat-card.ongoing .value { color: #3498db; }

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .card h2 {
            color: #435334;
            font-size: 20px;
            margin-bottom: 20px;
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .filter-group {
            flex: 1;
        }

        .filter-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #435334;
            margin-bottom: 8px;
        }

        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }

        .leave-item {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
        }

        .leave-item.pending {
            border-left: 4px solid #f39c12;
        }

        .leave-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .leave-employee {
            font-weight: 600;
            color: #435334;
            font-size: 18px;
            margin-bottom: 5px;
        }

        .leave-type {
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .leave-type-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .leave-dates {
            font-size: 14px;
            color: #666;
            margin: 10px 0;
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
            margin: 10px 0;
        }

        .leave-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-cancelled { background: #e2e3e5; color: #383d41; }

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

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
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
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <h1>Leave Management</h1>
            <a href="leave_calendar.php" class="btn btn-primary">
                View Calendar
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
                <div class="value"><?php echo $total; ?></div>
                <div class="label">Total Requests</div>
            </div>
            <div class="stat-card pending">
                <div class="value"><?php echo $pending; ?></div>
                <div class="label">Pending Approval</div>
            </div>
            <div class="stat-card approved">
                <div class="value"><?php echo $approved_today; ?></div>
                <div class="label">Approved Today</div>
            </div>
            <div class="stat-card ongoing">
                <div class="value"><?php echo $ongoing; ?></div>
                <div class="label">On Leave Now</div>
            </div>
        </div>

        <div class="card">
            <h2>Leave Requests</h2>

            <div class="filters">
                <div class="filter-group">
                    <label>Status</label>
                    <select onchange="window.location.href='leave.php?status=' + this.value + '&employee=<?php echo $employee_filter; ?>'">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Employee</label>
                    <select onchange="window.location.href='leave.php?status=<?php echo $status_filter; ?>&employee=' + this.value">
                        <option value="all">All Employees</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['employee_id']; ?>" <?php echo $employee_filter == $emp['employee_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if (empty($leave_requests)): ?>
                <div class="empty-state">
                    <h3>No leave requests found</h3>
                </div>
            <?php else: ?>
                <?php foreach ($leave_requests as $leave): ?>
                    <div class="leave-item <?php echo $leave['status']; ?>">
                        <div class="leave-header">
                            <div>
                                <div class="leave-employee">
                                    <?php echo htmlspecialchars($leave['employee_name']); ?>
                                </div>
                                <div class="leave-type">
                                    <span class="leave-type-dot" style="background: <?php echo $leave['color_code']; ?>"></span>
                                    <?php echo htmlspecialchars($leave['type_name']); ?>
                                </div>
                            </div>
                            <span class="badge status-<?php echo $leave['status']; ?>">
                                <?php echo ucfirst($leave['status']); ?>
                            </span>
                        </div>

                        <div class="leave-dates">
                            <strong><?php echo date('M d, Y', strtotime($leave['start_date'])); ?></strong>
                            to
                            <strong><?php echo date('M d, Y', strtotime($leave['end_date'])); ?></strong>
                            (<?php echo $leave['total_days']; ?> day<?php echo $leave['total_days'] > 1 ? 's' : ''; ?>)
                        </div>

                        <div class="leave-reason">
                            <strong>Reason:</strong> <?php echo htmlspecialchars($leave['reason']); ?>
                        </div>

                        <?php if ($leave['emergency_contact'] || $leave['emergency_phone']): ?>
                            <div style="font-size: 13px; color: #666; margin-top: 10px;">
                                <strong>Emergency Contact:</strong>
                                <?php echo htmlspecialchars($leave['emergency_contact'] ?? 'N/A'); ?>
                                <?php if ($leave['emergency_phone']): ?>
                                    - <?php echo htmlspecialchars($leave['emergency_phone']); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($leave['review_notes']): ?>
                            <div style="background: #e3f2fd; padding: 10px; border-radius: 8px; margin-top: 10px; font-size: 13px;">
                                <strong>Review Notes:</strong> <?php echo htmlspecialchars($leave['review_notes']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($leave['status'] === 'pending'): ?>
                            <div class="leave-actions">
                                <button onclick="openApproveModal(<?php echo $leave['leave_id']; ?>)" class="btn btn-success btn-small">
                                    ✓ Approve
                                </button>
                                <button onclick="openRejectModal(<?php echo $leave['leave_id']; ?>)" class="btn btn-danger btn-small">
                                    ✗ Reject
                                </button>
                            </div>
                        <?php else: ?>
                            <div style="font-size: 12px; color: #999; margin-top: 10px;">
                                Reviewed by: <?php echo htmlspecialchars($leave['reviewed_by_name'] ?? 'N/A'); ?>
                                on <?php echo $leave['reviewed_at'] ? date('M d, Y H:i', strtotime($leave['reviewed_at'])) : 'N/A'; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <div id="approveModal" class="modal">
        <div class="modal-content">
            <h3>✓ Approve Leave Request</h3>
            <form method="POST" action="leave_actions.php">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="leave_id" id="approveLeaveId">

                <div class="form-group">
                    <label>Notes (Optional)</label>
                    <textarea name="review_notes" placeholder="Add any notes for the employee..."></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" onclick="closeModal('approveModal')" class="btn" style="background: #e0e0e0; color: #666;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        ✓ Approve
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3>✗ Reject Leave Request</h3>
            <form method="POST" action="leave_actions.php">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="leave_id" id="rejectLeaveId">

                <div class="form-group">
                    <label>Reason for Rejection *</label>
                    <textarea name="review_notes" required placeholder="Please provide a reason for rejection..."></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" onclick="closeModal('rejectModal')" class="btn" style="background: #e0e0e0; color: #666;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        ✗ Reject
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openApproveModal(leaveId) {
            document.getElementById('approveLeaveId').value = leaveId;
            document.getElementById('approveModal').classList.add('show');
        }

        function openRejectModal(leaveId) {
            document.getElementById('rejectLeaveId').value = leaveId;
            document.getElementById('rejectModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });
    </script>
</body>
</html>