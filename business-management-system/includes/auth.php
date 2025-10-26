<?php
/**
 * Business Management System - Authentication System
 * Phase 1: Core Foundation
 */

// Prevent direct access
if (!defined('BMS_SYSTEM')) {
    die('Direct access not allowed');
}

// Include required files
require_once BMS_CONFIG . '/config.php';
require_once BMS_CONFIG . '/constants.php';
require_once BMS_CONFIG . '/database.php';
require_once BMS_INCLUDES . '/functions.php';

/**
 * Authentication class
 */
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Login user
     */
    public function login($username, $password, $rememberMe = false) {
        // Validate input
        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Username and password are required'
            ];
        }
        
        // Check if system is in maintenance mode
        if (isMaintenanceMode() && !canBypassMaintenance()) {
            return [
                'success' => false,
                'message' => 'System is currently under maintenance. Please try again later.'
            ];
        }
        
        // Attempt authentication
        $result = authenticateUser($username, $password, $rememberMe);
        
        if ($result['success']) {
            // Set session timeout
            $_SESSION['timeout'] = time() + SESSION_TIMEOUT;
            
            // Update session data
            $_SESSION['last_activity'] = time();
            $_SESSION['ip_address'] = getClientIP();
            $_SESSION['user_agent'] = getUserAgent();
        }
        
        return $result;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if (isLoggedIn()) {
            // Log logout activity
            logActivity(ACTIVITY_LOGOUT, 'User logged out');
            
            // Clear remember me token
            $this->clearRememberMeToken();
            
            // Destroy session
            session_destroy();
            
            // Clear session cookie
            setcookie(session_name(), '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        }
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isLoggedIn();
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser() {
        return getCurrentUser();
    }
    
    /**
     * Check session timeout
     */
    public function checkSessionTimeout() {
        if (!isLoggedIn()) {
            return false;
        }
        
        $timeout = $_SESSION['timeout'] ?? 0;
        
        if (time() > $timeout) {
            $this->logout();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Check IP address change
     */
    public function checkIPChange() {
        if (!isLoggedIn()) {
            return true;
        }
        
        $currentIP = getClientIP();
        $sessionIP = $_SESSION['ip_address'] ?? '';
        
        if ($currentIP !== $sessionIP) {
            // Log suspicious activity
            logActivity('security.ip_change', 'IP address changed during session', [
                'old_ip' => $sessionIP,
                'new_ip' => $currentIP
            ]);
            
            // Force logout
            $this->logout();
            return false;
        }
        
        return true;
    }
    
    /**
     * Check user agent change
     */
    public function checkUserAgentChange() {
        if (!isLoggedIn()) {
            return true;
        }
        
        $currentUA = getUserAgent();
        $sessionUA = $_SESSION['user_agent'] ?? '';
        
        if ($currentUA !== $sessionUA) {
            // Log suspicious activity
            logActivity('security.user_agent_change', 'User agent changed during session', [
                'old_ua' => $sessionUA,
                'new_ua' => $currentUA
            ]);
            
            // Force logout
            $this->logout();
            return false;
        }
        
        return true;
    }
    
    /**
     * Change password
     */
    public function changePassword($currentPassword, $newPassword, $confirmPassword) {
        if (!isLoggedIn()) {
            return [
                'success' => false,
                'message' => 'You must be logged in to change password'
            ];
        }
        
        // Validate input
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            return [
                'success' => false,
                'message' => 'All password fields are required'
            ];
        }
        
        if ($newPassword !== $confirmPassword) {
            return [
                'success' => false,
                'message' => 'New passwords do not match'
            ];
        }
        
        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            return [
                'success' => false,
                'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long'
            ];
        }
        
        // Get current user
        $user = getCurrentUser();
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found'
            ];
        }
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Current password is incorrect'
            ];
        }
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password
        $updated = $this->db->update(
            'users',
            ['password' => $hashedPassword, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$user['id']]
        );
        
        if ($updated) {
            // Log password change
            logActivity(ACTIVITY_PASSWORD_CHANGE, 'Password changed successfully');
            
            // Clear remember me tokens
            $this->clearRememberMeToken();
            
            return [
                'success' => true,
                'message' => 'Password changed successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to update password'
            ];
        }
    }
    
    /**
     * Update profile
     */
    public function updateProfile($data) {
        if (!isLoggedIn()) {
            return [
                'success' => false,
                'message' => 'You must be logged in to update profile'
            ];
        }
        
        $user = getCurrentUser();
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found'
            ];
        }
        
        // Validate data
        $allowedFields = ['first_name', 'last_name', 'email'];
        $updateData = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $updateData[$field] = sanitizeInput($data[$field]);
            }
        }
        
        if (empty($updateData)) {
            return [
                'success' => false,
                'message' => 'No valid data to update'
            ];
        }
        
        // Validate email if provided
        if (isset($updateData['email'])) {
            if (!isValidEmail($updateData['email'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid email address'
                ];
            }
            
            // Check if email is already in use
            $existingUser = $this->db->fetch(
                "SELECT id FROM " . $this->db->getTableName('users') . " 
                 WHERE email = ? AND id != ?",
                [$updateData['email'], $user['id']]
            );
            
            if ($existingUser) {
                return [
                    'success' => false,
                    'message' => 'Email address is already in use'
                ];
            }
        }
        
        // Update user
        $updated = $this->db->update(
            'users',
            array_merge($updateData, ['updated_at' => date('Y-m-d H:i:s')]),
            'id = ?',
            [$user['id']]
        );
        
        if ($updated) {
            // Log profile update
            logActivity(ACTIVITY_PROFILE_UPDATE, 'Profile updated successfully');
            
            return [
                'success' => true,
                'message' => 'Profile updated successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to update profile'
            ];
        }
    }
    
    /**
     * Clear remember me token
     */
    private function clearRememberMeToken() {
        if (isset($_COOKIE['remember_token'])) {
            $token = hash('sha256', $_COOKIE['remember_token']);
            $this->db->update(
                'api_tokens',
                ['is_active' => 0],
                'token = ? AND name = ?',
                [$token, 'Remember Me']
            );
            setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        }
    }
    
    /**
     * Get user permissions
     */
    public function getUserPermissions($userId = null) {
        if (!$userId) {
            $userId = getCurrentUserId();
        }
        
        if (!$userId) {
            return [];
        }
        
        $user = $this->db->fetch(
            "SELECT role_id FROM " . $this->db->getTableName('users') . " WHERE id = ?",
            [$userId]
        );
        
        if (!$user) {
            return [];
        }
        
        // Super admin has all permissions
        if ($user['role_id'] == ROLE_SUPER_ADMIN) {
            $permissions = $this->db->fetchAll(
                "SELECT name FROM " . $this->db->getTableName('permissions') . " ORDER BY name"
            );
            return array_column($permissions, 'name');
        }
        
        // Get role permissions
        $permissions = $this->db->fetchAll(
            "SELECT p.name 
             FROM " . $this->db->getTableName('permissions') . " p 
             JOIN " . $this->db->getTableName('role_permissions') . " rp ON p.id = rp.permission_id 
             WHERE rp.role_id = ? 
             ORDER BY p.name",
            [$user['role_id']]
        );
        
        return array_column($permissions, 'name');
    }
    
    /**
     * Check if user has permission
     */
    public function hasPermission($permission) {
        return hasPermission($permission);
    }
    
    /**
     * Get login history
     */
    public function getLoginHistory($userId = null, $limit = 10) {
        if (!$userId) {
            $userId = getCurrentUserId();
        }
        
        if (!$userId) {
            return [];
        }
        
        return $this->db->fetchAll(
            "SELECT * FROM " . $this->db->getTableName('activity_logs') . " 
             WHERE user_id = ? AND action IN (?, ?) 
             ORDER BY created_at DESC 
             LIMIT ?",
            [$userId, ACTIVITY_LOGIN, ACTIVITY_LOGOUT, $limit]
        );
    }
    
    /**
     * Get active sessions
     */
    public function getActiveSessions($userId = null) {
        if (!$userId) {
            $userId = getCurrentUserId();
        }
        
        if (!$userId) {
            return [];
        }
        
        return $this->db->fetchAll(
            "SELECT * FROM " . $this->db->getTableName('sessions') . " 
             WHERE user_id = ? AND last_activity > DATE_SUB(NOW(), INTERVAL ? SECOND) 
             ORDER BY last_activity DESC",
            [$userId, SESSION_TIMEOUT]
        );
    }
    
    /**
     * Terminate session
     */
    public function terminateSession($sessionId) {
        return $this->db->delete('sessions', 'id = ?', [$sessionId]);
    }
    
    /**
     * Terminate all other sessions
     */
    public function terminateAllOtherSessions($userId = null) {
        if (!$userId) {
            $userId = getCurrentUserId();
        }
        
        if (!$userId) {
            return false;
        }
        
        $currentSessionId = session_id();
        
        return $this->db->delete(
            'sessions',
            'user_id = ? AND id != ?',
            [$userId, $currentSessionId]
        );
    }
}

// Initialize authentication
$auth = new Auth();

// Check remember me token
checkRememberMe();

// Check session timeout
if (isLoggedIn()) {
    if (!$auth->checkSessionTimeout()) {
        redirect(BMS_ADMIN_URL . '/login.php?timeout=1');
    }
    
    // Check IP and user agent changes (optional, can be disabled for development)
    if (ENVIRONMENT === 'production') {
        if (!$auth->checkIPChange() || !$auth->checkUserAgentChange()) {
            redirect(BMS_ADMIN_URL . '/login.php?security=1');
        }
    }
}

// Helper functions for backward compatibility
function requireLogin() {
    if (!isLoggedIn()) {
        redirect(BMS_ADMIN_URL . '/login.php');
    }
}

function requirePermission($permission) {
    if (!hasPermission($permission)) {
        http_response_code(403);
        die('Access denied. You do not have permission to perform this action.');
    }
}

function logActivity($action, $description = '', $data = []) {
    return logActivity($action, $description, $data);
}
