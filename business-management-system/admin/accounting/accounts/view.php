<?php
/**
 * Business Management System - Account Statement
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

// Get account ID
$accountId = (int)($_GET['id'] ?? 0);

if (!$accountId) {
    header('Location: index.php');
    exit;
}

// Get account details
$stmt = $conn->prepare("
    SELECT a.*, parent.account_name as parent_name, u.first_name, u.last_name
    FROM " . DB_PREFIX . "accounts a
    LEFT JOIN " . DB_PREFIX . "accounts parent ON a.parent_account_id = parent.id
    LEFT JOIN " . DB_PREFIX . "users u ON a.created_by = u.id
    WHERE a.id = ?
");
$stmt->bind_param('i', $accountId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();

if (!$account) {
    header('Location: index.php');
    exit;
}

// Get date range parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Get account transactions
$stmt = $conn->prepare("
    SELECT je.journal_number, je.entry_date, je.description as entry_description,
           jel.description as line_description, jel.debit_amount, jel.credit_amount,
           je.entry_type, je.reference_type, je.reference_id
    FROM " . DB_PREFIX . "journal_entry_lines jel
    JOIN " . DB_PREFIX . "journal_entries je ON jel.journal_entry_id = je.id
    WHERE jel.account_id = ? 
    AND je.entry_date BETWEEN ? AND ?
    AND je.status = 'Posted'
    ORDER BY je.entry_date DESC, jel.id DESC
");
$stmt->bind_param('iss', $accountId, $startDate, $endDate);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate running balance
$runningBalance = $account['opening_balance'];
foreach ($transactions as &$transaction) {
    if (in_array($account['account_type'], ['Asset', 'Expense'])) {
        $runningBalance += $transaction['debit_amount'] - $transaction['credit_amount'];
    } else {
        $runningBalance += $transaction['credit_amount'] - $transaction['debit_amount'];
    }
    $transaction['running_balance'] = $runningBalance;
}

// Calculate totals
$totalDebits = array_sum(array_column($transactions, 'debit_amount'));
$totalCredits = array_sum(array_column($transactions, 'credit_amount'));

// Get opening balance for the period
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(
        CASE 
            WHEN a.account_type IN ('Asset', 'Expense') THEN jel.debit_amount - jel.credit_amount
            ELSE jel.credit_amount - jel.debit_amount
        END
    ), 0) as opening_balance
    FROM " . DB_PREFIX . "journal_entry_lines jel
    JOIN " . DB_PREFIX . "journal_entries je ON jel.journal_entry_id = je.id
    JOIN " . DB_PREFIX . "accounts a ON jel.account_id = a.id
    WHERE jel.account_id = ? 
    AND je.entry_date < ?
    AND je.status = 'Posted'
");
$stmt->bind_param('is', $accountId, $startDate);
$stmt->execute();
$periodOpeningBalance = $stmt->get_result()->fetch_assoc()['opening_balance'] + $account['opening_balance'];

// Set page title
$pageTitle = 'Account Statement - ' . $account['account_name'];

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Account Statement</h1>
        <p><?php echo htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']); ?></p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Accounts
        </a>
        <?php if (hasPermission('accounting.edit') && !$account['is_system']): ?>
        <a href="edit.php?id=<?php echo $account['id']; ?>" class="btn btn-primary">
            <i class="icon-edit"></i> Edit Account
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Account Details -->
<div class="card">
    <div class="card-header">
        <h3>Account Information</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Account Code:</strong></td>
                        <td><?php echo htmlspecialchars($account['account_code']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Account Name:</strong></td>
                        <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Account Type:</strong></td>
                        <td>
                            <span class="badge account-type-<?php echo strtolower($account['account_type']); ?>">
                                <?php echo $account['account_type']; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Subtype:</strong></td>
                        <td><?php echo htmlspecialchars($account['account_subtype']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Parent Account:</strong></td>
                        <td><?php echo htmlspecialchars($account['parent_name'] ?: 'None'); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Current Balance:</strong></td>
                        <td class="amount <?php echo strtolower($account['account_type']); ?>">
                            <?php echo formatCurrency($account['current_balance']); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Opening Balance:</strong></td>
                        <td><?php echo formatCurrency($account['opening_balance']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td>
                            <span class="badge badge-success">Active</span>
                            <?php if ($account['is_system']): ?>
                                <span class="badge badge-warning">System Account</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Created By:</strong></td>
                        <td><?php echo htmlspecialchars($account['first_name'] . ' ' . $account['last_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Created Date:</strong></td>
                        <td><?php echo date('M d, Y', strtotime($account['created_at'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php if ($account['description']): ?>
        <div class="mt-3">
            <strong>Description:</strong>
            <p><?php echo nl2br(htmlspecialchars($account['description'])); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Transaction Filter -->
<div class="card">
    <div class="card-header">
        <h3>Transaction History</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="form-inline mb-3">
            <input type="hidden" name="id" value="<?php echo $accountId; ?>">
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
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="view.php?id=<?php echo $accountId; ?>" class="btn btn-link">Reset</a>
        </form>
        
        <!-- Export Options -->
        <div class="export-options mb-3">
            <button onclick="exportToCSV()" class="btn btn-sm btn-outline-secondary">
                <i class="icon-download"></i> Export CSV
            </button>
            <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
                <i class="icon-print"></i> Print
            </button>
        </div>
        
        <!-- Transactions Table -->
        <div class="table-responsive">
            <table class="table table-striped" id="transactions-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Journal #</th>
                        <th>Description</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Credit</th>
                        <th class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">No transactions found for this period</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($transaction['entry_date'])); ?></td>
                            <td>
                                <a href="../journal/view.php?id=<?php echo $transaction['journal_entry_id'] ?? ''; ?>" 
                                   class="text-decoration-none">
                                    <?php echo htmlspecialchars($transaction['journal_number']); ?>
                                </a>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($transaction['line_description'] ?: $transaction['entry_description']); ?>
                                <?php if ($transaction['entry_type'] != 'Manual'): ?>
                                    <span class="badge badge-info"><?php echo $transaction['entry_type']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if ($transaction['debit_amount'] > 0): ?>
                                    <?php echo formatCurrency($transaction['debit_amount']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if ($transaction['credit_amount'] > 0): ?>
                                    <?php echo formatCurrency($transaction['credit_amount']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <span class="balance <?php echo $transaction['running_balance'] >= 0 ? 'positive' : 'negative'; ?>">
                                    <?php echo formatCurrency($transaction['running_balance']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <td colspan="3"><strong>Period Totals:</strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($totalDebits); ?></strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($totalCredits); ?></strong></td>
                        <td class="text-right">
                            <strong>
                                <?php echo formatCurrency($periodOpeningBalance + ($totalDebits - $totalCredits)); ?>
                            </strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Summary -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="summary-box">
                    <h4>Opening Balance</h4>
                    <p class="amount"><?php echo formatCurrency($periodOpeningBalance); ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-box">
                    <h4>Total Debits</h4>
                    <p class="amount"><?php echo formatCurrency($totalDebits); ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-box">
                    <h4>Total Credits</h4>
                    <p class="amount"><?php echo formatCurrency($totalCredits); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportToCSV() {
    const table = document.getElementById('transactions-table');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td, th');
        const rowData = Array.from(cells).map(cell => {
            return '"' + cell.textContent.trim().replace(/"/g, '""') + '"';
        });
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'account_statement_<?php echo $account['account_code']; ?>_<?php echo $startDate; ?>_<?php echo $endDate; ?>.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<style>
.account-type-asset { background-color: #007bff; color: white; }
.account-type-liability { background-color: #dc3545; color: white; }
.account-type-equity { background-color: #6f42c1; color: white; }
.account-type-income { background-color: #28a745; color: white; }
.account-type-expense { background-color: #fd7e14; color: white; }

.amount.asset { color: #007bff; font-weight: bold; }
.amount.liability { color: #dc3545; font-weight: bold; }
.amount.equity { color: #6f42c1; font-weight: bold; }
.amount.income { color: #28a745; font-weight: bold; }
.amount.expense { color: #fd7e14; font-weight: bold; }

.balance.positive { color: #28a745; font-weight: bold; }
.balance.negative { color: #dc3545; font-weight: bold; }

.summary-box {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.summary-box h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #6c757d;
    text-transform: uppercase;
}

.summary-box .amount {
    margin: 0;
    font-size: 20px;
    font-weight: bold;
    color: #333;
}

.badge-info {
    background-color: #17a2b8;
    color: white;
    font-size: 10px;
}

@media print {
    .export-options { display: none; }
    .page-actions { display: none; }
    .card { box-shadow: none; border: 1px solid #ddd; }
}
</style>

<?php include '../../../includes/footer.php'; ?>
