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

// Set current page for sidebar
$current_page = 'users';

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
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            font-size: 12px;
            color: #999;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: 700;
            color: #435334;
        }

        .stat-card .icon {
            font-size: 32px;
            margin-bottom: 10px;
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
            transition: all 0.3s;
        }

        .search-bar button:hover {
            background: #b8ceaa;
        }

        .btn-clear {
            padding: 12px 24px;
            background: #f0f0f0;
            border-radius: 10px;
            text-decoration: none;
            color: #666;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-clear:hover {
            background: #e0e0e0;
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
            padding: 25px;
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
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
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
            display: inline-flex;
            align-items: center;
            gap: 5px;
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

        .empty-state h3 {
            color: #435334;
            margin-bottom: 10px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-row {
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
                gap: 15px;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .search-bar form {
                flex-direction: column;
            }

            .tabs {
                flex-direction: column;
            }

            .table-container {
                overflow-x: auto;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>User Account Management</h1>
            <a href="user_add.php" class="btn-primary">
                <span>+</span> Add New User
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
                    placeholder="Search by name, email, or username..." 
                    value="<?php echo htmlspecialchars($search); ?>"
                >
                <button type="submit">Search</button>
                <?php if ($search): ?>
                    <a href="?tab=<?php echo $active_tab; ?>" class="btn-clear">Clear</a>
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
                                                Edit
                                            </a>
                                            <a href="user_delete.php?type=employee&id=<?php echo $employee['employee_id']; ?>" 
                                               class="btn-delete"
                                               onclick="return confirm('Are you sure you want to delete this employee?')">
                                                Delete
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
                        <div class="icon"></div>
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
                                                Edit
                                            </a>
                                            <?php if ($admin['admin_id'] != $_SESSION['user_id']): ?>
                                                <a href="user_delete.php?type=admin&id=<?php echo $admin['admin_id']; ?>" 
                                                   class="btn-delete"
                                                   onclick="return confirm('Are you sure you want to delete this admin?')">
                                                    Delete
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