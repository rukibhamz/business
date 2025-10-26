<?php
/**
 * Business Management System - Delete Invoice (AJAX)
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

// Get invoice ID
$invoiceId = (int)($_POST['invoice_id'] ?? 0);

if (!$invoiceId) {
    echo json_encode(['success' => false, 'message' => 'Invoice ID is required']);
    exit;
}

try {
    // Get invoice details
    $stmt = $conn->prepare("
        SELECT invoice_number, status, total_amount, customer_id 
        FROM " . DB_PREFIX . "invoices 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    
    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit;
    }
    
    // Check if invoice can be deleted (only Draft invoices)
    if ($invoice['status'] != 'Draft') {
        echo json_encode(['success' => false, 'message' => 'Only draft invoices can be deleted']);
        exit;
    }
    
    // Check if invoice has payments
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM " . DB_PREFIX . "payments 
        WHERE invoice_id = ?
    ");
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $paymentCount = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($paymentCount > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete invoice with existing payments']);
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        // Delete invoice items
        $stmt = $conn->prepare("DELETE FROM " . DB_PREFIX . "invoice_items WHERE invoice_id = ?");
        $stmt->bind_param('i', $invoiceId);
        $stmt->execute();
        
        // Delete invoice
        $stmt = $conn->prepare("DELETE FROM " . DB_PREFIX . "invoices WHERE id = ?");
        $stmt->bind_param('i', $invoiceId);
        
        if ($stmt->execute()) {
            // Update customer balance
            $stmt = $conn->prepare("
                UPDATE " . DB_PREFIX . "customers 
                SET outstanding_balance = outstanding_balance - ? 
                WHERE id = ?
            ");
            $stmt->bind_param('di', $invoice['total_amount'], $invoice['customer_id']);
            $stmt->execute();
            
            // Log activity
            logActivity('accounting.delete', "Invoice deleted: {$invoice['invoice_number']}", [
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoice['invoice_number'],
                'customer_id' => $invoice['customer_id'],
                'total_amount' => $invoice['total_amount']
            ]);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Invoice deleted successfully']);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to delete invoice']);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
