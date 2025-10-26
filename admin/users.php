<?php
/**
 * User Account Management
 * View and manage all admins and employees
 */

session_start();
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$admin_name = $_SESSION['full_name'] ?? 'Admin';

// Get active tab (default: employees)
$active_tab = $_GET['tab'] ?? 'employees';

// Get search query
$search = $_GET['search'] ?? '';

// Get success/error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Fetch admins
$admin_sql = "SELECT admin_id, username, email, full_name, phone_number, status, created_at, last_login 
              FROM admins 
              WHERE 1=1";
if ($search) {
    $admin_sql .= " AND (full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $search_param = "%$search%";
    $admins = getAll($admin_sql, [$search_param, $search_param, $search_param]);
} else {
    $admins = getAll($admin_sql);
}

// Fetch employees
$employee_sql = "SELECT e.employee_id, e.username, e.email, e.full_name, e.phone_number, 
                        e.status, e.created_at, e.last_login, a.area_name
                 FROM employees e
                 LEFT JOIN areas a ON e.area_id = a.area_id
                 WHERE 1=1";
if ($search) {
    $employee_sql .= " AND (e.full_name LIKE ? OR e.email LIKE ? OR e.username LIKE ?)";
    $employees = getAll($employee_sql, [$search_param, $search_param, $search_param]);
} else {
    $employees = getAll($employee_sql);
}

