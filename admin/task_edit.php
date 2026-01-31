<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'tasks';

$admin_id = $_SESSION['user_id'];
$task_id = $_GET['id'] ?? 0;

$task = getOne("SELECT * FROM tasks WHERE task_id = ?", [$task_id]);

if (!$task) {
    $_SESSION['error'] = "Task not found";
    header("Location: tasks.php");
    exit;
}

if ($task['status'] === 'completed' || $task['status'] === 'cancelled') {
    $_SESSION['error'] = "Cannot edit completed or cancelled tasks";
    header("Location: task_view.php?id=$task_id");
    exit;
}

$employees = getAll("SELECT employee_id, full_name, area_id FROM employees WHERE status = 'active' ORDER BY full_name");
$areas = getAll("SELECT area_id, area_name, block FROM areas ORDER BY area_name");
$bins = getAll("SELECT bin_id, bin_code, location_details, area_id, current_fill_level FROM bins ORDER BY bin_code");

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Task - EcoBin</title>
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

        .sidebar {
            width: 250px;
            background: #435334;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-logo {
            width: 120px;
            height: 120px;
            background: #CEDEBD;
            border-radius: 50%;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .sidebar-logo img {
            width: 90px;
            height: 90px;
            object-fit: contain;
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

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 900px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #435334;
            margin-bottom: 8px;
        }

        .form-group label .required {
            color: #e74c3c;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
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

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        .priority-selector {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        .priority-option {
            position: relative;
        }

        .priority-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .priority-option label {
            display: block;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .priority-option input[type="radio"]:checked + label {
            border-color: #435334;
            background: #435334;
            color: white;
        }

        .priority-urgent label {
            color: #c0392b;
        }

        .priority-high label {
            color: #e67e22;
        }

        .priority-medium label {
            color: #3498db;
        }

        .priority-low label {
            color: #27ae60;
        }

        .priority-option input[type="radio"]:checked + label {
            color: white !important;
        }

        .status-selector {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        .status-option {
            position: relative;
        }

        .status-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .status-option label {
            display: block;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            font-size: 13px;
        }

        .status-option input[type="radio"]:checked + label {
            border-color: #435334;
            background: #435334;
            color: white;
        }

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .warning-box h3 {
            color: #856404;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .warning-box p {
            color: #856404;
            font-size: 14px;
            line-height: 1.6;
        }

        .info-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #e8eaf6;
            color: #3f51b5;
            margin-left: 10px;
        }
    </style>
</head>
<body>
     <?php include '../includes/admin_sidebar.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1>Edit Task</h1>
                <?php if ($task['is_auto_generated']): ?>
                    <span class="info-badge">AUTO-GENERATED</span>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="task_view.php?id=<?php echo $task_id; ?>" class="btn btn-secondary">
                    View Details
                </a>
                <a href="tasks.php" class="btn btn-secondary">
                    ← Back to Tasks
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <span>⚠</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <span>✓</span>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($task['is_auto_generated']): ?>
            <div class="warning-box">
                <h3>Auto-Generated Task</h3>
                <p>This task was automatically created by the IoT system when a bin reached high capacity. You can modify the assignment and priority, but the bin association cannot be changed.</p>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" action="task_actions.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Task Title <span class="required">*</span></label>
                        <input type="text" name="task_title" required 
                               value="<?php echo htmlspecialchars($task['task_title']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Task Type <span class="required">*</span></label>
                        <select name="task_type" required>
                            <option value="collection" <?php echo $task['task_type'] === 'collection' ? 'selected' : ''; ?>>🗑️ Collection</option>
                            <option value="maintenance" <?php echo $task['task_type'] === 'maintenance' ? 'selected' : ''; ?>>🔧 Maintenance</option>
                            <option value="inspection" <?php echo $task['task_type'] === 'inspection' ? 'selected' : ''; ?>>🔍 Inspection</option>
                            <option value="emergency" <?php echo $task['task_type'] === 'emergency' ? 'selected' : ''; ?>>🚨 Emergency</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Assign To <span class="required">*</span></label>
                        <select name="assigned_to" id="assignedTo" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['employee_id']; ?>" 
                                        data-area="<?php echo $emp['area_id']; ?>"
                                        <?php echo $task['assigned_to'] == $emp['employee_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Area</label>
                        <select name="area_id" id="areaSelect">
                            <option value="">Select Area</option>
                            <?php foreach ($areas as $area): ?>
                                <option value="<?php echo $area['area_id']; ?>"
                                        <?php echo $task['area_id'] == $area['area_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($area['area_name']); ?>
                                    <?php if ($area['block']): ?>
                                        (Block <?php echo htmlspecialchars($area['block']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Bin</label>
                        <select name="triggered_by_bin" id="binSelect" 
                                <?php echo $task['is_auto_generated'] ? 'disabled' : ''; ?>>
                            <option value="">No specific bin</option>
                            <?php foreach ($bins as $bin): ?>
                                <option value="<?php echo $bin['bin_id']; ?>" 
                                        data-area="<?php echo $bin['area_id']; ?>"
                                        <?php echo $task['triggered_by_bin'] == $bin['bin_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bin['bin_code']); ?> - 
                                    <?php echo htmlspecialchars($bin['location_details']); ?>
                                    <?php if ($bin['current_fill_level']): ?>
                                        (<?php echo $bin['current_fill_level']; ?>% full)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($task['is_auto_generated']): ?>
                            <input type="hidden" name="triggered_by_bin" value="<?php echo $task['triggered_by_bin']; ?>">
                            <small style="color: #e67e22;">Bin cannot be changed for auto-generated tasks</small>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Scheduled Date <span class="required">*</span></label>
                        <input type="date" name="scheduled_date" required 
                               value="<?php echo $task['scheduled_date']; ?>" 
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Scheduled Time</label>
                        <input type="time" name="scheduled_time" 
                               value="<?php echo $task['scheduled_time'] ?? ''; ?>">
                    </div>

                    <div class="form-group full-width">
                        <label>Current Status <span class="required">*</span></label>
                        <div class="status-selector">
                            <div class="status-option">
                                <input type="radio" name="status" value="pending" id="status_pending"
                                       <?php echo $task['status'] === 'pending' ? 'checked' : ''; ?>>
                                <label for="status_pending">Pending</label>
                            </div>
                            <div class="status-option">
                                <input type="radio" name="status" value="in_progress" id="status_progress"
                                       <?php echo $task['status'] === 'in_progress' ? 'checked' : ''; ?>>
                                <label for="status_progress">In Progress</label>
                            </div>
                            <div class="status-option">
                                <input type="radio" name="status" value="completed" id="status_completed"
                                       <?php echo $task['status'] === 'completed' ? 'checked' : ''; ?>>
                                <label for="status_completed">Completed</label>
                            </div>
                            <div class="status-option">
                                <input type="radio" name="status" value="cancelled" id="status_cancelled"
                                       <?php echo $task['status'] === 'cancelled' ? 'checked' : ''; ?>>
                                <label for="status_cancelled">Cancelled</label>
                            </div>
                        </div>
                        <small>Note: Setting status to completed/cancelled will prevent further edits</small>
                    </div>

                    <div class="form-group full-width">
                        <label>Priority <span class="required">*</span></label>
                        <div class="priority-selector">
                            <div class="priority-option priority-urgent">
                                <input type="radio" name="priority" value="urgent" id="priority_urgent"
                                       <?php echo $task['priority'] === 'urgent' ? 'checked' : ''; ?>>
                                <label for="priority_urgent">URGENT</label>
                            </div>
                            <div class="priority-option priority-high">
                                <input type="radio" name="priority" value="high" id="priority_high"
                                       <?php echo $task['priority'] === 'high' ? 'checked' : ''; ?>>
                                <label for="priority_high">HIGH</label>
                            </div>
                            <div class="priority-option priority-medium">
                                <input type="radio" name="priority" value="medium" id="priority_medium"
                                       <?php echo $task['priority'] === 'medium' ? 'checked' : ''; ?>>
                                <label for="priority_medium">MEDIUM</label>
                            </div>
                            <div class="priority-option priority-low">
                                <input type="radio" name="priority" value="low" id="priority_low"
                                       <?php echo $task['priority'] === 'low' ? 'checked' : ''; ?>>
                                <label for="priority_low">LOW</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description"><?php echo htmlspecialchars($task['description'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <div>
                        <button type="button" class="btn btn-danger" onclick="if(confirm('Are you sure you want to cancel this task?')) { document.getElementById('cancelForm').submit(); }">
                            Cancel Task
                        </button>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <a href="tasks.php" class="btn btn-secondary">Back</a>
                        <button type="submit" class="btn btn-primary">
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>

            <form id="cancelForm" method="POST" action="task_actions.php" style="display: none;">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
            </form>
        </div>
    </main>

    <script>
        document.getElementById('assignedTo').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const areaId = selectedOption.getAttribute('data-area');
            if (areaId) {
                document.getElementById('areaSelect').value = areaId;
            }
        });

        const binSelect = document.getElementById('binSelect');
        if (!binSelect.disabled) {
            binSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const areaId = selectedOption.getAttribute('data-area');
                if (areaId) {
                    document.getElementById('areaSelect').value = areaId;
                }
            });
        }
    </script>
</body>
</html>