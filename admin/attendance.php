<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'attendance';

$admin_name = $_SESSION['full_name'] ?? 'Admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $attendance_id = $_POST['attendance_id'] ?? null;
    
    if ($action === 'approve' && $attendance_id) {
        $new_status = $_POST['approved_status'] ?? 'late';
        
        try {
            $sql = "UPDATE attendance SET status = ?, notes = CONCAT(IFNULL(notes, ''), '\n[Approved by Admin on ', NOW(), ']') WHERE attendance_id = ?";
            query($sql, [$new_status, $attendance_id]);
            $_SESSION['success'] = "Attendance approved and status changed to " . ucfirst($new_status);
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to approve attendance: " . $e->getMessage();
        }
        
        header("Location: attendance.php?date=" . ($_POST['date'] ?? date('Y-m-d')));
        exit;
    }
    
    if ($action === 'reject' && $attendance_id) {
        try {
            $sql = "UPDATE attendance SET notes = CONCAT(IFNULL(notes, ''), '\n[Rejected by Admin on ', NOW(), ']') WHERE attendance_id = ?";
            query($sql, [$attendance_id]);
            $_SESSION['success'] = "Attendance record rejected. Status remains as Absent.";
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to reject attendance: " . $e->getMessage();
        }
        
        header("Location: attendance.php?date=" . ($_POST['date'] ?? date('Y-m-d')));
        exit;
    }
}

$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_employee = $_GET['employee'] ?? '';
$selected_status = $_GET['status'] ?? '';

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$query = "SELECT a.*, e.full_name, e.username, ar.area_name
          FROM attendance a
          JOIN employees e ON a.employee_id = e.employee_id
          LEFT JOIN areas ar ON e.area_id = ar.area_id
          WHERE a.attendance_date = ?";
$params = [$selected_date];

if ($selected_employee) {
    $query .= " AND a.employee_id = ?";
    $params[] = $selected_employee;
}

if ($selected_status) {
    $query .= " AND a.status = ?";
    $params[] = $selected_status;
}

$query .= " ORDER BY a.check_in_time ASC";

$attendance_records = getAll($query, $params);

$employees = getAll("SELECT employee_id, full_name FROM employees WHERE status = 'active' ORDER BY full_name");

$total_employees = getOne("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")['count'];
$present_count = count(array_filter($attendance_records, fn($r) => $r['status'] === 'present'));
$late_count = count(array_filter($attendance_records, fn($r) => $r['status'] === 'late'));

$checked_in_count = count($attendance_records);
$absent_count = $total_employees - $checked_in_count;

$pending_approvals = array_filter($attendance_records, function($r) {
    return $r['status'] === 'absent' 
        && !empty($r['notes']) 
        && strpos($r['notes'], 'Late reason:') === 0
        && strpos($r['notes'], '[Approved by Admin') === false
        && strpos($r['notes'], '[Rejected by Admin') === false;
});
$pending_count = count($pending_approvals);

