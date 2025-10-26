<?php
/**
 * Business Management System - Invoice List
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
$customerId = (int)($_GET['customer_id'] ?? 0);
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$search = $_GET['search'] ?? '';

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

if (!empty($search)) {
    $whereConditions[] = '(i.invoice_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.company_name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $paramTypes .= 'ssss';
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
    ORDER BY i.created_at DESC
    LIMIT 50
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
$totalInvoiced = 0;
$totalPaid = 0;
$totalOutstanding = 0;

foreach ($invoices as $invoice) {
    $totalInvoiced += $invoice['total_amount'];
    $totalPaid += $invoice['amount_paid'];
    $totalOutstanding += $invoice['balance_due'];
}

// Set page title
$pageTitle = 'Invoices';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Invoices</h1>
        <p>Manage customer invoices and track payments</p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('accounting.create')): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Create Invoice
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($totalInvoiced); ?></h3>
                <p>Total Invoiced</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($totalPaid); ?></h3>
                <p>Total Paid</p>
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
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo count($invoices); ?></h3>
                <p>Total Invoices</p>
            </div>
        </div>
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
                    <option value="Cancelled" <?php echo $status == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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
                       placeholder="Invoice # or customer" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="index.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>
</div>

<!-- Invoices Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Due Date</th>
                        <th class="text-right">Amount</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Balance</th>
                        <th>Status</th>
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
                                <a href="view.php?id=<?php echo $invoice['id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                </a>
                            </td>
                            <td><?php echo getCustomerDisplayName($invoice); ?></td>
                            <td><?php echo date('M d, Y', strtotime($invoice['invoice_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></td>
                            <td class="text-right"><?php echo formatCurrency($invoice['total_amount']); ?></td>
                            <td class="text-right"><?php echo formatCurrency($invoice['amount_paid']); ?></td>
                            <td class="text-right">
                                <span class="<?php echo $invoice['balance_due'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo formatCurrency($invoice['balance_due']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge status-<?php echo strtolower($invoice['status']); ?>">
                                    <?php echo $invoice['status']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="view.php?id=<?php echo $invoice['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="icon-eye"></i>
                                    </a>
                                    
                                    <?php if (hasPermission('accounting.edit') && $invoice['status'] == 'Draft'): ?>
                                    <a href="edit.php?id=<?php echo $invoice['id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary" title="Edit">
                                        <i class="icon-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('accounting.create') && $invoice['balance_due'] > 0): ?>
                                    <a href="../payments/add.php?invoice_id=<?php echo $invoice['id']; ?>" 
                                       class="btn btn-sm btn-outline-success" title="Record Payment">
                                        <i class="icon-payment"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('accounting.delete') && $invoice['status'] == 'Draft'): ?>
                                    <button onclick="deleteInvoice(<?php echo $invoice['id']; ?>)" 
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
function deleteInvoice(invoiceId) {
    if (confirm('Are you sure you want to delete this invoice? This action cannot be undone.')) {
        fetch('delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'invoice_id=' + invoiceId + '&csrf_token=' + document.querySelector('meta[name="csrf-token"]').getAttribute('content')
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

.status-draft { background-color: #6c757d; color: white; }
.status-sent { background-color: #17a2b8; color: white; }
.status-partial { background-color: #ffc107; color: #333; }
.status-paid { background-color: #28a745; color: white; }
.status-overdue { background-color: #dc3545; color: white; }
.status-cancelled { background-color: #343a40; color: white; }

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
