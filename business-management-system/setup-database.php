<?php
/**
 * Business Management System - Database Setup
 * This script creates the basic database tables needed for the system
 */

// Include configuration
require_once '../config/config.php';

// Create database connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "Database connection successful!\n";
    
    // Create basic tables
    $tables = [
        // Users table
        "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "users (
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
        "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) UNIQUE NOT NULL,
            description TEXT,
            permissions TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Settings table
        "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Activity logs table
        "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "activity_logs (
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
        echo "Table created successfully!\n";
    }
    
    // Insert default admin user if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . DB_PREFIX . "users WHERE username = 'admin'");
    $stmt->execute();
    $adminExists = $stmt->fetchColumn();
    
    if (!$adminExists) {
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO " . DB_PREFIX . "users (username, email, password, first_name, last_name, role_id, status) 
            VALUES ('admin', 'admin@localhost', ?, 'Admin', 'User', 1, 'active')
        ");
        $stmt->execute([$adminPassword]);
        echo "Default admin user created!\n";
        echo "Username: admin\n";
        echo "Password: admin123\n";
    }
    
    // Insert default roles if not exist
    $roles = [
        ['Super Admin', 'Full system access', '["all"]'],
        ['Admin', 'Administrative access', '["users.view", "users.create", "users.update", "settings.view", "settings.update"]'],
        ['Manager', 'Management access', '["users.view", "settings.view"]'],
        ['Staff', 'Staff access', '["users.view"]'],
        ['Customer', 'Customer access', '[]']
    ];
    
    foreach ($roles as $role) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . DB_PREFIX . "roles WHERE name = ?");
        $stmt->execute([$role[0]]);
        $roleExists = $stmt->fetchColumn();
        
        if (!$roleExists) {
            $stmt = $pdo->prepare("
                INSERT INTO " . DB_PREFIX . "roles (name, description, permissions) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute($role);
            echo "Role '{$role[0]}' created!\n";
        }
    }
    
    echo "\nDatabase setup completed successfully!\n";
    echo "You can now access the admin panel at: " . SITE_URL . "/admin/\n";
    echo "Login with username: admin, password: admin123\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    echo "Please check your database configuration in config/config.php\n";
}
