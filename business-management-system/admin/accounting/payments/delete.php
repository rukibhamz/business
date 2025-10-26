<?php
/**
 * Business Management System - Delete Payment (AJAX)
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

// Get payment ID
$paymentId = (int)($_POST['payment_id'] ?? 0);

if (!$paymentId) {
    echo json_encode(['success' => false, 'message' => 'Payment ID is required']);
    exit;
}

try {
    // Get payment details
    $stmt = $conn->prepare("
        SELECT payment_number, status, amount, customer_id, invoice_id 
        FROM " . DB_PREFIX . "payments 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $paymentId);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    
    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit;
    }
    
    // Check if payment can be deleted (only Pending payments)
    if ($payment['status'] != 'Pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending payments can be deleted']);
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        // Reverse invoice payment if applicable
        if ($payment['invoice_id']) {
            $stmt = $conn->prepare("
                UPDATE " . DB_PREFIX . "invoices 
                SET amount_paid = amount_paid - ?,
                    balance_due = balance_due + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param('ddi', $payment['amount'], $payment['amount'], $payment['invoice_id']);
            $stmt->execute();
            
            // Update invoice status
            updateInvoiceStatus($payment['invoice_id']);
        }
        
        // Reverse customer balance
        $stmt = $conn->prepare("
            UPDATE " . DB_PREFIX . "customers 
            SET outstanding_balance = outstanding_balance + ? 
            WHERE id = ?
        ");
        $stmt->bind_param('di', $payment['amount'], $payment['customer_id']);
        $stmt->execute();
        
        // Delete payment
        $stmt = $conn->prepare("DELETE FROM " . DB_PREFIX . "payments WHERE id = ?");
        $stmt->bind_param('i', $paymentId);
        
        if ($stmt->execute()) {
            // Log activity
            logActivity('accounting.delete', "Payment deleted: {$payment['payment_number']}", [
                'payment_id' => $paymentId,
                'payment_number' => $payment['payment_number'],
                'customer_id' => $payment['customer_id'],
                'amount' => $payment['amount'],
                'invoice_id' => $payment['invoice_id']
            ]);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Payment deleted successfully']);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to delete payment']);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
