<?php
/**
 * Business Management System - Payment List
 * Phase 3: Accounting System Module
 */

// Define system constant
define('BMS_SYSTEM', true);

// Start session
session_start();

// Include required files
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/csrf.php';
require_once '../../../includes/accounting-functions.php';

// Check authentication and permissions
requireLogin();
requirePermission('accounting.view');

// Get database connection
$conn = getDB();

// Get filter parameters
$status = $_GET['status'] ?? '';
$method = $_GET['method'] ?? '';
$customerId = (int)($_GET['customer_id'] ?? 0);
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

if (!empty($status)) {
    $whereConditions[] = 'p.status = ?';
    $params[] = $status;
    $paramTypes .= 's';
}

if (!empty($method)) {
    $whereConditions[] = 'p.payment_method = ?';
    $params[] = $method;
    $paramTypes .= 's';
}

if ($customerId > 0) {
    $whereConditions[] = 'p.customer_id = ?';
    $params[] = $customerId;
    $paramTypes .= 'i';
}

if (!empty($startDate)) {
    $whereConditions[] = 'p.payment_date >= ?';
    $params[] = $startDate;
    $paramTypes .= 's';
}

if (!empty($endDate)) {
    $whereConditions[] = 'p.payment_date <= ?';
    $params[] = $endDate;
    $paramTypes .= 's';
}

if (!empty($search)) {
    $whereConditions[] = '(p.payment_number LIKE ? OR p.reference_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.company_name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $paramTypes .= 'sssss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get payments
$query = "
    SELECT p.*, c.first_name, c.last_name, c.company_name, c.customer_type,
           i.invoice_number, u.first_name as created_by_first, u.last_name as created_by_last
    FROM " . DB_PREFIX . "payments p
    JOIN " . DB_PREFIX . "customers c ON p.customer_id = c.id
    LEFT JOIN " . DB_PREFIX . "invoices i ON p.invoice_id = i.id
    LEFT JOIN " . DB_PREFIX . "users u ON p.created_by = u.id
    {$whereClause}
    ORDER BY p.created_at DESC
    LIMIT 50
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get customers for filter dropdown
$customers = $conn->query("
    SELECT id, first_name, last_name, company_name, customer_type 
    FROM " . DB_PREFIX . "customers 
    WHERE is_active = 1 
    ORDER BY company_name, first_name, last_name
")->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totalPayments = 0;
$methodTotals = [];

foreach ($payments as $payment) {
    $totalPayments += $payment['amount'];
    $method = $payment['payment_method'];
    $methodTotals[$method] = ($methodTotals[$method] ?? 0) + $payment['amount'];
}

// Set page title
$pageTitle = 'Payments';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Payments</h1>
        <p>Track customer payments and manage receivables</p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('accounting.create')): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Record Payment
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($totalPayments); ?></h3>
                <p>Total Payments</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo count($payments); ?></h3>
                <p>Payment Records</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo count($methodTotals); ?></h3>
                <p>Payment Methods</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo count(array_filter($payments, function($p) { return $p['status'] == 'Completed'; })); ?></h3>
                <p>Completed</p>
            </div>
        </div>
    </div>
</div>

<!-- Payment Methods Breakdown -->
<?php if (!empty($methodTotals)): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3>Payments by Method</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($methodTotals as $method => $total): ?>
                    <div class="col-md-2">
                        <div class="method-summary">
                            <h4><?php echo htmlspecialchars($method); ?></h4>
                            <p><?php echo formatCurrency($total); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="form-inline">
            <div class="form-group">
                <label for="status">Status:</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="Completed" <?php echo $status == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="Pending" <?php echo $status == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Failed" <?php echo $status == 'Failed' ? 'selected' : ''; ?>>Failed</option>
                    <option value="Refunded" <?php echo $status == 'Refunded' ? 'selected' : ''; ?>>Refunded</option>
                </select>
            </div>
            <div class="form-group">
                <label for="method">Method:</label>
                <select name="method" id="method" class="form-control">
                    <option value="">All Methods</option>
                    <option value="Cash" <?php echo $method == 'Cash' ? 'selected' : ''; ?>>Cash</option>
                    <option value="Bank Transfer" <?php echo $method == 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                    <option value="Credit Card" <?php echo $method == 'Credit Card' ? 'selected' : ''; ?>>Credit Card</option>
                    <option value="Debit Card" <?php echo $method == 'Debit Card' ? 'selected' : ''; ?>>Debit Card</option>
                    <option value="Check" <?php echo $method == 'Check' ? 'selected' : ''; ?>>Check</option>
                    <option value="Mobile Money" <?php echo $method == 'Mobile Money' ? 'selected' : ''; ?>>Mobile Money</option>
                    <option value="Other" <?php echo $method == 'Other' ? 'selected' : ''; ?>>Other</option>
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
            <div class="form-group">
                <label for="search">Search:</label>
                <input type="text" name="search" id="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Payment #, reference, customer" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="index.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>
</div>

<!-- Payments Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Payment #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Invoice #</th>
                        <th class="text-right">Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted">No payments found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td>
                                <a href="view.php?id=<?php echo $payment['id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($payment['payment_number']); ?>
                                </a>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                            <td><?php echo getCustomerDisplayName($payment); ?></td>
                            <td>
                                <?php if ($payment['invoice_number']): ?>
                                    <a href="../invoices/view.php?id=<?php echo $payment['invoice_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($payment['invoice_number']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">General</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right"><?php echo formatCurrency($payment['amount']); ?></td>
                            <td>
                                <span class="badge badge-info"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($payment['reference_number'] ?: '-'); ?></td>
                            <td>
                                <span class="badge status-<?php echo strtolower($payment['status']); ?>">
                                    <?php echo $payment['status']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="view.php?id=<?php echo $payment['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="icon-eye"></i>
                                    </a>
                                    
                                    <a href="receipt.php?id=<?php echo $payment['id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary" title="Receipt">
                                        <i class="icon-receipt"></i>
                                    </a>
                                    
                                    <?php if (hasPermission('accounting.edit') && $payment['status'] == 'Pending'): ?>
                                    <a href="edit.php?id=<?php echo $payment['id']; ?>" 
                                       class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="icon-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('accounting.delete') && $payment['status'] == 'Pending'): ?>
                                    <button onclick="deletePayment(<?php echo $payment['id']; ?>)" 
                                            class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="icon-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function deletePayment(paymentId) {
    if (confirm('Are you sure you want to delete this payment? This action cannot be undone.')) {
        fetch('delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'payment_id=' + paymentId + '&csrf_token=' + document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}
</script>

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

.method-summary {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 10px;
}

.method-summary h4 {
    margin: 0 0 8px 0;
    font-size: 14px;
    color: #6c757d;
    text-transform: uppercase;
}

.method-summary p {
    margin: 0;
    font-size: 18px;
    font-weight: bold;
    color: #333;
}

.status-completed { background-color: #28a745; color: white; }
.status-pending { background-color: #ffc107; color: #333; }
.status-failed { background-color: #dc3545; color: white; }
.status-refunded { background-color: #6c757d; color: white; }

.badge-info {
    background-color: #17a2b8;
    color: white;
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
</style>

<?php include '../../includes/footer.php'; ?>
