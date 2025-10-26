<?php
/**
 * Business Management System - Invoice Report
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
requirePermission('accounting.reports');

// Get database connection
$conn = getDB();

// Get filter parameters
$status = $_GET['status'] ?? '';
$customerId = (int)($_GET['customer_id'] ?? 0);
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$export = $_GET['export'] ?? '';

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

if (!empty($status)) {
    $whereConditions[] = 'i.status = ?';
    $params[] = $status;
    $paramTypes .= 's';
}

if ($customerId > 0) {
    $whereConditions[] = 'i.customer_id = ?';
    $params[] = $customerId;
    $paramTypes .= 'i';
}

if (!empty($startDate)) {
    $whereConditions[] = 'i.invoice_date >= ?';
    $params[] = $startDate;
    $paramTypes .= 's';
}

if (!empty($endDate)) {
    $whereConditions[] = 'i.invoice_date <= ?';
    $params[] = $endDate;
    $paramTypes .= 's';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get invoices
$query = "
    SELECT i.*, c.first_name, c.last_name, c.company_name, c.customer_type,
           u.first_name as created_by_first, u.last_name as created_by_last
    FROM " . DB_PREFIX . "invoices i
    JOIN " . DB_PREFIX . "customers c ON i.customer_id = c.id
    LEFT JOIN " . DB_PREFIX . "users u ON i.created_by = u.id
    {$whereClause}
    ORDER BY i.invoice_date DESC, i.created_at DESC
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get customers for filter dropdown
$customers = $conn->query("
    SELECT id, first_name, last_name, company_name, customer_type 
    FROM " . DB_PREFIX . "customers 
    WHERE is_active = 1 
    ORDER BY company_name, first_name, last_name
")->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totalInvoices = 0;
$totalAmount = 0;
$totalPaid = 0;
$totalOutstanding = 0;
$statusTotals = [];

foreach ($invoices as $invoice) {
    $totalInvoices++;
    $totalAmount += $invoice['total_amount'];
    $totalPaid += $invoice['amount_paid'];
    $totalOutstanding += $invoice['balance_due'];
    
    $status = $invoice['status'];
    if (!isset($statusTotals[$status])) {
        $statusTotals[$status] = ['count' => 0, 'amount' => 0];
    }
    $statusTotals[$status]['count']++;
    $statusTotals[$status]['amount'] += $invoice['total_amount'];
}

// Handle export
if ($export == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="invoice_report_' . $startDate . '_' . $endDate . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Invoice #', 'Date', 'Customer', 'Status', 'Total Amount', 'Amount Paid', 'Balance Due', 'Due Date']);
    
    foreach ($invoices as $invoice) {
        fputcsv($output, [
            $invoice['invoice_number'],
            $invoice['invoice_date'],
            getCustomerDisplayName($invoice),
            $invoice['status'],
            $invoice['total_amount'],
            $invoice['amount_paid'],
            $invoice['balance_due'],
            $invoice['due_date']
        ]);
    }
    
    fputcsv($output, ['', '', 'TOTAL', '', $totalAmount, $totalPaid, $totalOutstanding, '']);
    fclose($output);
    exit;
}

// Set page title
$pageTitle = 'Invoice Report';

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Invoice Report</h1>
        <p>Period: <?php echo date('M d, Y', strtotime($startDate)); ?> to <?php echo date('M d, Y', strtotime($endDate)); ?></p>
    </div>
    <div class="page-actions">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="icon-print"></i> Print
        </button>
        <a href="?status=<?php echo $status; ?>&customer_id=<?php echo $customerId; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&export=csv" class="btn btn-secondary">
            <i class="icon-download"></i> Export CSV
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Reports
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="form-inline">
            <div class="form-group">
                <label for="status">Status:</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="Draft" <?php echo $status == 'Draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="Sent" <?php echo $status == 'Sent' ? 'selected' : ''; ?>>Sent</option>
                    <option value="Partial" <?php echo $status == 'Partial' ? 'selected' : ''; ?>>Partial</option>
                    <option value="Paid" <?php echo $status == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="Overdue" <?php echo $status == 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                </select>
            </div>
            <div class="form-group">
                <label for="customer_id">Customer:</label>
                <select name="customer_id" id="customer_id" class="form-control">
                    <option value="">All Customers</option>
                    <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo $customer['id']; ?>" 
                            <?php echo $customerId == $customer['id'] ? 'selected' : ''; ?>>
                        <?php echo getCustomerDisplayName($customer); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="start_date">From:</label>
                <input type="date" name="start_date" id="start_date" 
                       value="<?php echo $startDate; ?>" class="form-control">
            </div>
            <div class="form-group">
                <label for="end_date">To:</label>
                <input type="date" name="end_date" id="end_date" 
                       value="<?php echo $endDate; ?>" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Update Report</button>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo $totalInvoices; ?></h3>
                <p>Total Invoices</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($totalAmount); ?></h3>
                <p>Total Amount</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($totalPaid); ?></h3>
                <p>Amount Paid</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($totalOutstanding); ?></h3>
                <p>Outstanding</p>
            </div>
        </div>
    </div>
</div>

<!-- Status Summary -->
<?php if (!empty($statusTotals)): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3>Summary by Status</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($statusTotals as $status => $totals): ?>
                    <div class="col-md-2">
                        <div class="status-summary">
                            <h4><?php echo htmlspecialchars($status); ?></h4>
                            <p><?php echo $totals['count']; ?> invoices</p>
                            <p><?php echo formatCurrency($totals['amount']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Invoice Report Table -->
<div class="card">
    <div class="card-header">
        <h3>Invoice Report</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th class="text-right">Total Amount</th>
                        <th class="text-right">Amount Paid</th>
                        <th class="text-right">Balance Due</th>
                        <th>Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted">No invoices found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td>
                                <a href="../invoices/view.php?id=<?php echo $invoice['id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                </a>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($invoice['invoice_date'])); ?></td>
                            <td><?php echo getCustomerDisplayName($invoice); ?></td>
                            <td>
                                <span class="badge status-<?php echo strtolower($invoice['status']); ?>">
                                    <?php echo $invoice['status']; ?>
                                </span>
                            </td>
                            <td class="text-right"><?php echo formatCurrency($invoice['total_amount']); ?></td>
                            <td class="text-right"><?php echo formatCurrency($invoice['amount_paid']); ?></td>
                            <td class="text-right"><?php echo formatCurrency($invoice['balance_due']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="../invoices/view.php?id=<?php echo $invoice['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="icon-eye"></i>
                                    </a>
                                    <a href="../invoices/pdf.php?id=<?php echo $invoice['id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary" title="PDF">
                                        <i class="icon-pdf"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="4"><strong>TOTAL</strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($totalAmount); ?></strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($totalPaid); ?></strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($totalOutstanding); ?></strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<style>
.summary-card {
    text-align: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
}

.summary-card h3 {
    margin: 0;
    font-size: 24px;
    font-weight: bold;
}

.summary-card p {
    margin: 5px 0 0 0;
    opacity: 0.9;
}

.status-summary {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 10px;
}

.status-summary h4 {
    margin: 0 0 8px 0;
    font-size: 14px;
    color: #6c757d;
    text-transform: uppercase;
}

.status-summary p {
    margin: 4px 0;
    font-size: 16px;
    font-weight: bold;
    color: #333;
}

.status-draft { background-color: #6c757d; color: white; }
.status-sent { background-color: #17a2b8; color: white; }
.status-partial { background-color: #ffc107; color: #333; }
.status-paid { background-color: #28a745; color: white; }
.status-overdue { background-color: #dc3545; color: white; }

.total-row {
    background-color: #e9ecef;
    font-weight: bold;
    border-top: 2px solid #333;
}

.form-group {
    margin-right: 15px;
    margin-bottom: 10px;
}

.form-group label {
    margin-right: 5px;
    font-weight: 500;
}

.btn-group .btn {
    margin-right: 2px;
}

.btn-group .btn:last-child {
    margin-right: 0;
}

@media print {
    .page-actions { display: none; }
    .card { box-shadow: none; border: 1px solid #ddd; }
}
</style>

<?php include '../../../includes/footer.php'; ?>
