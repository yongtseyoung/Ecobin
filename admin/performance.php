<?php

session_start();
require_once '../config/database.php';
require_once '../config/performance_calculator.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'employees';

$employees = getAll("SELECT * FROM employees WHERE status = 'active' ORDER BY full_name");

$period_type = $_GET['period'] ?? 'current_month';

$all_performance = [];
foreach ($employees as $employee) {
    $metrics = getEmployeePerformance($employee['employee_id'], $period_type);
    $metrics['employee_name'] = $employee['full_name'];
    $metrics['employee_username'] = $employee['username'];
    $all_performance[] = $metrics;
}

usort($all_performance, function($a, $b) {
    return $b['performance_score'] <=> $a['performance_score'];
});

$team_avg_score = 0;
$team_avg_completion = 0;
$team_avg_ontime = 0;
$team_avg_attendance = 0;

if (count($all_performance) > 0) {
    $team_avg_score = array_sum(array_column($all_performance, 'performance_score')) / count($all_performance);
    $team_avg_completion = array_sum(array_column($all_performance, 'completion_rate')) / count($all_performance);
    $team_avg_ontime = array_sum(array_column($all_performance, 'on_time_rate')) / count($all_performance);
    $team_avg_attendance = array_sum(array_column($all_performance, 'attendance_rate')) / count($all_performance);
}

