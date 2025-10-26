<?php
/**
 * Business Management System - Global Helper Functions
 * Phase 1: Core Foundation
 */

// Prevent direct access
if (!defined('BMS_SYSTEM')) {
    die('Direct access not allowed');
}

/**
 * Start secure session
 */
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configure session security
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Set session name
        session_name(SESSION_NAME);
        
        // Start session
        session_start();
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > SESSION_REGENERATE_INTERVAL) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    startSecureSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $userId = getCurrentUserId();
    $db = getDB();
    
    return $db->fetch(
        "SELECT u.*, r.name as role_name, r.description as role_description 
         FROM " . $db->getTableName('users') . " u 
         LEFT JOIN " . $db->getTableName('roles') . " r ON u.role_id = r.id 
         WHERE u.id = ? AND u.is_active = 1",
        [$userId]
    );
}

/**
 * Require user to be logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect(BMS_ADMIN_URL . '/login.php');
    }
}

/**
 * Check if user has permission
 */
function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }
    
    // Super admin has all permissions
    if ($user['role_id'] == ROLE_SUPER_ADMIN) {
        return true;
    }
    
    $db = getDB();
    
    // Check if user's role has the permission
    $hasPermission = $db->exists(
        'role_permissions rp',
        'rp.role_id = ? AND rp.permission_id = (SELECT id FROM ' . $db->getTableName('permissions') . ' WHERE name = ?)',
        [$user['role_id'], $permission]
    );
    
    return $hasPermission;
}

/**
 * Require specific permission
 */
function requirePermission($permission) {
    if (!hasPermission($permission)) {
        http_response_code(403);
        die('Access denied. You do not have permission to perform this action.');
    }
}

/**
 * Log user activity
 */
