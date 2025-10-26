<?php
/**
 * Business Management System - Chart of Accounts
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
$accountType = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = ['a.is_active = 1'];
$params = [];
$paramTypes = '';

if (!empty($accountType)) {
    $whereConditions[] = 'a.account_type = ?';
    $params[] = $accountType;
    $paramTypes .= 's';
}

if (!empty($search)) {
    $whereConditions[] = '(a.account_code LIKE ? OR a.account_name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $paramTypes .= 'ss';
}

$whereClause = implode(' AND ', $whereConditions);

// Get accounts grouped by type
$accountTypes = ['Asset', 'Liability', 'Equity', 'Income', 'Expense'];
$accountsByType = [];

foreach ($accountTypes as $type) {
    $query = "
        SELECT a.*, 
               COALESCE(parent.account_name, '') as parent_name,
               COUNT(child.id) as child_count
        FROM " . DB_PREFIX . "accounts a
        LEFT JOIN " . DB_PREFIX . "accounts parent ON a.parent_account_id = parent.id
        LEFT JOIN " . DB_PREFIX . "accounts child ON a.id = child.parent_account_id
        WHERE a.account_type = ? AND a.is_active = 1
        GROUP BY a.id
        ORDER BY a.account_code
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $type);
    $stmt->execute();
    $accountsByType[$type] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Set page title
$pageTitle = 'Chart of Accounts';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Chart of Accounts</h1>
        <p>Manage your accounting accounts and view balances</p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('accounting.create')): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Add Account
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="form-inline">
            <div class="form-group">
                <label for="type">Account Type:</label>
                <select name="type" id="type" class="form-control">
                    <option value="">All Types</option>
                    <option value="Asset" <?php echo $accountType == 'Asset' ? 'selected' : ''; ?>>Assets</option>
                    <option value="Liability" <?php echo $accountType == 'Liability' ? 'selected' : ''; ?>>Liabilities</option>
                    <option value="Equity" <?php echo $accountType == 'Equity' ? 'selected' : ''; ?>>Equity</option>
                    <option value="Income" <?php echo $accountType == 'Income' ? 'selected' : ''; ?>>Income</option>
                    <option value="Expense" <?php echo $accountType == 'Expense' ? 'selected' : ''; ?>>Expenses</option>
                </select>
            </div>
            <div class="form-group">
                <label for="search">Search:</label>
                <input type="text" name="search" id="search" class="form-control" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Code or name">
            </div>
            <button type="submit" class="btn btn-secondary">Filter</button>
            <a href="index.php" class="btn btn-link">Clear</a>
        </form>
    </div>
</div>

<!-- Accounts by Type -->
<?php foreach ($accountTypes as $type): ?>
    <?php if (!empty($accountsByType[$type])): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="account-type-header account-type-<?php echo strtolower($type); ?>">
                <?php echo $type; ?>s
            </h3>
        </div>
        <div class="card-body">
            <table class="table accounts-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Account Name</th>
                        <th>Subtype</th>
                        <th>Parent</th>
                        <th class="text-right">Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accountsByType[$type] as $account): ?>
                    <tr class="<?php echo $account['parent_account_id'] ? 'child-account' : 'parent-account'; ?>">
                        <td>
                            <?php if ($account['parent_account_id']): ?>
                                &nbsp;&nbsp;<?php echo htmlspecialchars($account['account_code']); ?>
                            <?php else: ?>
                                <strong><?php echo htmlspecialchars($account['account_code']); ?></strong>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($account['parent_account_id']): ?>
                                &nbsp;&nbsp;<?php echo htmlspecialchars($account['account_name']); ?>
                            <?php else: ?>
                                <strong><?php echo htmlspecialchars($account['account_name']); ?></strong>
                            <?php endif; ?>
                            <?php if ($account['child_count'] > 0): ?>
                                <span class="badge badge-info"><?php echo $account['child_count']; ?> sub-accounts</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($account['account_subtype']); ?></td>
                        <td><?php echo htmlspecialchars($account['parent_name']); ?></td>
                        <td class="text-right">
                            <?php if ($account['account_subtype'] != 'Header'): ?>
                                <?php echo formatCurrency($account['current_balance']); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-success">Active</span>
                            <?php if ($account['is_system']): ?>
                                <span class="badge badge-warning">System</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (hasPermission('accounting.view')): ?>
                            <a href="view.php?id=<?php echo $account['id']; ?>" 
                               class="btn btn-sm btn-outline-primary" title="View Statement">
                                <i class="icon-eye"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasPermission('accounting.edit') && !$account['is_system']): ?>
                            <a href="edit.php?id=<?php echo $account['id']; ?>" 
                               class="btn btn-sm btn-outline-secondary" title="Edit">
                                <i class="icon-edit"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasPermission('accounting.delete') && !$account['is_system'] && $account['current_balance'] == 0): ?>
                            <button onclick="deleteAccount(<?php echo $account['id']; ?>)" 
                                    class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="icon-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
<?php endforeach; ?>

<!-- Summary -->
<div class="card">
    <div class="card-header">
        <h3>Account Summary</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <?php 
            $totalAssets = 0;
            $totalLiabilities = 0;
            $totalEquity = 0;
            $totalIncome = 0;
            $totalExpenses = 0;
            
            foreach ($accountsByType as $type => $accounts) {
                foreach ($accounts as $account) {
                    if ($account['account_subtype'] != 'Header') {
                        switch ($type) {
                            case 'Asset':
                                $totalAssets += $account['current_balance'];
                                break;
                            case 'Liability':
                                $totalLiabilities += $account['current_balance'];
                                break;
                            case 'Equity':
                                $totalEquity += $account['current_balance'];
                                break;
                            case 'Income':
                                $totalIncome += $account['current_balance'];
                                break;
                            case 'Expense':
                                $totalExpenses += $account['current_balance'];
                                break;
                        }
                    }
                }
            }
            ?>
            <div class="col-md-2">
                <div class="summary-item">
                    <h4>Assets</h4>
                    <p class="amount asset"><?php echo formatCurrency($totalAssets); ?></p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="summary-item">
                    <h4>Liabilities</h4>
                    <p class="amount liability"><?php echo formatCurrency($totalLiabilities); ?></p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="summary-item">
                    <h4>Equity</h4>
                    <p class="amount equity"><?php echo formatCurrency($totalEquity); ?></p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="summary-item">
                    <h4>Income</h4>
                    <p class="amount income"><?php echo formatCurrency($totalIncome); ?></p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="summary-item">
                    <h4>Expenses</h4>
                    <p class="amount expense"><?php echo formatCurrency($totalExpenses); ?></p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="summary-item">
                    <h4>Net Worth</h4>
                    <p class="amount <?php echo ($totalAssets - $totalLiabilities) >= 0 ? 'asset' : 'liability'; ?>">
                        <?php echo formatCurrency($totalAssets - $totalLiabilities); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function deleteAccount(accountId) {
    if (confirm('Are you sure you want to delete this account? This action cannot be undone.')) {
        fetch('delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'account_id=' + accountId + '&csrf_token=' + document.querySelector('meta[name="csrf-token"]').getAttribute('content')
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
.account-type-header {
    margin: 0;
    padding: 10px 0;
    border-bottom: 2px solid;
}

.account-type-asset { color: #007bff; border-color: #007bff; }
.account-type-liability { color: #dc3545; border-color: #dc3545; }
.account-type-equity { color: #6f42c1; border-color: #6f42c1; }
.account-type-income { color: #28a745; border-color: #28a745; }
.account-type-expense { color: #fd7e14; border-color: #fd7e14; }

.accounts-table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.parent-account {
    background-color: #f8f9fa;
    font-weight: 600;
}

.child-account {
    background-color: white;
}

.summary-item {
    text-align: center;
    padding: 15px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.summary-item h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #6c757d;
    text-transform: uppercase;
}

.summary-item .amount {
    margin: 0;
    font-size: 18px;
    font-weight: bold;
}

.amount.asset { color: #007bff; }
.amount.liability { color: #dc3545; }
.amount.equity { color: #6f42c1; }
.amount.income { color: #28a745; }
.amount.expense { color: #fd7e14; }

.badge-info {
    background-color: #17a2b8;
    color: white;
}

.badge-warning {
    background-color: #ffc107;
    color: #333;
}
</style>

<?php include '../../includes/footer.php'; ?>
