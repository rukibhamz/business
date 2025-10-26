<?php
/**
 * Business Management System - View Payment
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

// Get payment ID
$paymentId = (int)($_GET['id'] ?? 0);

if (!$paymentId) {
    header('Location: index.php');
    exit;
}

// Get payment details
$stmt = $conn->prepare("
    SELECT p.*, c.first_name, c.last_name, c.company_name, c.customer_type, c.email, c.phone, c.address,
           i.invoice_number, i.total_amount as invoice_total, i.balance_due as invoice_balance,
           a.account_name as bank_account_name, a.account_code as bank_account_code,
           u.first_name as created_by_first, u.last_name as created_by_last
    FROM " . DB_PREFIX . "payments p
    JOIN " . DB_PREFIX . "customers c ON p.customer_id = c.id
    LEFT JOIN " . DB_PREFIX . "invoices i ON p.invoice_id = i.id
    LEFT JOIN " . DB_PREFIX . "accounts a ON p.bank_account_id = a.id
    LEFT JOIN " . DB_PREFIX . "users u ON p.created_by = u.id
    WHERE p.id = ?
");
$stmt->bind_param('i', $paymentId);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    header('Location: index.php');
    exit;
}

// Set page title
$pageTitle = 'Payment - ' . $payment['payment_number'];

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Payment #<?php echo htmlspecialchars($payment['payment_number']); ?></h1>
        <p><?php echo getCustomerDisplayName($payment); ?></p>
    </div>
    <div class="page-actions">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="icon-print"></i> Print Receipt
        </button>
        <a href="receipt.php?id=<?php echo $paymentId; ?>" class="btn btn-secondary">
            <i class="icon-receipt"></i> View Receipt
        </a>
        <?php if ($payment['status'] == 'Pending' && hasPermission('accounting.edit')): ?>
        <a href="edit.php?id=<?php echo $paymentId; ?>" class="btn btn-warning">
            <i class="icon-edit"></i> Edit
        </a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Payments
        </a>
    </div>
</div>

<!-- Payment Details -->
<div class="card">
    <div class="card-header">
        <h3>Payment Information</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Payment Number:</strong></td>
                        <td><?php echo htmlspecialchars($payment['payment_number']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Payment Date:</strong></td>
                        <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Amount:</strong></td>
                        <td class="amount"><?php echo formatCurrency($payment['amount']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Payment Method:</strong></td>
                        <td>
                            <span class="badge badge-info"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td>
                            <span class="badge status-<?php echo strtolower($payment['status']); ?>">
                                <?php echo $payment['status']; ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Customer:</strong></td>
                        <td><?php echo getCustomerDisplayName($payment); ?></td>
                    </tr>
                    <?php if ($payment['invoice_number']): ?>
                    <tr>
                        <td><strong>Invoice:</strong></td>
                        <td>
                            <a href="../invoices/view.php?id=<?php echo $payment['invoice_id']; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($payment['invoice_number']); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($payment['reference_number']): ?>
                    <tr>
                        <td><strong>Reference:</strong></td>
                        <td><?php echo htmlspecialchars($payment['reference_number']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($payment['bank_account_name']): ?>
                    <tr>
                        <td><strong>Bank Account:</strong></td>
                        <td><?php echo htmlspecialchars($payment['bank_account_code'] . ' - ' . $payment['bank_account_name']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong>Created By:</strong></td>
                        <td><?php echo htmlspecialchars($payment['created_by_first'] . ' ' . $payment['created_by_last']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Created Date:</strong></td>
                        <td><?php echo date('M d, Y H:i', strtotime($payment['created_at'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php if ($payment['notes']): ?>
        <div class="mt-3">
            <strong>Notes:</strong>
            <p><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Customer Information -->
<div class="card">
    <div class="card-header">
        <h3>Customer Information</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h4><?php echo getCustomerDisplayName($payment); ?></h4>
                <?php if ($payment['email']): ?>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($payment['email']); ?></p>
                <?php endif; ?>
                <?php if ($payment['phone']): ?>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($payment['phone']); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <?php if ($payment['address']): ?>
                <p><strong>Address:</strong></p>
                <p><?php echo nl2br(htmlspecialchars($payment['address'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Invoice Information (if applicable) -->
<?php if ($payment['invoice_number']): ?>
<div class="card">
    <div class="card-header">
        <h3>Related Invoice</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Invoice Number:</strong> 
                    <a href="../invoices/view.php?id=<?php echo $payment['invoice_id']; ?>" class="text-decoration-none">
                        <?php echo htmlspecialchars($payment['invoice_number']); ?>
                    </a>
                </p>
                <p><strong>Invoice Total:</strong> <?php echo formatCurrency($payment['invoice_total']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Payment Amount:</strong> <?php echo formatCurrency($payment['amount']); ?></p>
                <p><strong>Remaining Balance:</strong> <?php echo formatCurrency($payment['invoice_balance']); ?></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Journal Entry Information -->
<div class="card">
    <div class="card-header">
        <h3>Accounting Entry</h3>
    </div>
    <div class="card-body">
        <p>This payment has been automatically recorded in the accounting system:</p>
        <div class="journal-entry-preview">
            <div class="entry-line">
                <span class="account"><?php echo $payment['bank_account_name'] ?: ($payment['payment_method'] == 'Cash' ? 'Cash Account' : 'Bank Account'); ?></span>
                <span class="debit"><?php echo formatCurrency($payment['amount']); ?></span>
                <span class="credit">-</span>
            </div>
            <div class="entry-line">
                <span class="account">Accounts Receivable</span>
                <span class="debit">-</span>
                <span class="credit"><?php echo formatCurrency($payment['amount']); ?></span>
            </div>
        </div>
    </div>
</div>

<style>
.amount {
    font-size: 18px;
    font-weight: bold;
    color: #28a745;
}

.status-completed { background-color: #28a745; color: white; }
.status-pending { background-color: #ffc107; color: #333; }
.status-failed { background-color: #dc3545; color: white; }
.status-refunded { background-color: #6c757d; color: white; }

.badge-info {
    background-color: #17a2b8;
    color: white;
}

.journal-entry-preview {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-top: 10px;
}

.entry-line {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #dee2e6;
}

.entry-line:last-child {
    border-bottom: none;
}

.entry-line .account {
    flex: 1;
    font-weight: 500;
}

.entry-line .debit,
.entry-line .credit {
    width: 100px;
    text-align: right;
    font-weight: bold;
}

.entry-line .debit {
    color: #dc3545;
}

.entry-line .credit {
    color: #28a745;
}

@media print {
    .page-actions { display: none; }
    .card { box-shadow: none; border: 1px solid #ddd; }
}
</style>

<?php include '../../../includes/footer.php'; ?>
