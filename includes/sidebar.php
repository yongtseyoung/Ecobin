<?php
/**
 * Employee Sidebar Navigation - EcoBin Theme
 * Include this file in all employee pages
 * 
 * Required variables before including:
 * - $_SESSION['user_id'] - Employee ID
 * - $_SESSION['full_name'] - Employee full name
 * - $current_page (optional) - Current page identifier for active state
 */

// Get employee details if not already loaded
if (!isset($employee)) {
    $employee = getOne("SELECT e.*, a.area_name 
                        FROM employees e 
                        LEFT JOIN areas a ON e.area_id = a.area_id 
                        WHERE e.employee_id = ?", 
                        [$_SESSION['user_id']]);
}

$employee_name = $_SESSION['full_name'] ?? 'Employee';
$current_page = $current_page ?? ''; // Set this variable in each page before including sidebar
?>

<style>
    /* Sidebar Styles */
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
        z-index: 1000;
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

    .user-profile {
        text-align: center;
        padding: 0 20px;
        margin-bottom: 30px;
    }

    .user-name {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 5px;
        color: white;
    }

    .user-role {
        font-size: 12px;
        opacity: 0.8;
        color: white;
    }

    .user-area {
        font-size: 11px;
        opacity: 0.7;
        color: #CEDEBD;
        margin-top: 3px;
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
        width: calc(100% - 30px);
    }

    .logout-btn:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .logout-btn .icon {
        margin-right: 12px;
        font-size: 18px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .sidebar {
            width: 70px;
        }

        .sidebar-logo {
            width: 50px;
            height: 50px;
        }

        .sidebar-logo img {
            width: 40px;
            height: 40px;
        }

        .user-profile {
            display: none;
        }

        .nav-item span:not(.icon) {
            display: none;
        }

        .logout-btn span:not(.icon) {
            display: none;
        }

        .logout-btn {
            justify-content: center;
        }
    }
</style>

<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="../assets/images/logo.png" alt="EcoBin" onerror="this.style.display='none'">
    </div>

    <div class="user-profile">
        <div class="user-name"><?php echo htmlspecialchars($employee_name); ?></div>
        <div class="user-role">Employee</div>
        <?php if (isset($employee['area_name']) && $employee['area_name']): ?>
            <div class="user-area">📍 <?php echo htmlspecialchars($employee['area_name']); ?></div>
        <?php endif; ?>
    </div>

    <nav class="nav-menu">
        <a href="employee_dashboard.php" class="nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
            <span class="icon">🏠</span>
            <span>Dashboard</span>
        </a>
        <a href="my_tasks.php" class="nav-item <?php echo $current_page === 'tasks' ? 'active' : ''; ?>">
            <span class="icon">📋</span>
            <span>My Tasks</span>
        </a>
        <a href="my_attendance.php" class="nav-item <?php echo $current_page === 'attendance' ? 'active' : ''; ?>">
            <span class="icon">📅</span>
            <span>Attendance</span>
        </a>
        <a href="my_performance.php" class="nav-item <?php echo $current_page === 'performance' ? 'active' : ''; ?>">
            <span class="icon">📈</span>
            <span>My Performance</span>
        </a>
        <a href="my_schedule.php" class="nav-item <?php echo $current_page === 'inventory' ? 'active' : ''; ?>">
            <span class="icon">📦</span>
            <span>Inventory</span>
        </a>
        <a href="my_leave.php" class="nav-item <?php echo $current_page === 'leave' ? 'active' : ''; ?>">
            <span class="icon">🏖️</span>
            <span>Apply Leave</span>
        </a>
        <a href="my_profile.php" class="nav-item <?php echo $current_page === 'profile' ? 'active' : ''; ?>">
            <span class="icon">👤</span>
            <span>Profile</span>
        </a>
    </nav>

    <form method="POST" action="../auth/logout.php" style="margin: 0;">
        <button type="submit" class="logout-btn">
            <span class="icon">🚪</span>
            <span>Logout</span>
        </button>
    </form>
</aside>