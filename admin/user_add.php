<?php

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_page = 'users';

$user_type = $_GET['type'] ?? 'employee';

$areas = getAll("SELECT area_id, area_name, block FROM areas ORDER BY area_name");

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New User - EcoBin</title>
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

        .back-btn {
            padding: 10px 20px;
            background: white;
            color: #435334;
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            background: #f5f5f5;
        }

        .type-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            background: white;
            padding: 10px;
            border-radius: 15px;
            width: fit-content;
        }

        .type-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            background: transparent;
            color: #666;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .type-btn.active {
            background: #435334;
            color: white;
        }

        .form-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 800px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            font-size: 18px;
            color: #435334;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #435334;
        }

        .form-group label .required {
            color: #e74c3c;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #CEDEBD;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #999;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        .btn {
            padding: 12px 32px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #435334;
            color: white;
        }

        .btn-primary:hover {
            background: #354428;
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #666;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Add New User</h1>
            <a href="users.php" class="back-btn">
                <span>←</span> Back to Users
            </a>
        </div>

        <div class="type-selector">
            <a href="?type=employee" class="type-btn <?php echo $user_type === 'employee' ? 'active' : ''; ?>">
                Employee
            </a>
            <a href="?type=admin" class="type-btn <?php echo $user_type === 'admin' ? 'active' : ''; ?>">
                Admin
            </a>
        </div>

        <div class="form-container">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span>⚠</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form action="user_actions.php" method="POST">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="user_type" value="<?php echo htmlspecialchars($user_type); ?>">

                <div class="form-section">
                    <h3>Account Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>
                                Username <span class="required">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="username" 
                                required 
                                placeholder="Enter username"
                                pattern="[a-zA-Z0-9_]{3,20}"
                            >
                        </div>

                        <div class="form-group">
                            <label>
                                Password <span class="required">*</span>
                            </label>
                            <input 
                                type="password" 
                                name="password" 
                                required 
                                placeholder="Enter password"
                                minlength="6"
                            >
                        </div>

                        <div class="form-group">
                            <label>
                                Email <span class="required">*</span>
                            </label>
                            <input 
                                type="email" 
                                name="email" 
                                required 
                                placeholder="user@example.com"
                            >
                        </div>

                        <div class="form-group">
                            <label>
                                Status <span class="required">*</span>
                            </label>
                            <select name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Personal Information</h3>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>
                                Full Name <span class="required">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="full_name" 
                                required 
                                placeholder="Enter full name"
                            >
                        </div>

                        <div class="form-group">
                            <label>
                                Phone Number <span class="required">*</span>
                            </label>
                            <input 
                                type="tel" 
                                name="phone_number" 
                                required 
                                placeholder="0123456789"
                                pattern="[0-9]{10,15}"
                            >
                        </div>

                        <?php if ($user_type === 'employee'): ?>
                            <div class="form-group">
                                <label>
                                    Assigned Area
                                </label>
                                <select name="area_id">
                                    <option value="">-- Select Area --</option>
                                    <?php foreach ($areas as $area): ?>
                                        <option value="<?php echo $area['area_id']; ?>">
                                            <?php echo htmlspecialchars($area['area_name']) . ' (' . htmlspecialchars($area['block']) . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        ✓ Create <?php echo ucfirst($user_type); ?>
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>