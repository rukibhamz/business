<?php
/**
 * Business Management System - Installation Helper Functions
 * Phase 1: Core Foundation
 */

// Prevent direct access
if (!defined('BMS_INSTALL')) {
    die('Direct access not allowed');
}

/**
 * Check if system is already installed
 */
function isInstalled() {
    return file_exists('../installed.lock');
}

/**
 * Get current installation step
 */
function getCurrentStep() {
    return isset($_GET['step']) ? (int)$_GET['step'] : 1;
}

/**
 * Get total number of installation steps
 */
function getTotalSteps() {
    return 5;
}

/**
 * Check if step is valid
 */
function isValidStep($step) {
    return $step >= 1 && $step <= getTotalSteps();
}

/**
 * Get step title
 */
function getStepTitle($step) {
    $titles = [
        1 => 'System Requirements',
        2 => 'Database Configuration',
        3 => 'Site Configuration',
        4 => 'Admin Account Setup',
        5 => 'Installation Complete'
    ];
    
    return isset($titles[$step]) ? $titles[$step] : 'Unknown Step';
}

/**
 * Get step description
 */
function getStepDescription($step) {
    $descriptions = [
        1 => 'Checking system requirements and permissions',
        2 => 'Configure database connection settings',
        3 => 'Set up your business information',
        4 => 'Create your administrator account',
        5 => 'Finalizing installation and setup'
    ];
    
    return isset($descriptions[$step]) ? $descriptions[$step] : '';
}

/**
 * Check PHP requirements
 */
function checkPHPRequirements() {
    $requirements = [
        'php_version' => [
            'name' => 'PHP Version',
            'required' => '7.4.0',
            'current' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'message' => 'PHP 7.4 or higher is required'
        ],
        'mysqli' => [
            'name' => 'MySQLi Extension',
            'required' => 'Enabled',
            'current' => extension_loaded('mysqli') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('mysqli'),
            'message' => 'MySQLi extension is required for database operations'
        ],
        'mbstring' => [
            'name' => 'MBString Extension',
            'required' => 'Enabled',
            'current' => extension_loaded('mbstring') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('mbstring'),
            'message' => 'MBString extension is required for string operations'
        ],
        'curl' => [
            'name' => 'cURL Extension',
            'required' => 'Enabled',
            'current' => extension_loaded('curl') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('curl'),
            'message' => 'cURL extension is required for HTTP requests'
        ],
        'gd' => [
            'name' => 'GD Extension',
            'required' => 'Enabled',
            'current' => extension_loaded('gd') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('gd'),
            'message' => 'GD extension is required for image processing'
        ],
        'json' => [
            'name' => 'JSON Extension',
            'required' => 'Enabled',
            'current' => extension_loaded('json') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('json'),
            'message' => 'JSON extension is required for data processing'
        ],
        'openssl' => [
            'name' => 'OpenSSL Extension',
            'required' => 'Enabled',
            'current' => extension_loaded('openssl') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('openssl'),
            'message' => 'OpenSSL extension is required for encryption'
        ],
        'zip' => [
            'name' => 'ZIP Extension',
            'required' => 'Enabled',
            'current' => extension_loaded('zip') ? 'Enabled' : 'Disabled',
            'status' => extension_loaded('zip'),
            'message' => 'ZIP extension is required for file operations'
        ]
    ];
    
    return $requirements;
}

/**
 * Check directory permissions
 */
function checkDirectoryPermissions() {
    $directories = [
        '../config' => [
            'name' => 'Config Directory',
            'path' => '../config',
            'required' => 'Writable (755)',
            'status' => is_writable('../config'),
            'message' => 'Config directory must be writable for configuration files'
        ],
        '../uploads' => [
            'name' => 'Uploads Directory',
            'path' => '../uploads',
            'required' => 'Writable (755)',
            'status' => is_writable('../uploads'),
            'message' => 'Uploads directory must be writable for file uploads'
        ],
        '../cache' => [
            'name' => 'Cache Directory',
            'path' => '../cache',
            'required' => 'Writable (755)',
            'status' => is_writable('../cache'),
            'message' => 'Cache directory must be writable for caching'
        ],
        '../logs' => [
            'name' => 'Logs Directory',
            'path' => '../logs',
            'required' => 'Writable (755)',
            'status' => is_writable('../logs'),
            'message' => 'Logs directory must be writable for logging'
        ]
    ];
    
    return $directories;
}

/**
 * Check server requirements
 */
