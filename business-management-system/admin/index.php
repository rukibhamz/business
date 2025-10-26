<?php
/**
 * Business Management System - Admin Panel
 * Main dashboard
 */

// Include configuration
require_once '../config/config.php';

// Check if system is installed
if (!defined('INSTALLED') || !INSTALLED) {
    header('Location: ../install/');
    exit;
}

// Start session
session_start();

// Simple authentication check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Get basic statistics
try {
    $userCount = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "users")->fetchColumn();
    $roleCount = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "roles")->fetchColumn();
    $activityCount = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
} catch (PDOException $e) {
    $userCount = $roleCount = $activityCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo COMPANY_NAME; ?> Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 20px;
            background: #fafafa;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #2c3e50;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        .stat-card .number {
            font-size: 2em;
            font-weight: bold;
            color: #27ae60;
        }
        .welcome {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .logout {
            float: right;
            background: #e74c3c;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        .logout:hover {
            background: #c0392b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo COMPANY_NAME; ?> Admin Panel</h1>
            <a href="logout.php" class="logout">Logout</a>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="number"><?php echo $userCount; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Roles</h3>
                <div class="number"><?php echo $roleCount; ?></div>
            </div>
            <div class="stat-card">
                <h3>Today's Activity</h3>
                <div class="number"><?php echo $activityCount; ?></div>
            </div>
        </div>
        
        <div class="welcome">
            <h2>Welcome to the Admin Panel!</h2>
            <p>Your Business Management System has been successfully installed and is ready to use.</p>
            <p><strong>System Version:</strong> <?php echo VERSION; ?></p>
            <p><strong>Installation Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
            
            <h3>Quick Actions:</h3>
            <ul>
                <li><a href="users/">Manage Users</a></li>
                <li><a href="roles/">Manage Roles</a></li>
                <li><a href="settings/">System Settings</a></li>
                <li><a href="activity/">Activity Logs</a></li>
            </ul>
        </div>
    </div>
</body>
</html>