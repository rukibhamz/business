<?php
/**
 * Business Management System - Accounting Helper Functions
 * Phase 3: Accounting System Module
 */

// Prevent direct access
if (!defined('BMS_SYSTEM')) {
    die('Direct access not allowed');
}

/**
 * Generate next invoice number
 * @return string Invoice number
 */
function generateInvoiceNumber() {
    global $conn;
    $prefix = getSetting('invoice_prefix', 'INV-');
    $year = date('Y');
    
    // Get last invoice number for current year
    $stmt = $conn->prepare("
        SELECT invoice_number 
        FROM " . DB_PREFIX . "invoices 
        WHERE invoice_number LIKE ? 
        ORDER BY id DESC LIMIT 1
    ");
    $pattern = $prefix . $year . '%';
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        // Extract number and increment
        $lastNumber = (int)substr($result['invoice_number'], -4);
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '0001';
    }
    
    return $prefix . $year . '-' . $newNumber;
}

/**
 * Generate next payment number
 * @return string Payment number
 */
function generatePaymentNumber() {
    global $conn;
    $prefix = getSetting('payment_prefix', 'PAY-');
    $year = date('Y');
    
    $stmt = $conn->prepare("
        SELECT payment_number 
        FROM " . DB_PREFIX . "payments 
        WHERE payment_number LIKE ? 
        ORDER BY id DESC LIMIT 1
    ");
    $pattern = $prefix . $year . '%';
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        $lastNumber = (int)substr($result['payment_number'], -4);
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '0001';
    }
    
    return $prefix . $year . '-' . $newNumber;
}

/**
 * Generate next expense number
 * @return string Expense number
 */
function generateExpenseNumber() {
    global $conn;
    $prefix = getSetting('expense_prefix', 'EXP-');
    $year = date('Y');
    
    $stmt = $conn->prepare("
        SELECT expense_number 
        FROM " . DB_PREFIX . "expenses 
        WHERE expense_number LIKE ? 
        ORDER BY id DESC LIMIT 1
    ");
    $pattern = $prefix . $year . '%';
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        $lastNumber = (int)substr($result['expense_number'], -4);
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '0001';
    }
    
    return $prefix . $year . '-' . $newNumber;
}

/**
 * Generate next journal entry number
 * @return string Journal number
 */
function generateJournalNumber() {
    global $conn;
    $prefix = 'JE-';
    $year = date('Y');
    
    $stmt = $conn->prepare("
        SELECT journal_number 
        FROM " . DB_PREFIX . "journal_entries 
        WHERE journal_number LIKE ? 
        ORDER BY id DESC LIMIT 1
    ");
    $pattern = $prefix . $year . '%';
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        $lastNumber = (int)substr($result['journal_number'], -4);
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '0001';
    }
    
    return $prefix . $year . '-' . $newNumber;
}

/**
 * Create journal entry (double-entry bookkeeping)
 * @param string $date Entry date
 * @param string $description Entry description
 * @param array $lines Array of lines [['account_id', 'debit', 'credit', 'description']]
 * @param string $type Entry type
 * @param int $referenceId Reference ID
 * @param string $referenceType Reference type
 * @return int|false Journal entry ID or false
 */
