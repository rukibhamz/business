<?php
/**
 * Business Management System - Expense List
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
$categoryId = (int)($_GET['category_id'] ?? 0);
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

if (!empty($status)) {
    $whereConditions[] = 'e.status = ?';
    $params[] = $status;
    $paramTypes .= 's';
}

if ($categoryId > 0) {
    $whereConditions[] = 'e.category_id = ?';
    $params[] = $categoryId;
    $paramTypes .= 'i';
}

if (!empty($startDate)) {
    $whereConditions[] = 'e.expense_date >= ?';
    $params[] = $startDate;
    $paramTypes .= 's';
}

if (!empty($endDate)) {
    $whereConditions[] = 'e.expense_date <= ?';
    $params[] = $endDate;
    $paramTypes .= 's';
}

if (!empty($search)) {
    $whereConditions[] = '(e.expense_number LIKE ? OR e.vendor_name LIKE ? OR e.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $paramTypes .= 'sss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get expenses
$query = "
    SELECT e.*, ec.category_name, a.account_name, a.account_code,
           c.first_name, c.last_name, c.company_name, c.customer_type,
           u.first_name as created_by_first, u.last_name as created_by_last,
           approver.first_name as approved_by_first, approver.last_name as approved_by_last
    FROM " . DB_PREFIX . "expenses e
    JOIN " . DB_PREFIX . "expense_categories ec ON e.category_id = ec.id
    LEFT JOIN " . DB_PREFIX . "accounts a ON e.account_id = a.id
    LEFT JOIN " . DB_PREFIX . "customers c ON e.customer_id = c.id
    LEFT JOIN " . DB_PREFIX . "users u ON e.created_by = u.id
    LEFT JOIN " . DB_PREFIX . "users approver ON e.approved_by = approver.id
    {$whereClause}
    ORDER BY e.created_at DESC
    LIMIT 50
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get expense categories for filter dropdown
$categories = $conn->query("
    SELECT id, category_name 
    FROM " . DB_PREFIX . "expense_categories 
    WHERE is_active = 1 
    ORDER BY category_name
")->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totalExpenses = 0;
$categoryTotals = [];
$statusTotals = [];

foreach ($expenses as $expense) {
    $totalExpenses += $expense['amount'];
    $category = $expense['category_name'];
    $status = $expense['status'];
    
    $categoryTotals[$category] = ($categoryTotals[$category] ?? 0) + $expense['amount'];
    $statusTotals[$status] = ($statusTotals[$status] ?? 0) + $expense['amount'];
}

// Set page title
$pageTitle = 'Expenses';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Expenses</h1>
        <p>Track business expenses and manage approvals</p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('accounting.create')): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Add Expense
        </a>
        <a href="categories.php" class="btn btn-secondary">
            <i class="icon-settings"></i> Manage Categories
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($totalExpenses); ?></h3>
                <p>Total Expenses</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo count($expenses); ?></h3>
                <p>Expense Records</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo count(array_filter($expenses, function($e) { return $e['status'] == 'Approved'; })); ?></h3>
                <p>Approved</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo count(array_filter($expenses, function($e) { return $e['status'] == 'Pending'; })); ?></h3>
                <p>Pending Approval</p>
            </div>
        </div>
    </div>
</div>

<!-- Expenses by Category -->
<?php if (!empty($categoryTotals)): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3>Expenses by Category</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($categoryTotals as $category => $total): ?>
                    <div class="col-md-2">
                        <div class="category-summary">
                            <h4><?php echo htmlspecialchars($category); ?></h4>
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
                    <option value="Pending" <?php echo $status == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Approved" <?php echo $status == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="Rejected" <?php echo $status == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="Paid" <?php echo $status == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                </select>
            </div>
            <div class="form-group">
                <label for="category_id">Category:</label>
                <select name="category_id" id="category_id" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>" 
                            <?php echo $categoryId == $category['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['category_name']); ?>
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
                       placeholder="Expense #, vendor, description" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="index.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>
</div>

<!-- Expenses Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Expense #</th>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Vendor</th>
                        <th class="text-right">Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">No expenses found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td>
                                <a href="view.php?id=<?php echo $expense['id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($expense['expense_number']); ?>
                                </a>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($expense['expense_date'])); ?></td>
                            <td><?php echo htmlspecialchars($expense['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($expense['vendor_name'] ?: '-'); ?></td>
                            <td class="text-right"><?php echo formatCurrency($expense['amount']); ?></td>
                            <td>
                                <span class="badge badge-info"><?php echo htmlspecialchars($expense['payment_method']); ?></span>
                            </td>
                            <td>
                                <span class="badge status-<?php echo strtolower($expense['status']); ?>">
                                    <?php echo $expense['status']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="view.php?id=<?php echo $expense['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="icon-eye"></i>
                                    </a>
                                    
                                    <?php if (hasPermission('accounting.edit') && in_array($expense['status'], ['Pending', 'Approved'])): ?>
                                    <a href="edit.php?id=<?php echo $expense['id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary" title="Edit">
                                        <i class="icon-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('accounting.edit') && $expense['status'] == 'Pending'): ?>
                                    <button onclick="approveExpense(<?php echo $expense['id']; ?>)" 
                                            class="btn btn-sm btn-outline-success" title="Approve">
                                        <i class="icon-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('accounting.delete') && $expense['status'] == 'Pending'): ?>
                                    <button onclick="deleteExpense(<?php echo $expense['id']; ?>)" 
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
function approveExpense(expenseId) {
    if (confirm('Approve this expense?')) {
        fetch('approve.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'expense_id=' + expenseId + '&csrf_token=' + document.querySelector('meta[name="csrf-token"]').getAttribute('content')
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

function deleteExpense(expenseId) {
    if (confirm('Are you sure you want to delete this expense? This action cannot be undone.')) {
        fetch('delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'expense_id=' + expenseId + '&csrf_token=' + document.querySelector('meta[name="csrf-token"]').getAttribute('content')
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

.category-summary {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 10px;
}

.category-summary h4 {
    margin: 0 0 8px 0;
    font-size: 14px;
    color: #6c757d;
    text-transform: uppercase;
}

.category-summary p {
    margin: 0;
    font-size: 18px;
    font-weight: bold;
    color: #333;
}

.status-pending { background-color: #ffc107; color: #333; }
.status-approved { background-color: #28a745; color: white; }
.status-rejected { background-color: #dc3545; color: white; }
.status-paid { background-color: #17a2b8; color: white; }

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
