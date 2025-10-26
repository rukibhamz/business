<?php
/**
 * Business Management System - Admin Login
 * Phase 1: Core Foundation
 */

// Define system constant
define('BMS_SYSTEM', true);

// Start session
session_start();

// Include required files
require_once '../config/config.php';
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if already logged in
if (isLoggedIn()) {
    redirect(BMS_ADMIN_URL . '/');
}

// Handle login form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required';
    } else {
        $result = $auth->login($username, $password, $rememberMe);
        
        if ($result['success']) {
            redirect(BMS_ADMIN_URL . '/');
        } else {
            $error = $result['message'];
        }
    }
}

// Get messages from URL parameters
if (isset($_GET['timeout'])) {
    $error = 'Your session has expired. Please log in again.';
} elseif (isset($_GET['security'])) {
    $error = 'Security check failed. Please log in again.';
} elseif (isset($_GET['logout'])) {
    $success = 'You have been logged out successfully.';
} elseif (isset($_GET['password_changed'])) {
    $success = 'Your password has been changed successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo COMPANY_NAME; ?></title>
    <link rel="stylesheet" href="../public/css/admin.css">
    <link rel="icon" type="image/x-icon" href="../public/images/logo.png">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="logo">
                    <h1><?php echo COMPANY_NAME; ?></h1>
                    <p>Business Management System</p>
                </div>
            </div>

            <div class="login-content">
                <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="icon-warning"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="icon-check"></i>
                    <?php echo $success; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="" class="login-form">
                    <div class="form-group">
                        <label for="username">Username or Email</label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                               placeholder="Enter your username or email" 
                               required 
                               autofocus>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-input">
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Enter your password" 
                                   required>
                            <button type="button" class="toggle-password" onclick="togglePassword('password')">
                                <i class="icon-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember_me" value="1">
                            <span class="checkmark"></span>
                            Remember me for 30 days
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="icon-login"></i>
                        Sign In
                    </button>
                </form>
            </div>

            <div class="login-footer">
                <p>&copy; <?php echo date('Y'); ?> <?php echo COMPANY_NAME; ?>. All rights reserved.</p>
                <p>Version <?php echo BMS_VERSION; ?> - <?php echo BMS_PHASE; ?></p>
            </div>
        </div>

        <div class="login-info">
            <div class="info-content">
                <h2>Welcome to Business Management System</h2>
                <p>Your comprehensive business management solution with modules for accounting, events, properties, inventory, and more.</p>
                
                <div class="features">
                    <div class="feature">
                        <i class="icon-dashboard"></i>
                        <h3>Dashboard</h3>
                        <p>Real-time overview of your business metrics</p>
                    </div>
                    <div class="feature">
                        <i class="icon-users"></i>
                        <h3>User Management</h3>
                        <p>Manage users, roles, and permissions</p>
                    </div>
                    <div class="feature">
                        <i class="icon-settings"></i>
                        <h3>Settings</h3>
                        <p>Configure system settings and preferences</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'icon-eye-off';
            } else {
                field.type = 'password';
                icon.className = 'icon-eye';
            }
        }

        // Auto-focus username field
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            if (usernameField && !usernameField.value) {
                usernameField.focus();
            }
        });

        // Form validation
        document.querySelector('.login-form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please enter both username and password.');
                return false;
            }
        });
    </script>
</body>
</html>
