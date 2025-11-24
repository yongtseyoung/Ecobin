<?php
/**
 * Employee Performance Dashboard - EcoBin Theme
 * Shows detailed performance metrics with visualizations
 */

session_start();
require_once '../config/database.php';
require_once '../config/performance_calculator.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['user_id'];
$employee_name = $_SESSION['full_name'] ?? 'Employee';

// Set current page for sidebar
$current_page = 'performance';

$user_id = $_SESSION['user_id'];
$employee = getOne("
    SELECT e.*, a.area_name
    FROM employees e
    LEFT JOIN areas a ON e.area_id = a.area_id
    WHERE e.employee_id = ?
", [$user_id]);


// Get selected period (default: current_month)
$period_type = $_GET['period'] ?? 'current_month';
$custom_start = $_GET['start'] ?? null;
$custom_end = $_GET['end'] ?? null;

// Calculate performance metrics
$calculator = new PerformanceCalculator($user_id, $period_type, $custom_start, $custom_end);
$metrics = $calculator->calculatePerformance();

// Get performance history for chart
$history = $calculator->getPerformanceHistory();

// Get period display name
function getPeriodDisplayName($period_type, $start, $end) {
    switch ($period_type) {
        case 'current_month':
            return date('F Y');
        case 'last_7_days':
            return 'Last 7 Days';
        case 'last_30_days':
            return 'Last 30 Days';
        case 'all_time':
            return 'All Time';
        case 'custom':
            return date('M j, Y', strtotime($start)) . ' - ' . date('M j, Y', strtotime($end));
        default:
            return date('F Y');
    }
}

$period_display = getPeriodDisplayName($period_type, $metrics['period_start'], $metrics['period_end']);

// Grade configuration
$grade_config = [
    'excellent' => ['color' => '#27ae60', 'label' => 'Excellent', 'icon' => '🏆'],
    'good' => ['color' => '#9db89a', 'label' => 'Good', 'icon' => '👍'],
    'average' => ['color' => '#f39c12', 'label' => 'Average', 'icon' => '👌'],
    'needs_improvement' => ['color' => '#e67e22', 'label' => 'Needs Improvement', 'icon' => '⚠️'],
    'poor' => ['color' => '#e74c3c', 'label' => 'Poor', 'icon' => '❌']
];

$current_grade = $grade_config[$metrics['performance_grade']];

// Get current date info
$current_time = date('g:i A');
$current_date = date('l, F j, Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Performance - EcoBin</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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

        /* Page Header */
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
            display: inline-block;
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
        }

        /* Welcome Card */
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
        }

        .welcome-card p {
            font-size: 15px;
            opacity: 0.9;
        }

        /* Period Selector */
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

        /* Score Showcase */
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
            display: inline-block;
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

        /* Breakdown Cards */
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
        }

        .breakdown-card h4 {
            margin: 0 0 10px 0;
            color: #435334;
            font-size: 14px;
            font-weight: 600;
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

        /* Stats Grid */
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

        /* Chart Container */
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
        }

        /* Insights Section */
        .insights-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .insights-section h3 {
            color: #435334;
            margin-bottom: 20px;
            font-size: 18px;
        }

        .insight-item {
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #CEDEBD;
            background: #FAF1E4;
            border-radius: 5px;
        }

        .insight-item strong {
            color: #435334;
        }

        .insight-item p {
            margin-top: 5px;
            color: #666;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .score-showcase {
                grid-template-columns: 1fr;
            }
            
            .breakdown-cards {
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

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .period-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>📊 My Performance Report</h1>
            <div class="header-info">
                <div class="header-time"><?php echo $current_time; ?></div>
                <div class="header-date"><?php echo $current_date; ?></div>
            </div>
        </div>

        <a href="employee_dashboard.php" class="back-btn">← Back to Dashboard</a>

        <!-- Welcome Card -->
        <div class="welcome-card">
            <h2>Performance Metrics</h2>
            <p>Your performance report for <strong><?php echo htmlspecialchars($period_display); ?></strong></p>
        </div>

        <!-- Period Selector -->
        <div class="period-selector">
            <h3>📅 Select Time Period</h3>
            <div class="period-buttons">
                <a href="?period=current_month" class="period-btn <?php echo $period_type === 'current_month' ? 'active' : ''; ?>">
                    Current Month
                </a>
                <a href="?period=last_7_days" class="period-btn <?php echo $period_type === 'last_7_days' ? 'active' : ''; ?>">
                    Last 7 Days
                </a>
                <a href="?period=last_30_days" class="period-btn <?php echo $period_type === 'last_30_days' ? 'active' : ''; ?>">
                    Last 30 Days
                </a>
                <a href="?period=all_time" class="period-btn <?php echo $period_type === 'all_time' ? 'active' : ''; ?>">
                    All Time
                </a>
            </div>
        </div>

        <!-- Main Score Showcase -->
        <div class="score-showcase">
            <!-- Overall Score -->
            <div class="main-score-card">
                <div class="score-circle">
                    <div class="score-inner">
                        <div class="score-value"><?php echo number_format($metrics['performance_score'], 1); ?></div>
                        <div class="score-percent">%</div>
                    </div>
                </div>
                
                <div class="grade-badge">
                    <?php echo $current_grade['icon']; ?> <?php echo $current_grade['label']; ?>
                </div>
                
                <div class="stars-display">
                    <?php 
                    for ($i = 0; $i < 5; $i++) {
                        echo $i < $metrics['overall_stars'] ? '⭐' : '☆';
                    }
                    ?>
                </div>
                
                <p class="performance-label">
                    Overall Performance Score
                </p>
            </div>

            <!-- Performance Breakdown -->
            <div class="breakdown-cards">
                <!-- Task Completion Rate -->
                <div class="breakdown-card">
                    <h4>📋 Task Completion Rate</h4>
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
                    <div class="breakdown-weight">Weight: 30% of overall score</div>
                </div>

                <!-- On-Time Rate -->
                <div class="breakdown-card">
                    <h4>⏰ On-Time Completion Rate</h4>
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
                    <div class="breakdown-weight">Weight: 30% of overall score</div>
                </div>

                <!-- Attendance Rate -->
                <div class="breakdown-card">
                    <h4>👤 Attendance Rate</h4>
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
                    <div class="breakdown-weight">Weight: 25% of overall score</div>
                </div>

                <!-- Efficiency Score -->
                <div class="breakdown-card">
                    <h4>⚡ Efficiency Score</h4>
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
                    <div class="breakdown-weight">Weight: 15% of overall score</div>
                </div>
            </div>
        </div>

        <!-- Key Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-label">Tasks Assigned</div>
                <div class="stat-value"><?php echo $metrics['tasks_assigned']; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-label">Tasks Completed</div>
                <div class="stat-value"><?php echo $metrics['tasks_completed']; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🗑️</div>
                <div class="stat-label">Bins Collected</div>
                <div class="stat-value"><?php echo $metrics['total_bins_collected']; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">⚖️</div>
                <div class="stat-label">Weight Collected</div>
                <div class="stat-value"><?php echo number_format($metrics['total_weight_collected'], 1); ?> kg</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-label">Attendance Days</div>
                <div class="stat-value"><?php echo $metrics['attendance_days']; ?>/<?php echo $metrics['working_days']; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">⏱️</div>
                <div class="stat-label">Late Arrivals</div>
                <div class="stat-value"><?php echo $metrics['late_days']; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">⏰</div>
                <div class="stat-label">Avg Response Time</div>
                <div class="stat-value"><?php echo number_format($metrics['avg_task_completion_hours'], 1); ?>h</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🎯</div>
                <div class="stat-label">On-Time Tasks</div>
                <div class="stat-value"><?php echo $metrics['tasks_on_time']; ?></div>
            </div>
        </div>

        <!-- Performance Trend Chart -->
        <div class="chart-container">
            <h3>📈 Performance Trend (Last 6 Months)</h3>
            <canvas id="performanceChart"></canvas>
        </div>

        <!-- Insights Section -->
        <div class="insights-section">
            <h3>💡 Performance Insights</h3>
            
            <?php if ($metrics['performance_score'] >= 90): ?>
                <div class="insight-item" style="border-color: #27ae60;">
                    <strong>🏆 Outstanding Performance!</strong>
                    <p>You're in the top tier! Keep up the excellent work. Your dedication is making a real difference in our waste management operations.</p>
                </div>
            <?php elseif ($metrics['performance_score'] >= 75): ?>
                <div class="insight-item" style="border-color: #9db89a;">
                    <strong>👍 Great Job!</strong>
                    <p>Your performance is solid. Focus on maintaining consistency to reach excellence level.</p>
                </div>
            <?php else: ?>
                <div class="insight-item" style="border-color: #f39c12;">
                    <strong>💪 Room for Improvement</strong>
                    <p>Let's work together to boost your performance. Check the tips below for areas to focus on.</p>
                </div>
            <?php endif; ?>

            <?php if ($metrics['on_time_rate'] < 80): ?>
                <div class="insight-item" style="border-color: #e67e22;">
                    <strong>⏰ Improve On-Time Completion</strong>
                    <p>Try to complete tasks by their scheduled date. Current on-time rate: <?php echo number_format($metrics['on_time_rate'], 1); ?>%</p>
                </div>
            <?php endif; ?>

            <?php if ($metrics['attendance_rate'] < 90): ?>
                <div class="insight-item" style="border-color: #f39c12;">
                    <strong>👤 Boost Attendance</strong>
                    <p>Regular attendance is crucial. You've attended <?php echo $metrics['attendance_days']; ?> out of <?php echo $metrics['working_days']; ?> working days.</p>
                </div>
            <?php endif; ?>

            <?php if ($metrics['avg_task_completion_hours'] > 24): ?>
                <div class="insight-item" style="border-color: #435334;">
                    <strong>⚡ Speed Up Response Time</strong>
                    <p>Try to respond to tasks faster. Current average response time: <?php echo number_format($metrics['avg_task_completion_hours'], 1); ?> hours.</p>
                </div>
            <?php endif; ?>

            <?php if ($metrics['performance_score'] >= 90 && $metrics['on_time_rate'] >= 90 && $metrics['attendance_rate'] >= 95): ?>
                <div class="insight-item" style="border-color: #27ae60;">
                    <strong>🌟 Exceptional Work!</strong>
                    <p>You're consistently meeting all performance targets. Your commitment to excellence is truly appreciated!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Performance Trend Chart with EcoBin colors
        const ctx = document.getElementById('performanceChart').getContext('2d');
        const performanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($history, 'month')); ?>,
                datasets: [
                    {
                        label: 'Overall Score',
                        data: <?php echo json_encode(array_column($history, 'score')); ?>,
                        borderColor: '#435334',
                        backgroundColor: 'rgba(67, 83, 52, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 3
                    },
                    {
                        label: 'Completion Rate',
                        data: <?php echo json_encode(array_column($history, 'completion_rate')); ?>,
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        tension: 0.4,
                        fill: false,
                        borderWidth: 2
                    },
                    {
                        label: 'On-Time Rate',
                        data: <?php echo json_encode(array_column($history, 'on_time_rate')); ?>,
                        borderColor: '#9db89a',
                        backgroundColor: 'rgba(157, 184, 154, 0.1)',
                        tension: 0.4,
                        fill: false,
                        borderWidth: 2
                    },
                    {
                        label: 'Attendance Rate',
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