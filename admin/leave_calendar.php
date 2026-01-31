<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'leave';

$selected_month = $_GET['month'] ?? date('Y-m');
$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

$leave_requests = getAll("SELECT lr.*, e.full_name, lt.type_name, lt.color_code
                          FROM leave_requests lr
                          JOIN employees e ON lr.employee_id = e.employee_id
                          JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                          WHERE lr.status = 'approved'
                          AND (
                              (lr.start_date BETWEEN ? AND ?) OR
                              (lr.end_date BETWEEN ? AND ?) OR
                              (lr.start_date <= ? AND lr.end_date >= ?)
                          )
                          ORDER BY lr.start_date",
                          [$month_start, $month_end, $month_start, $month_end, $month_start, $month_end]);

$leave_by_date = [];
foreach ($leave_requests as $leave) {
    $start = new DateTime($leave['start_date']);
    $end = new DateTime($leave['end_date']);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
    
    foreach ($period as $date) {
        $date_key = $date->format('Y-m-d');
        if ($date_key >= $month_start && $date_key <= $month_end) {
            if (!isset($leave_by_date[$date_key])) {
                $leave_by_date[$date_key] = [];
            }
            $leave_by_date[$date_key][] = $leave;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Calendar - EcoBin</title>
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

        .month-selector {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .month-selector input {
            padding: 10px 15px;
            border: 2px solid #CEDEBD;
            border-radius: 10px;
            font-size: 14px;
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

        .calendar {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }

        .calendar-header {
            text-align: center;
            font-weight: 600;
            color: #435334;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 14px;
        }

        .calendar-day {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            min-height: 120px;
            padding: 10px;
            background: white;
            transition: all 0.3s;
        }

        .calendar-day:hover {
            border-color: #CEDEBD;
            box-shadow: 0 3px 10px rgba(67, 83, 52, 0.1);
        }

        .calendar-day.other-month {
            background: #fafafa;
            opacity: 0.5;
        }

        .calendar-day.today {
            border-color: #435334;
            background: #f0f5f0;
        }

        .day-number {
            font-weight: 600;
            color: #435334;
            margin-bottom: 8px;
        }

        .leave-item {
            background: #e8f5e9;
            border-left: 3px solid #27ae60;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 11px;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .legend {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <h1>Leave Calendar</h1>
            <div class="month-selector">
                <form method="GET" style="display: flex; gap: 10px;">
                    <input type="month" name="month" value="<?php echo $selected_month; ?>">
                    <button type="submit" class="btn btn-primary">View</button>
                </form>
                <a href="leave.php" class="btn" style="background: #CEDEBD; color: #435334;">
                    ← Back to Requests
                </a>
            </div>
        </div>

        <div class="calendar">
            <h2 style="color: #435334; margin-bottom: 20px; text-align: center;">
                <?php echo date('F Y', strtotime($month_start)); ?>
            </h2>

            <?php
            $first_day = new DateTime($month_start);
            $last_day = new DateTime($month_end);
            $days_in_month = $last_day->format('d');
            $start_weekday = $first_day->format('w'); // 0 = Sunday
            $today = date('Y-m-d');
            ?>

            <div class="calendar-grid">
                <div class="calendar-header">Sun</div>
                <div class="calendar-header">Mon</div>
                <div class="calendar-header">Tue</div>
                <div class="calendar-header">Wed</div>
                <div class="calendar-header">Thu</div>
                <div class="calendar-header">Fri</div>
                <div class="calendar-header">Sat</div>

                <?php
                for ($i = 0; $i < $start_weekday; $i++) {
                    echo '<div class="calendar-day other-month"></div>';
                }

                for ($day = 1; $day <= $days_in_month; $day++) {
                    $date = sprintf('%s-%02d', $selected_month, $day);
                    $is_today = ($date === $today);
                    $class = $is_today ? 'calendar-day today' : 'calendar-day';
                    
                    echo '<div class="' . $class . '">';
                    echo '<div class="day-number">' . $day . '</div>';
                    
                    if (isset($leave_by_date[$date])) {
                        $shown = [];
                        foreach ($leave_by_date[$date] as $leave) {
                            $key = $leave['employee_id'] . '-' . $leave['leave_id'];
                            if (!in_array($key, $shown)) {
                                echo '<div class="leave-item" style="border-left-color: ' . htmlspecialchars($leave['color_code']) . '; background: ' . htmlspecialchars($leave['color_code']) . '22;" title="' . htmlspecialchars($leave['full_name'] . ' - ' . $leave['type_name']) . '">';
                                echo htmlspecialchars($leave['full_name']);
                                echo '</div>';
                                $shown[] = $key;
                            }
                        }
                    }
                    
                    echo '</div>';
                }

                $total_cells = $start_weekday + $days_in_month;
                $remaining = (7 - ($total_cells % 7)) % 7;
                for ($i = 0; $i < $remaining; $i++) {
                    echo '<div class="calendar-day other-month"></div>';
                }
                ?>
            </div>
        </div>
    </main>
</body>
</html>