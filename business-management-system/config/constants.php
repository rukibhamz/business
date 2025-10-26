<?php
/**
 * Business Management System - System Constants
 * Phase 1: Core Foundation
 */

// Prevent direct access
if (!defined('BMS_SYSTEM')) {
    die('Direct access not allowed');
}

// System Information
define('BMS_VERSION', '1.0.0');
define('BMS_PHASE', 'Phase 1: Core Foundation');
define('BMS_BUILD_DATE', '2024-01-01');
define('BMS_AUTHOR', 'Business Management System Team');

// File Paths
define('BMS_ROOT', dirname(dirname(__FILE__)));
define('BMS_CONFIG', BMS_ROOT . '/config');
define('BMS_INCLUDES', BMS_ROOT . '/includes');
define('BMS_ADMIN', BMS_ROOT . '/admin');
define('BMS_PUBLIC', BMS_ROOT . '/public');
define('BMS_UPLOADS', BMS_ROOT . '/uploads');
define('BMS_CACHE', BMS_ROOT . '/cache');
define('BMS_LOGS', BMS_ROOT . '/logs');

// URL Paths
define('BMS_URL', SITE_URL);
define('BMS_ADMIN_URL', BMS_URL . '/admin');
define('BMS_PUBLIC_URL', BMS_URL . '/public');
define('BMS_UPLOADS_URL', BMS_URL . '/uploads');

// User Roles
define('ROLE_SUPER_ADMIN', 1);
define('ROLE_ADMIN', 2);
define('ROLE_MANAGER', 3);
define('ROLE_STAFF', 4);
define('ROLE_CUSTOMER', 5);

// User Status
define('USER_ACTIVE', 1);
define('USER_INACTIVE', 0);
define('USER_SUSPENDED', 2);
define('USER_PENDING', 3);

// Session Configuration
define('SESSION_NAME', 'BMS_SESSION');
define('SESSION_TIMEOUT', SESSION_LIFETIME);
define('SESSION_REGENERATE_INTERVAL', 300); // 5 minutes

// Security
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_SPECIAL', true);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBERS', true);

// File Upload
define('MAX_FILE_SIZE', MAX_UPLOAD_SIZE);
define('ALLOWED_IMAGE_TYPES', 'jpg,jpeg,png,gif,webp');
define('ALLOWED_DOCUMENT_TYPES', 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv');
define('ALLOWED_ARCHIVE_TYPES', 'zip,rar,7z,tar,gz');

// Cache
define('CACHE_DEFAULT_TTL', CACHE_LIFETIME);
define('CACHE_USER_TTL', 1800); // 30 minutes
define('CACHE_SETTINGS_TTL', 3600); // 1 hour
define('CACHE_SESSION_TTL', 1800); // 30 minutes

// Pagination
define('DEFAULT_PAGE_SIZE', 25);
define('MAX_PAGE_SIZE', 100);
define('PAGINATION_LINKS', 5);

// Date Formats
define('DATE_FORMAT_DATABASE', 'Y-m-d H:i:s');
define('DATE_FORMAT_DISPLAY', DATE_FORMAT);
define('TIME_FORMAT_DISPLAY', 'H:i:s');
define('DATETIME_FORMAT_DISPLAY', DATE_FORMAT . ' ' . TIME_FORMAT_DISPLAY);

// Currency
define('CURRENCY_SYMBOL', getCurrencySymbol(CURRENCY));
define('CURRENCY_DECIMAL_PLACES', 2);
define('CURRENCY_THOUSAND_SEPARATOR', ',');
define('CURRENCY_DECIMAL_SEPARATOR', '.');

// Email
define('EMAIL_FROM_NAME', COMPANY_NAME);
define('EMAIL_FROM_ADDRESS', ADMIN_EMAIL);
define('EMAIL_REPLY_TO', ADMIN_EMAIL);

// Logging
define('LOG_LEVEL_DEBUG', 'debug');
define('LOG_LEVEL_INFO', 'info');
define('LOG_LEVEL_WARNING', 'warning');
define('LOG_LEVEL_ERROR', 'error');

// Activity Log Actions
define('ACTIVITY_LOGIN', 'user.login');
define('ACTIVITY_LOGOUT', 'user.logout');
define('ACTIVITY_LOGIN_FAILED', 'user.login_failed');
define('ACTIVITY_PASSWORD_CHANGE', 'user.password_change');
define('ACTIVITY_PROFILE_UPDATE', 'user.profile_update');
define('ACTIVITY_USER_CREATE', 'user.create');
define('ACTIVITY_USER_UPDATE', 'user.update');
define('ACTIVITY_USER_DELETE', 'user.delete');
define('ACTIVITY_SETTINGS_UPDATE', 'settings.update');
define('ACTIVITY_SYSTEM_INSTALL', 'system.install');
define('ACTIVITY_SYSTEM_UPDATE', 'system.update');

// Notification Types
define('NOTIFICATION_SUCCESS', 'success');
define('NOTIFICATION_INFO', 'info');
define('NOTIFICATION_WARNING', 'warning');
define('NOTIFICATION_ERROR', 'error');

// System Status
define('STATUS_ACTIVE', 'active');
define('STATUS_INACTIVE', 'inactive');
define('STATUS_MAINTENANCE', 'maintenance');
define('STATUS_ERROR', 'error');

// HTTP Status Codes
define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_NO_CONTENT', 204);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_METHOD_NOT_ALLOWED', 405);
define('HTTP_CONFLICT', 409);
define('HTTP_UNPROCESSABLE_ENTITY', 422);
define('HTTP_INTERNAL_SERVER_ERROR', 500);
define('HTTP_SERVICE_UNAVAILABLE', 503);

