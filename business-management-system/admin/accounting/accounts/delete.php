<?php
/**
 * Business Management System - Delete Account (AJAX)
 * Phase 3: Accounting System Module
 */

// Define system constant
define('BMS_SYSTEM', true);

// Start session
session_start();

// Include required files
require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/auth.php';
require_once '../../../../includes/csrf.php';

// Check authentication and permissions
requireLogin();
requirePermission('accounting.delete');

// Get database connection
$conn = getDB();

// Set JSON header
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Get account ID
$accountId = (int)($_POST['account_id'] ?? 0);

if (!$accountId) {
    echo json_encode(['success' => false, 'message' => 'Account ID is required']);
    exit;
}

try {
    // Get account details
    $stmt = $conn->prepare("
        SELECT account_code, account_name, account_type, current_balance, is_system 
        FROM " . DB_PREFIX . "accounts 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    
    if (!$account) {
        echo json_encode(['success' => false, 'message' => 'Account not found']);
        exit;
    }
    
    // Check if it's a system account
    if ($account['is_system']) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete system accounts']);
        exit;
    }
    
    // Check if account has transactions
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM " . DB_PREFIX . "journal_entry_lines 
        WHERE account_id = ?
    ");
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $transactionCount = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($transactionCount > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete account with existing transactions']);
        exit;
    }
    
    // Check if account has child accounts
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM " . DB_PREFIX . "accounts 
        WHERE parent_account_id = ?
    ");
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $childCount = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($childCount > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete account with sub-accounts']);
        exit;
    }
    
    // Check if account has non-zero balance
    if ($account['current_balance'] != 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete account with non-zero balance']);
        exit;
    }
    
    // Delete the account
    $stmt = $conn->prepare("DELETE FROM " . DB_PREFIX . "accounts WHERE id = ?");
    $stmt->bind_param('i', $accountId);
    
    if ($stmt->execute()) {
        // Log activity
        logActivity('accounting.delete', "Account deleted: {$account['account_code']} - {$account['account_name']}", [
            'account_id' => $accountId,
            'account_code' => $account['account_code'],
            'account_name' => $account['account_name'],
            'account_type' => $account['account_type']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Account deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete account']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
