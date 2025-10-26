<?php
/**
 * Business Management System - Delete User (AJAX)
 * Phase 2: User Management & Settings System
 */

// Define system constant
define('BMS_SYSTEM', true);

// Start session
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication and permissions
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if (!hasPermission('users.delete')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Get user ID
$userId = (int)($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

// Get database connection
$conn = getDB();

// Get user data
$stmt = $conn->prepare("SELECT id, first_name, last_name, role_id FROM " . DB_PREFIX . "users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Security checks
if ($userId == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
    exit;
}

if ($user['role_id'] == 1) {
    echo json_encode(['success' => false, 'message' => 'Cannot delete Super Admin account']);
    exit;
}

// Soft delete (set is_active = 0)
$stmt = $conn->prepare("UPDATE " . DB_PREFIX . "users SET is_active = 0, updated_at = NOW() WHERE id = ?");
$stmt->bind_param('i', $userId);

if ($stmt->execute()) {
    // Log activity
    logActivity('users.delete', "User deleted: {$user['first_name']} {$user['last_name']}", [
        'user_id' => $userId,
        'deleted_by' => $_SESSION['user_id']
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'User deleted successfully',
        'user_name' => $user['first_name'] . ' ' . $user['last_name']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
}
