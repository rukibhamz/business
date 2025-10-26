<?php
/**
 * Business Management System - Sample Configuration File
 * Phase 1: Core Foundation
 * 
 * Copy this file to config.php and update the values
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'bms_database');
define('DB_USER', 'bms_user');
define('DB_PASS', 'your_password_here');
define('DB_PREFIX', 'bms_');

// Site Configuration
define('SITE_URL', 'http://localhost/bms');
define('COMPANY_NAME', 'Your Company Name');
define('ADMIN_EMAIL', 'admin@yourcompany.com');
define('TIMEZONE', 'UTC');
define('CURRENCY', 'USD');
define('DATE_FORMAT', 'Y-m-d');

// System Configuration
define('INSTALLED', false);
define('VERSION', '1.0.0');
define('ENVIRONMENT', 'development'); // development, staging, production

// Security Configuration
define('ENCRYPTION_KEY', 'your_32_character_encryption_key_here');
define('SESSION_LIFETIME', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);

// Email Configuration (for future use)
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', 'tls'); // tls or ssl

// File Upload Configuration
define('MAX_UPLOAD_SIZE', 10485760); // 10MB in bytes
define('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx');

// Cache Configuration
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 3600); // 1 hour

// Logging Configuration
define('LOG_LEVEL', 'info'); // debug, info, warning, error
define('LOG_MAX_FILES', 10);
define('LOG_MAX_SIZE', 10485760); // 10MB

// API Configuration (for future use)
define('API_ENABLED', false);
define('API_RATE_LIMIT', 100); // requests per hour

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error Reporting
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} elseif (ENVIRONMENT === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    // Default to production settings for unknown environments
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Prevent direct access
if (!defined('BMS_SYSTEM')) {
    http_response_code(403);
    exit('Direct access not allowed');
}
