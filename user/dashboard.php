<?php
/**
 * EcoBin Employee Dashboard
 * 
 * Main dashboard for cleaners/employees
 * Shows their assigned areas and tasks
 */

// Include database and check authentication
require_once '../config/database.php';
requireEmployee(); // Only employees can access this page

// Get employee info
$employee_id = getUserId();
$employee_name = $_SESSION['full_name'];

// Get employee's primary areas
try {
    $primary_areas = getAll("
        SELECT 
            a.area_id,
            a.area_name,
            a.total_floors,
            a.total_bins
        FROM employee_areas ea
        JOIN areas a ON ea.area_id = a.area_id
        WHERE ea.employee_id = ? 
        AND ea.is_primary = TRUE 
        AND ea.status = 'active'
        ORDER BY a.area_name
    ", [$employee_id]);
    
    // Get today's tasks for this employee
    $today_tasks = getAll("
        SELECT 
            t.task_id,
            t.task_title,
            t.priority,
            t.status,
            t.scheduled_date,
            t.scheduled_time,
            a.area_name,
            t.description
        FROM tasks t
        JOIN areas a ON t.area_id = a.area_id
        WHERE t.assigned_to = ?
        AND t.status IN ('pending', 'in_progress')
        AND t.scheduled_date = CURDATE()
        ORDER BY 
            FIELD(t.priority, 'urgent', 'high', 'medium', 'low'),
            t.scheduled_time
    ", [$employee_id]);
    
    // Get upcoming tasks (next 7 days)
    $upcoming_tasks = getAll("
        SELECT 
            t.task_id,
            t.task_title,
            t.priority,
            t.status,
            t.scheduled_date,
            a.area_name
        FROM tasks t
        JOIN areas a ON t.area_id = a.area_id
        WHERE t.assigned_to = ?
        AND t.status = 'pending'
        AND t.scheduled_date > CURDATE()
        AND t.scheduled_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY t.scheduled_date
        LIMIT 5
    ", [$employee_id]);
    
    // Get statistics
    $completed_today = getOne("
        SELECT COUNT(*) as count 
        FROM tasks 
        WHERE assigned_to = ? 
        AND status = 'completed'
        AND DATE(completed_at) = CURDATE()
    ", [$employee_id])['count'];
    
    $pending_total = getOne("
        SELECT COUNT(*) as count 
        FROM tasks 
        WHERE assigned_to = ? 
        AND status = 'pending'
    ", [$employee_id])['count'];
    
} catch (PDOException $e) {
    $error = "Error loading dashboard data";
    error_log("Employee Dashboard Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - EcoBin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dashboard">
    <!-- Navigation Bar -->
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">
            <span class="logo">🗑️</span>
            <span>EcoBin Cleaner</span>
        </a>
        <div class="navbar-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="#">My Tasks</a>
            <a href="#">Attendance</a>
            <a href="#">Reports</a>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($employee_name, 0, 1)); ?></div>
                <span><?php echo escape($employee_name); ?></span>
                <a href="../auth/logout.php" class="btn btn-secondary" style="margin-left: 10px;">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <h1>Welcome back, <?php echo escape($employee_name); ?>! 👋</h1>
            <p>Here are your tasks and areas for today</p>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <!-- Primary Areas -->
            <div class="stat-card">
                <div class="icon">📍</div>
                <div class="value"><?php echo count($primary_areas); ?></div>
                <div class="label">Your Primary Areas</div>
            </div>

            <!-- Today's Tasks -->
            <div class="stat-card">
                <div class="icon">📋</div>
                <div class="value"><?php echo count($today_tasks); ?></div>
                <div class="label">Today's Tasks</div>
            </div>

            <!-- Completed Today -->
            <div class="stat-card">
                <div class="icon">✅</div>
                <div class="value"><?php echo $completed_today; ?></div>
                <div class="label">Completed Today</div>
            </div>

            <!-- Pending Total -->
            <div class="stat-card">
                <div class="icon">⏳</div>
                <div class="value"><?php echo $pending_total; ?></div>
                <div class="label">Pending Tasks</div>
            </div>
        </div>

        <!-- Primary Areas -->
        <div class="card mb-20">
            <div class="card-header">
                <h2>📍 Your Primary Areas</h2>
            </div>
            <div class="card-body">
                <?php if (empty($primary_areas)): ?>
                    <p style="text-align: center; color: #7f8c8d; padding: 20px;">
                        No areas assigned yet. Contact your admin.
                    </p>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <?php foreach ($primary_areas as $area): ?>
                            <div style="
                                padding: 20px; 
                                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                border-radius: 12px;
                                color: white;
                                text-align: center;
                            ">
                                <div style="font-size: 36px; margin-bottom: 10px;">🏢</div>
                                <div style="font-size: 24px; font-weight: 600; margin-bottom: 5px;">
                                    Block <?php echo escape($area['area_name']); ?>
                                </div>
                                <div style="font-size: 14px; opacity: 0.9;">
                                    <?php echo $area['total_floors']; ?> floors • <?php echo $area['total_bins']; ?> bins
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Today's Tasks -->
        <div class="card mb-20">
            <div class="card-header">
                <h2>📋 Today's Tasks</h2>
            </div>
            <div class="card-body">
                <?php if (empty($today_tasks)): ?>
                    <div style="text-align: center; padding: 40px;">
                        <div style="font-size: 48px; margin-bottom: 15px;">🎉</div>
                        <h3 style="color: #2ecc71; margin-bottom: 10px;">No tasks for today!</h3>
                        <p style="color: #7f8c8d;">Enjoy your day or check upcoming tasks.</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Area</th>
                                <th>Time</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($today_tasks as $task): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo escape($task['task_title']); ?></strong>
                                        <?php if ($task['description']): ?>
                                            <br><small style="color: #7f8c8d;"><?php echo escape($task['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo escape($task['area_name']); ?></td>
                                    <td><?php echo $task['scheduled_time'] ? date('g:i A', strtotime($task['scheduled_time'])) : '-'; ?></td>
                                    <td>
                                        <?php
                                        $priority_class = [
                                            'low' => 'badge-info',
                                            'medium' => 'badge-warning',
                                            'high' => 'badge-danger',
                                            'urgent' => 'badge-danger'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $priority_class[$task['priority']]; ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = [
                                            'pending' => 'badge-warning',
                                            'in_progress' => 'badge-info',
                                            'completed' => 'badge-success'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $status_class[$task['status']]; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Tasks -->
        <div class="card">
            <div class="card-header">
                <h2>📅 Upcoming Tasks (Next 7 Days)</h2>
            </div>
            <div class="card-body">
                <?php if (empty($upcoming_tasks)): ?>
                    <p style="text-align: center; color: #7f8c8d; padding: 20px;">
                        No upcoming tasks scheduled
                    </p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Area</th>
                                <th>Scheduled Date</th>
                                <th>Priority</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_tasks as $task): ?>
                                <tr>
                                    <td><?php echo escape($task['task_title']); ?></td>
                                    <td><?php echo escape($task['area_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($task['scheduled_date'])); ?></td>
                                    <td>
                                        <?php
                                        $priority_class = [
                                            'low' => 'badge-info',
                                            'medium' => 'badge-warning',
                                            'high' => 'badge-danger',
                                            'urgent' => 'badge-danger'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $priority_class[$task['priority']]; ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>
