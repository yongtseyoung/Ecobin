<?php
/**
 * EcoBin Login Page
 * Beautiful minimal design with custom branding
 */

// Start session
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/employee_dashboard.php");
    }
    exit;
}

// Check for error or success messages
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';

// Clear messages after displaying
unset($_SESSION['error']);
unset($_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EcoBin Smart Waste Management</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #FAF1E4; /* Cream background */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        /* Logo Circle */
        .logo-circle {
            width: 310px;
            height: 310px;
            background: #c6e4b9; /* Light green circle */
            border-radius: 50%;
            margin: 0 auto 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.8s ease-out;
        }

        .logo-circle img {
            max-width: 220px;
            max-height: 220px;
            object-fit: contain;
        }

        /* If no logo image, show emoji */
        .logo-circle .emoji-logo {
            font-size: 80px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Login Form Card */
        .login-card {
            background: white;
            border-radius: 30px;
            padding: 50px 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Alert Messages */
        .alert {
            padding: 12px 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            font-size: 14px;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .alert-error {
            background: #ffe5e5;
            color: #c53030;
            border: 1px solid #ffc9c9;
        }

        .alert-success {
            background: #e5ffe5;
            color: #2d6a2d;
            border: 1px solid #b3ffb3;
        }

        /* Form Inputs */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group input {
            width: 100%;
            padding: 18px 25px;
            border: none;
            background: #f5f5f5;
            border-radius: 25px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s ease;
            color: #666;
        }

        .form-group input::placeholder {
            color: #aaa;
        }

        .form-group input:focus {
            outline: none;
            background: #efefef;
            box-shadow: 0 0 0 3px rgba(206, 222, 189, 0.3);
        }

        /* Sign In Button */
        .btn-signin {
            width: 100%;
            max-width: 200px;
            padding: 15px 40px;
            background: #CEDEBD; /* Light green button */
            border: none;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 600;
            color: #5a7655;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: 0 5px 15px rgba(206, 222, 189, 0.4);
        }

        .btn-signin:hover {
            background: #b8ceaa;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(206, 222, 189, 0.5);
        }

        .btn-signin:active {
            transform: translateY(0);
        }

        /* Brand Text */
        .brand-text {
            margin-top: 30px;
            color: #999;
            font-size: 13px;
        }

        /* Responsive */
        @media (max-width: 500px) {
            .login-card {
                padding: 40px 30px;
            }

            .logo-circle {
                width: 160px;
                height: 160px;
            }

            .logo-circle img {
                max-width: 110px;
                max-height: 110px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Logo Circle -->
        <div class="logo-circle">
            <?php if (file_exists('assets/images/logo.png')): ?>
                <img src="assets/images/logo.png" alt="EcoBin Logo">
            <?php else: ?>
                <!-- Fallback emoji if no logo image -->
                <div class="emoji-logo">🗑️</div>
            <?php endif; ?>
        </div>

        <!-- Login Card -->
        <div class="login-card">
            <!-- Error/Success Messages -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    ✓ <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="auth/login_process.php" method="POST" id="loginForm">
                <!-- Username -->
                <div class="form-group">
                    <input 
                        type="text" 
                        name="username" 
                        placeholder="Username"
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        required
                        autofocus
                    >
                </div>

                <!-- Password -->
                <div class="form-group">
                    <input 
                        type="password" 
                        name="password" 
                        placeholder="Password"
                        required
                    >
                </div>

                <!-- Sign In Button -->
                <button type="submit" class="btn-signin">
                    Sign in
                </button>
            </form>

            <!-- Brand Text -->
            <div class="brand-text">
                EcoBin - Smart Waste Management
            </div>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });

        // Form loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Signing in...';
        });
    </script>
</body>
</html>