<?php
/**
 * Business Management System - Delete Role (AJAX)
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

if (!hasPermission('roles.delete')) {
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

// Get role ID
$roleId = (int)($_POST['role_id'] ?? 0);

if ($roleId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid role ID']);
    exit;
}

// Get database connection
$conn = getDB();

// Get role data
$stmt = $conn->prepare("SELECT name, is_system_role FROM " . DB_PREFIX . "roles WHERE id = ?");
$stmt->bind_param('i', $roleId);
$stmt->execute();
$role = $stmt->get_result()->fetch_assoc();

if (!$role) {
    echo json_encode(['success' => false, 'message' => 'Role not found']);
    exit;
}

// Security checks
if ($role['is_system_role']) {
    echo json_encode(['success' => false, 'message' => 'Cannot delete system role']);
    exit;
}

// Check if role has users assigned
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "users WHERE role_id = ?");
$stmt->bind_param('i', $roleId);
$stmt->execute();
$userCount = $stmt->get_result()->fetch_assoc()['count'];

if ($userCount > 0) {
    echo json_encode(['success' => false, 'message' => "Cannot delete role. {$userCount} user(s) are assigned to this role."]);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Delete role permissions first
    $stmt = $conn->prepare("DELETE FROM " . DB_PREFIX . "role_permissions WHERE role_id = ?");
    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    
    // Delete role
    $stmt = $conn->prepare("DELETE FROM " . DB_PREFIX . "roles WHERE id = ?");
    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Log activity
    logActivity('roles.delete', "Role deleted: {$role['name']}", [
        'role_id' => $roleId,
        'deleted_by' => $_SESSION['user_id']
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Role deleted successfully',
        'role_name' => $role['name']
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to delete role']);
}
