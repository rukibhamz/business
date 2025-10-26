<?php
/**
 * Business Management System - Database Setup Script
 * This script creates the basic database tables needed for the system
 */

// Database configuration
$host = 'localhost';
$dbname = 'business_management';
$username = 'root';
$password = '';

echo "Starting database setup...\n";
echo "=====================================\n";

try {
    // Create database connection
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Connected to MySQL server\n";
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database '$dbname' created/verified\n";
    
    // Connect to the specific database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Connected to '$dbname' database\n";
    
    // Create tables
    $tables = [
        // Users table
        "CREATE TABLE IF NOT EXISTS bms_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            role_id INT DEFAULT 2,
            status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Roles table
        "CREATE TABLE IF NOT EXISTS bms_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) UNIQUE NOT NULL,
            description TEXT,
            permissions TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Settings table
        "CREATE TABLE IF NOT EXISTS bms_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Activity logs table
        "CREATE TABLE IF NOT EXISTS bms_activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    // Execute table creation queries
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
    echo "✓ All tables created successfully\n";
    
    // Insert default roles
    $roles = [
        ['Super Admin', 'Full system access', '["all"]'],
        ['Admin', 'Administrative access', '["users.view", "users.create", "users.update", "settings.view", "settings.update"]'],
        ['Manager', 'Management access', '["users.view", "settings.view"]'],
        ['Staff', 'Staff access', '["users.view"]'],
        ['Customer', 'Customer access', '[]']
    ];
    
    foreach ($roles as $role) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bms_roles WHERE name = ?");
        $stmt->execute([$role[0]]);
        $roleExists = $stmt->fetchColumn();
        
        if (!$roleExists) {
            $stmt = $pdo->prepare("INSERT INTO bms_roles (name, description, permissions) VALUES (?, ?, ?)");
            $stmt->execute($role);
            echo "✓ Role '{$role[0]}' created\n";
        }
    }
    
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bms_users WHERE username = 'admin'");
    $stmt->execute();
    $adminExists = $stmt->fetchColumn();
    
    if (!$adminExists) {
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO bms_users (username, email, password, first_name, last_name, role_id, status) 
            VALUES ('admin', 'admin@localhost', ?, 'Admin', 'User', 1, 'active')
        ");
        $stmt->execute([$adminPassword]);
        echo "✓ Admin user created\n";
    } else {
        echo "✓ Admin user already exists\n";
    }
    
    // Insert default settings
    $settings = [
        ['site_name', 'Business Management System', 'string', 'Site name'],
        ['site_url', 'http://localhost/business-management-system', 'string', 'Site URL'],
        ['admin_email', 'admin@localhost', 'string', 'Admin email'],
        ['timezone', 'Africa/Lagos', 'string', 'Default timezone'],
        ['currency', 'NGN', 'string', 'Default currency'],
        ['date_format', 'Y-m-d', 'string', 'Date format'],
        ['items_per_page', '25', 'number', 'Items per page'],
        ['maintenance_mode', 'false', 'boolean', 'Maintenance mode']
    ];
    
    foreach ($settings as $setting) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bms_settings WHERE setting_key = ?");
        $stmt->execute([$setting[0]]);
        $settingExists = $stmt->fetchColumn();
        
        if (!$settingExists) {
            $stmt = $pdo->prepare("INSERT INTO bms_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
            $stmt->execute($setting);
            echo "✓ Setting '{$setting[0]}' created\n";
        }
    }
    
    echo "\n=====================================\n";
    echo "🎉 DATABASE SETUP COMPLETED SUCCESSFULLY!\n";
    echo "=====================================\n";
    echo "\n📋 LOGIN CREDENTIALS:\n";
    echo "   Username: admin\n";
    echo "   Password: admin123\n";
    echo "\n🌐 ACCESS URLS:\n";
    echo "   Admin Panel: http://localhost/business-management-system/admin/\n";
    echo "   Login Page:  http://localhost/business-management-system/admin/login.php\n";
    echo "\n⚠️  IMPORTANT: Change the default password after first login!\n";
    echo "\n";
    
} catch (PDOException $e) {
    echo "\n❌ DATABASE ERROR:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\n🔧 TROUBLESHOOTING:\n";
    echo "1. Make sure MySQL server is running\n";
    echo "2. Check your database credentials in config/config.php\n";
    echo "3. Ensure the MySQL user has CREATE DATABASE privileges\n";
    echo "4. Verify the database name 'business_management' is available\n";
    echo "\n";
    exit(1);
}