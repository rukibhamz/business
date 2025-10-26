<?php
/**
 * Business Management System - Hall Accounting Integration
 * Phase 4: Hall Booking System Module
 */

// Prevent direct access
if (!defined('BMS_SYSTEM')) {
    die('Direct access not allowed');
}

/**
 * Create journal entry for hall booking
 */
function createHallBookingJournalEntry($bookingId) {
    $conn = getDB();
    
    // Get booking details
    $stmt = $conn->prepare("
        SELECT hb.*, h.hall_name
        FROM " . DB_PREFIX . "hall_bookings hb
        JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
        WHERE hb.id = ?
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        return false;
    }
    
    // Get hall revenue account
    $stmt = $conn->prepare("
        SELECT id FROM " . DB_PREFIX . "accounts 
        WHERE account_name = 'Hall Rental Revenue' AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute();
    $revenueAccount = $stmt->get_result()->fetch_assoc();
    
    if (!$revenueAccount) {
        return false;
    }
    
    // Get accounts receivable account
    $stmt = $conn->prepare("
        SELECT id FROM " . DB_PREFIX . "accounts 
        WHERE account_name = 'Accounts Receivable' AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute();
    $arAccount = $stmt->get_result()->fetch_assoc();
    
    if (!$arAccount) {
        return false;
    }
    
    try {
        $conn->begin_transaction();
        
        // Generate journal entry number
        $entryNumber = generateJournalEntryNumber($conn);
        
        // Create journal entry
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "journal_entries 
            (entry_number, entry_date, description, reference_type, reference_id, created_by, created_at) 
            VALUES (?, ?, ?, 'hall_booking', ?, ?, NOW())
        ");
        
        $description = "Hall booking - " . $booking['hall_name'] . " (" . $booking['booking_number'] . ")";
        $userId = $_SESSION['user_id'] ?? 1;
        
        $stmt->bind_param('sssi', $entryNumber, $booking['start_date'], $description, $bookingId, $userId);
        $stmt->execute();
        $entryId = $conn->getConnection()->lastInsertId();
        
        // Create debit entry (Accounts Receivable)
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "journal_entry_lines 
            (entry_id, account_id, description, debit_amount, credit_amount, created_at) 
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        
        $debitDescription = "Hall booking receivable - " . $booking['booking_number'];
        $stmt->bind_param('iisd', $entryId, $arAccount['id'], $debitDescription, $booking['total_amount']);
        $stmt->execute();
        
        // Create credit entry (Hall Revenue)
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "journal_entry_lines 
            (entry_id, account_id, description, debit_amount, credit_amount, created_at) 
            VALUES (?, ?, ?, 0, ?, NOW())
        ");
        
        $creditDescription = "Hall rental revenue - " . $booking['booking_number'];
        $stmt->bind_param('iisd', $entryId, $revenueAccount['id'], $creditDescription, $booking['total_amount']);
        $stmt->execute();
        
        // Update account balances
        updateAccountBalance($arAccount['id'], $booking['total_amount'], 'debit');
        updateAccountBalance($revenueAccount['id'], $booking['total_amount'], 'credit');
        
        $conn->commit();
        return $entryId;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error creating hall booking journal entry: " . $e->getMessage());
        return false;
    }
}

/**
 * Create journal entry for hall booking payment
 */
function createHallPaymentJournalEntry($paymentId) {
    $conn = getDB();
    
    // Get payment details
    $stmt = $conn->prepare("
        SELECT p.*, hb.booking_number, h.hall_name
        FROM " . DB_PREFIX . "payments p
        JOIN " . DB_PREFIX . "hall_bookings hb ON p.reference_id = hb.id AND p.reference_type = 'hall_booking'
        JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
        WHERE p.id = ?
    ");
    $stmt->bind_param('i', $paymentId);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    
    if (!$payment) {
        return false;
    }
    
    // Get cash account
    $stmt = $conn->prepare("
        SELECT id FROM " . DB_PREFIX . "accounts 
        WHERE account_name = 'Cash' AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute();
    $cashAccount = $stmt->get_result()->fetch_assoc();
    
    if (!$cashAccount) {
        return false;
    }
    
    // Get accounts receivable account
    $stmt = $conn->prepare("
        SELECT id FROM " . DB_PREFIX . "accounts 
        WHERE account_name = 'Accounts Receivable' AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute();
    $arAccount = $stmt->get_result()->fetch_assoc();
    
    if (!$arAccount) {
        return false;
    }
    
    try {
        $conn->begin_transaction();
        
        // Generate journal entry number
        $entryNumber = generateJournalEntryNumber($conn);
        
        // Create journal entry
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "journal_entries 
            (entry_number, entry_date, description, reference_type, reference_id, created_by, created_at) 
            VALUES (?, ?, ?, 'hall_payment', ?, ?, NOW())
        ");
        
        $description = "Hall payment received - " . $payment['hall_name'] . " (" . $payment['booking_number'] . ")";
        $userId = $_SESSION['user_id'] ?? 1;
        
        $stmt->bind_param('sssi', $entryNumber, $payment['payment_date'], $description, $paymentId, $userId);
        $stmt->execute();
        $entryId = $conn->getConnection()->lastInsertId();
        
        // Create debit entry (Cash)
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "journal_entry_lines 
            (entry_id, account_id, description, debit_amount, credit_amount, created_at) 
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        
        $debitDescription = "Hall payment received - " . $payment['booking_number'];
        $stmt->bind_param('iisd', $entryId, $cashAccount['id'], $debitDescription, $payment['amount']);
        $stmt->execute();
        
        // Create credit entry (Accounts Receivable)
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "journal_entry_lines 
            (entry_id, account_id, description, debit_amount, credit_amount, created_at) 
            VALUES (?, ?, ?, 0, ?, NOW())
        ");
        
        $creditDescription = "Hall payment receivable - " . $payment['booking_number'];
        $stmt->bind_param('iisd', $entryId, $arAccount['id'], $creditDescription, $payment['amount']);
        $stmt->execute();
        
        // Update account balances
        updateAccountBalance($cashAccount['id'], $payment['amount'], 'debit');
        updateAccountBalance($arAccount['id'], $payment['amount'], 'credit');
        
        $conn->commit();
        return $entryId;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error creating hall payment journal entry: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate journal entry number
 */
function generateJournalEntryNumber($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM " . DB_PREFIX . "journal_entries WHERE entry_number LIKE ?");
    $pattern = 'JE' . date('Y') . '%';
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_row()[0];
    
    return 'JE' . date('Y') . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

/**
 * Update account balance
 */
function updateAccountBalance($accountId, $amount, $type) {
    $conn = getDB();
    
    if ($type === 'debit') {
        $stmt = $conn->prepare("
            UPDATE " . DB_PREFIX . "accounts 
            SET current_balance = current_balance + ? 
            WHERE id = ?
        ");
    } else {
        $stmt = $conn->prepare("
            UPDATE " . DB_PREFIX . "accounts 
            SET current_balance = current_balance - ? 
            WHERE id = ?
        ");
    }
    
    $stmt->bind_param('di', $amount, $accountId);
    return $stmt->execute();
}

/**
 * Create invoice for hall booking
 */
function createHallBookingInvoice($bookingId) {
    $conn = getDB();
    
    // Get booking details
    $stmt = $conn->prepare("
        SELECT hb.*, h.hall_name, h.location,
               c.first_name, c.last_name, c.company_name, c.email, c.phone, c.address
        FROM " . DB_PREFIX . "hall_bookings hb
        JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
        JOIN " . DB_PREFIX . "customers c ON hb.customer_id = c.id
        WHERE hb.id = ?
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        return false;
    }
    
    try {
        $conn->begin_transaction();
        
        // Generate invoice number
        $invoiceNumber = generateInvoiceNumber($conn);
        
        // Create invoice
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "invoices 
            (invoice_number, customer_id, invoice_date, due_date, subtotal, tax_amount, total_amount, 
             status, reference_type, reference_id, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', 'hall_booking', ?, ?, NOW())
        ");
        
        $invoiceDate = date('Y-m-d');
        $dueDate = date('Y-m-d', strtotime('+30 days'));
        $userId = $_SESSION['user_id'] ?? 1;
        
        $stmt->bind_param('sisssddii', $invoiceNumber, $booking['customer_id'], $invoiceDate, $dueDate, 
                         $booking['subtotal'], $booking['tax_amount'], $booking['total_amount'], 
                         $bookingId, $userId);
        $stmt->execute();
        $invoiceId = $conn->getConnection()->lastInsertId();
        
        // Create invoice line item
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "invoice_items 
            (invoice_id, description, quantity, unit_price, line_total, created_at) 
            VALUES (?, ?, 1, ?, ?, NOW())
        ");
        
        $description = "Hall Rental - " . $booking['hall_name'] . " (" . $booking['booking_number'] . ")";
        $stmt->bind_param('isdd', $invoiceId, $description, $booking['subtotal'], $booking['subtotal']);
        $stmt->execute();
        
        // Update booking with invoice reference
        $stmt = $conn->prepare("
            UPDATE " . DB_PREFIX . "hall_bookings 
            SET invoice_id = ? 
            WHERE id = ?
        ");
        $stmt->bind_param('ii', $invoiceId, $bookingId);
        $stmt->execute();
        
        $conn->commit();
        return $invoiceId;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error creating hall booking invoice: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate invoice number
 */
function generateInvoiceNumber($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM " . DB_PREFIX . "invoices WHERE invoice_number LIKE ?");
    $pattern = 'INV' . date('Y') . '%';
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_row()[0];
    
    return 'INV' . date('Y') . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

/**
 * Record hall booking payment
 */
function recordHallBookingPayment($bookingId, $amount, $paymentMethod, $paymentDate = null) {
    $conn = getDB();
    
    if (!$paymentDate) {
        $paymentDate = date('Y-m-d');
    }
    
    try {
        $conn->begin_transaction();
        
        // Generate payment number
        $paymentNumber = generatePaymentNumber($conn);
        
        // Create payment record
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "payments 
            (payment_number, customer_id, payment_date, amount, payment_method, status, 
             reference_type, reference_id, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, 'Completed', 'hall_booking', ?, ?, NOW())
        ");
        
        // Get customer ID from booking
        $stmt2 = $conn->prepare("SELECT customer_id FROM " . DB_PREFIX . "hall_bookings WHERE id = ?");
        $stmt2->bind_param('i', $bookingId);
        $stmt2->execute();
        $customerId = $stmt2->get_result()->fetch_row()[0];
        
        $userId = $_SESSION['user_id'] ?? 1;
        $stmt->bind_param('sisdsii', $paymentNumber, $customerId, $paymentDate, $amount, $paymentMethod, $bookingId, $userId);
        $stmt->execute();
        $paymentId = $conn->getConnection()->lastInsertId();
        
        // Update booking payment status
        $stmt = $conn->prepare("
            UPDATE " . DB_PREFIX . "hall_bookings 
            SET amount_paid = amount_paid + ?, 
                balance_due = total_amount - (amount_paid + ?),
                payment_status = CASE 
                    WHEN (amount_paid + ?) >= total_amount THEN 'Paid'
                    ELSE 'Partial'
                END
            WHERE id = ?
        ");
        $stmt->bind_param('dddi', $amount, $amount, $amount, $bookingId);
        $stmt->execute();
        
        // Create journal entry if auto-create is enabled
        if (getHallSetting('auto_create_journal_entry', 1)) {
            createHallPaymentJournalEntry($paymentId);
        }
        
        $conn->commit();
        return $paymentId;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error recording hall booking payment: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate payment number
 */
function generatePaymentNumber($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM " . DB_PREFIX . "payments WHERE payment_number LIKE ?");
    $pattern = 'PAY' . date('Y') . '%';
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_row()[0];
    
    return 'PAY' . date('Y') . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

