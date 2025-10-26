<?php
/**
 * Business Management System - Payment Receipt
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
           i.invoice_number, u.first_name as created_by_first, u.last_name as created_by_last
    FROM " . DB_PREFIX . "payments p
    JOIN " . DB_PREFIX . "customers c ON p.customer_id = c.id
    LEFT JOIN " . DB_PREFIX . "invoices i ON p.invoice_id = i.id
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

// Get company settings
$companyName = getSetting('company_name', 'Business Management System');
$companyEmail = getSetting('company_email', 'admin@example.com');
$companyPhone = getSetting('company_phone', '');
$companyAddress = getSetting('company_address', '');
$companyLogo = getSetting('company_logo', '');

// Set page title
$pageTitle = 'Payment Receipt - ' . $payment['payment_number'];

include '../../../includes/header.php';
?>

<div class="receipt-document">
    <!-- Action Buttons -->
    <div class="receipt-actions no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="icon-print"></i> Print Receipt
        </button>
        <a href="view.php?id=<?php echo $paymentId; ?>" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Payment
        </a>
    </div>
    
    <!-- Receipt Header -->
    <div class="receipt-header">
        <?php if ($companyLogo): ?>
        <img src="../../../../uploads/logos/<?php echo htmlspecialchars($companyLogo); ?>" alt="Company Logo" class="company-logo">
        <?php endif; ?>
        <h2><?php echo htmlspecialchars($companyName); ?></h2>
        <h3>PAYMENT RECEIPT</h3>
        <p>Receipt #: <?php echo htmlspecialchars($payment['payment_number']); ?></p>
    </div>
    
    <!-- Receipt Body -->
    <div class="receipt-body">
        <table class="receipt-info">
            <tr>
                <td><strong>Date:</strong></td>
                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
            </tr>
            <tr>
                <td><strong>Received From:</strong></td>
                <td><?php echo getCustomerDisplayName($payment); ?></td>
            </tr>
            <tr>
                <td><strong>Amount:</strong></td>
                <td><strong><?php echo formatCurrency($payment['amount']); ?></strong></td>
            </tr>
            <tr>
                <td><strong>Payment Method:</strong></td>
                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
            </tr>
            <?php if ($payment['reference_number']): ?>
            <tr>
                <td><strong>Reference:</strong></td>
                <td><?php echo htmlspecialchars($payment['reference_number']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($payment['invoice_number']): ?>
            <tr>
                <td><strong>For Invoice:</strong></td>
                <td><?php echo htmlspecialchars($payment['invoice_number']); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td><strong>Status:</strong></td>
                <td>
                    <span class="badge status-<?php echo strtolower($payment['status']); ?>">
                        <?php echo $payment['status']; ?>
                    </span>
                </td>
            </tr>
        </table>
        
        <?php if ($payment['notes']): ?>
        <div class="receipt-notes">
            <strong>Notes:</strong>
            <p><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Customer Information -->
        <div class="customer-info">
            <h4>Customer Information:</h4>
            <p><strong><?php echo getCustomerDisplayName($payment); ?></strong></p>
            <?php if ($payment['email']): ?>
            <p><?php echo htmlspecialchars($payment['email']); ?></p>
            <?php endif; ?>
            <?php if ($payment['phone']): ?>
            <p><?php echo htmlspecialchars($payment['phone']); ?></p>
            <?php endif; ?>
            <?php if ($payment['address']): ?>
            <p><?php echo nl2br(htmlspecialchars($payment['address'])); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Receipt Footer -->
    <div class="receipt-footer">
        <p>Thank you for your payment!</p>
        <p>This is a computer-generated receipt</p>
        <p><small>Processed by: <?php echo htmlspecialchars($payment['created_by_first'] . ' ' . $payment['created_by_last']); ?> on <?php echo date('M d, Y H:i', strtotime($payment['created_at'])); ?></small></p>
    </div>
</div>

<style>
@media print {
    .no-print { display: none; }
    .receipt-document { 
        box-shadow: none; 
        padding: 0;
    }
}

.receipt-document {
    background: white;
    padding: 40px;
    max-width: 600px;
    margin: 20px auto;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

.receipt-header {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #333;
}

.company-logo {
    max-width: 150px;
    margin-bottom: 15px;
}

.receipt-header h2 {
    margin: 0 0 10px 0;
    font-size: 24px;
    color: #333;
}

.receipt-header h3 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: bold;
    color: #333;
}

.receipt-header p {
    margin: 0;
    font-size: 16px;
    color: #666;
}

.receipt-body {
    margin-bottom: 30px;
}

.receipt-info {
    width: 100%;
    margin-bottom: 20px;
}

.receipt-info td {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.receipt-info td:first-child {
    width: 40%;
    font-weight: 500;
}

.receipt-info td:last-child {
    width: 60%;
    text-align: right;
}

.status-completed { background-color: #28a745; color: white; }
.status-pending { background-color: #ffc107; color: #333; }
.status-failed { background-color: #dc3545; color: white; }
.status-refunded { background-color: #6c757d; color: white; }

.receipt-notes {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.receipt-notes strong {
    display: block;
    margin-bottom: 8px;
}

.customer-info {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.customer-info h4 {
    margin: 0 0 10px 0;
    font-size: 16px;
    color: #333;
}

.receipt-footer {
    text-align: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.receipt-footer p {
    margin: 5px 0;
}

.receipt-footer p:first-child {
    font-size: 18px;
    font-weight: bold;
    color: #28a745;
}

.receipt-actions {
    text-align: center;
    margin-bottom: 20px;
}

.receipt-actions .btn {
    margin: 0 10px;
}
</style>

<?php include '../../../includes/footer.php'; ?>