function logActivity($action, $description = '', $data = []) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $db = getDB();
    
    return $db->insert('activity_logs', [
        'user_id' => getCurrentUserId(),
        'action' => $action,
        'description' => $description,
        'ip_address' => getClientIP(),
        'user_agent' => getUserAgent(),
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Log system activity
 */
function logSystemActivity($level, $message, $context = []) {
    $db = getDB();
    
    return $db->insert('system_logs', [
        'level' => $level,
        'message' => $message,
        'context' => json_encode($context),
        'file' => debug_backtrace()[0]['file'] ?? '',
        'line' => debug_backtrace()[0]['line'] ?? 0,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Check login attempts
 */
function checkLoginAttempts($ip, $username = null) {
    $db = getDB();
    
    $where = 'ip_address = ?';
    $params = [$ip];
    
    if ($username) {
        $where .= ' AND username = ?';
        $params[] = $username;
    }
    
    $attempt = $db->fetch(
        "SELECT * FROM " . $db->getTableName('login_attempts') . " 
         WHERE {$where} AND (blocked_until IS NULL OR blocked_until > NOW()) 
         ORDER BY last_attempt DESC LIMIT 1",
        $params
    );
    
    if (!$attempt) {
        return ['allowed' => true, 'attempts' => 0];
    }
    
    $allowed = $attempt['attempts'] < MAX_LOGIN_ATTEMPTS;
    
    return [
        'allowed' => $allowed,
        'attempts' => $attempt['attempts'],
        'blocked_until' => $attempt['blocked_until']
    ];
}

/**
 * Record failed login attempt
 */
function recordFailedLogin($ip, $username = null) {
    $db = getDB();
    
    $existing = $db->fetch(
        "SELECT * FROM " . $db->getTableName('login_attempts') . " 
         WHERE ip_address = ? AND (username = ? OR username IS NULL) 
         ORDER BY last_attempt DESC LIMIT 1",
        [$ip, $username]
    );
    
    if ($existing) {
        $attempts = $existing['attempts'] + 1;
        $blockedUntil = null;
        
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $blockedUntil = date('Y-m-d H:i:s', time() + (15 * 60)); // 15 minutes
        }
        
        $db->update(
            'login_attempts',
            [
                'attempts' => $attempts,
                'blocked_until' => $blockedUntil,
                'last_attempt' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$existing['id']]
        );
    } else {
        $db->insert('login_attempts', [
            'ip_address' => $ip,
            'username' => $username,
            'attempts' => 1,
            'last_attempt' => date('Y-m-d H:i:s')
        ]);
    }
}

/**
 * Clear login attempts
 */
function clearLoginAttempts($ip, $username = null) {
    $db = getDB();
    
    $where = 'ip_address = ?';
    $params = [$ip];
    
    if ($username) {
        $where .= ' AND username = ?';
        $params[] = $username;
    }
    
    $db->delete('login_attempts', $where, $params);
}

/**
 * Authenticate user
 */
function authenticateUser($username, $password, $rememberMe = false) {
    $db = getDB();
    
    // Check login attempts
    $ip = getClientIP();
    $attempts = checkLoginAttempts($ip, $username);
    
    if (!$attempts['allowed']) {
        return [
            'success' => false,
            'message' => 'Too many failed login attempts. Please try again later.',
            'blocked_until' => $attempts['blocked_until']
        ];
    }
    
    // Get user by username or email
    $user = $db->fetch(
        "SELECT u.*, r.name as role_name 
         FROM " . $db->getTableName('users') . " u 
         LEFT JOIN " . $db->getTableName('roles') . " r ON u.role_id = r.id 
         WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1",
        [$username, $username]
    );
    
    if (!$user) {
        recordFailedLogin($ip, $username);
        return [
            'success' => false,
            'message' => 'Invalid username or password'
        ];
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        recordFailedLogin($ip, $username);
        return [
            'success' => false,
            'message' => 'Invalid username or password'
        ];
    }
    
    // Clear failed login attempts
    clearLoginAttempts($ip, $username);
    
    // Start session
    startSecureSession();
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['role_name'] = $user['role_name'];
    $_SESSION['login_time'] = time();
    
    // Set remember me cookie
    if ($rememberMe) {
        $token = generateRandomString(64);
        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', isset($_SERVER['HTTPS']), true);
        
        // Store token in database
        $db->insert('api_tokens', [
            'user_id' => $user['id'],
            'name' => 'Remember Me',
            'token' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)),
            'is_active' => 1
        ]);
    }
    
    // Update last login
    $db->update(
        'users',
        ['last_login' => date('Y-m-d H:i:s')],
        'id = ?',
        [$user['id']]
    );
    
    // Log successful login
    logActivity(ACTIVITY_LOGIN, 'User logged in successfully');
    
    return [
        'success' => true,
        'user' => $user,
        'message' => 'Login successful'
    ];
}

/**
 * Logout user
 */
function logoutUser() {
    if (!isLoggedIn()) {
        return;
    }
    
    // Log logout activity
    logActivity(ACTIVITY_LOGOUT, 'User logged out');
    
    // Clear remember me token
    if (isset($_COOKIE['remember_token'])) {
        $db = getDB();
        $token = hash('sha256', $_COOKIE['remember_token']);
        $db->update(
            'api_tokens',
            ['is_active' => 0],
            'token = ? AND name = ?',
            [$token, 'Remember Me']
        );
        setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    }
    
    // Destroy session
    session_destroy();
    
    // Clear session cookie
    setcookie(session_name(), '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
}

/**
 * Check remember me token
 */
function checkRememberMe() {
    if (isset($_COOKIE['remember_token']) && !isLoggedIn()) {
        $db = getDB();
        $token = hash('sha256', $_COOKIE['remember_token']);
        
        $tokenData = $db->fetch(
            "SELECT t.*, u.*, r.name as role_name 
             FROM " . $db->getTableName('api_tokens') . " t 
             JOIN " . $db->getTableName('users') . " u ON t.user_id = u.id 
             LEFT JOIN " . $db->getTableName('roles') . " r ON u.role_id = r.id 
             WHERE t.token = ? AND t.name = ? AND t.is_active = 1 
             AND (t.expires_at IS NULL OR t.expires_at > NOW()) 
             AND u.is_active = 1",
            [$token, 'Remember Me']
        );
        
        if ($tokenData) {
            // Start session
            startSecureSession();
            
            // Set session variables
            $_SESSION['user_id'] = $tokenData['user_id'];
            $_SESSION['username'] = $tokenData['username'];
            $_SESSION['role_id'] = $tokenData['role_id'];
            $_SESSION['role_name'] = $tokenData['role_name'];
            $_SESSION['login_time'] = time();
            
            // Update last login
            $db->update(
                'users',
                ['last_login' => date('Y-m-d H:i:s')],
                'id = ?',
                [$tokenData['user_id']]
            );
            
            // Log automatic login
            logActivity(ACTIVITY_LOGIN, 'User logged in via remember me');
        }
    }
}

/**
 * Get setting value
 */
function getSetting($key, $default = null) {
    $db = getDB();
    
    $setting = $db->fetch(
        "SELECT setting_value FROM " . $db->getTableName('settings') . " WHERE setting_key = ?",
        [$key]
    );
    
    return $setting ? $setting['setting_value'] : $default;
}

/**
 * Set setting value
 */
function setSetting($key, $value) {
    $db = getDB();
    
    return $db->query(
        "INSERT INTO " . $db->getTableName('settings') . " (setting_key, setting_value, updated_at) 
         VALUES (?, ?, NOW()) 
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()",
        [$key, $value]
    );
}

/**
 * Get all settings
 */
function getAllSettings() {
    $db = getDB();
    
    $settings = $db->fetchAll(
        "SELECT setting_key, setting_value FROM " . $db->getTableName('settings') . " ORDER BY setting_key"
    );
    
    $result = [];
    foreach ($settings as $setting) {
        $result[$setting['setting_key']] = $setting['setting_value'];
    }
    
    return $result;
}

/**
 * Send notification to user
 */
function sendNotification($userId, $title, $message, $type = NOTIFICATION_INFO) {
    $db = getDB();
    
    return $db->insert('notifications', [
        'user_id' => $userId,
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get user notifications
 */
function getUserNotifications($userId, $unreadOnly = false) {
    $db = getDB();
    
    $where = 'user_id = ?';
    $params = [$userId];
    
    if ($unreadOnly) {
        $where .= ' AND is_read = 0';
    }
    
    return $db->fetchAll(
        "SELECT * FROM " . $db->getTableName('notifications') . " 
         WHERE {$where} 
         ORDER BY created_at DESC",
        $params
    );
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($notificationId, $userId) {
    $db = getDB();
    
    return $db->update(
        'notifications',
        [
            'is_read' => 1,
            'read_at' => date('Y-m-d H:i:s')
        ],
        'id = ? AND user_id = ?',
        [$notificationId, $userId]
    );
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsAsRead($userId) {
    $db = getDB();
    
    return $db->update(
        'notifications',
        [
            'is_read' => 1,
            'read_at' => date('Y-m-d H:i:s')
        ],
        'user_id = ? AND is_read = 0',
        [$userId]
    );
}

/**
 * Get unread notification count
 */
function getUnreadNotificationCount($userId) {
    $db = getDB();
    
    return $db->count('notifications', 'user_id = ? AND is_read = 0', [$userId]);
}

/**
 * Format date for display
 */
function formatDate($date, $format = null) {
    if (!$date) {
        return '';
    }
    
    $format = $format ?: DATE_FORMAT_DISPLAY;
    
    if (is_string($date)) {
        $date = new DateTime($date);
    }
    
    return $date->format($format);
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime, $format = null) {
    if (!$datetime) {
        return '';
    }
    
    $format = $format ?: DATETIME_FORMAT_DISPLAY;
    
    if (is_string($datetime)) {
        $datetime = new DateTime($datetime);
    }
    
    return $datetime->format($format);
}

/**
 * Get time ago string
 */
function timeAgo($datetime) {
    if (!$datetime) {
        return 'Never';
    }
    
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return 'Just now';
    } elseif ($time < 3600) {
        $minutes = floor($time / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($time < 2592000) {
        $days = floor($time / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($datetime);
    }
}

/**
 * Generate pagination
 */
function generatePagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $pagination = '<nav class="pagination"><ul>';
    
    // Previous button
    if ($currentPage > 1) {
        $pagination .= '<li><a href="' . $baseUrl . '?page=' . ($currentPage - 1) . '">&laquo; Previous</a></li>';
    }
    
    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $pagination .= '<li><a href="' . $baseUrl . '?page=1">1</a></li>';
        if ($start > 2) {
            $pagination .= '<li><span>...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $currentPage ? ' class="active"' : '';
        $pagination .= '<li' . $active . '><a href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $pagination .= '<li><span>...</span></li>';
        }
        $pagination .= '<li><a href="' . $baseUrl . '?page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $pagination .= '<li><a href="' . $baseUrl . '?page=' . ($currentPage + 1) . '">Next &raquo;</a></li>';
    }
    
    $pagination .= '</ul></nav>';
    
    return $pagination;
}

/**
 * Sanitize filename
 */
function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    $filename = preg_replace('/\.{2,}/', '.', $filename);
    return $filename;
}

/**
 * Get file extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if file type is allowed
 */
function isAllowedFileType($filename, $allowedTypes = null) {
    $extension = getFileExtension($filename);
    $allowedTypes = $allowedTypes ?: explode(',', ALLOWED_FILE_TYPES);
    
    return in_array($extension, $allowedTypes);
}

/**
 * Generate unique filename
 */
function generateUniqueFilename($originalFilename, $directory = '') {
    $extension = getFileExtension($originalFilename);
    $basename = pathinfo($originalFilename, PATHINFO_FILENAME);
    $basename = sanitizeFilename($basename);
    
    $filename = $basename . '_' . time() . '_' . generateRandomString(8) . '.' . $extension;
    
    if ($directory) {
        $filename = rtrim($directory, '/') . '/' . $filename;
    }
    
    return $filename;
}

/**
 * Create directory if it doesn't exist
 */
function createDirectory($path, $permissions = 0755) {
    if (!is_dir($path)) {
        return mkdir($path, $permissions, true);
    }
    return true;
}

/**
 * Delete file safely
 */
function deleteFile($filepath) {
    if (file_exists($filepath) && is_file($filepath)) {
        return unlink($filepath);
    }
    return false;
}

/**
 * Get file size in human readable format
 */
function getFileSize($filepath) {
    if (!file_exists($filepath)) {
        return '0 B';
    }
    
    return formatFileSize(filesize($filepath));
}

/**
 * Check if system is in maintenance mode
 */
function isMaintenanceMode() {
    return getSetting('maintenance_mode', '0') === '1';
}

/**
 * Check if user can bypass maintenance mode
 */
function canBypassMaintenance() {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user = getCurrentUser();
    return $user && in_array($user['role_id'], [ROLE_SUPER_ADMIN, ROLE_ADMIN]);
}

/**
 * Get system status
 */
function getSystemStatus() {
    $status = [
        'database' => false,
        'cache' => false,
        'uploads' => false,
        'logs' => false
    ];
    
    // Check database
    try {
        $db = getDB();
        $status['database'] = $db->testConnection();
    } catch (Exception $e) {
        $status['database'] = false;
    }
    
    // Check cache directory
    $status['cache'] = is_writable(BMS_CACHE);
    
    // Check uploads directory
    $status['uploads'] = is_writable(BMS_UPLOADS);
    
    // Check logs directory
    $status['logs'] = is_writable(BMS_LOGS);
    
    return $status;
}

/**
 * Get system statistics
 */
function getSystemStats() {
    $db = getDB();
    
    return [
        'total_users' => $db->count('users'),
        'active_users' => $db->count('users', 'is_active = 1'),
        'total_notifications' => $db->count('notifications'),
        'unread_notifications' => $db->count('notifications', 'is_read = 0'),
        'total_activity_logs' => $db->count('activity_logs'),
        'system_uptime' => getSetting('system_uptime', '0')
    ];
}
