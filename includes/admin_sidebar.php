<?php
/**
 * Admin Sidebar Navigation - EcoBin Theme
 * Include this file in all admin pages
 * 
 * Required variables before including:
 * - $_SESSION['user_id'] - Admin ID
 * - $_SESSION['full_name'] - Admin full name
 * - $current_page (optional) - Current page identifier for active state
 */

// Get admin name
$admin_name = $_SESSION['full_name'] ?? 'Admin';
$current_page = $current_page ?? ''; // Set this variable in each page before including sidebar
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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

    .admin-profile {
        text-align: center;
        padding: 0 20px;
        margin-bottom: 30px;
    }

    .admin-name {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 5px;
        color: white;
    }

    .admin-role {
        font-size: 12px;
        opacity: 0.8;
        color: white;
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

        .admin-profile {
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

    <div class="admin-profile">
        <div class="admin-name"><?php echo htmlspecialchars($admin_name); ?></div>
        <div class="admin-role">Administrator</div>
    </div>

<nav class="nav-menu">
    <a href="dashboard.php" class="nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
        <span class="icon"><i class="fa-solid fa-house"></i></span>
        <span>Dashboard</span>
    </a>

    <a href="users.php" class="nav-item <?php echo $current_page === 'users' ? 'active' : ''; ?>">
        <span class="icon"><i class="fa-solid fa-users"></i></span>
        <span>User Management</span>
    </a>

    <a href="bins.php" class="nav-item <?php echo $current_page === 'bins' ? 'active' : ''; ?>">
        <span class="icon"><i class="fa-solid fa-trash"></i></span>
        <span>Bin Monitoring</span>
    </a>

    <a href="tasks.php" class="nav-item <?php echo $current_page === 'tasks' ? 'active' : ''; ?>">
        <span class="icon"><i class="fa-solid fa-list-check"></i></span>
        <span>Task Management</span>
    </a>

    <a href="attendance.php" class="nav-item <?php echo $current_page === 'attendance' ? 'active' : ''; ?>">
        <span class="icon"><i class="fa-solid fa-user-check"></i></span>
        <span>Attendance</span>
    </a>

    <a href="leave.php" class="nav-item <?php echo $current_page === 'leave' ? 'active' : ''; ?>">
        <span class="icon"><i class="fa-solid fa-calendar-days"></i></span>
        <span>Leave Management</span>
    </a>

    <a href="performance.php" class="nav-item <?php echo $current_page === 'employees' ? 'active' : ''; ?>">
        <span class="icon"><i class="fa-solid fa-chart-line"></i></span>
        <span>Employees Performance</span>
    </a>

    <a href="analytics.php" class="nav-item <?php echo $current_page === 'reports' ? 'active' : ''; ?>">
        <span class="icon"><i class="fa-solid fa-chart-pie"></i></span>
        <span>Waste Analytics</span>
    </a>

    <a href="inventory.php" class="nav-item <?php echo $current_page === 'inventory' ? 'active' : ''; ?>">
        <span class="icon"><i class="fa-solid fa-boxes-stacked"></i></span>
        <span>Inventory</span>
    </a>

    <a href="supply_requests.php" class="nav-item <?php echo $current_page === 'Supply Requests' ? 'active' : ''; ?>">
        <span class="icon"><i class="fa-solid fa-clipboard-list"></i></span>
        <span>Supply Requests</span>
    </a>        

    <a href="maintenance_reports.php" class="nav-item <?php echo $current_page === 'maintenance' ? 'active' : ''; ?>">
        <span class="icon"><i class="fa-solid fa-screwdriver-wrench"></i></span>
        <span>Maintenance</span>
    </a>
</nav>


    <form method="POST" action="../auth/logout.php" style="margin: 0;">
        <button type="submit" class="logout-btn">
            <span class="icon"><i class="fa-solid fa-right-from-bracket"></i></span>
            <span>Logout</span>
        </button>
    </form>
</aside>