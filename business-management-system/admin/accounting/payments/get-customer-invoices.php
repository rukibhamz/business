<?php
/**
 * Business Management System - Get Customer Invoices (AJAX)
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

// Check authentication
requireLogin();

// Get database connection
$conn = getDB();

// Set JSON header
header('Content-Type: application/json');

// Get customer ID
$customerId = (int)($_GET['customer_id'] ?? 0);

if (!$customerId) {
    echo json_encode([]);
    exit;
}

try {
    // Get customer invoices with outstanding balance
    $stmt = $conn->prepare("
        SELECT id, invoice_number, total_amount, amount_paid, balance_due, due_date, status
        FROM " . DB_PREFIX . "invoices 
        WHERE customer_id = ? 
        AND balance_due > 0
        AND status IN ('Sent', 'Partial', 'Overdue')
        ORDER BY due_date ASC, created_at DESC
    ");
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode($invoices);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
