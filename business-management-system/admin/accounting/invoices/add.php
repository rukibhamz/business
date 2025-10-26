<?php
/**
 * Business Management System - Create Invoice
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
    $invoiceNumber = generateInvoiceNumber();
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $invoiceDate = sanitizeInput($_POST['invoice_date'] ?? '');
    $dueDate = sanitizeInput($_POST['due_date'] ?? '');
    $paymentTerms = sanitizeInput($_POST['payment_terms'] ?? 'Net 30');
    $reference = sanitizeInput($_POST['reference'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    $termsConditions = sanitizeInput($_POST['terms_conditions'] ?? '');
    $discountType = sanitizeInput($_POST['discount_type'] ?? 'percentage');
    $discountValue = (float)($_POST['discount_value'] ?? 0);
    
    // Get items data
    $items = [];
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $item) {
            if (!empty($item['name']) && $item['quantity'] > 0 && $item['unit_price'] >= 0) {
                $items[] = [
                    'name' => sanitizeInput($item['name']),
                    'description' => sanitizeInput($item['description'] ?? ''),
                    'quantity' => (float)$item['quantity'],
                    'unit_price' => (float)$item['unit_price'],
                    'tax_rate' => (float)($item['tax_rate'] ?? 0)
                ];
            }
        }
    }
    
    // Validation
    if ($customerId <= 0) {
        $errors[] = 'Please select a customer';
    }
    
    if (empty($invoiceDate)) {
        $errors[] = 'Invoice date is required';
    }
    
    if (empty($dueDate)) {
        $errors[] = 'Due date is required';
    }
    
    if (empty($items)) {
        $errors[] = 'At least one invoice item is required';
    }
    
    if ($discountValue < 0) {
        $errors[] = 'Discount value cannot be negative';
    }
    
    // If no errors, create invoice
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Calculate totals
            $totals = calculateInvoiceTotals($items, [
                'type' => $discountType,
                'value' => $discountValue
            ]);
            
            // Insert invoice
            $stmt = $conn->prepare("
                INSERT INTO " . DB_PREFIX . "invoices 
                (invoice_number, customer_id, invoice_date, due_date, payment_terms, 
                 reference, notes, terms_conditions, subtotal, discount_type, discount_value, 
                 discount_amount, tax_amount, total_amount, balance_due, status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $status = ($_POST['action'] == 'save_send') ? 'Sent' : 'Draft';
            $balanceDue = $totals['total'];
            $userId = $_SESSION['user_id'];
            
            $stmt->bind_param('sissssssddsddddsi',
                $invoiceNumber, $customerId, $invoiceDate, $dueDate, $paymentTerms,
                $reference, $notes, $termsConditions, $totals['subtotal'],
                $discountType, $discountValue, $totals['discount_amount'],
                $totals['tax_amount'], $totals['total'], $balanceDue, $status, $userId
            );
            $stmt->execute();
            $invoiceId = $conn->getConnection()->lastInsertId();
            
            // Insert invoice items
            $itemOrder = 0;
            foreach ($items as $item) {
                $quantity = $item['quantity'];
                $unitPrice = $item['unit_price'];
                $taxRate = $item['tax_rate'];
                $lineSubtotal = $quantity * $unitPrice;
                $lineTax = ($lineSubtotal * $taxRate) / 100;
                $lineTotal = $lineSubtotal + $lineTax;
                
                $stmt = $conn->prepare("
                    INSERT INTO " . DB_PREFIX . "invoice_items 
                    (invoice_id, item_order, item_name, description, quantity, 
                     unit_price, tax_rate, tax_amount, line_total) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('iissddddd',
                    $invoiceId, $itemOrder, $item['name'], $item['description'],
                    $quantity, $unitPrice, $taxRate, $lineTax, $lineTotal
                );
                $stmt->execute();
                $itemOrder++;
            }
            
            // Create journal entry if sent
            if ($status == 'Sent') {
                $lines = [
                    [
                        'account_id' => 1100, // Accounts Receivable
                        'debit' => $totals['total'],
                        'credit' => 0,
                        'description' => 'Invoice ' . $invoiceNumber
                    ],
                    [
                        'account_id' => 4010, // Revenue account
                        'debit' => 0,
                        'credit' => $totals['subtotal'] - $totals['discount_amount'],
                        'description' => 'Revenue from invoice ' . $invoiceNumber
                    ]
                ];
                
                // Add tax liability entry if tax > 0
                if ($totals['tax_amount'] > 0) {
                    $lines[] = [
                        'account_id' => 2210, // Tax Payable
                        'debit' => 0,
                        'credit' => $totals['tax_amount'],
                        'description' => 'Tax on invoice ' . $invoiceNumber
                    ];
                }
                
                createJournalEntry(
                    $invoiceDate,
                    'Invoice ' . $invoiceNumber . ' - ' . getCustomerName($customerId),
                    $lines,
                    'Invoice',
                    $invoiceId,
                    'invoice'
                );
            }
            
            // Update customer balance
            $stmt = $conn->prepare("
                UPDATE " . DB_PREFIX . "customers 
                SET outstanding_balance = outstanding_balance + ? 
                WHERE id = ?
            ");
            $stmt->bind_param('di', $totals['total'], $customerId);
            $stmt->execute();
            
            // Log activity
            logActivity('accounting.create', "Invoice created: {$invoiceNumber}", [
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'customer_id' => $customerId,
                'total_amount' => $totals['total']
            ]);
            
            $conn->commit();
            $success = true;
            $_SESSION['success'] = 'Invoice created successfully!';
            
            header('Location: view.php?id=' . $invoiceId);
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error creating invoice: ' . $e->getMessage();
        }
    }
}

// Get customers for dropdown
$customers = $conn->query("
    SELECT id, first_name, last_name, company_name, customer_type 
    FROM " . DB_PREFIX . "customers 
    WHERE is_active = 1 
    ORDER BY company_name, first_name, last_name
")->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = 'Create Invoice';

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Create Invoice</h1>
        <p>Create a new invoice for a customer</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Invoices
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

<form method="POST" id="invoice-form">
    <?php csrfField(); ?>
    
    <!-- Header Section -->
    <div class="card">
        <div class="card-header">
            <h3>Invoice Header</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Invoice Number</label>
                        <input type="text" name="invoice_number" value="<?php echo generateInvoiceNumber(); ?>" readonly class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="required">Customer</label>
                        <select name="customer_id" id="customer_id" required class="form-control">
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" 
                                    <?php echo ($customerId ?? 0) == $customer['id'] ? 'selected' : ''; ?>>
                                <?php echo getCustomerDisplayName($customer); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">
                            <a href="../customers/add.php" target="_blank">+ Add New Customer</a>
                        </small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="required">Invoice Date</label>
                        <input type="date" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="required">Due Date</label>
                        <input type="date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Payment Terms</label>
                        <select name="payment_terms" class="form-control">
                            <option value="Due on Receipt">Due on Receipt</option>
                            <option value="Net 15">Net 15</option>
                            <option value="Net 30" selected>Net 30</option>
                            <option value="Net 60">Net 60</option>
                            <option value="Net 90">Net 90</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Invoice Items Section -->
    <div class="card">
        <div class="card-header">
            <h3>Invoice Items</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table" id="items-table">
                    <thead>
                        <tr>
                            <th style="width: 30%">Item Name *</th>
                            <th style="width: 25%">Description</th>
                            <th style="width: 10%">Quantity *</th>
                            <th style="width: 12%">Unit Price *</th>
                            <th style="width: 10%">Tax Rate (%)</th>
                            <th style="width: 10%">Total</th>
                            <th style="width: 3%"></th>
                        </tr>
                    </thead>
                    <tbody id="items-body">
                        <tr class="item-row">
                            <td><input type="text" name="items[0][name]" class="form-control" required></td>
                            <td><input type="text" name="items[0][description]" class="form-control"></td>
                            <td><input type="number" name="items[0][quantity]" class="form-control quantity" value="1" step="0.01" required></td>
                            <td><input type="number" name="items[0][unit_price]" class="form-control unit-price" value="0" step="0.01" required></td>
                            <td>
                                <select name="items[0][tax_rate]" class="form-control tax-rate">
                                    <option value="0">None</option>
                                    <option value="7.5">VAT (7.5%)</option>
                                    <option value="2">AMAC (2%)</option>
                                </select>
                            </td>
                            <td class="line-total">₦0.00</td>
                            <td><button type="button" class="btn btn-sm btn-danger btn-remove-item">×</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-secondary" id="add-item-row">+ Add Item</button>
        </div>
    </div>
    
    <!-- Totals Section -->
    <div class="card">
        <div class="card-header">
            <h3>Invoice Details</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Reference</label>
                        <input type="text" name="reference" class="form-control" placeholder="PO Number, Project Code, etc.">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes for the customer"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Terms & Conditions</label>
                        <textarea name="terms_conditions" class="form-control" rows="3" placeholder="Payment terms and conditions"></textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="invoice-totals">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span id="subtotal-display">₦0.00</span>
                        </div>
                        <div class="total-row">
                            <span>Discount:</span>
                            <div class="discount-controls">
                                <select name="discount_type" id="discount-type" class="form-control">
                                    <option value="percentage">%</option>
                                    <option value="fixed">Fixed</option>
                                </select>
                                <input type="number" name="discount_value" id="discount-value" value="0" step="0.01" class="form-control">
                                <span id="discount-display">₦0.00</span>
                            </div>
                        </div>
                        <div class="total-row">
                            <span>Tax:</span>
                            <span id="tax-display">₦0.00</span>
                        </div>
                        <div class="total-row grand-total">
                            <span><strong>Total:</strong></span>
                            <span id="total-display"><strong>₦0.00</strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Hidden fields for calculated values -->
    <input type="hidden" name="subtotal" id="subtotal">
    <input type="hidden" name="discount_amount" id="discount-amount">
    <input type="hidden" name="tax_amount" id="tax-amount">
    <input type="hidden" name="total_amount" id="total-amount">
    
    <!-- Submit Buttons -->
    <div class="form-actions">
        <button type="submit" name="action" value="save_draft" class="btn btn-secondary">
            <i class="icon-save"></i> Save as Draft
        </button>
        <button type="submit" name="action" value="save_send" class="btn btn-primary">
            <i class="icon-send"></i> Save & Send
        </button>
        <a href="index.php" class="btn btn-link">Cancel</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let itemCount = 1;
    
    // Add new item row
    document.getElementById('add-item-row').addEventListener('click', function() {
        const tbody = document.getElementById('items-body');
        const newRow = document.querySelector('.item-row').cloneNode(true);
        
        // Update name attributes
        newRow.querySelectorAll('input, select').forEach(function(input) {
            const name = input.getAttribute('name');
            if (name) {
                input.setAttribute('name', name.replace(/\[\d+\]/, '[' + itemCount + ']'));
                input.value = input.type === 'number' ? (input.classList.contains('quantity') ? 1 : 0) : '';
            }
        });
        
        tbody.appendChild(newRow);
        itemCount++;
        attachItemEventListeners(newRow);
    });
    
    // Remove item row
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-remove-item')) {
            if (document.querySelectorAll('.item-row').length > 1) {
                e.target.closest('.item-row').remove();
                calculateTotals();
            }
        }
    });
    
    // Attach event listeners to item rows
    function attachItemEventListeners(row) {
        row.querySelectorAll('.quantity, .unit-price, .tax-rate').forEach(function(input) {
            input.addEventListener('input', function() {
                updateLineTotal(row);
                calculateTotals();
            });
        });
    }
    
    // Update line total
    function updateLineTotal(row) {
        const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
        const unitPrice = parseFloat(row.querySelector('.unit-price').value) || 0;
        const taxRate = parseFloat(row.querySelector('.tax-rate').value) || 0;
        
        const lineSubtotal = quantity * unitPrice;
        const lineTax = (lineSubtotal * taxRate) / 100;
        const lineTotal = lineSubtotal + lineTax;
        
        row.querySelector('.line-total').textContent = formatCurrency(lineTotal);
    }
    
    // Calculate invoice totals
    function calculateTotals() {
        let subtotal = 0;
        let totalTax = 0;
        
        document.querySelectorAll('.item-row').forEach(function(row) {
            const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
            const unitPrice = parseFloat(row.querySelector('.unit-price').value) || 0;
            const taxRate = parseFloat(row.querySelector('.tax-rate').value) || 0;
            
            const lineSubtotal = quantity * unitPrice;
            const lineTax = (lineSubtotal * taxRate) / 100;
            
            subtotal += lineSubtotal;
            totalTax += lineTax;
        });
        
        // Calculate discount
        const discountType = document.getElementById('discount-type').value;
        const discountValue = parseFloat(document.getElementById('discount-value').value) || 0;
        let discountAmount = 0;
        
        if (discountType === 'percentage') {
            discountAmount = (subtotal * discountValue) / 100;
        } else {
            discountAmount = discountValue;
        }
        
        const total = subtotal - discountAmount + totalTax;
        
        // Update displays
        document.getElementById('subtotal-display').textContent = formatCurrency(subtotal);
        document.getElementById('discount-display').textContent = formatCurrency(discountAmount);
        document.getElementById('tax-display').textContent = formatCurrency(totalTax);
        document.getElementById('total-display').textContent = formatCurrency(total);
        
        // Update hidden fields
        document.getElementById('subtotal').value = subtotal.toFixed(2);
        document.getElementById('discount-amount').value = discountAmount.toFixed(2);
        document.getElementById('tax-amount').value = totalTax.toFixed(2);
        document.getElementById('total-amount').value = total.toFixed(2);
    }
    
    // Format currency
    function formatCurrency(amount) {
        return '₦' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
    
    // Discount input listener
    document.getElementById('discount-type').addEventListener('change', calculateTotals);
    document.getElementById('discount-value').addEventListener('input', calculateTotals);
    
    // Initialize listeners for existing rows
    document.querySelectorAll('.item-row').forEach(attachItemEventListeners);
});
</script>

<style>
.invoice-totals {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
}

.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #dee2e6;
}

.total-row:last-child {
    border-bottom: none;
}

.grand-total {
    font-size: 18px;
    font-weight: bold;
    border-top: 2px solid #333;
    margin-top: 10px;
    padding-top: 15px;
}

.discount-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.discount-controls select,
.discount-controls input {
    width: 80px;
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

.btn-remove-item {
    border: none;
    background: #dc3545;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    font-size: 16px;
    line-height: 1;
}

.btn-remove-item:hover {
    background: #c82333;
}

.required::after {
    content: " *";
    color: #dc3545;
}
</style>

<?php include '../../../includes/footer.php'; ?>
