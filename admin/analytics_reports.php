<?php
/**
 * Waste Analytics Reports
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
$current_page = 'reports';

$admin_name = $_SESSION['full_name'] ?? 'Admin';

// Get report type
$report_type = $_GET['type'] ?? 'summary';

// Get date range
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Get success/error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Function to generate report data based on type
function generateReportData($type, $start_date, $end_date) {
    $data = [];
    
    switch($type) {
        case 'summary':
            $data = [
                'title' => 'Waste Collection Summary Report',
                'stats' => getOne("
                    SELECT 
                        COUNT(DISTINCT t.task_id) as total_collections,
                        COUNT(DISTINCT t.triggered_by_bin) as total_bins,
                        COALESCE(SUM(cr.total_weight), 0) as total_weight,
                        COALESCE(AVG(cr.total_weight), 0) as avg_weight
                    FROM tasks t
                    LEFT JOIN collection_reports cr ON cr.task_id = t.task_id
                    WHERE t.status = 'completed'
                    AND t.task_type = 'collection'
                    AND DATE(t.completed_at) BETWEEN ? AND ?
                ", [$start_date, $end_date]),
                'by_area' => getAll("
                    SELECT 
                        a.area_name,
                        COUNT(t.task_id) as collections,
                        COUNT(DISTINCT t.triggered_by_bin) as bins,
                        COALESCE(SUM(cr.total_weight), 0) as weight
                    FROM tasks t
                    LEFT JOIN areas a ON t.area_id = a.area_id
                    LEFT JOIN collection_reports cr ON cr.task_id = t.task_id
                    WHERE t.status = 'completed'
                    AND t.task_type = 'collection'
                    AND DATE(t.completed_at) BETWEEN ? AND ?
                    GROUP BY a.area_id
                    ORDER BY weight DESC
                ", [$start_date, $end_date])
            ];
            break;
            
        case 'performance':
            $data = [
                'title' => 'Employee Performance Report',
                'employees' => getAll("
                    SELECT 
                        e.full_name,
                        e.email,
                        a.area_name,
                        COUNT(t.task_id) as tasks_completed,
                        COUNT(DISTINCT t.triggered_by_bin) as total_bins,
                        COALESCE(SUM(cr.total_weight), 0) as total_weight,
                        COALESCE(AVG(TIMESTAMPDIFF(HOUR, t.scheduled_date, t.completed_at)), 0) as avg_completion_time
                    FROM employees e
                    LEFT JOIN areas a ON e.area_id = a.area_id
                    LEFT JOIN tasks t ON e.employee_id = t.assigned_to 
                        AND t.status = 'completed'
                        AND t.task_type = 'collection'
                        AND DATE(t.completed_at) BETWEEN ? AND ?
                    LEFT JOIN collection_reports cr ON cr.task_id = t.task_id
                    WHERE e.status = 'active'
                    GROUP BY e.employee_id
                    ORDER BY tasks_completed DESC
                ", [$start_date, $end_date])
            ];
            break;
            
        case 'bins':
            $data = [
                'title' => 'Bin Performance Report',
                'bins' => getAll("
                    SELECT 
                        b.bin_id,
                        b.bin_code,
                        b.location_details,
                        b.bin_capacity,
                        b.current_fill_level,
                        b.battery_level,
                        a.area_name,
                        COUNT(t.task_id) as service_count,
                        COALESCE(SUM(cr.total_weight), 0) as total_weight,
                        MAX(t.completed_at) as last_serviced
                    FROM bins b
                    LEFT JOIN areas a ON b.area_id = a.area_id
                    LEFT JOIN tasks t ON b.bin_id = t.triggered_by_bin 
                        AND t.status = 'completed'
                        AND t.task_type = 'collection'
                        AND DATE(t.completed_at) BETWEEN ? AND ?
                    LEFT JOIN collection_reports cr ON cr.task_id = t.task_id
                    GROUP BY b.bin_id
                    ORDER BY service_count DESC
                ", [$start_date, $end_date]),
                'maintenance' => getAll("
                    SELECT 
                        b.bin_id,
                        b.bin_code,
                        b.location_details,
                        b.status,
                        COUNT(m.maintenance_id) as maintenance_count,
                        MAX(m.reported_at) as last_maintenance
                    FROM bins b
                    LEFT JOIN maintenance_reports m ON b.bin_id = m.bin_id
                        AND DATE(m.reported_at) BETWEEN ? AND ?
                    WHERE m.maintenance_id IS NOT NULL
                    GROUP BY b.bin_id
                    ORDER BY maintenance_count DESC
                ", [$start_date, $end_date])
            ];
            break;
            
        case 'detailed':
            $data = [
                'title' => 'Detailed Collection Report',
                'collections' => getAll("
                    SELECT 
                        t.task_id,
                        t.task_title,
                        t.scheduled_date,
                        t.completed_at,
                        cr.total_weight,
                        e.full_name as employee_name,
                        a.area_name,
                        b.bin_code,
                        b.location_details as bin_location
                    FROM tasks t
                    LEFT JOIN employees e ON t.assigned_to = e.employee_id
                    LEFT JOIN areas a ON t.area_id = a.area_id
                    LEFT JOIN bins b ON t.triggered_by_bin = b.bin_id
                    LEFT JOIN collection_reports cr ON cr.task_id = t.task_id
                    WHERE t.status = 'completed'
                    AND t.task_type = 'collection'
                    AND DATE(t.completed_at) BETWEEN ? AND ?
                    ORDER BY t.completed_at DESC
                ", [$start_date, $end_date])
            ];
            break;
    }
    
    return $data;
}

// Generate report if requested
$report_data = null;
if (isset($_GET['generate'])) {
    $report_data = generateReportData($report_type, $start_date, $end_date);
}

// Export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $report_data = generateReportData($report_type, $start_date, $end_date);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write CSV based on report type
    switch($report_type) {
        case 'summary':
            fputcsv($output, ['Waste Collection Summary Report']);
            fputcsv($output, ['Period', $start_date . ' to ' . $end_date]);
            fputcsv($output, []);
            fputcsv($output, ['Total Collections', $report_data['stats']['total_collections']]);
            fputcsv($output, ['Total Bins', $report_data['stats']['total_bins']]);
            fputcsv($output, ['Total Weight (kg)', number_format($report_data['stats']['total_weight'], 2)]);
            fputcsv($output, ['Average Weight (kg)', number_format($report_data['stats']['avg_weight'], 2)]);
            fputcsv($output, []);
            fputcsv($output, ['Area', 'Collections', 'Bins', 'Weight (kg)']);
            foreach ($report_data['by_area'] as $area) {
                fputcsv($output, [
                    $area['area_name'],
                    $area['collections'],
                    $area['bins'],
                    number_format($area['weight'], 2)
                ]);
            }
            break;
            
        case 'performance':
            fputcsv($output, ['Employee Performance Report']);
            fputcsv($output, ['Period', $start_date . ' to ' . $end_date]);
            fputcsv($output, []);
            fputcsv($output, ['Employee', 'Email', 'Area', 'Tasks', 'Bins', 'Weight (kg)', 'Avg Time (hrs)']);
            foreach ($report_data['employees'] as $emp) {
                fputcsv($output, [
                    $emp['full_name'],
                    $emp['email'],
                    $emp['area_name'] ?? 'N/A',
                    $emp['tasks_completed'],
                    $emp['total_bins'],
                    number_format($emp['total_weight'], 2),
                    number_format($emp['avg_completion_time'], 2)
                ]);
            }
            break;
            
        case 'bins':
            fputcsv($output, ['Bin Performance Report']);
            fputcsv($output, ['Period', $start_date . ' to ' . $end_date]);
            fputcsv($output, []);
            fputcsv($output, ['Bin Code', 'Location', 'Area', 'Services', 'Weight (kg)', 'Last Serviced']);
            foreach ($report_data['bins'] as $bin) {
                fputcsv($output, [
                    $bin['bin_code'],
                    $bin['location_details'],
                    $bin['area_name'] ?? 'N/A',
                    $bin['service_count'],
                    number_format($bin['total_weight'], 2),
                    $bin['last_serviced'] ? date('Y-m-d H:i', strtotime($bin['last_serviced'])) : 'Never'
                ]);
            }
            break;

        case 'detailed':
            fputcsv($output, ['Detailed Collection Report']);
            fputcsv($output, ['Period', $start_date . ' to ' . $end_date]);
            fputcsv($output, []);
            fputcsv($output, ['Task ID', 'Title', 'Employee', 'Area', 'Bin Code', 'Location', 'Scheduled', 'Completed', 'Weight (kg)']);
            foreach ($report_data['collections'] as $task) {
                fputcsv($output, [
                    $task['task_id'],
                    $task['task_title'],
                    $task['employee_name'] ?? 'N/A',
                    $task['area_name'] ?? 'N/A',
                    $task['bin_code'] ?? 'N/A',
                    $task['bin_location'] ?? 'N/A',
                    date('Y-m-d', strtotime($task['scheduled_date'])),
                    date('Y-m-d H:i', strtotime($task['completed_at'])),
                    number_format($task['total_weight'] ?? 0, 2)
                ]);
            }
            break;
    }
    
    fclose($output);
    exit;
}

// Get current date info
$current_time = date('g:i A');
$current_date = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Reports - EcoBin</title>
    
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

        .header-actions {
            display: flex;
            gap: 10px;
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
            transition: all 0.3s;
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

        /* Report Generator Card */
        .generator-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .generator-card h2 {
            color: #435334;
            margin-bottom: 20px;
            font-size: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group select,
        .form-group input {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #CEDEBD;
        }

        /* Report Display */
        .report-display {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .report-header {
            border-bottom: 3px solid #435334;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .report-header h2 {
            color: #435334;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .report-meta {
            color: #666;
            font-size: 14px;
        }

        .report-section {
            margin-bottom: 40px;
        }

        .report-section h3 {
            color: #435334;
            margin-bottom: 20px;
            font-size: 20px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #CEDEBD;
        }

        .stat-box .label {
            font-size: 12px;
            color: #999;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .stat-box .value {
            font-size: 28px;
            font-weight: 700;
            color: #435334;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .data-table th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            color: #435334;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #e0e0e0;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .data-table tr:hover {
            background: #fafafa;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #435334;
            margin-bottom: 10px;
        }

        .impact-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .impact-box {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
        }

        .impact-box .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .impact-box .value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .impact-box .label {
            font-size: 14px;
            opacity: 0.9;
        }

        /* Print Styles */
        @media print {
            .sidebar,
            .page-header,
            .generator-card,
            .header-actions,
            .btn {
                display: none !important;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .report-display {
                box-shadow: none;
            }
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .impact-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>Analytics Reports</h1>

            </div>
            <div style="display: flex; align-items: center; gap: 20px;">
                <div class="header-info">
                    <div class="header-time"><?php echo $current_time; ?></div>
                    <div class="header-date"><?php echo $current_date; ?></div>
                </div>
                <a href="analytics.php" class="btn-secondary">
                    <i class="fa-solid fa-arrow-left" style="color:#435334;"></i> Back to Analytics
                </a>
            </div>
        </div>

        <!-- Report Generator -->
        <div class="generator-card">
            <h2>Generate Report</h2>
            <form method="GET" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Report Type</label>
                        <select name="type" required>
                            <option value="summary" <?php echo $report_type === 'summary' ? 'selected' : ''; ?>>Summary Report</option>
                            <option value="detailed" <?php echo $report_type === 'detailed' ? 'selected' : ''; ?>>Detailed Collections</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $start_date; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?php echo $end_date; ?>" required>
                    </div>
                </div>

                <div class="header-actions">
                    <button type="submit" name="generate" class="btn btn-primary">
                        Generate Report
                    </button>
                    <?php if ($report_data): ?>
                        <a href="?type=<?php echo $report_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&export=csv" class="btn btn-secondary">
                            Export CSV
                        </a>
                        <button type="button" onclick="window.print()" class="btn btn-secondary">
                            Print
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Report Display -->
        <?php if ($report_data): ?>
            <div class="report-display">
                <!-- Report Header -->
                <div class="report-header">
                    <h2><?php echo $report_data['title']; ?></h2>
                    <div class="report-meta">
                        <strong>Period:</strong> <?php echo date('F j, Y', strtotime($start_date)); ?> - <?php echo date('F j, Y', strtotime($end_date)); ?>
                        <br>
                        <strong>Generated:</strong> <?php echo date('F j, Y g:i A'); ?>
                        <br>
                        <strong>Generated by:</strong> <?php echo htmlspecialchars($admin_name); ?>
                    </div>
                </div>

                <!-- Report Content Based on Type -->
                <?php if ($report_type === 'summary'): ?>
                    <!-- Summary Report -->
                    <div class="report-section">
                        <h3>Overview Statistics</h3>
                        <div class="stats-row">
                            <div class="stat-box">
                                <div class="label">Total Collections</div>
                                <div class="value"><?php echo $report_data['stats']['total_collections']; ?></div>
                            </div>
                            <div class="stat-box">
                                <div class="label">Bins Collected</div>
                                <div class="value"><?php echo number_format($report_data['stats']['total_bins']); ?></div>
                            </div>
                            <div class="stat-box">
                                <div class="label">Total Weight (kg)</div>
                                <div class="value"><?php echo number_format($report_data['stats']['total_weight'], 1); ?></div>
                            </div>
                            <div class="stat-box">
                                <div class="label">Avg Weight (kg)</div>
                                <div class="value"><?php echo number_format($report_data['stats']['avg_weight'], 1); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="report-section">
                        <h3>Collection by Area</h3>
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
                                <?php foreach ($report_data['by_area'] as $area): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($area['area_name'] ?? 'Unknown'); ?></strong></td>
                                        <td><?php echo $area['collections']; ?></td>
                                        <td><?php echo $area['bins']; ?></td>
                                        <td><?php echo number_format($area['weight'], 1); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($report_type === 'performance'): ?>
                    <!-- Performance Report -->
                    <div class="report-section">
                        <h3>Employee Performance</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Area</th>
                                    <th>Tasks</th>
                                    <th>Bins</th>
                                    <th>Weight (kg)</th>
                                    <th>Avg Time (hrs)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['employees'] as $emp): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($emp['full_name']); ?></strong><br>
                                            <small style="color: #999;"><?php echo htmlspecialchars($emp['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($emp['area_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo $emp['tasks_completed']; ?></td>
                                        <td><?php echo $emp['total_bins']; ?></td>
                                        <td><?php echo number_format($emp['total_weight'], 1); ?></td>
                                        <td><?php echo number_format($emp['avg_completion_time'], 1); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($report_type === 'bins'): ?>
                    <!-- Bin Performance Report -->
                    <div class="report-section">
                        <h3>Bin Performance</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Bin Code</th>
                                    <th>Location</th>
                                    <th>Area</th>
                                    <th>Services</th>
                                    <th>Weight (kg)</th>
                                    <th>Last Serviced</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['bins'] as $bin): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($bin['bin_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($bin['location_details']); ?></td>
                                        <td><?php echo htmlspecialchars($bin['area_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo $bin['service_count']; ?></td>
                                        <td><?php echo number_format($bin['total_weight'], 1); ?></td>
                                        <td><?php echo $bin['last_serviced'] ? date('M j, Y g:i A', strtotime($bin['last_serviced'])) : 'Never'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($report_data['maintenance'])): ?>
                        <div class="report-section">
                            <h3>Maintenance Issues</h3>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Bin Code</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Issues</th>
                                        <th>Last Maintenance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['maintenance'] as $maint): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($maint['bin_code']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($maint['location_details']); ?></td>
                                            <td><?php echo ucfirst($maint['status']); ?></td>
                                            <td><?php echo $maint['maintenance_count']; ?></td>
                                            <td><?php echo date('M j, Y', strtotime($maint['last_maintenance'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                <?php elseif ($report_type === 'detailed'): ?>
                    <!-- Detailed Collections Report -->
                    <div class="report-section">
                        <h3>Detailed Collection Records</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Task ID</th>
                                    <th>Title</th>
                                    <th>Employee</th>
                                    <th>Area</th>
                                    <th>Bin Code</th>
                                    <th>Scheduled</th>
                                    <th>Completed</th>
                                    <th>Weight (kg)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['collections'] as $task): ?>
                                    <tr>
                                        <td><?php echo $task['task_id']; ?></td>
                                        <td><?php echo htmlspecialchars($task['task_title']); ?></td>
                                        <td><?php echo htmlspecialchars($task['employee_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($task['area_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($task['bin_code'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($task['scheduled_date'])); ?></td>
                                        <td><?php echo date('M j, g:i A', strtotime($task['completed_at'])); ?></td>
                                        <td><?php echo number_format($task['total_weight'] ?? 0, 1); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <h3>No Report Generated</h3>
                <p>Select report parameters above and click "Generate Report"</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>