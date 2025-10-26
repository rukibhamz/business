<?php
/**
 * Business Management System - Delete Expense (AJAX)
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

// Get expense ID
$expenseId = (int)($_POST['expense_id'] ?? 0);

if (!$expenseId) {
    echo json_encode(['success' => false, 'message' => 'Expense ID is required']);
    exit;
}

try {
    // Get expense details
    $stmt = $conn->prepare("
        SELECT expense_number, status, receipt_file
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
    
    // Check if expense can be deleted (only Pending expenses)
    if ($expense['status'] != 'Pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending expenses can be deleted']);
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        // Delete receipt file if exists
        if ($expense['receipt_file'] && file_exists('../../../../' . $expense['receipt_file'])) {
            unlink('../../../../' . $expense['receipt_file']);
        }
        
        // Delete expense
        $stmt = $conn->prepare("DELETE FROM " . DB_PREFIX . "expenses WHERE id = ?");
        $stmt->bind_param('i', $expenseId);
        
        if ($stmt->execute()) {
            // Log activity
            logActivity('accounting.delete', "Expense deleted: {$expense['expense_number']}", [
                'expense_id' => $expenseId,
                'expense_number' => $expense['expense_number']
            ]);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Expense deleted successfully']);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to delete expense']);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
