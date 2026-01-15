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
        transition: transform 0.3s ease;
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

    /* Hamburger Menu Button */
    .hamburger-btn {
        display: none;
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1001;
        background: #435334;
        color: white;
        border: none;
        width: 50px;
        height: 50px;
        border-radius: 12px;
        cursor: pointer;
        font-size: 20px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
    }

    .hamburger-btn:hover {
        background: #5a6f4a;
    }

    .hamburger-btn.active {
        left: 270px;
    }

    /* Overlay */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
    }

/* Responsive */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .hamburger-btn {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Keep all text visible when sidebar is open on mobile */
        .sidebar .user-profile,
        .sidebar .user-name,
        .sidebar .user-role,
        .sidebar .user-area,
        .sidebar .nav-item span:not(.icon),
        .sidebar .logout-btn span:not(.icon) {
            display: block !important;
        }

        .sidebar .nav-item {
            display: flex !important;
        }

        .sidebar .logout-btn {
            display: flex !important;
        }
    }
</style>

<!-- Hamburger Button -->
<button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()">
    <i class="fa-solid fa-bars"></i>
</button>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="../assets/images/logo.png" alt="EcoBin" onerror="this.style.display='none'">
    </div>

    <div class="user-profile">
        <div class="user-name"><?php echo htmlspecialchars($employee_name); ?></div>
        <div class="user-role"><?php echo t('waste_collection_employee'); ?></div>
        <?php if (isset($employee['area_name']) && $employee['area_name']): ?>
            <div class="user-area">📍 <?php echo htmlspecialchars($employee['area_name']); ?></div>
        <?php endif; ?>
    </div>

    <nav class="nav-menu">
        <a href="employee_dashboard.php" class="nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
            <span class="icon"><i class="fa-solid fa-house"></i></span>
            <span><?php echo t('dashboard'); ?></span>
        </a>
        <a href="my_tasks.php" class="nav-item <?php echo $current_page === 'tasks' ? 'active' : ''; ?>">
            <span class="icon"><i class="fa-solid fa-list-check"></i></span>
            <span><?php echo t('my_tasks'); ?></span>
        </a>
        <a href="my_attendance.php" class="nav-item <?php echo $current_page === 'attendance' ? 'active' : ''; ?>">
            <span class="icon"><i class="fa-solid fa-user-check"></i></span>
            <span><?php echo t('attendance'); ?></span>
        </a>
        <a href="my_performance.php" class="nav-item <?php echo $current_page === 'performance' ? 'active' : ''; ?>">
            <span class="icon"><i class="fa-solid fa-chart-line"></i></span>
            <span><?php echo t('my_performance'); ?></span>
        </a>
        <a href="inventory.php" class="nav-item <?php echo $current_page === 'inventory' ? 'active' : ''; ?>">
            <span class="icon"><i class="fa-solid fa-boxes-stacked"></i></span>
            <span><?php echo t('inventory'); ?></span>
        </a>
        <a href="my_leave.php" class="nav-item <?php echo $current_page === 'leave' ? 'active' : ''; ?>">
            <span class="icon"><i class="fa-solid fa-calendar-days"></i></span>
            <span><?php echo t('apply_leave'); ?></span>
        </a>
        <a href="my_reports.php" class="nav-item <?php echo $current_page === 'maintenance' ? 'active' : ''; ?>">
            <span class="icon"><i class="fa-solid fa-screwdriver-wrench"></i></span>
            <span><?php echo t('maintenance'); ?></span>
        </a>
        <a href="my_profile.php" class="nav-item <?php echo $current_page === 'profile' ? 'active' : ''; ?>">
            <span class="icon"><i class="fa-solid fa-user"></i></span>
            <span><?php echo t('profile'); ?></span>
        </a>
    </nav>

    <form method="POST" action="../auth/logout.php" style="margin: 0;">
        <button type="submit" class="logout-btn">
            <span class="icon"><i class="fa-solid fa-right-from-bracket"></i></span>
            <span><?php echo t('logout'); ?></span>
        </button>
    </form>
</aside>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
    hamburgerBtn.classList.toggle('active');
}

// Close sidebar when clicking on a nav item (mobile only)
document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
            toggleSidebar();
        }
    });
});
</script>