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
        $sqlFile = __DIR__ . '/database.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception('Database SQL file not found: ' . $sqlFile);
        }
        $sql = file_get_contents($sqlFile);
        
        // Replace table prefix if different
        if ($prefix !== 'bms_') {
            $sql = str_replace('bms_', $prefix, $sql);
        }
        
        // Split SQL into individual statements - improved parsing
        $statements = [];
        $currentStatement = '';
        $lines = explode("\n", $sql);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if (empty($line) || strpos($line, '--') === 0 || strpos($line, '/*') === 0) {
                continue;
            }
            
            $currentStatement .= $line . "\n";
            
            // Check if statement ends with semicolon
            if (substr(rtrim($line), -1) === ';') {
                $statements[] = trim($currentStatement);
                $currentStatement = '';
            }
        }
        
        // Add any remaining statement
        if (!empty(trim($currentStatement))) {
            $statements[] = trim($currentStatement);
        }
        
        $executedStatements = 0;
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                    $executedStatements++;
                    error_log("Executed SQL: " . substr($statement, 0, 100) . "...");
                } catch (PDOException $e) {
                    error_log("Failed to execute SQL statement: " . $statement);
                    error_log("Error: " . $e->getMessage());
                    throw $e;
                }
            }
        }
        
        return [
            'success' => true,
            'message' => "Database schema installed successfully! ({$executedStatements} statements executed)"
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Database installation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Verify database tables were created successfully
 */
function verifyDatabaseTables($pdo, $prefix = 'bms_') {
    try {
        $requiredTables = [
            $prefix . 'users',
            $prefix . 'roles', 
            $prefix . 'permissions',
            $prefix . 'role_permissions',
            $prefix . 'settings',
            $prefix . 'activity_logs'
        ];
        
        $existingTables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $existingTables[] = $row[0];
        }
        
        error_log("Required tables: " . implode(', ', $requiredTables));
        error_log("Existing tables: " . implode(', ', $existingTables));
        
        $missingTables = array_diff($requiredTables, $existingTables);
        
        if (!empty($missingTables)) {
            return [
                'success' => false,
                'message' => 'Missing tables: ' . implode(', ', $missingTables),
                'missing_tables' => $missingTables
            ];
        }
        
        // Check if roles table has data
        $stmt = $pdo->query("SELECT COUNT(*) FROM {$prefix}roles");
        $roleCount = $stmt->fetchColumn();
        
        if ($roleCount == 0) {
            return [
                'success' => false,
                'message' => 'Roles table is empty - default data not inserted'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'All required tables created successfully',
            'tables_created' => count($existingTables),
            'roles_count' => $roleCount
        ];
        
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Database verification failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Create configuration file
 */
function createConfigFile($config) {
    // Auto-detect the base URL and paths
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = dirname(dirname($scriptName));
    $baseUrl = $protocol . '://' . $host . $basePath;
    
    $configContent = "<?php\n";
    $configContent .= "// Business Management System Configuration\n";
    $configContent .= "// Generated during installation on " . date('Y-m-d H:i:s') . "\n\n";
    
    $configContent .= "// Path Configuration\n";
    $configContent .= "define('BASE_PATH', dirname(__DIR__));\n";
    $configContent .= "define('BASE_URL', '{$baseUrl}');\n";
    $configContent .= "define('ADMIN_PATH', BASE_PATH . '/admin');\n";
    $configContent .= "define('ADMIN_URL', BASE_URL . '/admin');\n";
    $configContent .= "define('PUBLIC_PATH', BASE_PATH . '/public');\n";
    $configContent .= "define('PUBLIC_URL', BASE_URL . '/public');\n";
    $configContent .= "define('UPLOADS_PATH', BASE_PATH . '/uploads');\n";
    $configContent .= "define('UPLOADS_URL', BASE_URL . '/uploads');\n";
    $configContent .= "define('CACHE_PATH', BASE_PATH . '/cache');\n";
    $configContent .= "define('LOGS_PATH', BASE_PATH . '/logs');\n\n";
    
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
    $configContent .= "define('VERSION', '4.0.0');\n";
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
    
    return file_put_contents('../config/config.php', $configContent);
}

/**
 * Create admin user
 */
function createAdminUser($pdo, $adminData, $prefix = 'bms_') {
    try {
        // Hash password
        $hashedPassword = password_hash($adminData['password'], PASSWORD_DEFAULT);
        
        // Insert admin user
        $stmt = $pdo->prepare("
            INSERT INTO {$prefix}users 
            (username, email, password, first_name, last_name, phone, role_id, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 1, 'active', NOW())
        ");
        
        $stmt->execute([
            $adminData['username'],
            $adminData['email'],
            $hashedPassword,
            $adminData['first_name'],
            $adminData['last_name'],
            $adminData['phone'] ?? null
        ]);
        
        return [
            'success' => true,
            'message' => 'Admin user created successfully!',
            'user_id' => $pdo->lastInsertId()
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Failed to create admin user: ' . $e->getMessage()
        ];
    }
}

/**
 * Create installation lock file
 */
function createInstallationLock() {
    $lockContent = "Business Management System Installation Lock\n";
    $lockContent .= "Installed on: " . date('Y-m-d H:i:s') . "\n";
    $lockContent .= "Version: 1.0.0\n";
    $lockContent .= "DO NOT DELETE THIS FILE\n";
    
    return file_put_contents('../installed.lock', $lockContent);
}

/**
 * Log installation activity
 */
function logInstallationActivity($action, $message, $data = []) {
    $logFile = '../logs/installation.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$action}: {$message}";
    
    if (!empty($data)) {
        $logEntry .= " | Data: " . json_encode($data);
    }
    
    $logEntry .= "\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Get timezone list
 */
function getTimezoneList() {
    return [
        'Africa/Lagos' => 'Lagos (GMT+1)',
        'Africa/Cairo' => 'Cairo (GMT+2)',
        'Africa/Johannesburg' => 'Johannesburg (GMT+2)',
        'Europe/London' => 'London (GMT+0)',
        'Europe/Paris' => 'Paris (GMT+1)',
        'America/New_York' => 'New York (GMT-5)',
        'America/Los_Angeles' => 'Los Angeles (GMT-8)',
        'Asia/Tokyo' => 'Tokyo (GMT+9)',
        'Asia/Shanghai' => 'Shanghai (GMT+8)',
        'UTC' => 'UTC (GMT+0)'
    ];
}

/**
 * Get currency list
 */
function getCurrencyList() {
    return [
        'NGN' => 'Nigerian Naira (₦)',
        'USD' => 'US Dollar ($)',
        'EUR' => 'Euro (€)',
        'GBP' => 'British Pound (£)',
        'CAD' => 'Canadian Dollar (C$)',
        'AUD' => 'Australian Dollar (A$)',
        'JPY' => 'Japanese Yen (¥)',
        'CHF' => 'Swiss Franc (CHF)',
        'CNY' => 'Chinese Yuan (¥)',
        'INR' => 'Indian Rupee (₹)'
    ];
}