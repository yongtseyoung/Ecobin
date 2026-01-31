<?php

session_start();
require_once '../config/database.php';
require_once '../config/performance_calculator.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'employees';

$employee_id = $_GET['id'] ?? 0;
$period_type = $_GET['period'] ?? 'current_month';

$employee = getOne("SELECT e.*, a.area_name 
                    FROM employees e 
                    LEFT JOIN areas a ON e.area_id = a.area_id 
                    WHERE e.employee_id = ?", [$employee_id]);

if (!$employee) {
    $_SESSION['error'] = "Employee not found";
    header("Location: admin_performance_overview.php");
    exit;
}

$calculator = new PerformanceCalculator($employee_id, $period_type);
$metrics = $calculator->calculatePerformance();

$history = $calculator->getPerformanceHistory();

$recent_tasks = getAll(
    "SELECT t.*, a.area_name, b.bin_code 
     FROM tasks t 
     LEFT JOIN areas a ON t.area_id = a.area_id 
     LEFT JOIN bins b ON t.triggered_by_bin = b.bin_id 
     WHERE t.assigned_to = ? 
     ORDER BY t.created_at DESC 
     LIMIT 10",
    [$employee_id]
);

$recent_attendance = getAll(
    "SELECT * FROM attendance 
     WHERE employee_id = ? 
     ORDER BY attendance_date DESC 
     LIMIT 10",
    [$employee_id]
);

$grade_colors = [
    'excellent' => '#27ae60',
    'good' => '#9db89a',
    'average' => '#f39c12',
    'needs_improvement' => '#e67e22',
    'poor' => '#e74c3c'
];

$current_time = date('g:i A');
$current_date = date('l, F j, Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($employee['full_name']); ?> - Performance Details</title>
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

        .sidebar {
            width: 250px;
            background: #435334;
            color: white;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
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
            flex: 1;
            padding: 0 15px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin-bottom: 5px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: white;
            font-size: 13px;
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
            width: 24px;
            text-align: center;
        }

        .logout-btn {
            padding: 12px 15px;
            margin: 10px 15px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 10px;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
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

        .employee-header {
            background: linear-gradient(135deg, #CEDEBD, #9db89a);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            color: #435334;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .employee-header h2 {
            font-size: 28px;
            margin-bottom: 15px;
        }

        .employee-info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .info-item {
            background: rgba(255, 255, 255, 0.82);
            padding: 12px;
            border-radius: 8px;
        }

        .info-label {
            font-size: 12px;
            opacity: 0.8;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
        }

        .performance-summary {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .score-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }

        .score-circle {
            width: 200px;
            height: 200px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: conic-gradient(
                <?php echo $grade_colors[$metrics['performance_grade']]; ?> 0deg,
                <?php echo $grade_colors[$metrics['performance_grade']]; ?> <?php echo $metrics['performance_score'] * 3.6; ?>deg,
                #CEDEBD <?php echo $metrics['performance_score'] * 3.6; ?>deg
            );
        }

        .score-inner {
            width: 170px;
            height: 170px;
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
            color: <?php echo $grade_colors[$metrics['performance_grade']]; ?>;
        }

        .score-percent {
            font-size: 18px;
            color: #999;
        }

        .grade-badge {
            display: inline-block;
            padding: 10px 20px;
            background: <?php echo $grade_colors[$metrics['performance_grade']]; ?>;
            color: white;
            border-radius: 50px;
            font-weight: bold;
            margin: 10px 0;
            font-size: 14px;
        }

        .stars-display {
            font-size: 28px;
            margin: 10px 0;
            color: #FFD700;
        }

        .period-info {
            color: #999;
            margin-top: 15px;
            font-size: 13px;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .metric-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .metric-label {
            color: #999;
            font-size: 13px;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .metric-value {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }

        .metric-bar {
            width: 100%;
            height: 8px;
            background: #FAF1E4;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }

        .metric-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        .metric-weight {
            color: #999;
            font-size: 11px;
            margin-top: 8px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
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
            font-size: 12px;
            color: #999;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #435334;
        }

        .chart-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .chart-section h3 {
            color: #435334;
            margin-bottom: 20px;
            font-size: 18px;
        }

        .data-tables {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
        }

        .data-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 20px;
            background: #FAF1E4;
            border-bottom: 2px solid #CEDEBD;
        }

        .table-header h3 {
            color: #435334;
            font-size: 16px;
        }

        .data-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: #FAF1E4;
            padding: 12px;
            text-align: left;
            font-size: 12px;
            color: #435334;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }

        .data-table tr:hover {
            background: #fafafa;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-completed { background: #d4edda; color: #27ae60; }
        .status-pending { background: #fff3cd; color: #f39c12; }
        .status-in_progress { background: #cce5ff; color: #004085; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-present { background: #d4edda; color: #27ae60; }
        .status-late { background: #fff3cd; color: #f39c12; }
        .status-absent { background: #f8d7da; color: #721c24; }

        @media (max-width: 1200px) {
            .performance-summary {
                grid-template-columns: 1fr;
            }
            
            .metrics-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .data-tables {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .employee-info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>👤 Employee Performance Details</h1>
            <div class="header-info">
                <div class="header-time"><?php echo $current_time; ?></div>
                <div class="header-date"><?php echo $current_date; ?></div>
            </div>
        </div>

        <a href="performance.php?period=<?php echo $period_type; ?>" class="back-btn">
            ← Back to Team Overview
        </a>

        <div class="employee-header">
            <h2><?php echo htmlspecialchars($employee['full_name']); ?></h2>
            
            <div class="employee-info-grid">
                <div class="info-item">
                    <div class="info-label">Username</div>
                    <div class="info-value">@<?php echo htmlspecialchars($employee['username']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($employee['email']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Phone</div>
                    <div class="info-value"><?php echo htmlspecialchars($employee['phone_number'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Area Assignment</div>
                    <div class="info-value"><?php echo htmlspecialchars($employee['area_name'] ?? 'Not assigned'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value"><?php echo ucfirst($employee['status']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Last Login</div>
                    <div class="info-value">
                        <?php 
                        echo $employee['last_login'] 
                            ? date('M j, Y g:i A', strtotime($employee['last_login'])) 
                            : 'Never'; 
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="performance-summary">
            <div class="score-card">
                <div class="score-circle">
                    <div class="score-inner">
                        <div class="score-value"><?php echo number_format($metrics['performance_score'], 1); ?></div>
                        <div class="score-percent">%</div>
                    </div>
                </div>
                
                <div class="grade-badge">
                    <?php 
                    $grade_icons = [
                        'excellent' => '',
                        'good' => '',
                        'average' => '',
                        'needs_improvement' => '',
                        'poor' => ''
                    ];
                    echo $grade_icons[$metrics['performance_grade']] . ' ';
                    echo ucfirst(str_replace('_', ' ', $metrics['performance_grade'])); 
                    ?>
                </div>
                
                <div class="stars-display">
                    <?php 
                    for ($i = 0; $i < 5; $i++) {
                        echo $i < $metrics['overall_stars'] ? '⭐' : '☆';
                    }
                    ?>
                </div>
                
                <div class="period-info">
                    Performance Period:<br>
                    <strong><?php echo date('M j, Y', strtotime($metrics['period_start'])); ?></strong><br>to<br>
                    <strong><?php echo date('M j, Y', strtotime($metrics['period_end'])); ?></strong>
                </div>
            </div>

            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-label">Task Completion Rate</div>
                    <div class="metric-value" style="color: #435334;">
                        <?php echo number_format($metrics['completion_rate'], 1); ?>%
                    </div>
                    <div class="metric-bar">
                        <div class="metric-fill" style="width: <?php echo $metrics['completion_rate']; ?>%; background: #27ae60;"></div>
                    </div>
                    <div class="metric-weight">Weight: 30% of overall score</div>
                </div>

                <div class="metric-card">
                    <div class="metric-label">On-Time Completion</div>
                    <div class="metric-value" style="color: #435334;">
                        <?php echo number_format($metrics['on_time_rate'], 1); ?>%
                    </div>
                    <div class="metric-bar">
                        <div class="metric-fill" style="width: <?php echo $metrics['on_time_rate']; ?>%; background: #9db89a;"></div>
                    </div>
                    <div class="metric-weight">Weight: 30% of overall score</div>
                </div>

                <div class="metric-card">
                    <div class="metric-label">Attendance Rate</div>
                    <div class="metric-value" style="color: #435334;">
                        <?php echo number_format($metrics['attendance_rate'], 1); ?>%
                    </div>
                    <div class="metric-bar">
                        <div class="metric-fill" style="width: <?php echo $metrics['attendance_rate']; ?>%; background: #f39c12;"></div>
                    </div>
                    <div class="metric-weight">Weight: 25% of overall score</div>
                </div>

                <div class="metric-card">
                    <div class="metric-label">Efficiency Score</div>
                    <div class="metric-value" style="color: #435334;">
                        <?php echo number_format($metrics['efficiency_score'], 1); ?>%
                    </div>
                    <div class="metric-bar">
                        <div class="metric-fill" style="width: <?php echo $metrics['efficiency_score']; ?>%; background: #435334;"></div>
                    </div>
                    <div class="metric-weight">Weight: 15% of overall score</div>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Tasks Assigned</div>
                <div class="stat-value"><?php echo $metrics['tasks_assigned']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Tasks Completed</div>
                <div class="stat-value"><?php echo $metrics['tasks_completed']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Tasks On-Time</div>
                <div class="stat-value"><?php echo $metrics['tasks_on_time']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Bins Collected</div>
                <div class="stat-value"><?php echo $metrics['total_bins_collected']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Weight Collected</div>
                <div class="stat-value"><?php echo number_format($metrics['total_weight_collected'], 1); ?> kg</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Attendance Days</div>
                <div class="stat-value"><?php echo $metrics['attendance_days']; ?>/<?php echo $metrics['working_days']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Late Arrivals</div>
                <div class="stat-value"><?php echo $metrics['late_days']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Avg Response Time</div>
                <div class="stat-value"><?php echo number_format($metrics['avg_task_completion_hours'], 1); ?>h</div>
            </div>
        </div>

        <div class="chart-section">
            <h3>Performance Trend (Last 6 Months)</h3>
            <canvas id="performanceChart"></canvas>
        </div>

        <div class="data-tables">
            <div class="data-table">
                <div class="table-header">
                    <h3>Recent Tasks</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_tasks)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 20px; color: #999;">
                                    No tasks found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_tasks as $task): ?>
                                <tr>
                                    <td>
                                        <strong style="color: #435334;"><?php echo htmlspecialchars($task['task_title']); ?></strong><br>
                                        <small style="color: #999;">
                                            <?php echo $task['bin_code'] ? 'Bin: ' . htmlspecialchars($task['bin_code']) : htmlspecialchars($task['area_name']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $task['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                        </span>
                                    </td>
                                    <td style="color: #666;">
                                        <?php echo date('M j, Y', strtotime($task['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="data-table">
                <div class="table-header">
                    <h3>Recent Attendance</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Check-In</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_attendance)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 20px; color: #999;">
                                    No attendance records found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_attendance as $att): ?>
                                <tr>
                                    <td style="color: #666;">
                                        <?php echo date('M j, Y', strtotime($att['attendance_date'])); ?>
                                    </td>
                                    <td style="color: #435334;">
                                        <strong><?php echo $att['check_in_time'] ? date('h:i A', strtotime($att['check_in_time'])) : '-'; ?></strong>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $att['status']; ?>">
                                            <?php echo ucfirst($att['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
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