function createJournalEntry($date, $description, $lines, $type = 'Manual', $referenceId = null, $referenceType = null) {
    global $conn;
    
    // Validate debits = credits
    $totalDebit = 0;
    $totalCredit = 0;
    foreach ($lines as $line) {
        $totalDebit += $line['debit'];
        $totalCredit += $line['credit'];
    }
    
    if (round($totalDebit, 2) != round($totalCredit, 2)) {
        return false; // Debits must equal credits
    }
    
    $conn->begin_transaction();
    
    try {
        // Insert journal entry
        $journalNumber = generateJournalNumber();
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "journal_entries 
            (journal_number, entry_date, entry_type, reference_id, reference_type, 
             description, total_debit, total_credit, posted_by, posted_date, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $userId = $_SESSION['user_id'];
        $stmt->bind_param('sssississi', 
            $journalNumber, $date, $type, $referenceId, $referenceType, 
            $description, $totalDebit, $totalCredit, $userId, $userId
        );
        $stmt->execute();
        $journalId = $conn->getConnection()->lastInsertId();
        
        // Insert journal lines
        $lineOrder = 0;
        foreach ($lines as $line) {
            $stmt = $conn->prepare("
                INSERT INTO " . DB_PREFIX . "journal_entry_lines 
                (journal_entry_id, line_order, account_id, description, debit_amount, credit_amount) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('iiisdd', 
                $journalId, $lineOrder, $line['account_id'], 
                $line['description'], $line['debit'], $line['credit']
            );
            $stmt->execute();
            
            // Update account balance
            updateAccountBalance($line['account_id'], $line['debit'], $line['credit']);
            
            $lineOrder++;
        }
        
        $conn->commit();
        return $journalId;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Update account balance
 * @param int $accountId Account ID
 * @param float $debit Debit amount
 * @param float $credit Credit amount
 */
function updateAccountBalance($accountId, $debit, $credit) {
    global $conn;
    
    // Get account type
    $stmt = $conn->prepare("
        SELECT account_type, current_balance 
        FROM " . DB_PREFIX . "accounts 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    
    $currentBalance = $account['current_balance'];
    $accountType = $account['account_type'];
    
    // Calculate new balance based on account type
    // Assets and Expenses increase with debits, decrease with credits
    // Liabilities, Equity, and Income increase with credits, decrease with debits
    if (in_array($accountType, ['Asset', 'Expense'])) {
        $newBalance = $currentBalance + $debit - $credit;
    } else {
        $newBalance = $currentBalance + $credit - $debit;
    }
    
    // Update account balance
    $stmt = $conn->prepare("
        UPDATE " . DB_PREFIX . "accounts 
        SET current_balance = ? 
        WHERE id = ?
    ");
    $stmt->bind_param('di', $newBalance, $accountId);
    $stmt->execute();
}

/**
 * Calculate invoice totals
 * @param array $items Invoice items
 * @param array $discount Discount info ['type', 'value']
 * @return array Calculated totals
 */
function calculateInvoiceTotals($items, $discount = []) {
    $subtotal = 0;
    $totalTax = 0;
    
    foreach ($items as $item) {
        $lineTotal = $item['quantity'] * $item['unit_price'];
        $subtotal += $lineTotal;
        
        if (isset($item['tax_rate']) && $item['tax_rate'] > 0) {
            $taxAmount = ($lineTotal * $item['tax_rate']) / 100;
            $totalTax += $taxAmount;
        }
    }
    
    // Calculate discount
    $discountAmount = 0;
    if (!empty($discount)) {
        if ($discount['type'] == 'percentage') {
            $discountAmount = ($subtotal * $discount['value']) / 100;
        } else {
            $discountAmount = $discount['value'];
        }
    }
    
    $total = $subtotal - $discountAmount + $totalTax;
    
    return [
        'subtotal' => round($subtotal, 2),
        'discount_amount' => round($discountAmount, 2),
        'tax_amount' => round($totalTax, 2),
        'total' => round($total, 2)
    ];
}

/**
 * Update invoice status based on payments
 * @param int $invoiceId Invoice ID
 */
function updateInvoiceStatus($invoiceId) {
    global $conn;
    
    // Get invoice total and payments
    $stmt = $conn->prepare("
        SELECT total_amount, amount_paid, balance_due, due_date 
        FROM " . DB_PREFIX . "invoices 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    
    $status = 'Sent';
    
    if ($invoice['balance_due'] <= 0) {
        $status = 'Paid';
    } elseif ($invoice['amount_paid'] > 0) {
        $status = 'Partial';
    } elseif (strtotime($invoice['due_date']) < time() && $invoice['balance_due'] > 0) {
        $status = 'Overdue';
    }
    
    // Update invoice status
    $stmt = $conn->prepare("
        UPDATE " . DB_PREFIX . "invoices 
        SET status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->bind_param('si', $status, $invoiceId);
    $stmt->execute();
}

/**
 * Format currency
 * @param float $amount Amount
 * @param string $currency Currency code
 * @return string Formatted amount
 */
function formatCurrency($amount, $currency = null) {
    if ($currency === null) {
        $currency = getSetting('currency', 'NGN');
    }
    
    $symbol = getSetting('currency_symbol', '₦');
    return $symbol . number_format($amount, 2);
}

/**
 * Get customer display name
 * @param array $customer Customer data
 * @return string Display name
 */
function getCustomerDisplayName($customer) {
    if ($customer['customer_type'] == 'Company') {
        return $customer['company_name'];
    }
    return $customer['first_name'] . ' ' . $customer['last_name'];
}

/**
 * Get customer name by ID
 * @param int $customerId Customer ID
 * @return string Customer name
 */
function getCustomerName($customerId) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT customer_type, first_name, last_name, company_name 
        FROM " . DB_PREFIX . "customers 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    
    if (!$customer) {
        return 'Unknown Customer';
    }
    
    return getCustomerDisplayName($customer);
}

/**
 * Get account balance
 * @param int $accountId Account ID
 * @return float Balance
 */
function getAccountBalance($accountId) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT current_balance 
        FROM " . DB_PREFIX . "accounts 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['current_balance'] ?? 0;
}

/**
 * Get total revenue for period
 * @param string $startDate Start date
 * @param string $endDate End date
 * @return float Total revenue
 */
function getTotalRevenue($startDate, $endDate) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT SUM(credit_amount) as total 
        FROM " . DB_PREFIX . "journal_entry_lines jel
        JOIN " . DB_PREFIX . "journal_entries je ON jel.journal_entry_id = je.id
        JOIN " . DB_PREFIX . "accounts a ON jel.account_id = a.id
        WHERE a.account_type = 'Income' 
        AND je.entry_date BETWEEN ? AND ?
        AND je.status = 'Posted'
    ");
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total'] ?? 0;
}

/**
 * Get total expenses for period
 * @param string $startDate Start date
 * @param string $endDate End date
 * @return float Total expenses
 */
function getTotalExpenses($startDate, $endDate) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT SUM(debit_amount) as total 
        FROM " . DB_PREFIX . "journal_entry_lines jel
        JOIN " . DB_PREFIX . "journal_entries je ON jel.journal_entry_id = je.id
        JOIN " . DB_PREFIX . "accounts a ON jel.account_id = a.id
        WHERE a.account_type = 'Expense' 
        AND je.entry_date BETWEEN ? AND ?
        AND je.status = 'Posted'
    ");
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total'] ?? 0;
}

/**
 * Generate customer code
 * @return string Customer code
 */
function generateCustomerCode() {
    global $conn;
    $prefix = getSetting('customer_prefix', 'CUST-');
    
    $stmt = $conn->prepare("
        SELECT customer_code 
        FROM " . DB_PREFIX . "customers 
        WHERE customer_code LIKE ? 
        ORDER BY id DESC LIMIT 1
    ");
    $pattern = $prefix . '%';
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        $lastNumber = (int)substr($result['customer_code'], -4);
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '0001';
    }
    
    return $prefix . $newNumber;
}

/**
 * Validate email address
 * @param string $email Email address
 * @return bool Valid or not
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Sanitize input
 * @param string $input Input string
 * @return string Sanitized string
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Require CSRF token
 * @param string $token Token to validate
 */
function requireCSRFToken($token) {
    if (!validateCSRFToken($token)) {
        die('Invalid CSRF token');
    }
}
?>
