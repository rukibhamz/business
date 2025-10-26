<?php
/**
 * Business Management System - Record Payment
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
requirePermission('accounting.create');

// Get database connection
$conn = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken($_POST['csrf_token'] ?? '');
    
    $errors = [];
    $success = false;
    
    // Get form data
    $paymentNumber = generatePaymentNumber();
    $paymentDate = sanitizeInput($_POST['payment_date'] ?? '');
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $invoiceId = !empty($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : null;
    $amount = (float)($_POST['amount'] ?? 0);
    $paymentMethod = sanitizeInput($_POST['payment_method'] ?? '');
    $referenceNumber = sanitizeInput($_POST['reference_number'] ?? '');
    $bankAccountId = !empty($_POST['bank_account_id']) ? (int)$_POST['bank_account_id'] : null;
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    // Validation
    if ($customerId <= 0) {
        $errors[] = 'Please select a customer';
    }
    
    if (empty($paymentDate)) {
        $errors[] = 'Payment date is required';
    }
    
    if ($amount <= 0) {
        $errors[] = 'Payment amount must be greater than zero';
    }
    
    if (empty($paymentMethod)) {
        $errors[] = 'Payment method is required';
    }
    
    // If no errors, create payment
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Insert payment
            $stmt = $conn->prepare("
                INSERT INTO " . DB_PREFIX . "payments 
                (payment_number, payment_date, customer_id, invoice_id, amount, 
                 payment_method, reference_number, bank_account_id, notes, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $userId = $_SESSION['user_id'];
            $stmt->bind_param('ssiidssssi',
                $paymentNumber, $paymentDate, $customerId, $invoiceId, $amount,
                $paymentMethod, $referenceNumber, $bankAccountId, $notes, $userId
            );
            $stmt->execute();
            $paymentId = $conn->getConnection()->lastInsertId();
            
            // Update invoice if specified
            if ($invoiceId) {
                $stmt = $conn->prepare("
                    UPDATE " . DB_PREFIX . "invoices 
                    SET amount_paid = amount_paid + ?,
                        balance_due = balance_due - ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param('ddi', $amount, $amount, $invoiceId);
                $stmt->execute();
                
                // Update invoice status
                updateInvoiceStatus($invoiceId);
            }
            
            // Update customer balance
            $stmt = $conn->prepare("
                UPDATE " . DB_PREFIX . "customers 
                SET outstanding_balance = outstanding_balance - ? 
                WHERE id = ?
            ");
            $stmt->bind_param('di', $amount, $customerId);
            $stmt->execute();
            
            // Create journal entry
            $lines = [];
            
            // Debit: Bank/Cash account
            if ($bankAccountId) {
                $accountId = $bankAccountId;
            } else {
                // Use default cash account based on payment method
                $accountId = ($paymentMethod == 'Cash') ? 1010 : 1020; // Cash or Bank Account
            }
            
            $lines[] = [
                'account_id' => $accountId,
                'debit' => $amount,
                'credit' => 0,
                'description' => 'Payment received - ' . $paymentNumber
            ];
            
            // Credit: Accounts Receivable
            $lines[] = [
                'account_id' => 1100, // Accounts Receivable
                'debit' => 0,
                'credit' => $amount,
                'description' => 'Payment received from customer'
            ];
            
            createJournalEntry(
                $paymentDate,
                'Payment ' . $paymentNumber . ' from ' . getCustomerName($customerId),
                $lines,
                'Payment',
                $paymentId,
                'payment'
            );
            
            // Log activity
            logActivity('accounting.create', "Payment recorded: {$paymentNumber}", [
                'payment_id' => $paymentId,
                'payment_number' => $paymentNumber,
                'customer_id' => $customerId,
                'amount' => $amount,
                'invoice_id' => $invoiceId
            ]);
            
            $conn->commit();
            $success = true;
            $_SESSION['success'] = 'Payment recorded successfully!';
            
            header('Location: view.php?id=' . $paymentId);
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error recording payment: ' . $e->getMessage();
        }
    }
}

// Get customers for dropdown
$customers = $conn->query("
    SELECT id, first_name, last_name, company_name, customer_type, outstanding_balance
    FROM " . DB_PREFIX . "customers 
    WHERE is_active = 1 
    ORDER BY company_name, first_name, last_name
")->fetch_all(MYSQLI_ASSOC);

// Get bank accounts for dropdown
$bankAccounts = $conn->query("
    SELECT id, account_code, account_name 
    FROM " . DB_PREFIX . "accounts 
    WHERE account_type = 'Asset' 
    AND account_subtype IN ('Current Asset', 'Non-Current Asset')
    AND is_active = 1
    ORDER BY account_code
")->fetch_all(MYSQLI_ASSOC);

// Get invoice ID from URL if specified
$preSelectedInvoiceId = (int)($_GET['invoice_id'] ?? 0);

// Set page title
$pageTitle = 'Record Payment';

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Record Payment</h1>
        <p>Record a customer payment</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Payments
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <h4>Please correct the following errors:</h4>
    <ul>
        <?php foreach ($errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Payment Information</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="payment-form">
                    <?php csrfField(); ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payment Number</label>
                                <input type="text" name="payment_number" value="<?php echo generatePaymentNumber(); ?>" readonly class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Payment Date</label>
                                <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Customer</label>
                                <select name="customer_id" id="customer_id" required class="form-control">
                                    <option value="">Select Customer</option>
                                    <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" 
                                            <?php echo ($customerId ?? 0) == $customer['id'] ? 'selected' : ''; ?>>
                                        <?php echo getCustomerDisplayName($customer); ?>
                                        (Balance: <?php echo formatCurrency($customer['outstanding_balance']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" id="invoice-group" style="display:none;">
                                <label>Invoice (Optional)</label>
                                <select name="invoice_id" id="invoice_id" class="form-control">
                                    <option value="">Select Invoice</option>
                                </select>
                                <small class="form-text text-muted">Leave blank for general payment</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required">Amount</label>
                                <input type="number" name="amount" id="amount" step="0.01" min="0" required class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Payment Method</label>
                                <select name="payment_method" required class="form-control">
                                    <option value="">Select Method</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Credit Card">Credit Card</option>
                                    <option value="Debit Card">Debit Card</option>
                                    <option value="Check">Check</option>
                                    <option value="Mobile Money">Mobile Money</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Bank Account</label>
                                <select name="bank_account_id" class="form-control">
                                    <option value="">Select Bank Account</option>
                                    <?php foreach ($bankAccounts as $account): ?>
                                    <option value="<?php echo $account['id']; ?>">
                                        <?php echo $account['account_code'] . ' - ' . $account['account_name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Reference Number</label>
                                <input type="text" name="reference_number" placeholder="Check #, Transaction ID, etc." class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="3" class="form-control" placeholder="Additional notes about this payment"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="icon-plus"></i> Record Payment
                        </button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>Payment Methods Guide</h3>
            </div>
            <div class="card-body">
                <div class="payment-methods-guide">
                    <div class="method-item">
                        <h4>Cash</h4>
                        <p>Physical cash payments</p>
                    </div>
                    
                    <div class="method-item">
                        <h4>Bank Transfer</h4>
                        <p>Direct bank transfers or wire transfers</p>
                    </div>
                    
                    <div class="method-item">
                        <h4>Credit/Debit Card</h4>
                        <p>Card payments processed through payment gateways</p>
                    </div>
                    
                    <div class="method-item">
                        <h4>Check</h4>
                        <p>Physical or electronic check payments</p>
                    </div>
                    
                    <div class="method-item">
                        <h4>Mobile Money</h4>
                        <p>Mobile payment services (MTN, Airtel, etc.)</p>
                    </div>
                    
                    <div class="method-item">
                        <h4>Other</h4>
                        <p>Any other payment method not listed above</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Quick Tips</h3>
            </div>
            <div class="card-body">
                <ul class="tips-list">
                    <li>Always verify payment amounts before recording</li>
                    <li>Include reference numbers for traceability</li>
                    <li>Select the appropriate bank account for deposits</li>
                    <li>Use general payments for advance payments</li>
                    <li>Link payments to specific invoices when possible</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Load customer invoices when customer selected
document.getElementById('customer_id').addEventListener('change', function() {
    const customerId = this.value;
    const invoiceGroup = document.getElementById('invoice-group');
    const invoiceSelect = document.getElementById('invoice_id');
    
    if (customerId) {
        invoiceGroup.style.display = 'block';
        
        // Fetch customer invoices via AJAX
        fetch('get-customer-invoices.php?customer_id=' + customerId)
            .then(response => response.json())
            .then(invoices => {
                invoiceSelect.innerHTML = '<option value="">Select Invoice</option>';
                invoices.forEach(invoice => {
                    const option = document.createElement('option');
                    option.value = invoice.id;
                    option.textContent = invoice.invoice_number + ' - Balance: ' + invoice.balance_due;
                    option.dataset.balance = invoice.balance_due;
                    invoiceSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error loading invoices:', error);
                invoiceSelect.innerHTML = '<option value="">Error loading invoices</option>';
            });
    } else {
        invoiceGroup.style.display = 'none';
    }
});

// Auto-fill amount when invoice selected
document.getElementById('invoice_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    if (selectedOption.dataset.balance) {
        document.getElementById('amount').value = selectedOption.dataset.balance;
    }
});

// Pre-select invoice if specified in URL
<?php if ($preSelectedInvoiceId): ?>
document.addEventListener('DOMContentLoaded', function() {
    // This would need to be implemented to pre-select the invoice
    // For now, we'll just show a note
    console.log('Pre-selected invoice ID: <?php echo $preSelectedInvoiceId; ?>');
});
<?php endif; ?>
</script>

<style>
.payment-methods-guide .method-item {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.payment-methods-guide .method-item:last-child {
    border-bottom: none;
}

.payment-methods-guide h4 {
    margin: 0 0 5px 0;
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.payment-methods-guide p {
    margin: 0;
    font-size: 12px;
    color: #6c757d;
}

.tips-list {
    margin: 0;
    padding-left: 20px;
}

.tips-list li {
    margin-bottom: 8px;
    font-size: 14px;
    color: #6c757d;
}

.required::after {
    content: " *";
    color: #dc3545;
}

.form-actions {
    text-align: center;
    padding: 20px 0;
    border-top: 1px solid #dee2e6;
    margin-top: 20px;
}

.form-actions .btn {
    margin: 0 10px;
}
</style>

<?php include '../../../includes/footer.php'; ?>