// API Response Codes
define('API_SUCCESS', 'success');
define('API_ERROR', 'error');
define('API_VALIDATION_ERROR', 'validation_error');
define('API_AUTHENTICATION_ERROR', 'authentication_error');
define('API_AUTHORIZATION_ERROR', 'authorization_error');
define('API_NOT_FOUND', 'not_found');
define('API_RATE_LIMIT_EXCEEDED', 'rate_limit_exceeded');

// Database Table Names (without prefix)
define('TABLE_USERS', 'users');
define('TABLE_ROLES', 'roles');
define('TABLE_SETTINGS', 'settings');
define('TABLE_ACTIVITY_LOGS', 'activity_logs');
define('TABLE_SESSIONS', 'sessions');
define('TABLE_LOGIN_ATTEMPTS', 'login_attempts');
define('TABLE_SYSTEM_LOGS', 'system_logs');
define('TABLE_MODULES', 'modules');
define('TABLE_PERMISSIONS', 'permissions');
define('TABLE_ROLE_PERMISSIONS', 'role_permissions');
define('TABLE_NOTIFICATIONS', 'notifications');
define('TABLE_API_TOKENS', 'api_tokens');

// Module Status
define('MODULE_ENABLED', 1);
define('MODULE_DISABLED', 0);
define('MODULE_CORE', 1);
define('MODULE_CUSTOM', 0);

// Permission Types
define('PERMISSION_VIEW', 'view');
define('PERMISSION_CREATE', 'create');
define('PERMISSION_UPDATE', 'update');
define('PERMISSION_DELETE', 'delete');
define('PERMISSION_EXPORT', 'export');
define('PERMISSION_IMPORT', 'import');

// Time Constants
define('TIME_SECOND', 1);
define('TIME_MINUTE', 60);
define('TIME_HOUR', 3600);
define('TIME_DAY', 86400);
define('TIME_WEEK', 604800);
define('TIME_MONTH', 2592000); // 30 days
define('TIME_YEAR', 31536000); // 365 days

// Memory Limits
define('MEMORY_LIMIT_SMALL', '64M');
define('MEMORY_LIMIT_MEDIUM', '128M');
define('MEMORY_LIMIT_LARGE', '256M');
define('MEMORY_LIMIT_XLARGE', '512M');

