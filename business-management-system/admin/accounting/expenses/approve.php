<?php
/**
 * Business Management System - Approve Expense (AJAX)
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
require_once '../../../../includes/accounting-functions.php';

// Check authentication and permissions
requireLogin();
requirePermission('accounting.edit');

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

// Get expense ID
$expenseId = (int)($_POST['expense_id'] ?? 0);

if (!$expenseId) {
    echo json_encode(['success' => false, 'message' => 'Expense ID is required']);
    exit;
}

try {
    // Get expense details
    $stmt = $conn->prepare("
        SELECT expense_number, status, amount, expense_date, description, 
               vendor_name, account_id, category_id
        FROM " . DB_PREFIX . "expenses 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $expenseId);
    $stmt->execute();
    $expense = $stmt->get_result()->fetch_assoc();
    
    if (!$expense) {
        echo json_encode(['success' => false, 'message' => 'Expense not found']);
        exit;
    }
    
    // Check if expense can be approved
    if ($expense['status'] != 'Pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending expenses can be approved']);
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        // Update expense status
        $stmt = $conn->prepare("
            UPDATE " . DB_PREFIX . "expenses 
            SET status = 'Approved', 
                approved_by = ?, 
                approved_date = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $userId = $_SESSION['user_id'];
        $stmt->bind_param('ii', $userId, $expenseId);
        $stmt->execute();
        
        // Create journal entry
        $expenseAccountId = $expense['account_id'] ?? 5000; // Default expense account
        
        $lines = [
            [
                'account_id' => $expenseAccountId,
                'debit' => $expense['amount'],
                'credit' => 0,
                'description' => 'Expense - ' . $expense['description']
            ],
            [
                'account_id' => 1010, // Cash account
                'debit' => 0,
                'credit' => $expense['amount'],
                'description' => 'Payment for expense'
            ]
        ];
        
        createJournalEntry(
            $expense['expense_date'],
            'Expense ' . $expense['expense_number'] . ' - ' . $expense['vendor_name'],
            $lines,
            'Expense',
            $expenseId,
            'expense'
        );
        
        // Log activity
        logActivity('accounting.edit', "Expense approved: {$expense['expense_number']}", [
            'expense_id' => $expenseId,
            'expense_number' => $expense['expense_number'],
            'amount' => $expense['amount'],
            'approved_by' => $userId
        ]);
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Expense approved successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
