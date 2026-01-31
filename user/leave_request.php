<?php
/**
 * Employee Leave Request Form
 * Allows employees to request leave
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
$current_page = 'leave';

$employee_id = $_SESSION['user_id'];
$employee_name = $_SESSION['full_name'] ?? 'Employee';
$current_year = date('Y');

// Get employee details and load language preference
$employee = getOne("SELECT e.*, a.area_name 
                    FROM employees e 
                    LEFT JOIN areas a ON e.area_id = a.area_id 
                    WHERE e.employee_id = ?", 
                    [$employee_id]);


// Get leave types
$leave_types = getAll("SELECT * FROM leave_types WHERE is_active = TRUE ORDER BY type_name");

// Get leave balances for current year
$leave_balances = getAll("SELECT lb.*, lt.type_name, lt.color_code 
                          FROM leave_balances lb
                          JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id
                          WHERE lb.employee_id = ? AND lb.year = ?
                          ORDER BY lt.type_name",
                          [$employee_id, $current_year]);

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('apply_leave'); ?> - EcoBin</title>
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
            max-width: 1400px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #435334;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header p {
            color: #666;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            display: flex;
            align-items: center;
            gap: 10px;
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

        .form-group label .required {
            color: #e74c3c;
        }

        .form-group label i {
            margin-right: 5px;
            color: #666;
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
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #435334;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #435334;
            color: white;
        }

        .btn-primary:hover {
            background: #354428;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #CEDEBD;
            color: #435334;
        }

        .btn-secondary:hover {
            background: #b8cda8;
        }

        .balance-card {
            padding: 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid;
            transition: transform 0.3s;
        }

        .balance-card:hover {
            transform: translateX(5px);
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
            display: flex;
            align-items: center;
            gap: 8px;
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
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .days-calculator {
            background: #fff3cd;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            text-align: center;
            border: 2px solid #ffc107;
        }

        .days-calculator .days {
            font-size: 32px;
            font-weight: 700;
            color: #856404;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .days-calculator .label {
            font-size: 13px;
            color: #856404;
            margin-top: 5px;
        }

@media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 80px 15px 20px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .page-header p {
                font-size: 14px;
            }

            .card {
                padding: 20px;
            }

            .card h2 {
                font-size: 18px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-group {
                margin-bottom: 15px;
            }

            .form-group label {
                font-size: 13px;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 10px;
                font-size: 14px;
            }

            .btn {
                width: 100%;
                justify-content: center;
                padding: 12px 20px;
            }

            .balance-card {
                padding: 12px;
            }

            .balance-type {
                font-size: 14px;
            }

            .balance-remaining {
                font-size: 20px;
            }

            .balance-detail {
                font-size: 11px;
            }

            .days-calculator .days {
                font-size: 28px;
            }

            .days-calculator .label {
                font-size: 12px;
            }

            .alert {
                padding: 12px 15px;
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 70px 10px 15px;
            }

            .page-header {
                margin-bottom: 20px;
            }

            .page-header h1 {
                font-size: 20px;
            }

            .page-header p {
                font-size: 13px;
            }

            .card {
                padding: 15px;
                margin-bottom: 20px;
            }

            .card h2 {
                font-size: 16px;
                margin-bottom: 15px;
            }

            .form-group {
                margin-bottom: 12px;
            }

            .form-group label {
                font-size: 12px;
                margin-bottom: 6px;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 10px;
                font-size: 13px;
                border-radius: 8px;
            }

            .form-group textarea {
                min-height: 80px;
            }

            .btn {
                padding: 12px 18px;
                font-size: 14px;
            }

            .balance-card {
                padding: 10px;
                margin-bottom: 10px;
            }

            .balance-type {
                font-size: 13px;
            }

            .balance-remaining {
                font-size: 18px;
            }

            .balance-detail {
                font-size: 10px;
            }

            .days-calculator {
                padding: 12px;
            }

            .days-calculator .days {
                font-size: 24px;
            }

            .days-calculator .label {
                font-size: 11px;
            }

            .alert {
                padding: 10px 12px;
                font-size: 13px;
            }

            div[style*="display: flex"] {
                flex-direction: column !important;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>
                <i class="fa-solid fa-calendar-plus"></i>
                <?php echo t('apply_leave'); ?>
            </h1>
            <p><?php echo t('submit_leave_for_approval'); ?></p>
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

        <div class="content-grid">
            <div>
                <div class="card">
                    <h2>
                        <?php echo t('leave_request_form'); ?>
                    </h2>

                    <form action="leave_actions.php" method="POST">
                        <input type="hidden" name="action" value="request">

                        <div class="form-group">
                            <label>
                                <?php echo t('leave_type'); ?> <span class="required">*</span>
                            </label>
                            <select name="leave_type_id" id="leaveType" required>
                                <option value=""><?php echo t('select_leave_type'); ?></option>
                                <?php foreach ($leave_types as $type): ?>
                                    <option value="<?php echo $type['leave_type_id']; ?>">
                                        <?php echo htmlspecialchars($type['type_name']); ?>
                                        <?php if ($type['description']): ?>
                                            - <?php echo htmlspecialchars($type['description']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>
                                    <?php echo t('start_date'); ?> <span class="required">*</span>
                                </label>
                                <input type="date" name="start_date" id="startDate" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>
                                    <?php echo t('end_date'); ?> <span class="required">*</span>
                                </label>
                                <input type="date" name="end_date" id="endDate" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div id="daysCalculator" class="days-calculator" style="display: none;">
                            <div class="days">
                                <i class="fa-solid fa-calendar-days"></i>
                                <span id="totalDays">0</span>
                            </div>
                            <div class="label"><?php echo t('total_days_requested'); ?></div>
                        </div>

                        <div class="form-group">
                            <label>
                                <?php echo t('reason'); ?> <span class="required">*</span>
                            </label>
                            <textarea name="reason" required placeholder="<?php echo t('leave_reason_placeholder'); ?>"></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>
                                    <?php echo t('emergency_contact'); ?>
                                </label>
                                <input type="text" name="emergency_contact" placeholder="<?php echo t('contact_person_during_leave'); ?>">
                            </div>

                            <div class="form-group">
                                <label>
                                    <?php echo t('emergency_phone'); ?>
                                </label>
                                <input type="tel" name="emergency_phone" placeholder="+60 XX-XXX XXXX">
                            </div>
                        </div>

                        <div style="display: flex; gap: 15px; margin-top: 30px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-paper-plane"></i>
                                <?php echo t('submit_request'); ?>
                            </button>
                            <a href="my_leave.php" class="btn btn-secondary">
                                <i class="fa-solid fa-xmark"></i>
                                <?php echo t('cancel'); ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div>
                <div class="card">
                    <h2>
                        <?php echo t('leave_balance'); ?> (<?php echo $current_year; ?>)
                    </h2>
                    <?php if (empty($leave_balances)): ?>
                        <p style="color: #999; text-align: center; padding: 20px;">
                            <i class="fa-solid fa-inbox" style="font-size: 48px; display: block; margin-bottom: 10px;"></i>
                            <?php echo t('no_leave_balance_data'); ?>
                        </p>
                    <?php else: ?>
                        <?php foreach ($leave_balances as $balance): ?>
                            <div class="balance-card" style="border-left-color: <?php echo $balance['color_code']; ?>">
                                <div class="balance-header">
                                    <div class="balance-type">
                                        <?php echo htmlspecialchars($balance['type_name']); ?>
                                    </div>
                                    <div class="balance-remaining"><?php echo $balance['remaining_days']; ?></div>
                                </div>
                                <div class="balance-detail">
                                    <?php echo t('used'); ?>: <?php echo $balance['used_days']; ?> / <?php echo $balance['total_days']; ?> <?php echo t('days'); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>


            </div>
        </div>
    </main>

    <script>
        const translations = {
            totalDaysRequested: "<?php echo t('total_days_requested'); ?>"
        };

        const startDate = document.getElementById('startDate');
        const endDate = document.getElementById('endDate');
        const totalDaysEl = document.getElementById('totalDays');
        const calculator = document.getElementById('daysCalculator');

        function calculateDays() {
            if (startDate.value && endDate.value) {
                const start = new Date(startDate.value);
                const end = new Date(endDate.value);
                
                if (end >= start) {
                    const diffTime = Math.abs(end - start);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    
                    totalDaysEl.textContent = diffDays;
                    calculator.style.display = 'block';
                } else {
                    calculator.style.display = 'none';
                }
            } else {
                calculator.style.display = 'none';
            }
        }

        startDate.addEventListener('change', calculateDays);
        endDate.addEventListener('change', calculateDays);

        startDate.addEventListener('change', function() {
            endDate.min = this.value;
        });
    </script>
</body>
</html>