// Error Messages
define('ERROR_DATABASE_CONNECTION', 'Database connection failed');
define('ERROR_INVALID_CREDENTIALS', 'Invalid username or password');
define('ERROR_ACCOUNT_LOCKED', 'Account is locked due to too many failed login attempts');
define('ERROR_ACCOUNT_INACTIVE', 'Account is inactive');
define('ERROR_PERMISSION_DENIED', 'Permission denied');
define('ERROR_VALIDATION_FAILED', 'Validation failed');
define('ERROR_FILE_UPLOAD_FAILED', 'File upload failed');
define('ERROR_CSRF_TOKEN_INVALID', 'Invalid CSRF token');
define('ERROR_SESSION_EXPIRED', 'Session has expired');

// Success Messages
define('SUCCESS_LOGIN', 'Login successful');
define('SUCCESS_LOGOUT', 'Logout successful');
define('SUCCESS_PASSWORD_CHANGED', 'Password changed successfully');
define('SUCCESS_PROFILE_UPDATED', 'Profile updated successfully');
define('SUCCESS_SETTINGS_UPDATED', 'Settings updated successfully');
define('SUCCESS_FILE_UPLOADED', 'File uploaded successfully');
define('SUCCESS_DATA_SAVED', 'Data saved successfully');
define('SUCCESS_DATA_DELETED', 'Data deleted successfully');

// Helper Functions

/**
 * Get currency symbol
 */
function getCurrencySymbol($currency) {
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'NGN' => '₦',
        'CAD' => 'C$',
        'AUD' => 'A$',
        'JPY' => '¥',
        'CHF' => 'CHF',
        'CNY' => '¥',
        'INR' => '₹',
        'BRL' => 'R$',
        'MXN' => '$',
        'ZAR' => 'R',
        'AED' => 'د.إ',
        'SAR' => 'ر.س'
    ];
    
    return $symbols[$currency] ?? $currency;
}

/**
 * Get role name by ID
 */
function getRoleName($roleId) {
    $roles = [
        ROLE_SUPER_ADMIN => 'Super Admin',
        ROLE_ADMIN => 'Admin',
        ROLE_MANAGER => 'Manager',
        ROLE_STAFF => 'Staff',
        ROLE_CUSTOMER => 'Customer'
    ];
    
    return $roles[$roleId] ?? 'Unknown';
}

/**
 * Get user status name
 */
function getUserStatusName($status) {
    $statuses = [
        USER_ACTIVE => 'Active',
        USER_INACTIVE => 'Inactive',
        USER_SUSPENDED => 'Suspended',
        USER_PENDING => 'Pending'
    ];
    
    return $statuses[$status] ?? 'Unknown';
}

/**
 * Get notification type class
 */
function getNotificationClass($type) {
    $classes = [
        NOTIFICATION_SUCCESS => 'success',
        NOTIFICATION_INFO => 'info',
        NOTIFICATION_WARNING => 'warning',
        NOTIFICATION_ERROR => 'error'
    ];
    
    return $classes[$type] ?? 'info';
}

/**
 * Format currency amount
 */
function formatCurrency($amount, $currency = null) {
    $currency = $currency ?: CURRENCY;
    $symbol = getCurrencySymbol($currency);
    
    return $symbol . number_format($amount, CURRENCY_DECIMAL_PLACES, CURRENCY_DECIMAL_SEPARATOR, CURRENCY_THOUSAND_SEPARATOR);
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Generate random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateRandomString(32);
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
 * Sanitize input
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL
 */
function isValidURL($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Get client IP address
 */
function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get user agent
 */
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Check if request is POST
 */
function isPostRequest() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Check if request is GET
 */
function isGetRequest() {
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

/**
 * Redirect to URL
 */
function redirect($url, $statusCode = 302) {
    header("Location: $url", true, $statusCode);
    exit;
}

/**
 * Get current URL
 */
function getCurrentURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    
    return $protocol . '://' . $host . $uri;
}

/**
 * Get base URL
 */
function getBaseURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']);
    
    return $protocol . '://' . $host . $path;
}