function checkServerRequirements() {
    $requirements = [
        'mod_rewrite' => [
            'name' => 'Apache mod_rewrite',
            'required' => 'Enabled',
            'current' => function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules()) ? 'Enabled' : 'Unknown',
            'status' => function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules()) : true,
            'message' => 'Apache mod_rewrite is required for clean URLs'
        ],
        'mysql_available' => [
            'name' => 'MySQL/MariaDB',
            'required' => 'Available',
            'current' => function_exists('mysqli_connect') ? 'Available' : 'Not Available',
            'status' => function_exists('mysqli_connect'),
            'message' => 'MySQL or MariaDB database server is required'
        ]
    ];
    
    return $requirements;
}

/**
 * Test database connection
 */
function testDatabaseConnection($host, $port, $username, $password, $database) {
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        return [
            'success' => true,
            'message' => 'Database connection successful!',
            'pdo' => $pdo
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database connection failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Install database schema
 */
function installDatabaseSchema($pdo, $prefix = 'bms_') {
    try {
        // Read and execute SQL file
        $sql = file_get_contents('database.sql');
        
        // Replace table prefix if different
        if ($prefix !== 'bms_') {
            $sql = str_replace('bms_', $prefix, $sql);
        }
        
        // Split SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^(--|\/\*)/', $statement)) {
                $pdo->exec($statement);
            }
        }
        
        return [
            'success' => true,
            'message' => 'Database schema installed successfully!'
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database installation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Create configuration file
 */
function createConfigFile($config) {
    $configContent = "<?php\n";
    $configContent .= "// Business Management System Configuration\n";
    $configContent .= "// Generated during installation on " . date('Y-m-d H:i:s') . "\n\n";
    
    $configContent .= "// Database Configuration\n";
    $configContent .= "define('DB_HOST', '{$config['db_host']}');\n";
    $configContent .= "define('DB_PORT', '{$config['db_port']}');\n";
    $configContent .= "define('DB_NAME', '{$config['db_name']}');\n";
    $configContent .= "define('DB_USER', '{$config['db_user']}');\n";
    $configContent .= "define('DB_PASS', '{$config['db_pass']}');\n";
    $configContent .= "define('DB_PREFIX', '{$config['db_prefix']}');\n\n";
    
    $configContent .= "// Site Configuration\n";
    $configContent .= "define('SITE_URL', '{$config['site_url']}');\n";
    $configContent .= "define('COMPANY_NAME', '{$config['company_name']}');\n";
    $configContent .= "define('ADMIN_EMAIL', '{$config['admin_email']}');\n";
    $configContent .= "define('TIMEZONE', '{$config['timezone']}');\n";
    $configContent .= "define('CURRENCY', '{$config['currency']}');\n";
    $configContent .= "define('DATE_FORMAT', '{$config['date_format']}');\n\n";
    
    $configContent .= "// System Configuration\n";
    $configContent .= "define('INSTALLED', true);\n";
    $configContent .= "define('VERSION', '1.0.0');\n";
    $configContent .= "define('ENVIRONMENT', 'production');\n\n";
    
    $configContent .= "// Security Configuration\n";
    $configContent .= "define('ENCRYPTION_KEY', '" . bin2hex(random_bytes(32)) . "');\n";
    $configContent .= "define('SESSION_LIFETIME', 3600);\n";
    $configContent .= "define('MAX_LOGIN_ATTEMPTS', 5);\n\n";
    
    $configContent .= "// Timezone\n";
    $configContent .= "date_default_timezone_set(TIMEZONE);\n\n";
    
    $configContent .= "// Error Reporting (disable in production)\n";
    $configContent .= "if (ENVIRONMENT === 'development') {\n";
    $configContent .= "    error_reporting(E_ALL);\n";
    $configContent .= "    ini_set('display_errors', 1);\n";
    $configContent .= "} else {\n";
    $configContent .= "    error_reporting(0);\n";
    $configContent .= "    ini_set('display_errors', 0);\n";
    $configContent .= "}\n";
    
    return file_put_contents('../config/config.php', $configContent) !== false;
}

/**
 * Create admin user
 */
function createAdminUser($pdo, $userData, $prefix = 'bms_') {
    try {
        // Hash password
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // Insert admin user
        $stmt = $pdo->prepare("
            INSERT INTO {$prefix}users 
            (first_name, last_name, email, username, password, role_id, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, 1, 1, NOW())
        ");
        
        $stmt->execute([
            $userData['first_name'],
            $userData['last_name'],
            $userData['email'],
            $userData['username'],
            $hashedPassword
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Insert welcome notification
        $stmt = $pdo->prepare("
            INSERT INTO {$prefix}notifications 
            (user_id, title, message, type, created_at) 
            VALUES (?, ?, ?, 'success', NOW())
        ");
        
        $stmt->execute([
            $userId,
            'Welcome to Business Management System!',
            'Your installation has been completed successfully. You can now start using the system.'
        ]);
        
        // Log installation activity
        $stmt = $pdo->prepare("
            INSERT INTO {$prefix}activity_logs 
            (user_id, action, description, ip_address, user_agent, created_at) 
            VALUES (?, 'system.install', ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            'System installation completed successfully',
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
        
        return [
            'success' => true,
            'message' => 'Admin user created successfully!',
            'user_id' => $userId
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Failed to create admin user: ' . $e->getMessage()
        ];
    }
}

/**
 * Update site settings
 */
function updateSiteSettings($pdo, $settings, $prefix = 'bms_') {
    try {
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO {$prefix}settings (setting_key, setting_value, setting_type, category, created_at) 
                VALUES (?, ?, 'text', 'general', NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");
            
            $stmt->execute([$key, $value]);
        }
        
        return [
            'success' => true,
            'message' => 'Site settings updated successfully!'
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Failed to update site settings: ' . $e->getMessage()
        ];
    }
}

/**
 * Create installed lock file
 */
function createInstalledLock() {
    $lockContent = "Business Management System - Installation Lock File\n";
    $lockContent .= "Created: " . date('Y-m-d H:i:s') . "\n";
    $lockContent .= "Version: 1.0.0\n";
    $lockContent .= "DO NOT DELETE THIS FILE\n";
    
    return file_put_contents('../installed.lock', $lockContent) !== false;
}

/**
 * Validate email address
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 */
function validatePasswordStrength($password) {
    $strength = 0;
    $messages = [];
    
    if (strlen($password) >= 8) {
        $strength++;
    } else {
        $messages[] = 'At least 8 characters';
    }
    
    if (preg_match('/[a-z]/', $password)) {
        $strength++;
    } else {
        $messages[] = 'At least one lowercase letter';
    }
    
    if (preg_match('/[A-Z]/', $password)) {
        $strength++;
    } else {
        $messages[] = 'At least one uppercase letter';
    }
    
    if (preg_match('/[0-9]/', $password)) {
        $strength++;
    } else {
        $messages[] = 'At least one number';
    }
    
    if (preg_match('/[^a-zA-Z0-9]/', $password)) {
        $strength++;
    } else {
        $messages[] = 'At least one special character';
    }
    
    $levels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    $level = $levels[min($strength, 5)];
    
    return [
        'strength' => $strength,
        'level' => $level,
        'messages' => $messages,
        'is_valid' => $strength >= 3
    ];
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get available timezones
 */
function getTimezones() {
    $timezones = timezone_identifiers_list();
    $grouped = [];
    
    foreach ($timezones as $timezone) {
        $parts = explode('/', $timezone);
        if (count($parts) > 1) {
            $region = $parts[0];
            $city = implode('/', array_slice($parts, 1));
            $grouped[$region][] = $city;
        }
    }
    
    return $grouped;
}

/**
 * Get available currencies
 */
function getCurrencies() {
    return [
        'USD' => 'US Dollar ($)',
        'EUR' => 'Euro (€)',
        'GBP' => 'British Pound (£)',
        'NGN' => 'Nigerian Naira (₦)',
        'CAD' => 'Canadian Dollar (C$)',
        'AUD' => 'Australian Dollar (A$)',
        'JPY' => 'Japanese Yen (¥)',
        'CHF' => 'Swiss Franc (CHF)',
        'CNY' => 'Chinese Yuan (¥)',
        'INR' => 'Indian Rupee (₹)',
        'BRL' => 'Brazilian Real (R$)',
        'MXN' => 'Mexican Peso ($)',
        'ZAR' => 'South African Rand (R)',
        'AED' => 'UAE Dirham (د.إ)',
        'SAR' => 'Saudi Riyal (ر.س)'
    ];
}

/**
 * Get available date formats
 */
function getDateFormats() {
    return [
        'Y-m-d' => 'YYYY-MM-DD (2024-01-15)',
        'd/m/Y' => 'DD/MM/YYYY (15/01/2024)',
        'm/d/Y' => 'MM/DD/YYYY (01/15/2024)',
        'd-m-Y' => 'DD-MM-YYYY (15-01-2024)',
        'Y/m/d' => 'YYYY/MM/DD (2024/01/15)'
    ];
}

/**
 * Log installation activity
 */
function logInstallationActivity($action, $description, $data = []) {
    $logFile = '../logs/installation.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $logEntry = "[{$timestamp}] [{$ip}] [{$action}] {$description}";
    if (!empty($data)) {
        $logEntry .= " | Data: " . json_encode($data);
    }
    $logEntry .= " | User-Agent: {$userAgent}\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Clean up installation files (optional)
 */
function cleanupInstallationFiles() {
    $filesToRemove = [
        'install-functions.php',
        'database.sql'
    ];
    
    foreach ($filesToRemove as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    // Remove install directory
    if (is_dir('.') && count(scandir('.')) <= 2) {
        rmdir('.');
    }
}
