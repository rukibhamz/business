<?php
/**
 * Business Management System - cPanel Configuration
 * Update these values for your cPanel hosting
 */

// Database Configuration (Update with your cPanel database details)
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'your_database_name');        // Your cPanel database name
define('DB_USER', 'your_db_username');          // Your cPanel database username
define('DB_PASS', 'your_db_password');          // Your cPanel database password
define('DB_PREFIX', 'bms_');

// Site Configuration (Update with your domain)
define('SITE_URL', 'https://yourdomain.com');   // Your actual domain
define('SITE_NAME', 'Business Management System');
define('COMPANY_NAME', 'Your Company Name');
define('ADMIN_EMAIL', 'admin@yourdomain.com');  // Your actual email

// Security Configuration
define('ENCRYPTION_KEY', 'your-32-character-secret-key-here-change-this');
define('SESSION_LIFETIME', 7200);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);

// File Upload Configuration
define('MAX_UPLOAD_SIZE', 10485760); // 10MB
define('UPLOAD_PATH', 'uploads/');
define('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx');

// Cache Configuration
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 3600);
define('CACHE_PATH', 'cache/');

// Logging Configuration
define('LOG_ENABLED', true);
define('LOG_LEVEL', 'info');
define('LOG_PATH', 'logs/');

// Date and Time Configuration
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i:s');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('TIMEZONE', 'Africa/Lagos');

// Currency Configuration
define('CURRENCY', 'NGN');
define('CURRENCY_POSITION', 'before');

// Email Configuration (Update with your SMTP settings)
define('SMTP_HOST', 'mail.yourdomain.com');     // Your cPanel mail server
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@yourdomain.com'); // Your email
define('SMTP_PASSWORD', 'your_email_password');    // Your email password
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_FROM_NAME', COMPANY_NAME);
define('SMTP_FROM_EMAIL', ADMIN_EMAIL);

// System Settings
define('DEBUG_MODE', false);  // Set to false for production
define('MAINTENANCE_MODE', false);
define('VERSION', '1.0.0');
define('BUILD_DATE', '2024-01-01');

// API Configuration
define('API_ENABLED', true);
define('API_RATE_LIMIT', 1000);
define('API_TOKEN_LIFETIME', 86400);

// Pagination
define('DEFAULT_PAGE_SIZE', 25);
define('MAX_PAGE_SIZE', 100);

// Include other configuration files
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/database.php';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error reporting (disabled for production)
error_reporting(0);
ini_set('display_errors', 0);

// Set memory limit
ini_set('memory_limit', '256M');

// Set execution time limit
ini_set('max_execution_time', 300);

// Set upload limits
ini_set('upload_max_filesize', MAX_UPLOAD_SIZE . 'B');
ini_set('post_max_size', MAX_UPLOAD_SIZE . 'B');

// Create necessary directories if they don't exist
$directories = [
    UPLOAD_PATH,
    CACHE_PATH,
    LOG_PATH,
    UPLOAD_PATH . 'properties/',
    UPLOAD_PATH . 'tenants/',
    UPLOAD_PATH . 'documents/',
    UPLOAD_PATH . 'temp/'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Helper functions (same as before)
function getDB() {
    return Database::getInstance();
}

function getMysqliConnection() {
    static $connection = null;
    
    if ($connection === null) {
        $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        
        if ($connection->connect_error) {
            die('Database connection failed: ' . $connection->connect_error);
        }
        
        $connection->set_charset('utf8mb4');
    }
    
    return $connection;
}

function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requirePermission($permission) {
    requireLogin();
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function formatCurrency($amount, $currency = null) {
    $currency = $currency ?: CURRENCY;
    $symbol = ($currency === 'NGN') ? '₦' : (($currency === 'USD') ? '$' : $currency);
    
    return $symbol . number_format($amount, 2);
}

function logActivity($action, $description, $user_id = null) {
    if (!LOG_ENABLED) return;
    
    $user_id = $user_id ?: ($_SESSION['user_id'] ?? 0);
    $logFile = LOG_PATH . 'activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $logEntry = "[{$timestamp}] User: {$user_id} | Action: {$action} | Description: {$description} | IP: {$ip} | UserAgent: {$userAgent}\n";
    
    if (is_writable(LOG_PATH)) {
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

function sendEmail($to, $subject, $message, $from = null) {
    $from = $from ?: SMTP_FROM_EMAIL;
    
    $headers = [
        'From: ' . SMTP_FROM_NAME . ' <' . $from . '>',
        'Reply-To: ' . SMTP_FROM_EMAIL,
        'X-Mailer: PHP/' . phpversion(),
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    return mail($to, $subject, $message, implode("\r\n", $headers));
}

function redirect($url, $statusCode = 302) {
    header("Location: $url", true, $statusCode);
    exit;
}

function getCurrentURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    
    return $protocol . '://' . $host . $uri;
}

function getBaseURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']);
    
    return $protocol . '://' . $host . $path;
}

// Auto-load classes
spl_autoload_register(function ($class) {
    $classFile = __DIR__ . '/../includes/classes/' . $class . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

// Initialize system
if (INSTALLED && function_exists('logActivity')) {
    logActivity('system.access', 'System accessed', 0);
}
