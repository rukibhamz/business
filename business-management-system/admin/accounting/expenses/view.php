<?php
/**
 * Business Management System - View Expense
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

// Get expense ID
$expenseId = (int)($_GET['id'] ?? 0);

if (!$expenseId) {
    header('Location: index.php');
    exit;
}

// Get expense details
$stmt = $conn->prepare("
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
    WHERE e.id = ?
");
$stmt->bind_param('i', $expenseId);
$stmt->execute();
$expense = $stmt->get_result()->fetch_assoc();

if (!$expense) {
    header('Location: index.php');
    exit;
}

// Set page title
$pageTitle = 'Expense - ' . $expense['expense_number'];

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Expense #<?php echo htmlspecialchars($expense['expense_number']); ?></h1>
        <p><?php echo htmlspecialchars($expense['category_name']); ?> - <?php echo htmlspecialchars($expense['vendor_name'] ?: 'No Vendor'); ?></p>
    </div>
    <div class="page-actions">
        <?php if ($expense['status'] == 'Pending' && hasPermission('accounting.edit')): ?>
        <button onclick="approveExpense(<?php echo $expenseId; ?>)" class="btn btn-success">
            <i class="icon-check"></i> Approve
        </button>
        <a href="edit.php?id=<?php echo $expenseId; ?>" class="btn btn-warning">
            <i class="icon-edit"></i> Edit
        </a>
        <?php endif; ?>
        <?php if ($expense['status'] == 'Pending' && hasPermission('accounting.delete')): ?>
        <button onclick="deleteExpense(<?php echo $expenseId; ?>)" class="btn btn-danger">
            <i class="icon-trash"></i> Delete
        </button>
        <?php endif; ?>
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Expenses
        </a>
    </div>
</div>

<!-- Expense Details -->
<div class="card">
    <div class="card-header">
        <h3>Expense Information</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Expense Number:</strong></td>
                        <td><?php echo htmlspecialchars($expense['expense_number']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Expense Date:</strong></td>
                        <td><?php echo date('M d, Y', strtotime($expense['expense_date'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Category:</strong></td>
                        <td><?php echo htmlspecialchars($expense['category_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Amount:</strong></td>
                        <td class="amount"><?php echo formatCurrency($expense['amount']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Tax Amount:</strong></td>
                        <td><?php echo formatCurrency($expense['tax_amount']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Total Amount:</strong></td>
                        <td class="total-amount"><?php echo formatCurrency($expense['amount'] + $expense['tax_amount']); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Payment Method:</strong></td>
                        <td>
                            <span class="badge badge-info"><?php echo htmlspecialchars($expense['payment_method']); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td>
                            <span class="badge status-<?php echo strtolower($expense['status']); ?>">
                                <?php echo $expense['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Vendor:</strong></td>
                        <td><?php echo htmlspecialchars($expense['vendor_name'] ?: '-'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Reference:</strong></td>
                        <td><?php echo htmlspecialchars($expense['reference'] ?: '-'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Account:</strong></td>
                        <td>
                            <?php if ($expense['account_code']): ?>
                                <?php echo htmlspecialchars($expense['account_code'] . ' - ' . $expense['account_name']); ?>
                            <?php else: ?>
                                <span class="text-muted">Not assigned</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Billable:</strong></td>
                        <td>
                            <?php if ($expense['is_billable']): ?>
                                <span class="badge badge-success">Yes</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">No</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="mt-3">
            <strong>Description:</strong>
            <p><?php echo nl2br(htmlspecialchars($expense['description'])); ?></p>
        </div>
        
        <?php if ($expense['receipt_file']): ?>
        <div class="mt-3">
            <strong>Receipt:</strong>
            <p>
                <a href="../../../../<?php echo htmlspecialchars($expense['receipt_file']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="icon-download"></i> View Receipt
                </a>
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Customer Information (if billable) -->
<?php if ($expense['is_billable'] && $expense['customer_id']): ?>
<div class="card">
    <div class="card-header">
        <h3>Customer Information</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h4><?php echo getCustomerDisplayName($expense); ?></h4>
                <?php if ($expense['email']): ?>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($expense['email']); ?></p>
                <?php endif; ?>
                <?php if ($expense['phone']): ?>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($expense['phone']); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <?php if ($expense['address']): ?>
                <p><strong>Address:</strong></p>
                <p><?php echo nl2br(htmlspecialchars($expense['address'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Approval Information -->
<?php if ($expense['status'] == 'Approved'): ?>
<div class="card">
    <div class="card-header">
        <h3>Approval Information</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Approved By:</strong> <?php echo htmlspecialchars($expense['approved_by_first'] . ' ' . $expense['approved_by_last']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Approved Date:</strong> <?php echo date('M d, Y H:i', strtotime($expense['approved_date'])); ?></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Accounting Entry Information -->
<?php if ($expense['status'] == 'Approved'): ?>
<div class="card">
    <div class="card-header">
        <h3>Accounting Entry</h3>
    </div>
    <div class="card-body">
        <p>This expense has been automatically recorded in the accounting system:</p>
        <div class="journal-entry-preview">
            <div class="entry-line">
                <span class="account"><?php echo $expense['account_name'] ?: 'Expense Account'; ?></span>
                <span class="debit"><?php echo formatCurrency($expense['amount']); ?></span>
                <span class="credit">-</span>
            </div>
            <div class="entry-line">
                <span class="account">Cash/Bank Account</span>
                <span class="debit">-</span>
                <span class="credit"><?php echo formatCurrency($expense['amount']); ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Audit Information -->
<div class="card">
    <div class="card-header">
        <h3>Audit Information</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Created By:</strong> <?php echo htmlspecialchars($expense['created_by_first'] . ' ' . $expense['created_by_last']); ?></p>
                <p><strong>Created Date:</strong> <?php echo date('M d, Y H:i', strtotime($expense['created_at'])); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Last Updated:</strong> <?php echo date('M d, Y H:i', strtotime($expense['updated_at'])); ?></p>
            </div>
        </div>
    </div>
</div>

<script>
function approveExpense(expenseId) {
    if (confirm('Approve this expense? This will create accounting entries.')) {
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
                location.href = 'index.php';
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}
</script>

<style>
.amount {
    font-size: 18px;
    font-weight: bold;
    color: #dc3545;
}

.total-amount {
    font-size: 20px;
    font-weight: bold;
    color: #dc3545;
}

.status-pending { background-color: #ffc107; color: #333; }
.status-approved { background-color: #28a745; color: white; }
.status-rejected { background-color: #dc3545; color: white; }
.status-paid { background-color: #17a2b8; color: white; }

.badge-info {
    background-color: #17a2b8;
    color: white;
}

.badge-success {
    background-color: #28a745;
    color: white;
}

.badge-secondary {
    background-color: #6c757d;
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
</style>

<?php include '../../../includes/footer.php'; ?>
