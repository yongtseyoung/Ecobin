<?php
/**
 * My Tasks - Employee View
 * Mobile-friendly interface for employees to view and manage their assigned tasks
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check employee authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['user_id'];
$employee_name = $_SESSION['full_name'] ?? 'Employee';

// Get filter
$filter = $_GET['filter'] ?? 'active'; // active, today, all

// Build query based on filter
$query = "SELECT t.*, 
          a.area_name,
          b.bin_code,
          b.location_details as bin_location,
          b.current_fill_level,
          b.gps_latitude,
          b.gps_longitude
          FROM tasks t
          LEFT JOIN areas a ON t.area_id = a.area_id
          LEFT JOIN bins b ON t.triggered_by_bin = b.bin_id
          WHERE t.assigned_to = ?";

$params = [$employee_id];

if ($filter === 'today') {
    $query .= " AND DATE(t.scheduled_date) = CURDATE()";
} elseif ($filter === 'active') {
    $query .= " AND t.status IN ('pending', 'in_progress')";
}

$query .= " ORDER BY 
            FIELD(t.priority, 'urgent', 'high', 'medium', 'low'),
            FIELD(t.status, 'in_progress', 'pending', 'completed'),
            t.scheduled_date ASC";

$tasks = getAll($query, $params);

// Calculate stats
$total_tasks = count($tasks);
$pending = count(array_filter($tasks, fn($t) => $t['status'] === 'pending'));
$in_progress = count(array_filter($tasks, fn($t) => $t['status'] === 'in_progress'));
$today_tasks = count(array_filter($tasks, fn($t) => date('Y-m-d', strtotime($t['scheduled_date'])) === date('Y-m-d')));

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - EcoBin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #FAF1E4;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            background: #435334;
            color: white;
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .header p {
            opacity: 0.9;
            font-size: 15px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            color: #435334;
            padding: 12px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 18px;
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

        .stat-card.today .value {
            color: #3498db;
        }

        .stat-card.progress .value {
            color: #f39c12;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: white;
            padding: 8px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filter-tab {
            flex: 1;
            padding: 12px;
            border: none;
            background: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
        }

        .filter-tab.active {
            background: #435334;
            color: white;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }

        .task-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .task-title {
            font-size: 18px;
            font-weight: 600;
            color: #435334;
            margin-bottom: 8px;
        }

        .task-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 12px;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .priority-urgent { background: #fee; color: #c00; }
        .priority-high { background: #fff3cd; color: #856404; }
        .priority-medium { background: #d1ecf1; color: #0c5460; }
        .priority-low { background: #d4edda; color: #155724; }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-in_progress { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }

        .auto-badge {
            background: #e8eaf6;
            color: #3f51b5;
        }

        .task-details {
            font-size: 13px;
            color: #666;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .task-details strong {
            color: #333;
        }

        .task-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            flex: 1;
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
        }

        .btn-complete {
            background: #28a745;
            color: white;
        }

        .btn-complete:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #435334;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
            font-size: 15px;
        }

        .complete-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .complete-modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
        }

        .modal-content h3 {
            color: #435334;
            font-size: 24px;
            margin-bottom: 20px;
        }

        .modal-content textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            min-height: 120px;
            margin-bottom: 20px;
            resize: vertical;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #666;
        }

        @media (max-width: 600px) {
            body {
                padding: 15px;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .filter-tabs {
                flex-direction: column;
            }

            .task-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <a href="employee_dashboard.php" class="back-button">
        ← Back to Dashboard
    </a>

    <div class="header">
        <h1>📋 My Tasks</h1>
        <p>Hello, <?php echo htmlspecialchars($employee_name); ?>!</p>
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
        <div class="stat-card today">
            <div class="value"><?php echo $today_tasks; ?></div>
            <div class="label">Today</div>
        </div>
        <div class="stat-card">
            <div class="value"><?php echo $pending; ?></div>
            <div class="label">Pending</div>
        </div>
        <div class="stat-card progress">
            <div class="value"><?php echo $in_progress; ?></div>
            <div class="label">In Progress</div>
        </div>
    </div>

    <div class="filter-tabs">
        <a href="?filter=active" class="filter-tab <?php echo $filter === 'active' ? 'active' : ''; ?>">
            Active Tasks
        </a>
        <a href="?filter=today" class="filter-tab <?php echo $filter === 'today' ? 'active' : ''; ?>">
            Today
        </a>
        <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
            All Tasks
        </a>
    </div>

    <?php if (empty($tasks)): ?>
        <div class="empty-state">
            <div class="icon">✨</div>
            <h3>All Clear!</h3>
            <p>You have no <?php echo $filter; ?> tasks</p>
        </div>
    <?php else: ?>
        <?php foreach ($tasks as $task): ?>
            <div class="task-card">
                <div class="task-title">
                    <?php echo htmlspecialchars($task['task_title']); ?>
                </div>

                <div class="task-badges">
                    <span class="badge priority-<?php echo $task['priority']; ?>">
                        <?php echo strtoupper($task['priority']); ?>
                    </span>
                    <span class="badge status-<?php echo $task['status']; ?>">
                        <?php echo strtoupper(str_replace('_', ' ', $task['status'])); ?>
                    </span>
                    <?php if ($task['is_auto_generated']): ?>
                        <span class="badge auto-badge">🤖 AUTO</span>
                    <?php endif; ?>
                </div>

                <div class="task-details">
                    <?php if ($task['description']): ?>
                        <div style="margin-bottom: 10px; color: #555;">
                            <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                        </div>
                    <?php endif; ?>

                    <div><strong>Type:</strong> <?php echo ucfirst($task['task_type']); ?></div>
                    <div><strong>Area:</strong> <?php echo htmlspecialchars($task['area_name'] ?? 'N/A'); ?></div>
                    
                    <?php if ($task['bin_code']): ?>
                        <div>
                            <strong>Bin:</strong> <?php echo htmlspecialchars($task['bin_code']); ?>
                            <?php if ($task['current_fill_level']): ?>
                                (<?php echo $task['current_fill_level']; ?>% full)
                            <?php endif; ?>
                        </div>
                        <?php if ($task['bin_location']): ?>
                            <div><strong>Location:</strong> <?php echo htmlspecialchars($task['bin_location']); ?></div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div>
                        <strong>Due:</strong> 
                        <?php 
                        $due_date = date('M j, Y', strtotime($task['scheduled_date']));
                        $is_today = date('Y-m-d', strtotime($task['scheduled_date'])) === date('Y-m-d');
                        $is_overdue = strtotime($task['scheduled_date']) < time() && $task['status'] !== 'completed';
                        
                        if ($is_today) {
                            echo "<span style='color: #3498db; font-weight: 600;'>Today</span>";
                        } elseif ($is_overdue) {
                            echo "<span style='color: #e74c3c; font-weight: 600;'>$due_date (Overdue)</span>";
                        } else {
                            echo $due_date;
                        }
                        ?>
                    </div>

                    <?php if ($task['gps_latitude'] && $task['gps_longitude']): ?>
                        <div>
                            <a href="https://www.google.com/maps?q=<?php echo $task['gps_latitude']; ?>,<?php echo $task['gps_longitude']; ?>" 
                               target="_blank" 
                               style="color: #3498db; text-decoration: none;">
                                📍 View on Map
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="task-actions">
                    <?php if ($task['status'] === 'pending' || $task['status'] === 'in_progress'): ?>
                        <button onclick="openCompleteModal(<?php echo $task['task_id']; ?>)" class="btn btn-complete">
                            ✓ Complete Task
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Complete Task Modal -->
    <div id="completeModal" class="complete-modal">
        <div class="modal-content">
            <h3>✓ Complete Task</h3>
            <p style="color: #666; margin-bottom: 20px;">Add any notes about this task (optional)</p>
            <form method="POST" action="task_actions.php">
                <input type="hidden" name="action" value="employee_complete">
                <input type="hidden" name="task_id" id="modalTaskId">
                <textarea name="completion_notes" placeholder="e.g., Collected 15.5 kg, bin was at 85%, no issues"></textarea>
                <div class="modal-actions">
                    <button type="button" onclick="closeCompleteModal()" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-complete">
                        ✓ Complete Task
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCompleteModal(taskId) {
            document.getElementById('modalTaskId').value = taskId;
            document.getElementById('completeModal').classList.add('active');
        }

        function closeCompleteModal() {
            document.getElementById('completeModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('completeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCompleteModal();
            }
        });
    </script>
</body>
</html>