$grade_colors = [
    'excellent' => '#27ae60',
    'good' => '#9db89a',
    'average' => '#f39c12',
    'needs_improvement' => '#e67e22',
    'poor' => '#e74c3c'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Performance - EcoBin Admin</title>
    
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
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #435334;
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
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 36px;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: #435334;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .performance-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 25px;
            border-bottom: 2px solid #f0f0f0;
        }

        .table-header h3 {
            color: #435334;
            font-size: 18px;
        }

        .performance-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .performance-table th {
            background: #FAF1E4;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #435334;
            border-bottom: 2px solid #CEDEBD;
            font-size: 13px;
        }

        .performance-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .performance-table tr:hover {
            background: #fafafa;
        }

        .rank-badge {
            display: inline-block;
            width: 35px;
            height: 35px;
            line-height: 35px;
            text-align: center;
            border-radius: 50%;
            font-weight: bold;
            font-size: 14px;
        }

        .rank-1 { 
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: white;
        }
        .rank-2 { 
            background: linear-gradient(135deg, #C0C0C0, #808080);
            color: white;
        }
        .rank-3 { 
            background: linear-gradient(135deg, #CD7F32, #8B4513);
            color: white;
        }
        .rank-other { 
            background: #CEDEBD;
            color: #435334;
        }

        .score-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 600;
            color: white;
            font-size: 14px;
        }

        .grade-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .stars {
            color: #FFD700;
            font-size: 16px;
        }

        .view-btn {
            padding: 8px 16px;
            background: #CEDEBD;
            color: #435334;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
        }

        .view-btn:hover {
            background: #435334;
            color: white;
        }

        .insights-card {
            margin-top: 30px;
            padding: 25px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .insights-card h3 {
            color: #435334;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .insights-card ul {
            list-style: none;
        }

        .insights-card li {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            color: #666;
            font-size: 14px;
        }

        .insights-card li:last-child {
            border-bottom: none;
        }

        .insights-card strong {
            color: #435334;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .period-buttons {
                flex-direction: column;
            }

            .performance-table {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>Team Performance Overview</h1>
        </div>

        <div class="period-selector">
            <h3>Select Time Period</h3>
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

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($team_avg_score, 1); ?>%</div>
                <div class="stat-label">Team Average Score</div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($team_avg_completion, 1); ?>%</div>
                <div class="stat-label">Avg Completion Rate</div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($team_avg_ontime, 1); ?>%</div>
                <div class="stat-label">Avg On-Time Rate</div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($team_avg_attendance, 1); ?>%</div>
                <div class="stat-label">Avg Attendance</div>
            </div>
        </div>

        <div class="performance-table">
            <div class="table-header">
                <h3>Employee Performance Rankings</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">Rank</th>
                        <th>Employee</th>
                        <th style="text-align: center;">Overall Score</th>
                        <th style="text-align: center;">Stars</th>
                        <th style="text-align: center;">Grade</th>
                        <th style="text-align: center;">Completion</th>
                        <th style="text-align: center;">On-Time</th>
                        <th style="text-align: center;">Attendance</th>
                        <th style="text-align: center;">Tasks</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_performance)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 40px; color: #999;">
                                No performance data available for this period.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($all_performance as $index => $perf): ?>
                            <?php 
                            $rank = $index + 1;
                            $rank_class = $rank <= 3 ? "rank-$rank" : "rank-other";
                            $rank_display = $rank <= 3 ? ['🥇', '🥈', '🥉'][$rank - 1] : $rank;
                            ?>
                            <tr>
                                <td>
                                    <span class="rank-badge <?php echo $rank_class; ?>">
                                        <?php echo $rank_display; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: #435334;"><?php echo htmlspecialchars($perf['employee_name']); ?></strong><br>
                                    <small style="color: #999;">@<?php echo htmlspecialchars($perf['employee_username']); ?></small>
                                </td>
                                <td style="text-align: center;">
                                    <span class="score-badge" style="background: <?php echo $grade_colors[$perf['performance_grade']]; ?>">
                                        <?php echo number_format($perf['performance_score'], 1); ?>%
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="stars">
                                        <?php 
                                        for ($i = 0; $i < 5; $i++) {
                                            echo $i < $perf['overall_stars'] ? '⭐' : '☆';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="grade-badge" style="background: <?php echo $grade_colors[$perf['performance_grade']]; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $perf['performance_grade'])); ?>
                                    </span>
                                </td>
                                <td style="text-align: center; color: #435334;">
                                    <strong><?php echo number_format($perf['completion_rate'], 1); ?>%</strong>
                                </td>
                                <td style="text-align: center; color: #435334;">
                                    <strong><?php echo number_format($perf['on_time_rate'], 1); ?>%</strong>
                                </td>
                                <td style="text-align: center; color: #435334;">
                                    <strong><?php echo number_format($perf['attendance_rate'], 1); ?>%</strong>
                                </td>
                                <td style="text-align: center; color: #435334;">
                                    <strong><?php echo $perf['tasks_completed']; ?></strong>/<?php echo $perf['tasks_assigned']; ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="employee_detail.php?id=<?php echo $perf['employee_id']; ?>&period=<?php echo $period_type; ?>" 
                                       class="view-btn">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="insights-card">
            <h3>Performance Insights</h3>
            <ul>
                <li>
                    <strong>Top Performer:</strong> 
                    <?php if (!empty($all_performance)): ?>
                        <?php echo htmlspecialchars($all_performance[0]['employee_name']); ?> 
                        with <?php echo number_format($all_performance[0]['performance_score'], 1); ?>% performance score 🏆
                    <?php else: ?>
                        No data available
                    <?php endif; ?>
                </li>
                <li>
                    <strong>Team Average Score:</strong> <?php echo number_format($team_avg_score, 1); ?>%
                </li>
                <li>
                    <strong>Total Active Employees:</strong> <?php echo count($all_performance); ?>
                </li>
                <li>
                    <strong>Employees Above Team Average:</strong> 
                    <?php 
                    $above_avg = array_filter($all_performance, function($p) use ($team_avg_score) {
                        return $p['performance_score'] >= $team_avg_score;
                    });
                    echo count($above_avg);
                    ?> (<?php echo count($all_performance) > 0 ? round((count($above_avg) / count($all_performance)) * 100, 1) : 0; ?>%)
                </li>
                <li>
                    <strong>Excellent Performers:</strong> 
                    <?php 
                    $excellent = array_filter($all_performance, function($p) {
                        return $p['performance_grade'] === 'excellent';
                    });
                    echo count($excellent);
                    ?> employees with 90%+ scores ⭐
                </li>
            </ul>
        </div>
    </div>
</body>
</html>