$checked_in_ids = array_column($attendance_records, 'employee_id');
$absent_employees = [];
if ($selected_date === date('Y-m-d')) {
    $placeholders = $checked_in_ids ? str_repeat('?,', count($checked_in_ids) - 1) . '?' : '';
    $absent_query = "SELECT employee_id, full_name FROM employees WHERE status = 'active'";
    if ($placeholders) {
        $absent_query .= " AND employee_id NOT IN ($placeholders)";
        $absent_employees = getAll($absent_query, $checked_in_ids);
    } else {
        $absent_employees = getAll($absent_query);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance System - EcoBin</title>
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

        .header-actions {
            display: flex;
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
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #435334;
            color: white;
        }

        .btn-primary:hover {
            background: #354428;
        }

        .btn-secondary {
            background: #CEDEBD;
            color: #435334;
        }

        .btn-secondary:hover {
            background: #b8ceaa;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
        }

        .btn-approve {
            background: #27ae60;
            color: white;
        }

        .btn-approve:hover {
            background: #229954;
        }

        .btn-reject {
            background: #e74c3c;
            color: white;
        }

        .btn-reject:hover {
            background: #c0392b;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
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

        .stats-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stat-card h3 {
            font-size: 14px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: 700;
            color: #435334;
        }

        .stat-card.present .value {
            color: #27ae60;
        }

        .stat-card.late .value {
            color: #f39c12;
        }

        .stat-card.absent .value {
            color: #e74c3c;
        }

        .stat-card.pending .value {
            color: #ff9800;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filters form {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #435334;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            border-bottom: 2px solid #e0e0e0;
        }

        tbody td {
            padding: 15px 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        tbody tr.needs-approval {
            background: #fff8e1;
        }

        tbody tr.needs-approval:hover {
            background: #ffecb3;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-present {
            background: #d4edda;
            color: #155724;
        }

        .status-late {
            background: #fff3cd;
            color: #856404;
        }

        .status-absent {
            background: #f8d7da;
            color: #721c24;
        }

        .location-link {
            color: #435334;
            text-decoration: none;
            font-size: 13px;
        }

        .location-link:hover {
            text-decoration: underline;
        }

        .notes-cell {
            max-width: 250px;
        }

        .notes-text {
            font-size: 13px;
            color: #666;
            line-height: 1.4;
        }

        .notes-empty {
            color: #999;
            font-style: italic;
        }

        .notes-reason {
            background: #fff3cd;
            padding: 10px 12px;
            border-radius: 8px;
            border-left: 3px solid #ffc107;
            font-size: 13px;
            color: #856404;
            line-height: 1.4;
            margin-bottom: 8px;
        }

        .notes-reason strong {
            display: block;
            margin-bottom: 4px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .approval-actions {
            display: flex;
            gap: 6px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .approval-form {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-select {
            padding: 6px 8px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            background: white;
        }

        .status-select:focus {
            outline: none;
            border-color: #435334;
        }

        .approval-status {
            display: inline-block;
            margin-top: 8px;
            padding: 6px 12px;
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .rejection-status {
            display: inline-block;
            margin-top: 8px;
            padding: 6px 12px;
            background: #ffebee;
            color: #c62828;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #435334;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
        }

        @media (max-width: 968px) {
            .main-content {
                margin-left: 70px;
            }
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            .filters form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Attendance System</h1>
            <div class="header-actions">
                <a href="attendance_report.php" class="btn btn-secondary">
                    View Reports
                </a>
                <a href="attendance_manual.php" class="btn btn-secondary">
                    Manual Entry
                </a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <span>✓</span>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <span>⚠</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Employees</h3>
                <div class="value"><?php echo $total_employees; ?></div>
            </div>
            <div class="stat-card present">
                <h3>Present</h3>
                <div class="value"><?php echo $present_count; ?></div>
            </div>
            <div class="stat-card late">
                <h3>Late</h3>
                <div class="value"><?php echo $late_count; ?></div>
            </div>
            <div class="stat-card absent">
                <h3>Absent</h3>
                <div class="value"><?php echo $absent_count; ?></div>
            </div>
            <div class="stat-card pending">
                <h3>Pending Approval</h3>
                <div class="value"><?php echo $pending_count; ?></div>
            </div>
        </div>

        <div class="filters">
            <form method="GET" action="">
                <div class="filter-group">
                    <label>Date</label>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($selected_date); ?>">
                </div>
                <div class="filter-group">
                    <label>Employee</label>
                    <select name="employee">
                        <option value="">All Employees</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['employee_id']; ?>" 
                                    <?php echo $selected_employee == $emp['employee_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="present" <?php echo $selected_status === 'present' ? 'selected' : ''; ?>>Present</option>
                        <option value="late" <?php echo $selected_status === 'late' ? 'selected' : ''; ?>>Late</option>
                        <option value="absent" <?php echo $selected_status === 'absent' ? 'selected' : ''; ?>>Absent</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </form>
        </div>

        <div class="table-container">
            <?php if (empty($attendance_records)): ?>
                <div class="empty-state">
                    <h3>No attendance records found</h3>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Work Hours</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Notes / Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): 
                            $needs_approval = $record['status'] === 'absent' 
                                && !empty($record['notes']) 
                                && strpos($record['notes'], 'Late reason:') === 0
                                && strpos($record['notes'], '[Approved by Admin') === false
                                && strpos($record['notes'], '[Rejected by Admin') === false;
                            
                            $is_approved = strpos($record['notes'], '[Approved by Admin') !== false;
                            $is_rejected = strpos($record['notes'], '[Rejected by Admin') !== false;
                        ?>
                            <tr <?php echo $needs_approval ? 'class="needs-approval"' : ''; ?>>
                                <td>
                                    <strong><?php echo htmlspecialchars($record['full_name']); ?></strong><br>
                                    <small style="color: #999;"><?php echo htmlspecialchars($record['area_name'] ?? 'No Area'); ?></small>
                                </td>
                                <td>
                                    <?php echo $record['check_in_time'] ? date('g:i A', strtotime($record['check_in_time'])) : '--:--'; ?>
                                </td>
                                <td>
                                    <?php echo $record['check_out_time'] ? date('g:i A', strtotime($record['check_out_time'])) : '--:--'; ?>
                                </td>
                                <td>
                                    <?php echo $record['work_hours'] ? number_format($record['work_hours'], 2) . ' hrs' : '0.00 hrs'; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $record['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $record['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($record['check_in_location']): ?>
                                        <a href="https://www.google.com/maps?q=<?php echo urlencode($record['check_in_location']); ?>" 
                                           target="_blank" 
                                           class="location-link">
                                            View Map
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #999;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="notes-cell">
                                    <?php if (!empty($record['notes'])): ?>
                                        <?php if (strpos($record['notes'], 'Late reason:') === 0): ?>
                                            <div class="notes-reason">
                                                <strong>Late Reason:</strong>
                                                <?php 
                                                $notes_parts = explode("\n", $record['notes']);
                                                $reason = trim(str_replace('Late reason:', '', $notes_parts[0]));
                                                echo htmlspecialchars($reason); 
                                                ?>
                                            </div>
                                            
                                            <?php if ($needs_approval): ?>
                                                <div class="approval-actions">
                                                    <form method="POST" class="approval-form">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="attendance_id" value="<?php echo $record['attendance_id']; ?>">
                                                        <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                                                        <select name="approved_status" class="status-select" required>
                                                            <option value="late">Change to Late</option>
                                                            <option value="present">Change to Present</option>
                                                            <option value="absent">Keep as Absent</option>
                                                        </select>
                                                        <button type="submit" class="btn btn-sm btn-approve">✓ Approve</button>
                                                    </form>
                                                    <form method="POST" class="approval-form">
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="attendance_id" value="<?php echo $record['attendance_id']; ?>">
                                                        <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                                                        <button type="submit" class="btn btn-sm btn-reject" onclick="return confirm('Reject this attendance? Status will remain as Absent.')">✗ Reject</button>
                                                    </form>
                                                </div>
                                            <?php elseif ($is_approved): ?>
                                                <span class="approval-status">✓ Approved</span>
                                            <?php elseif ($is_rejected): ?>
                                                <span class="rejection-status">✗ Rejected</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="notes-text"><?php echo htmlspecialchars($record['notes']); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="notes-empty">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($selected_date === date('Y-m-d') && !empty($absent_employees)): ?>
                <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 10px;">
                    <h3 style="color: #856404; margin-bottom: 10px;">⚠ Not Checked In Yet:</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                        <?php foreach ($absent_employees as $absent): ?>
                            <span style="padding: 6px 12px; background: white; border-radius: 8px; font-size: 13px;">
                                <?php echo htmlspecialchars($absent['full_name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>