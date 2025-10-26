<?php
/**
 * Business Management System - Main Configuration File
 * This file contains all system configuration settings
 */

// Prevent direct access
if (!defined('BMS_SYSTEM')) {
    define('BMS_SYSTEM', true);
}

// System Installation Status
define('INSTALLED', true);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'business_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PREFIX', 'bms_');

// Site Configuration
define('SITE_URL', 'http://localhost/business-management-system');
define('SITE_NAME', 'Business Management System');
define('COMPANY_NAME', 'Your Company Name');
define('ADMIN_EMAIL', 'admin@yourcompany.com');

// Security Configuration
define('ENCRYPTION_KEY', 'your-32-character-secret-key-here');
define('SESSION_LIFETIME', 7200); // 2 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// File Upload Configuration
define('MAX_UPLOAD_SIZE', 10485760); // 10MB
define('UPLOAD_PATH', 'uploads/');
define('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx');

// Cache Configuration
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 3600); // 1 hour
define('CACHE_PATH', 'cache/');

// Logging Configuration
define('LOG_ENABLED', true);
define('LOG_LEVEL', 'info'); // debug, info, warning, error
define('LOG_PATH', 'logs/');

// Date and Time Configuration
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i:s');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('TIMEZONE', 'Africa/Lagos');

// Currency Configuration
define('CURRENCY', 'NGN');
define('CURRENCY_POSITION', 'before'); // before or after

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_FROM_NAME', COMPANY_NAME);
define('SMTP_FROM_EMAIL', ADMIN_EMAIL);

// System Settings
define('DEBUG_MODE', true);
define('MAINTENANCE_MODE', false);
define('VERSION', '1.0.0');
define('BUILD_DATE', '2024-01-01');

// API Configuration
define('API_ENABLED', true);
define('API_RATE_LIMIT', 1000); // requests per hour
define('API_TOKEN_LIFETIME', 86400); // 24 hours

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

// Error reporting based on debug mode
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
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

// Helper function to get database connection (for backward compatibility)
function getDB() {
    return Database::getInstance();
}

// Helper function to get mysqli connection (for property management system)
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

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Helper function to require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Helper function to check permissions
function requirePermission($permission) {
    // For now, just check if user is logged in
    // In a full implementation, you would check user roles and permissions
    requireLogin();
}

// Helper function to generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Helper function to verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Helper function to sanitize input
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Helper function to format currency
function formatCurrency($amount, $currency = null) {
    $currency = $currency ?: CURRENCY;
    $symbol = ($currency === 'NGN') ? '₦' : (($currency === 'USD') ? '$' : $currency);
    
    return $symbol . number_format($amount, 2);
}

// Helper function to log activity
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

// Helper function to send email
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

// Helper function to redirect
function redirect($url, $statusCode = 302) {
    header("Location: $url", true, $statusCode);
    exit;
}

// Helper function to get current URL
function getCurrentURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    
    return $protocol . '://' . $host . $uri;
}

// Helper function to get base URL
function getBaseURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']);
    
    return $protocol . '://' . $host . $path;
}

// Auto-load classes (simple implementation)
spl_autoload_register(function ($class) {
    $classFile = __DIR__ . '/../includes/classes/' . $class . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

// Initialize system
if (INSTALLED && function_exists('logActivity')) {
    // System is installed and ready
    logActivity('system.access', 'System accessed', 0);
}
