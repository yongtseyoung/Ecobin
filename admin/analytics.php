<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'reports';

$admin_name = $_SESSION['full_name'] ?? 'Admin';

$period = $_GET['period'] ?? 'this_month';
$custom_start = $_GET['start'] ?? null;
$custom_end = $_GET['end'] ?? null;

switch ($period) {
    case 'today':
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        $period_label = 'Today';
        break;
    case 'this_week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        $period_label = 'This Week';
        break;
    case 'this_month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        $period_label = date('F Y');
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('last month'));
        $end_date = date('Y-m-t', strtotime('last month'));
        $period_label = date('F Y', strtotime('last month'));
        break;
    case 'this_year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        $period_label = date('Y');
        break;
    case 'custom':
        $start_date = $custom_start ?? date('Y-m-01');
        $end_date = $custom_end ?? date('Y-m-t');
        $period_label = date('M j', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date));
        break;
    default:
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        $period_label = date('F Y');
}

$waste_stats = getOne("
    SELECT 
        COUNT(DISTINCT t.task_id) as total_collections,
        COUNT(DISTINCT t.triggered_by_bin) as total_bins_collected
    FROM tasks t
    WHERE t.status = 'completed'
    AND t.task_type = 'collection'
    AND DATE(t.completed_at) BETWEEN ? AND ?
", [$start_date, $end_date]);

$weight_data = getOne("
    SELECT 
        COALESCE(SUM(total_weight), 0) as total_weight,
        COALESCE(AVG(total_weight), 0) as avg_weight
    FROM collection_reports
    WHERE collection_date BETWEEN ? AND ?
    AND total_weight IS NOT NULL
", [$start_date, $end_date]);

$waste_stats['total_weight'] = $weight_data['total_weight'];
$waste_stats['avg_weight_per_collection'] = $weight_data['avg_weight'];

$waste_by_area = getAll("
    SELECT 
        a.area_name,
        COUNT(t.task_id) as collection_count,
        COUNT(DISTINCT t.triggered_by_bin) as bins_collected,
        COALESCE(SUM(cr.total_weight), 0) as total_weight
    FROM tasks t
    LEFT JOIN areas a ON t.area_id = a.area_id
    LEFT JOIN collection_reports cr ON cr.task_id = t.task_id
    WHERE t.status = 'completed'
    AND t.task_type = 'collection'
    AND DATE(t.completed_at) BETWEEN ? AND ?
    GROUP BY a.area_id
    ORDER BY total_weight DESC
", [$start_date, $end_date]);

$bin_performance = getAll("
    SELECT 
        b.bin_id,
        b.bin_code,
        b.location_details,
        a.area_name,
        COUNT(DISTINCT t.task_id) as service_count,
        COALESCE(SUM(cr.total_weight), 0) as total_weight
    FROM tasks t
    INNER JOIN bins b ON t.triggered_by_bin = b.bin_id
    INNER JOIN areas a ON b.area_id = a.area_id
    LEFT JOIN collection_reports cr ON cr.task_id = t.task_id 
        AND cr.collection_date BETWEEN ? AND ?
    WHERE t.status = 'completed' 
        AND t.task_type = 'collection'
        AND t.triggered_by_bin IS NOT NULL
        AND DATE(t.completed_at) BETWEEN ? AND ?
    GROUP BY b.bin_id, b.bin_code, b.location_details, a.area_name
    HAVING service_count > 0
    ORDER BY total_weight DESC, service_count DESC
    LIMIT 10
", [$start_date, $end_date, $start_date, $end_date]);

$daily_trend = getAll("
    SELECT 
        DATE(t.completed_at) as collection_date,
        COUNT(t.task_id) as collections,
        COUNT(DISTINCT t.triggered_by_bin) as bins,
        COALESCE(SUM(cr.total_weight), 0) as weight
    FROM tasks t
    LEFT JOIN collection_reports cr ON cr.task_id = t.task_id
    WHERE t.status = 'completed'
    AND t.task_type = 'collection'
    AND DATE(t.completed_at) BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
    GROUP BY DATE(t.completed_at)
    ORDER BY collection_date ASC
");

$monthly_comparison = getAll("
    SELECT 
        DATE_FORMAT(t.completed_at, '%Y-%m') as month,
        COUNT(t.task_id) as collections,
        COALESCE(SUM(cr.total_weight), 0) as weight
    FROM tasks t
    LEFT JOIN collection_reports cr ON cr.task_id = t.task_id
    WHERE t.status = 'completed'
    AND t.task_type = 'collection'
    AND t.completed_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(t.completed_at, '%Y-%m')
    ORDER BY month ASC
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waste Analytics - EcoBin</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #435334;
        }

        .btn-secondary {
            padding: 12px 24px;
            background: #CEDEBD;
            color: #435334;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            background: #b8ceaa;
        }

        .period-filter {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .period-filter h3 {
            color: #435334;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .period-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
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

        .custom-date-inputs {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .custom-date-inputs input {
            padding: 10px;
            border: 2px solid #CEDEBD;
            border-radius: 10px;
            font-size: 13px;
        }

        .custom-date-inputs button {
            padding: 10px 20px;
            background: #CEDEBD;
            border: none;
            border-radius: 10px;
            color: #435334;
            font-weight: 600;
            cursor: pointer;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 36px;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #435334;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-subtext {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }

        .impact-card {
            background: linear-gradient(135deg, #b0c599ff, #566a43ff);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .impact-card h2 {
            font-size: 24px;
            margin-bottom: 20px;
        }

        .impact-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .impact-item {
            text-align: center;
        }

        .impact-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .impact-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .impact-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .chart-card h3 {
            color: #435334;
            margin-bottom: 20px;
            font-size: 18px;
        }

        .chart-card canvas {
            max-height: 300px;
        }

        .data-tables {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .table-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .table-card h3 {
            color: #435334;
            margin-bottom: 20px;
            font-size: 18px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            color: #435334;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            border-bottom: 2px solid #e0e0e0;
        }

        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .data-table tr:hover {
            background: #fafafa;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .data-tables {
                grid-template-columns: 1fr;
            }

            .impact-grid {
                grid-template-columns: 1fr;
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
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>Waste Analytics</h1>
            <a href="analytics_reports.php" class="btn-secondary">
                View Reports
            </a>
        </div>

        <div class="period-filter">
            <h3>Select Time Period: <strong><?php echo $period_label; ?></strong></h3>
            <div class="period-buttons">
                <a href="?period=today" class="period-btn <?php echo $period === 'today' ? 'active' : ''; ?>">
                    Today
                </a>
                <a href="?period=this_week" class="period-btn <?php echo $period === 'this_week' ? 'active' : ''; ?>">
                    This Week
                </a>
                <a href="?period=this_month" class="period-btn <?php echo $period === 'this_month' ? 'active' : ''; ?>">
                    This Month
                </a>
                <a href="?period=last_month" class="period-btn <?php echo $period === 'last_month' ? 'active' : ''; ?>">
                    Last Month
                </a>
                <a href="?period=this_year" class="period-btn <?php echo $period === 'this_year' ? 'active' : ''; ?>">
                    This Year
                </a>
                
                <form method="GET" class="custom-date-inputs">
                    <input type="hidden" name="period" value="custom">
                    <input type="date" name="start" value="<?php echo $start_date; ?>" required>
                    <span>to</span>
                    <input type="date" name="end" value="<?php echo $end_date; ?>" required>
                    <button type="submit">Apply</button>
                </form>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($waste_stats['total_weight'], 1); ?> kg</div>
                <div class="stat-label">Total Weight</div>
                <div class="stat-subtext">Avg: <?php echo number_format($waste_stats['avg_weight_per_collection'], 1); ?> kg/collection</div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?php echo $waste_stats['total_collections']; ?></div>
                <div class="stat-label">Collections Completed</div>
                <div class="stat-subtext">Successfully completed</div>
            </div>

            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    $days_in_period = max(1, (strtotime($end_date) - strtotime($start_date)) / 86400 + 1);
                    $total_collections = $waste_stats['total_collections'] ?? 0;
                    $avg_per_day = $total_collections > 0 ? $total_collections / $days_in_period : 0;
                    echo number_format($avg_per_day, 1);
                    ?>
                </div>
                <div class="stat-label">Avg Collections/Day</div>
                <div class="stat-subtext">Over <?php echo round($days_in_period); ?> days</div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h3>Daily Waste Trend (Last 30 Days)</h3>
                <canvas id="dailyTrendChart"></canvas>
            </div>

            <div class="chart-card">
                <h3>Monthly Comparison (Last 6 Months)</h3>
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>

        <div class="data-tables">
            <div class="table-card">
                <h3>Waste Collection by Area</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Area</th>
                            <th>Collections</th>
                            <th>Bins</th>
                            <th>Weight (kg)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($waste_by_area)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #999;">No data available</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($waste_by_area as $area): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($area['area_name'] ?? 'Unknown'); ?></strong></td>
                                    <td><?php echo $area['collection_count']; ?></td>
                                    <td><?php echo $area['bins_collected']; ?></td>
                                    <td><?php echo number_format($area['total_weight'], 1); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-card">
                <h3>Top Performing Bins</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Bin Code</th>
                            <th>Location</th>
                            <th>Services</th>
                            <th>Weight (kg)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bin_performance)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #999;">No data available</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bin_performance as $bin): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($bin['bin_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($bin['location_details']); ?></td>
                                    <td><?php echo $bin['service_count']; ?></td>
                                    <td><?php echo number_format($bin['total_weight'], 1); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const dailyCtx = document.getElementById('dailyTrendChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($d) {
                    return date('M j', strtotime($d['collection_date']));
                }, $daily_trend)); ?>,
                datasets: [{
                    label: 'Weight (kg)',
                    data: <?php echo json_encode(array_column($daily_trend, 'weight')); ?>,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(function($m) {
                    return date('M Y', strtotime($m['month'] . '-01'));
                }, $monthly_comparison)); ?>,
                datasets: [{
                    label: 'Weight (kg)',
                    data: <?php echo json_encode(array_column($monthly_comparison, 'weight')); ?>,
                    backgroundColor: '#435334',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>