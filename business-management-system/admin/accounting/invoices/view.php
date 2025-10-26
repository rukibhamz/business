<?php
/**
 * Business Management System - View Invoice
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
requirePermission('accounting.view');

// Get database connection
$conn = getDB();

// Get invoice ID
$invoiceId = (int)($_GET['id'] ?? 0);

if (!$invoiceId) {
    header('Location: index.php');
    exit;
}

// Get invoice details
$stmt = $conn->prepare("
    SELECT i.*, c.first_name, c.last_name, c.company_name, c.customer_type, c.email, c.phone, c.address,
           u.first_name as created_by_first, u.last_name as created_by_last
    FROM " . DB_PREFIX . "invoices i
    JOIN " . DB_PREFIX . "customers c ON i.customer_id = c.id
    LEFT JOIN " . DB_PREFIX . "users u ON i.created_by = u.id
    WHERE i.id = ?
");
$stmt->bind_param('i', $invoiceId);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    header('Location: index.php');
    exit;
}

// Get invoice items
$stmt = $conn->prepare("
    SELECT * FROM " . DB_PREFIX . "invoice_items 
    WHERE invoice_id = ? 
    ORDER BY item_order
");
$stmt->bind_param('i', $invoiceId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get company settings
$companyName = getSetting('company_name', 'Business Management System');
$companyEmail = getSetting('company_email', 'admin@example.com');
$companyPhone = getSetting('company_phone', '');
$companyAddress = getSetting('company_address', '');
$companyLogo = getSetting('company_logo', '');

// Set page title
$pageTitle = 'Invoice - ' . $invoice['invoice_number'];

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?></h1>
        <p><?php echo getCustomerDisplayName($invoice); ?></p>
    </div>
    <div class="page-actions no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="icon-print"></i> Print
        </button>
        <a href="pdf.php?id=<?php echo $invoiceId; ?>" class="btn btn-secondary">
            <i class="icon-pdf"></i> Download PDF
        </a>
        <?php if ($invoice['status'] != 'Paid' && hasPermission('accounting.create')): ?>
        <a href="../payments/add.php?invoice_id=<?php echo $invoiceId; ?>" class="btn btn-success">
            <i class="icon-payment"></i> Record Payment
        </a>
        <?php endif; ?>
        <?php if ($invoice['status'] == 'Draft' && hasPermission('accounting.edit')): ?>
        <a href="edit.php?id=<?php echo $invoiceId; ?>" class="btn btn-warning">
            <i class="icon-edit"></i> Edit
        </a>
        <?php endif; ?>
        <button onclick="sendInvoice(<?php echo $invoiceId; ?>)" class="btn btn-info">
            <i class="icon-email"></i> Send Email
        </button>
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Invoices
        </a>
    </div>
</div>

<!-- Invoice Document -->
<div class="invoice-document">
    <!-- Header -->
    <div class="invoice-header">
        <div class="company-info">
            <?php if ($companyLogo): ?>
            <img src="../../../../uploads/logos/<?php echo htmlspecialchars($companyLogo); ?>" alt="Company Logo" class="company-logo">
            <?php endif; ?>
            <h2><?php echo htmlspecialchars($companyName); ?></h2>
            <p><?php echo nl2br(htmlspecialchars($companyAddress)); ?></p>
            <p><?php echo htmlspecialchars($companyEmail); ?></p>
            <?php if ($companyPhone): ?>
            <p><?php echo htmlspecialchars($companyPhone); ?></p>
            <?php endif; ?>
        </div>
        <div class="invoice-title">
            <h1>INVOICE</h1>
            <p><strong>#<?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></p>
            <p class="status-badge status-<?php echo strtolower($invoice['status']); ?>">
                <?php echo $invoice['status']; ?>
            </p>
        </div>
    </div>
    
    <!-- Bill To / Invoice Info -->
    <div class="invoice-info">
        <div class="bill-to">
            <h4>Bill To:</h4>
            <p><strong><?php echo getCustomerDisplayName($invoice); ?></strong></p>
            <?php if ($invoice['email']): ?>
            <p><?php echo htmlspecialchars($invoice['email']); ?></p>
            <?php endif; ?>
            <?php if ($invoice['phone']): ?>
            <p><?php echo htmlspecialchars($invoice['phone']); ?></p>
            <?php endif; ?>
            <?php if ($invoice['address']): ?>
            <p><?php echo nl2br(htmlspecialchars($invoice['address'])); ?></p>
            <?php endif; ?>
        </div>
        <div class="invoice-details">
            <table>
                <tr>
                    <td><strong>Invoice Date:</strong></td>
                    <td><?php echo date('M d, Y', strtotime($invoice['invoice_date'])); ?></td>
                </tr>
                <tr>
                    <td><strong>Due Date:</strong></td>
                    <td><?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></td>
                </tr>
                <tr>
                    <td><strong>Payment Terms:</strong></td>
                    <td><?php echo htmlspecialchars($invoice['payment_terms']); ?></td>
                </tr>
                <?php if ($invoice['reference']): ?>
                <tr>
                    <td><strong>Reference:</strong></td>
                    <td><?php echo htmlspecialchars($invoice['reference']); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
    
    <!-- Invoice Items -->
    <table class="invoice-items-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $index => $item): ?>
            <tr>
                <td><?php echo $index + 1; ?></td>
                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                <td><?php echo htmlspecialchars($item['description']); ?></td>
                <td class="text-right"><?php echo number_format($item['quantity'], 2); ?></td>
                <td class="text-right"><?php echo formatCurrency($item['unit_price']); ?></td>
                <td class="text-right">
                    <?php if ($item['tax_rate'] > 0): ?>
                        <?php echo $item['tax_rate']; ?>%
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td class="text-right"><?php echo formatCurrency($item['line_total']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Totals -->
    <div class="invoice-totals">
        <table>
            <tr>
                <td>Subtotal:</td>
                <td><?php echo formatCurrency($invoice['subtotal']); ?></td>
            </tr>
            <?php if ($invoice['discount_amount'] > 0): ?>
            <tr>
                <td>Discount (<?php echo $invoice['discount_type'] == 'percentage' ? $invoice['discount_value'] . '%' : 'Fixed'; ?>):</td>
                <td>-<?php echo formatCurrency($invoice['discount_amount']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($invoice['tax_amount'] > 0): ?>
            <tr>
                <td>Tax:</td>
                <td><?php echo formatCurrency($invoice['tax_amount']); ?></td>
            </tr>
            <?php endif; ?>
            <tr class="total-row">
                <td><strong>Total:</strong></td>
                <td><strong><?php echo formatCurrency($invoice['total_amount']); ?></strong></td>
            </tr>
            <?php if ($invoice['amount_paid'] > 0): ?>
            <tr>
                <td>Amount Paid:</td>
                <td><?php echo formatCurrency($invoice['amount_paid']); ?></td>
            </tr>
            <tr class="balance-row">
                <td><strong>Balance Due:</strong></td>
                <td><strong><?php echo formatCurrency($invoice['balance_due']); ?></strong></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <!-- Notes and Terms -->
    <?php if ($invoice['notes']): ?>
    <div class="invoice-notes">
        <h4>Notes:</h4>
        <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if ($invoice['terms_conditions']): ?>
    <div class="invoice-terms">
        <h4>Terms & Conditions:</h4>
        <p><?php echo nl2br(htmlspecialchars($invoice['terms_conditions'])); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Footer -->
    <div class="invoice-footer">
        <p>Thank you for your business!</p>
        <p><small>Created by: <?php echo htmlspecialchars($invoice['created_by_first'] . ' ' . $invoice['created_by_last']); ?> on <?php echo date('M d, Y H:i', strtotime($invoice['created_at'])); ?></small></p>
    </div>
</div>

<script>
function sendInvoice(invoiceId) {
    if (confirm('Send this invoice via email to the customer?')) {
        // TODO: Implement email sending functionality
        alert('Email functionality will be implemented in the next phase.');
    }
}
</script>

<style>
@media print {
    .no-print { display: none; }
    .invoice-document { 
        box-shadow: none; 
        padding: 0;
    }
}

.invoice-document {
    background: white;
    padding: 40px;
    max-width: 900px;
    margin: 20px auto;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

.invoice-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #333;
}

.company-logo {
    max-width: 150px;
    margin-bottom: 10px;
}

.invoice-title h1 {
    font-size: 36px;
    margin: 0;
}

.status-badge {
    display: inline-block;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-paid { background: #28a745; color: white; }
.status-partial { background: #ffc107; color: #333; }
.status-sent { background: #17a2b8; color: white; }
.status-overdue { background: #dc3545; color: white; }
.status-draft { background: #6c757d; color: white; }

.invoice-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
}

.invoice-items-table {
    width: 100%;
    margin-bottom: 20px;
    border-collapse: collapse;
}

.invoice-items-table th {
    background: #f8f9fa;
    padding: 10px;
    text-align: left;
    border-bottom: 2px solid #dee2e6;
}

.invoice-items-table td {
    padding: 10px;
    border-bottom: 1px solid #dee2e6;
}

.invoice-totals {
    margin-left: auto;
    width: 300px;
}

.invoice-totals table {
    width: 100%;
}

.invoice-totals td {
    padding: 8px;
}

.invoice-totals .total-row td {
    font-size: 18px;
    padding-top: 15px;
    border-top: 2px solid #333;
}

.invoice-totals .balance-row td {
    color: #dc3545;
    font-size: 16px;
}

.invoice-notes,
.invoice-terms {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
}

.invoice-footer {
    margin-top: 40px;
    text-align: center;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
}
</style>

<?php include '../../../includes/footer.php'; ?>
