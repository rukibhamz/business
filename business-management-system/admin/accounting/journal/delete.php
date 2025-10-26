<?php
/**
 * Business Management System - Delete Journal Entry (AJAX)
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

// Get journal entry ID
$entryId = (int)($_POST['entry_id'] ?? 0);

if (!$entryId) {
    echo json_encode(['success' => false, 'message' => 'Journal entry ID is required']);
    exit;
}

try {
    // Get journal entry details
    $stmt = $conn->prepare("
        SELECT entry_number, reference_type
        FROM " . DB_PREFIX . "journal_entries 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $entryId);
    $stmt->execute();
    $entry = $stmt->get_result()->fetch_assoc();
    
    if (!$entry) {
        echo json_encode(['success' => false, 'message' => 'Journal entry not found']);
        exit;
    }
    
    // Check if entry can be deleted (only manual journal entries)
    if ($entry['reference_type'] != 'Journal') {
        echo json_encode(['success' => false, 'message' => 'Only manual journal entries can be deleted']);
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        // Get journal lines to reverse account balances
        $stmt = $conn->prepare("
            SELECT account_id, debit, credit 
            FROM " . DB_PREFIX . "journal_entry_lines 
            WHERE journal_entry_id = ?
        ");
        $stmt->bind_param('i', $entryId);
        $stmt->execute();
        $lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Reverse account balances
        foreach ($lines as $line) {
            // Reverse the balance update (debit becomes credit, credit becomes debit)
            updateAccountBalance($line['account_id'], $line['credit'], $line['debit']);
        }
        
        // Delete journal entry lines
        $stmt = $conn->prepare("DELETE FROM " . DB_PREFIX . "journal_entry_lines WHERE journal_entry_id = ?");
        $stmt->bind_param('i', $entryId);
        $stmt->execute();
        
        // Delete journal entry
        $stmt = $conn->prepare("DELETE FROM " . DB_PREFIX . "journal_entries WHERE id = ?");
        $stmt->bind_param('i', $entryId);
        
        if ($stmt->execute()) {
            // Log activity
            logActivity('accounting.delete', "Journal entry deleted: {$entry['entry_number']}", [
                'entry_id' => $entryId,
                'entry_number' => $entry['entry_number']
            ]);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Journal entry deleted successfully']);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to delete journal entry']);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
