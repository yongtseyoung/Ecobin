<?php


session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'attendance';

$mode = $_GET['mode'] ?? 'add';
$attendance_id = $_GET['id'] ?? null;

$employees = getAll("SELECT employee_id, full_name FROM employees WHERE status = 'active' ORDER BY full_name");

$record = null;
if ($mode === 'edit' && $attendance_id) {
    $record = getOne("SELECT * FROM attendance WHERE attendance_id = ?", [$attendance_id]);
    if (!$record) {
        $_SESSION['error'] = "Attendance record not found";
        header("Location: attendance.php");
        exit;
    }
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'];
    $attendance_date = $_POST['attendance_date'];
    $check_in_time = $_POST['check_in_time'];
    $check_out_time = $_POST['check_out_time'] ?? null;
    $status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';
    
    $work_hours = null;
    if ($check_in_time && $check_out_time) {
        $checkin = strtotime($check_in_time);
        $checkout = strtotime($check_out_time);
        $work_hours = round(($checkout - $checkin) / 3600, 2);
    }
    
    try {
        if ($mode === 'add') {
            $existing = getOne("SELECT attendance_id FROM attendance WHERE employee_id = ? AND attendance_date = ?", 
                              [$employee_id, $attendance_date]);
            
            if ($existing) {
                $_SESSION['error'] = "Attendance record already exists for this employee on this date";
            } else {
                $sql = "INSERT INTO attendance (employee_id, attendance_date, check_in_time, check_out_time, work_hours, status, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                query($sql, [$employee_id, $attendance_date, $check_in_time, $check_out_time, $work_hours, $status, $notes]);
                $_SESSION['success'] = "Attendance record added successfully";
                header("Location: attendance.php?date=$attendance_date");
                exit;
            }
        } else {
            $sql = "UPDATE attendance 
                    SET employee_id = ?, attendance_date = ?, check_in_time = ?, check_out_time = ?, 
                        work_hours = ?, status = ?, notes = ?
                    WHERE attendance_id = ?";
            query($sql, [$employee_id, $attendance_date, $check_in_time, $check_out_time, $work_hours, $status, $notes, $attendance_id]);
            $_SESSION['success'] = "Attendance record updated successfully";
            header("Location: attendance.php?date=$attendance_date");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Attendance Entry - EcoBin</title>
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

        .back-btn {
            padding: 10px 20px;
            background: white;
            color: #435334;
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
        }

        .form-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 800px;
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

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            font-size: 18px;
            color: #435334;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
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
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #435334;
        }

        .form-group label .required {
            color: #e74c3c;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #CEDEBD;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #999;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        .btn {
            padding: 12px 32px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
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
            background: #f0f0f0;
            color: #666;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1><?php echo $mode === 'edit' ? 'Edit' : 'Add'; ?> Attendance Record</h1>
            <a href="attendance.php" class="back-btn">← Back to Attendance</a>
        </div>

        <div class="form-container">
            <?php if ($success): ?>
                <div class="alert alert-success">✓ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">⚠ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-section">
                    <h3>Basic Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>
                                Employee <span class="required">*</span>
                            </label>
                            <select name="employee_id" required <?php echo $mode === 'edit' ? 'disabled' : ''; ?>>
                                <option value="">-- Select Employee --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['employee_id']; ?>" 
                                            <?php echo ($record && $record['employee_id'] == $emp['employee_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($mode === 'edit'): ?>
                                <input type="hidden" name="employee_id" value="<?php echo $record['employee_id']; ?>">
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label>
                                Date <span class="required">*</span>
                            </label>
                            <input 
                                type="date" 
                                name="attendance_date" 
                                required 
                                max="<?php echo date('Y-m-d'); ?>"
                                value="<?php echo $record ? $record['attendance_date'] : date('Y-m-d'); ?>"
                            >
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Time Details</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>
                                Check In Time <span class="required">*</span>
                            </label>
                            <input 
                                type="time" 
                                name="check_in_time" 
                                required
                                value="<?php echo $record ? $record['check_in_time'] : ''; ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label>Check Out Time</label>
                            <input 
                                type="time" 
                                name="check_out_time"
                                value="<?php echo $record ? $record['check_out_time'] : ''; ?>"
                            >
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Status & Notes</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>
                                Status <span class="required">*</span>
                            </label>
                            <select name="status" required>
                                <option value="present" <?php echo ($record && $record['status'] === 'present') ? 'selected' : ''; ?>>Present</option>
                                <option value="late" <?php echo ($record && $record['status'] === 'late') ? 'selected' : ''; ?>>Late</option>
                                <option value="absent" <?php echo ($record && $record['status'] === 'absent') ? 'selected' : ''; ?>>Absent</option>
                                <option value="half_day" <?php echo ($record && $record['status'] === 'half_day') ? 'selected' : ''; ?>>Half Day</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label>Notes</label>
                            <textarea 
                                name="notes" 
                                rows="3" 
                                placeholder="Optional notes (e.g., reason for late arrival, manual entry reason)"
                            ><?php echo $record ? htmlspecialchars($record['notes']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="attendance.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $mode === 'edit' ? '✓ Update Record' : '✓ Add Record'; ?>
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        const checkinInput = document.querySelector('input[name="check_in_time"]');
        const checkoutInput = document.querySelector('input[name="check_out_time"]');

        function calculateHours() {
            if (checkinInput.value && checkoutInput.value) {
                const checkin = new Date(`2000-01-01 ${checkinInput.value}`);
                const checkout = new Date(`2000-01-01 ${checkoutInput.value}`);
                const hours = (checkout - checkin) / (1000 * 60 * 60);
                
                if (hours > 0) {
                    console.log(`Work hours: ${hours.toFixed(2)} hours`);
                }
            }
        }

        checkinInput.addEventListener('change', calculateHours);
        checkoutInput.addEventListener('change', calculateHours);
    </script>
</body>
</html>