<?php
/**
 * Business Management System - General Ledger Report
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
$accountId = (int)($_GET['account_id'] ?? 0);
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$export = $_GET['export'] ?? '';

// Get accounts for dropdown
$accounts = $conn->query("
    SELECT id, account_code, account_name, account_type 
    FROM " . DB_PREFIX . "accounts 
    WHERE is_active = 1 
    ORDER BY account_code
")->fetch_all(MYSQLI_ASSOC);

// Get account details if selected
$account = null;
if ($accountId > 0) {
    $stmt = $conn->prepare("
        SELECT * FROM " . DB_PREFIX . "accounts 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
}

// Get ledger entries if account selected
$ledgerEntries = [];
$runningBalance = 0;

if ($account) {
    // Get opening balance
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(CASE WHEN jel.debit > 0 THEN jel.debit ELSE 0 END), 0) - 
               COALESCE(SUM(CASE WHEN jel.credit > 0 THEN jel.credit ELSE 0 END), 0) as opening_balance
        FROM " . DB_PREFIX . "journal_entry_lines jel
        JOIN " . DB_PREFIX . "journal_entries je ON jel.journal_entry_id = je.id
        WHERE jel.account_id = ? AND je.entry_date < ?
    ");
    $stmt->bind_param('is', $accountId, $startDate);
    $stmt->execute();
    $openingBalance = $stmt->get_result()->fetch_assoc()['opening_balance'];
    $runningBalance = $openingBalance;
    
    // Get ledger entries
    $stmt = $conn->prepare("
        SELECT je.entry_date, je.entry_number, je.description, je.reference_type, je.reference_id,
               jel.debit, jel.credit, jel.description as line_description,
               jel.created_at
        FROM " . DB_PREFIX . "journal_entry_lines jel
        JOIN " . DB_PREFIX . "journal_entries je ON jel.journal_entry_id = je.id
        WHERE jel.account_id = ? 
        AND je.entry_date BETWEEN ? AND ?
        ORDER BY je.entry_date, je.created_at
    ");
    $stmt->bind_param('iss', $accountId, $startDate, $endDate);
    $stmt->execute();
    $ledgerEntries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calculate running balance
    foreach ($ledgerEntries as &$entry) {
        $runningBalance += $entry['debit'] - $entry['credit'];
        $entry['running_balance'] = $runningBalance;
    }
}

// Handle export
if ($export == 'csv' && $account) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="general_ledger_' . $account['account_code'] . '_' . $startDate . '_' . $endDate . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Entry #', 'Description', 'Reference', 'Debit', 'Credit', 'Balance']);
    
    // Opening balance
    fputcsv($output, [$startDate, 'OPENING', 'Opening Balance', '', '', '', $openingBalance]);
    
    foreach ($ledgerEntries as $entry) {
        fputcsv($output, [
            $entry['entry_date'],
            $entry['entry_number'],
            $entry['description'],
            $entry['reference_type'] . ($entry['reference_id'] ? ' #' . $entry['reference_id'] : ''),
            $entry['debit'],
            $entry['credit'],
            $entry['running_balance']
        ]);
    }
    
    fclose($output);
    exit;
}

// Set page title
$pageTitle = 'General Ledger Report';

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>General Ledger Report</h1>
        <p><?php echo $account ? $account['account_code'] . ' - ' . $account['account_name'] : 'Select an account to view ledger'; ?></p>
    </div>
    <div class="page-actions">
        <?php if ($account): ?>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="icon-print"></i> Print
        </button>
        <a href="?account_id=<?php echo $accountId; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&export=csv" class="btn btn-secondary">
            <i class="icon-download"></i> Export CSV
        </a>
        <?php endif; ?>
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
                <label for="account_id">Account:</label>
                <select name="account_id" id="account_id" required class="form-control">
                    <option value="">Select Account</option>
                    <?php foreach ($accounts as $acc): ?>
                    <option value="<?php echo $acc['id']; ?>" 
                            <?php echo $accountId == $acc['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($acc['account_code'] . ' - ' . $acc['account_name']); ?>
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
            <button type="submit" class="btn btn-primary">Generate Report</button>
        </form>
    </div>
</div>

<?php if ($account): ?>
<!-- Account Summary -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($openingBalance); ?></h3>
                <p>Opening Balance</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($runningBalance); ?></h3>
                <p>Closing Balance</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo count($ledgerEntries); ?></h3>
                <p>Transactions</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo htmlspecialchars($account['account_type']); ?></h3>
                <p>Account Type</p>
            </div>
        </div>
    </div>
</div>

<!-- General Ledger Table -->
<div class="card">
    <div class="card-header">
        <h3>General Ledger - <?php echo htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']); ?></h3>
        <p>Period: <?php echo date('M d, Y', strtotime($startDate)); ?> to <?php echo date('M d, Y', strtotime($endDate)); ?></p>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Entry #</th>
                        <th>Description</th>
                        <th>Reference</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Credit</th>
                        <th class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Opening Balance Row -->
                    <tr class="opening-balance-row">
                        <td><?php echo date('M d, Y', strtotime($startDate)); ?></td>
                        <td>OPENING</td>
                        <td>Opening Balance</td>
                        <td>-</td>
                        <td class="text-right">-</td>
                        <td class="text-right">-</td>
                        <td class="text-right <?php echo $openingBalance >= 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo formatCurrency($openingBalance); ?>
                        </td>
                    </tr>
                    
                    <?php if (empty($ledgerEntries)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">No transactions found for this period</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($ledgerEntries as $entry): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($entry['entry_date'])); ?></td>
                            <td>
                                <a href="../journal/view.php?id=<?php echo $entry['reference_id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($entry['entry_number']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($entry['description']); ?></td>
                            <td>
                                <?php if ($entry['reference_type'] && $entry['reference_id']): ?>
                                    <?php echo htmlspecialchars($entry['reference_type'] . ' #' . $entry['reference_id']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if ($entry['debit'] > 0): ?>
                                    <?php echo formatCurrency($entry['debit']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if ($entry['credit'] > 0): ?>
                                    <?php echo formatCurrency($entry['credit']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-right <?php echo $entry['running_balance'] >= 0 ? 'text-danger' : 'text-success'; ?>">
                                <?php echo formatCurrency($entry['running_balance']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body text-center">
        <h3>Select an Account</h3>
        <p>Please select an account from the dropdown above to view its general ledger.</p>
    </div>
</div>
<?php endif; ?>

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

.opening-balance-row {
    background-color: #f8f9fa;
    font-weight: bold;
    border-top: 2px solid #dee2e6;
}

.text-danger {
    color: #dc3545 !important;
}

.text-success {
    color: #28a745 !important;
}

.form-group {
    margin-right: 15px;
    margin-bottom: 10px;
}

.form-group label {
    margin-right: 5px;
    font-weight: 500;
}

@media print {
    .page-actions { display: none; }
    .card { box-shadow: none; border: 1px solid #ddd; }
}
</style>

<?php include '../../../includes/footer.php'; ?>