// Get counts
$total_admins = count($admins);
$total_employees = count($employees);
$active_admins = count(array_filter($admins, fn($a) => $a['status'] === 'active'));
$active_employees = count(array_filter($employees, fn($e) => $e['status'] === 'active'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Account Management - EcoBin</title>
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

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #435334;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
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
        }

        .sidebar-logo img {
            width: 90px;
            height: 90px;
            object-fit: contain;
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
        }

        /* Main Content */
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

        .btn-primary {
            background: #435334;
            color: white;
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
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #354428;
            transform: translateY(-2px);
        }

        /* Alert Messages */
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

        /* Stats Cards */
        .stats-row {
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
        }

        .stat-card h3 {
            font-size: 14px;
            color: #999;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #435334;
        }

        /* Search Bar */
        .search-bar {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .search-bar form {
            display: flex;
            gap: 10px;
        }

        .search-bar input {
            flex: 1;
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }

        .search-bar input:focus {
            outline: none;
            border-color: #CEDEBD;
        }

        .search-bar button {
            padding: 12px 24px;
            background: #CEDEBD;
            border: none;
            border-radius: 10px;
            color: #435334;
            font-weight: 600;
            cursor: pointer;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .tab {
            padding: 12px 24px;
            background: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .tab:hover {
            background: #f5f5f5;
        }

        .tab.active {
            background: #435334;
            color: white;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            color: #435334;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #e0e0e0;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        tr:hover {
            background: #fafafa;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-edit, .btn-delete {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: #CEDEBD;
            color: #435334;
        }

        .btn-edit:hover {
            background: #b8ceaa;
        }

        .btn-delete {
            background: #f8d7da;
            color: #721c24;
        }

        .btn-delete:hover {
            background: #f5c6cb;
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

        /* Responsive */
        @media (max-width: 968px) {
            .sidebar {
                width: 70px;
            }
            .main-content {
                margin-left: 70px;
            }
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <?php if (file_exists('../assets/images/logo.png')): ?>
                <img src="../assets/images/logo.png" alt="EcoBin">
            <?php else: ?>
                <span style="font-size: 40px;">🗑️</span>
            <?php endif; ?>
        </div>

        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item">
                <span class="icon">📊</span>
                <span>Dashboard</span>
            </a>
            <a href="users.php" class="nav-item active">
                <span class="icon">👥</span>
                <span>User Management</span>
            </a>
            <a href="bins.php" class="nav-item">
                <span class="icon">🗑️</span>
                <span>Bin Monitoring</span>
            </a>
            <a href="attendance.php" class="nav-item">
                <span class="icon">✅</span>
                <span>Attendance</span>
            </a>
            <a href="tasks.php" class="nav-item">
                <span class="icon">📋</span>
                <span>Tasks</span>
            </a>
            <a href="performance.php" class="nav-item">
                <span class="icon">📈</span>
                <span>Employee Performance</span>
            </a>
            <a href="analytics.php" class="nav-item">
                <span class="icon">📊</span>
                <span>Waste Analytics</span>
            </a>
            <a href="inventory.php" class="nav-item">
                <span class="icon">📦</span>
                <span>Inventory</span>
            </a>
            <a href="leave.php" class="nav-item">
                <span class="icon">📅</span>
                <span>Leave Management</span>
            </a>
            <a href="maintenance.php" class="nav-item">
                <span class="icon">🔧</span>
                <span>Maintenance & Issues</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>👥 User Account Management</h1>
            <a href="user_add.php" class="btn-primary">
                <span>➕</span> Add New User
            </a>
        </div>

        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <span>✓</span>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <span>⚠</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Admins</h3>
                <div class="value"><?php echo $total_admins; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Admins</h3>
                <div class="value"><?php echo $active_admins; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Employees</h3>
                <div class="value"><?php echo $total_employees; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Employees</h3>
                <div class="value"><?php echo $active_employees; ?></div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="search-bar">
            <form method="GET" action="">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($active_tab); ?>">
                <input 
                    type="text" 
                    name="search" 
                    placeholder="🔍 Search by name, email, or username..." 
                    value="<?php echo htmlspecialchars($search); ?>"
                >
                <button type="submit">Search</button>
                <?php if ($search): ?>
                    <a href="?tab=<?php echo $active_tab; ?>" style="padding: 12px 24px; background: #f0f0f0; border-radius: 10px; text-decoration: none; color: #666;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <a href="?tab=employees&search=<?php echo urlencode($search); ?>" 
               class="tab <?php echo $active_tab === 'employees' ? 'active' : ''; ?>">
                Employees (<?php echo $total_employees; ?>)
            </a>
            <a href="?tab=admins&search=<?php echo urlencode($search); ?>" 
               class="tab <?php echo $active_tab === 'admins' ? 'active' : ''; ?>">
                Admins (<?php echo $total_admins; ?>)
            </a>
        </div>

        <!-- Users Table -->
        <div class="table-container">
            <?php if ($active_tab === 'employees'): ?>
                <!-- Employees Table -->
                <?php if (empty($employees)): ?>
                    <div class="empty-state">
                        <div class="icon">👥</div>
                        <h3>No employees found</h3>
                        <p>Start by adding your first employee</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Area</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($employee['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($employee['username']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['phone_number']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['area_name'] ?? 'Not Assigned'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $employee['status']; ?>">
                                            <?php echo ucfirst($employee['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($employee['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="user_edit.php?type=employee&id=<?php echo $employee['employee_id']; ?>" class="btn-edit">
                                                ✏️ Edit
                                            </a>
                                            <a href="user_delete.php?type=employee&id=<?php echo $employee['employee_id']; ?>" 
                                               class="btn-delete"
                                               onclick="return confirm('Are you sure you want to delete this employee?')">
                                                🗑️ Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

            <?php else: ?>
                <!-- Admins Table -->
                <?php if (empty($admins)): ?>
                    <div class="empty-state">
                        <div class="icon">👤</div>
                        <h3>No admins found</h3>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($admin['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['phone_number']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $admin['status']; ?>">
                                            <?php echo ucfirst($admin['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        echo $admin['last_login'] 
                                            ? date('M j, Y g:i A', strtotime($admin['last_login'])) 
                                            : 'Never';
                                        ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="user_edit.php?type=admin&id=<?php echo $admin['admin_id']; ?>" class="btn-edit">
                                                ✏️ Edit
                                            </a>
                                            <?php if ($admin['admin_id'] != $_SESSION['user_id']): ?>
                                                <a href="user_delete.php?type=admin&id=<?php echo $admin['admin_id']; ?>" 
                                                   class="btn-delete"
                                                   onclick="return confirm('Are you sure you want to delete this admin?')">
                                                    🗑️ Delete
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>