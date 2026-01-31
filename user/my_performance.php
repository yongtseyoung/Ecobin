<?php

session_start();
require_once '../config/database.php';
require_once '../config/performance_calculator.php';
require_once '../config/languages.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['user_id'];
$employee_name = $_SESSION['full_name'] ?? 'Employee';

$current_page = 'performance';

$user_id = $_SESSION['user_id'];
$employee = getOne("
    SELECT e.*, a.area_name
    FROM employees e
    LEFT JOIN areas a ON e.area_id = a.area_id
    WHERE e.employee_id = ?
", [$user_id]);

$_SESSION['language'] = $employee['language'] ?? 'en';

$period_type = $_GET['period'] ?? 'current_month';
$custom_start = $_GET['start'] ?? null;
$custom_end = $_GET['end'] ?? null;

$calculator = new PerformanceCalculator($user_id, $period_type, $custom_start, $custom_end);
$metrics = $calculator->calculatePerformance();

$history = $calculator->getPerformanceHistory();

function getPeriodDisplayName($period_type, $start, $end) {
    switch ($period_type) {
        case 'current_month':
            return t('current_month_display');
        case 'last_7_days':
            return t('last_7_days');
        case 'last_30_days':
            return t('last_30_days');
        case 'all_time':
            return t('all_time');
        case 'custom':
            return date('M j, Y', strtotime($start)) . ' - ' . date('M j, Y', strtotime($end));
        default:
            return date('F Y');
    }
}

$period_display = getPeriodDisplayName($period_type, $metrics['period_start'], $metrics['period_end']);

$grade_config = [
    'excellent' => ['color' => '#27ae60', 'label' => t('grade_excellent'), 'icon' => '<i class="fa-solid fa-trophy"></i>'],
    'good' => ['color' => '#9db89a', 'label' => t('grade_good'), 'icon' => '<i class="fa-solid fa-thumbs-up"></i>'],
    'average' => ['color' => '#f39c12', 'label' => t('grade_average'), 'icon' => '<i class="fa-solid fa-face-meh"></i>'],
    'needs_improvement' => ['color' => '#e67e22', 'label' => t('grade_needs_improvement'), 'icon' => '<i class="fa-solid fa-triangle-exclamation"></i>'],
    'poor' => ['color' => '#e74c3c', 'label' => t('grade_poor'), 'icon' => '<i class="fa-solid fa-circle-xmark"></i>']
];

$current_grade = $grade_config[$metrics['performance_grade']];

$current_time = date('g:i A');
$current_date = date('l, F j, Y');
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['language']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('my_performance'); ?> - EcoBin</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        }

        .icon-main {
            color: #435334;
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-info {
            text-align: right;
        }

        .header-time {
            font-size: 14px;
            color: #666;
        }

        .header-date {
            font-size: 12px;
            color: #999;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #CEDEBD;
            color: #435334;
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #435334;
            color: white;
            transform: translateY(-2px);
        }

        .welcome-card {
            background: linear-gradient(135deg, #CEDEBD, #9db89a);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            color: #435334;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .welcome-card h2 {
            font-size: 28px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .welcome-card p {
            font-size: 15px;
            opacity: 0.9;
        }

        .period-selector {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .period-selector h3 {
            color: #435334;
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .period-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .period-btn {
            padding: 10px 20px;
            border: 2px solid #CEDEBD;
            background: white;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #435334;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .period-btn:hover {
            border-color: #435334;
            background: #FAF1E4;
        }

        .period-btn.active {
            background: #435334;
            color: white;
            border-color: #435334;
        }

        .score-showcase {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .main-score-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }

        .score-circle {
            width: 180px;
            height: 180px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: conic-gradient(
                <?php echo $current_grade['color']; ?> 0deg,
                <?php echo $current_grade['color']; ?> <?php echo $metrics['performance_score'] * 3.6; ?>deg,
                #CEDEBD <?php echo $metrics['performance_score'] * 3.6; ?>deg
            );
        }

        .score-inner {
            width: 150px;
            height: 150px;
            background: white;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .score-value {
            font-size: 48px;
            font-weight: bold;
            color: <?php echo $current_grade['color']; ?>;
        }

        .score-percent {
            font-size: 20px;
            color: #999;
        }

        .grade-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: <?php echo $current_grade['color']; ?>;
            color: white;
            border-radius: 50px;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stars-display {
            font-size: 32px;
            margin: 10px 0;
            color: #FFD700;
        }

        .performance-label {
            color: #999;
            margin-top: 15px;
            font-size: 14px;
        }

        .breakdown-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .breakdown-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }

        .breakdown-card:hover {
            transform: translateY(-3px);
        }

        .breakdown-card h4 {
            margin: 0 0 10px 0;
            color: #435334;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .breakdown-score {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }

        .breakdown-stars {
            font-size: 20px;
            margin: 10px 0;
            color: #FFD700;
        }

        .breakdown-bar {
            width: 100%;
            height: 8px;
            background: #FAF1E4;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }

        .breakdown-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        .breakdown-weight {
            color: #999;
            font-size: 11px;
            margin-top: 10px;
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #999;
            font-size: 12px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #435334;
        }

        .chart-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .chart-container h3 {
            color: #435334;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @media (max-width: 1200px) {
            .score-showcase {
                grid-template-columns: 1fr;
            }
            
            .breakdown-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 80px 15px 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .header-info {
                text-align: left;
            }

            .back-btn {
                width: 100%;
                justify-content: center;
                margin-bottom: 15px;
            }

            .welcome-card {
                padding: 20px;
            }

            .welcome-card h2 {
                font-size: 20px;
            }

            .welcome-card p {
                font-size: 14px;
            }

            .period-selector {
                padding: 15px;
            }

            .period-selector h3 {
                font-size: 14px;
            }

            .period-buttons {
                flex-direction: column;
            }

            .period-btn {
                width: 100%;
                justify-content: center;
            }

            .score-showcase {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .main-score-card {
                padding: 25px;
            }

            .score-circle {
                width: 160px;
                height: 160px;
            }

            .score-inner {
                width: 135px;
                height: 135px;
            }

            .score-value {
                font-size: 40px;
            }

            .score-percent {
                font-size: 18px;
            }

            .grade-badge {
                font-size: 14px;
                padding: 10px 20px;
            }

            .stars-display {
                font-size: 28px;
            }

            .breakdown-cards {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .breakdown-card {
                padding: 15px;
            }

            .breakdown-card h4 {
                font-size: 13px;
            }

            .breakdown-score {
                font-size: 28px;
            }

            .breakdown-stars {
                font-size: 18px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-icon {
                font-size: 28px;
                margin-bottom: 8px;
            }

            .stat-label {
                font-size: 11px;
            }

            .stat-value {
                font-size: 20px;
            }

            .chart-container {
                padding: 20px;
            }

            .chart-container h3 {
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 70px 10px 15px;
            }

            .page-header h1 {
                font-size: 20px;
            }

            .header-time {
                font-size: 12px;
            }

            .header-date {
                font-size: 11px;
            }

            .back-btn {
                padding: 10px 18px;
                font-size: 13px;
            }

            .welcome-card {
                padding: 15px;
                margin-bottom: 20px;
            }

            .welcome-card h2 {
                font-size: 18px;
            }

            .welcome-card p {
                font-size: 13px;
            }

            .period-selector {
                padding: 12px;
                margin-bottom: 15px;
            }

            .period-selector h3 {
                font-size: 13px;
                margin-bottom: 12px;
            }

            .period-btn {
                padding: 10px 15px;
                font-size: 12px;
            }

            .score-showcase {
                gap: 12px;
                margin-bottom: 20px;
            }

            .main-score-card {
                padding: 20px;
            }

            .score-circle {
                width: 140px;
                height: 140px;
                margin-bottom: 15px;
            }

            .score-inner {
                width: 120px;
                height: 120px;
            }

            .score-value {
                font-size: 36px;
            }

            .score-percent {
                font-size: 16px;
            }

            .grade-badge {
                font-size: 13px;
                padding: 8px 16px;
            }

            .stars-display {
                font-size: 24px;
                margin: 8px 0;
            }

            .performance-label {
                font-size: 12px;
            }

            .breakdown-cards {
                gap: 10px;
            }

            .breakdown-card {
                padding: 12px;
            }

            .breakdown-card h4 {
                font-size: 12px;
                margin-bottom: 8px;
            }

            .breakdown-score {
                font-size: 24px;
                margin: 8px 0;
            }

            .breakdown-stars {
                font-size: 16px;
                margin: 8px 0;
            }

            .breakdown-bar {
                height: 6px;
                margin-top: 8px;
            }

            .breakdown-weight {
                font-size: 10px;
                margin-top: 8px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
                margin-bottom: 20px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-icon {
                font-size: 24px;
                margin-bottom: 8px;
            }

            .stat-label {
                font-size: 10px;
                margin-bottom: 6px;
            }

            .stat-value {
                font-size: 18px;
            }

            .chart-container {
                padding: 15px;
                margin-bottom: 20px;
            }

            .chart-container h3 {
                font-size: 14px;
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>
                <?php echo t('my_performance_report'); ?>
            </h1>
            <div class="header-info">
                <div class="header-time"><?php echo $current_time; ?></div>
                <div class="header-date"><?php echo $current_date; ?></div>
            </div>
        </div>

        <a href="employee_dashboard.php" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i>
            <?php echo t('back_to_dashboard'); ?>
        </a>

        <div class="welcome-card">
            <p><?php echo t('performance_report_for'); ?> <strong><?php echo htmlspecialchars($period_display); ?></strong></p>
        </div>

        <div class="period-selector">
            <h3>
                <i class="fa-solid fa-calendar-days"></i>
                <?php echo t('select_time_period'); ?>
            </h3>
            <div class="period-buttons">
                <a href="?period=current_month" class="period-btn <?php echo $period_type === 'current_month' ? 'active' : ''; ?>">
                    <?php echo t('current_month'); ?>
                </a>
                <a href="?period=last_7_days" class="period-btn <?php echo $period_type === 'last_7_days' ? 'active' : ''; ?>">
                    <?php echo t('last_7_days'); ?>
                </a>
                <a href="?period=last_30_days" class="period-btn <?php echo $period_type === 'last_30_days' ? 'active' : ''; ?>">
                    <?php echo t('last_30_days'); ?>
                </a>
                <a href="?period=all_time" class="period-btn <?php echo $period_type === 'all_time' ? 'active' : ''; ?>">
                    <?php echo t('all_time'); ?>
                </a>
            </div>
        </div>

        <div class="score-showcase">
            <div class="main-score-card">
                <div class="score-circle">
                    <div class="score-inner">
                        <div class="score-value"><?php echo number_format($metrics['performance_score'], 1); ?></div>
                        <div class="score-percent">%</div>
                    </div>
                </div>
                
                <div class="grade-badge">
                    <?php echo $current_grade['icon'] . ' ' . $current_grade['label']; ?>
                </div>
                
                <div class="stars-display">
                    <?php 
                    for ($i = 0; $i < 5; $i++) {
                        echo $i < $metrics['overall_stars'] ? '⭐' : '☆';
                    }
                    ?>
                </div>
                
                <p class="performance-label">
                    <?php echo t('overall_performance_score'); ?>
                </p>
            </div>

            <div class="breakdown-cards">
                <div class="breakdown-card">
                    <h4>
                        <?php echo t('task_completion_rate'); ?>
                    </h4>
                    <div class="breakdown-score" style="color: #27ae60;">
                        <?php echo number_format($metrics['completion_rate'], 1); ?>%
                    </div>
                    <div class="breakdown-stars">
                        <?php 
                        for ($i = 0; $i < 5; $i++) {
                            echo $i < $metrics['task_performance_stars'] ? '⭐' : '☆';
                        }
                        ?>
                    </div>
                    <div class="breakdown-bar">
                        <div class="breakdown-fill" style="width: <?php echo $metrics['completion_rate']; ?>%; background: #27ae60;"></div>
                    </div>
                    <div class="breakdown-weight"><?php echo t('weight_30_percent'); ?></div>
                </div>

                <div class="breakdown-card">
                    <h4>
                        <?php echo t('on_time_completion_rate'); ?>
                    </h4>
                    <div class="breakdown-score" style="color: #9db89a;">
                        <?php echo number_format($metrics['on_time_rate'], 1); ?>%
                    </div>
                    <div class="breakdown-stars">
                        <?php 
                        for ($i = 0; $i < 5; $i++) {
                            echo $i < $metrics['task_performance_stars'] ? '⭐' : '☆';
                        }
                        ?>
                    </div>
                    <div class="breakdown-bar">
                        <div class="breakdown-fill" style="width: <?php echo $metrics['on_time_rate']; ?>%; background: #9db89a;"></div>
                    </div>
                    <div class="breakdown-weight"><?php echo t('weight_30_percent'); ?></div>
                </div>

                <div class="breakdown-card">
                    <h4>
                        <?php echo t('attendance_rate'); ?>
                    </h4>
                    <div class="breakdown-score" style="color: #f39c12;">
                        <?php echo number_format($metrics['attendance_rate'], 1); ?>%
                    </div>
                    <div class="breakdown-stars">
                        <?php 
                        for ($i = 0; $i < 5; $i++) {
                            echo $i < $metrics['attendance_stars'] ? '⭐' : '☆';
                        }
                        ?>
                    </div>
                    <div class="breakdown-bar">
                        <div class="breakdown-fill" style="width: <?php echo $metrics['attendance_rate']; ?>%; background: #f39c12;"></div>
                    </div>
                    <div class="breakdown-weight"><?php echo t('weight_25_percent'); ?></div>
                </div>

                <div class="breakdown-card">
                    <h4>
                        <?php echo t('efficiency_score'); ?>
                    </h4>
                    <div class="breakdown-score" style="color: #435334;">
                        <?php echo number_format($metrics['efficiency_score'], 1); ?>%
                    </div>
                    <div class="breakdown-stars">
                        <?php 
                        for ($i = 0; $i < 5; $i++) {
                            echo $i < $metrics['efficiency_stars'] ? '⭐' : '☆';
                        }
                        ?>
                    </div>
                    <div class="breakdown-bar">
                        <div class="breakdown-fill" style="width: <?php echo $metrics['efficiency_score']; ?>%; background: #435334;"></div>
                    </div>
                    <div class="breakdown-weight"><?php echo t('weight_15_percent'); ?></div>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-clipboard-list icon-main"></i></div>
                <div class="stat-label"><?php echo t('tasks_assigned'); ?></div>
                <div class="stat-value"><?php echo $metrics['tasks_assigned']; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-circle-check icon-main"></i></div>
                <div class="stat-label"><?php echo t('tasks_completed'); ?></div>
                <div class="stat-value"><?php echo $metrics['tasks_completed']; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-trash icon-main"></i></div>
                <div class="stat-label"><?php echo t('bins_collected'); ?></div>
                <div class="stat-value"><?php echo $metrics['total_bins_collected']; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-weight-hanging icon-main"></i></div>
                <div class="stat-label"><?php echo t('weight_collected'); ?></div>
                <div class="stat-value"><?php echo number_format($metrics['total_weight_collected'], 1); ?> kg</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-calendar-check icon-main"></i></div>
                <div class="stat-label"><?php echo t('attendance_days'); ?></div>
                <div class="stat-value"><?php echo $metrics['attendance_days']; ?>/<?php echo $metrics['working_days']; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-stopwatch icon-main"></i></div>
                <div class="stat-label"><?php echo t('late_arrivals'); ?></div>
                <div class="stat-value"><?php echo $metrics['late_days']; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-clock icon-main"></i></div>
                <div class="stat-label"><?php echo t('avg_response_time'); ?></div>
                <div class="stat-value"><?php echo number_format($metrics['avg_task_completion_hours'], 1); ?>h</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-bullseye icon-main"></i></div>
                <div class="stat-label"><?php echo t('on_time_tasks'); ?></div>
                <div class="stat-value"><?php echo $metrics['tasks_on_time']; ?></div>
            </div>
        </div>

        <div class="chart-container">
            <h3>
                <?php echo t('performance_trend_6_months'); ?>
            </h3>
            <canvas id="performanceChart"></canvas>
        </div>
    </div>

    <script>
        const chartTranslations = {
            overallScore: "<?php echo t('overall_score'); ?>",
            completionRate: "<?php echo t('completion_rate_chart'); ?>",
            onTimeRate: "<?php echo t('on_time_rate_chart'); ?>",
            attendanceRate: "<?php echo t('attendance_rate_chart'); ?>"
        };

        const ctx = document.getElementById('performanceChart').getContext('2d');
        const performanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($history, 'month')); ?>,
                datasets: [
                    {
                        label: chartTranslations.overallScore,
                        data: <?php echo json_encode(array_column($history, 'score')); ?>,
                        borderColor: '#435334',
                        backgroundColor: 'rgba(67, 83, 52, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 3
                    },
                    {
                        label: chartTranslations.completionRate,
                        data: <?php echo json_encode(array_column($history, 'completion_rate')); ?>,
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        tension: 0.4,
                        fill: false,
                        borderWidth: 2
                    },
                    {
                        label: chartTranslations.onTimeRate,
                        data: <?php echo json_encode(array_column($history, 'on_time_rate')); ?>,
                        borderColor: '#9db89a',
                        backgroundColor: 'rgba(157, 184, 154, 0.1)',
                        tension: 0.4,
                        fill: false,
                        borderWidth: 2
                    },
                    {
                        label: chartTranslations.attendanceRate,
                        data: <?php echo json_encode(array_column($history, 'attendance_rate')); ?>,
                        borderColor: '#f39c12',
                        backgroundColor: 'rgba(243, 156, 18, 0.1)',
                        tension: 0.4,
                        fill: false,
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: {
                                size: 12,
                                family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            },
                            color: '#999'
                        },
                        grid: {
                            color: '#f0f0f0'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#999'
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>