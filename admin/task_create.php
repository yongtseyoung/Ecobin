<?php
/**
 * Create New Task
 * Manual task creation by admin
 */

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Set current page for sidebar
$current_page = 'tasks';

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['full_name'] ?? 'Admin';

// Get active employees
$employees = getAll("SELECT employee_id, full_name, area_id FROM employees WHERE status = 'active' ORDER BY full_name");

// Get areas
$areas = getAll("SELECT area_id, area_name, block FROM areas ORDER BY area_name");

// Get bins
$bins = getAll("SELECT bin_id, bin_code, location_details, area_id, current_fill_level FROM bins ORDER BY bin_code");

// Get error/success messages
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Task - EcoBin</title>
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
            justify-content: flex-end;
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

        .info-box {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .info-box h3 {
            color: #2e7d32;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .info-box p {
            color: #558b2f;
            font-size: 14px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>Create New Task</h1>
            <a href="tasks.php" class="btn btn-secondary">
                ← Back to Tasks
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <span>⚠</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="form-container">

            <form method="POST" action="task_actions.php">
                <input type="hidden" name="action" value="create">
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Task Title <span class="required">*</span></label>
                        <input type="text" name="task_title" required placeholder="e.g., Collect Bin A1, Inspect Area 2">
                    </div>

                    <div class="form-group">
                        <label>Task Type <span class="required">*</span></label>
                        <select name="task_type" required>
                            <option value="">Select Type</option>
                            <option value="collection">Collection</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="inspection">Inspection</option>
                            <option value="emergency">Emergency</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Assign To <span class="required">*</span></label>
                        <select name="assigned_to" id="assignedTo" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['employee_id']; ?>" data-area="<?php echo $emp['area_id']; ?>">
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
                                <option value="<?php echo $area['area_id']; ?>">
                                    <?php echo htmlspecialchars($area['area_name']); ?>
                                    <?php if ($area['block']): ?>
                                        (Block <?php echo htmlspecialchars($area['block']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Bin (Optional)</label>
                        <select name="triggered_by_bin" id="binSelect">
                            <option value="">No specific bin</option>
                            <?php foreach ($bins as $bin): ?>
                                <option value="<?php echo $bin['bin_id']; ?>" data-area="<?php echo $bin['area_id']; ?>">
                                    <?php echo htmlspecialchars($bin['bin_code']); ?> - 
                                    <?php echo htmlspecialchars($bin['location_details']); ?>
                                    <?php if ($bin['current_fill_level']): ?>
                                        (<?php echo $bin['current_fill_level']; ?>% full)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Scheduled Date <span class="required">*</span></label>
                        <input type="date" name="scheduled_date" required value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Scheduled Time</label>
                        <input type="time" name="scheduled_time" value="09:00">
                    </div>

                    <div class="form-group full-width">
                        <label>Priority <span class="required">*</span></label>
                        <div class="priority-selector">
                            <div class="priority-option priority-urgent">
                                <input type="radio" name="priority" value="urgent" id="priority_urgent">
                                <label for="priority_urgent">URGENT</label>
                            </div>
                            <div class="priority-option priority-high">
                                <input type="radio" name="priority" value="high" id="priority_high">
                                <label for="priority_high">HIGH</label>
                            </div>
                            <div class="priority-option priority-medium">
                                <input type="radio" name="priority" value="medium" id="priority_medium" checked>
                                <label for="priority_medium">MEDIUM</label>
                            </div>
                            <div class="priority-option priority-low">
                                <input type="radio" name="priority" value="low" id="priority_low">
                                <label for="priority_low">LOW</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" placeholder="Add task details, instructions, or notes..."></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="tasks.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        ✓ Create Task
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Auto-fill area when employee is selected
        document.getElementById('assignedTo').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const areaId = selectedOption.getAttribute('data-area');
            if (areaId) {
                document.getElementById('areaSelect').value = areaId;
            }
        });

        // Auto-fill area when bin is selected
        document.getElementById('binSelect').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const areaId = selectedOption.getAttribute('data-area');
            if (areaId) {
                document.getElementById('areaSelect').value = areaId;
            }
        });
    </script>
</body>
</html>