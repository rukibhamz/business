<?php
/**
 * Business Management System - Trial Balance Report
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
$asOfDate = $_GET['as_of_date'] ?? date('Y-m-d');
$export = $_GET['export'] ?? '';

// Get trial balance data
$stmt = $conn->prepare("
    SELECT a.id, a.account_code, a.account_name, a.account_type, a.account_subtype,
           COALESCE(SUM(CASE WHEN jel.debit > 0 THEN jel.debit ELSE 0 END), 0) as total_debits,
           COALESCE(SUM(CASE WHEN jel.credit > 0 THEN jel.credit ELSE 0 END), 0) as total_credits,
           COALESCE(SUM(CASE WHEN jel.debit > 0 THEN jel.debit ELSE 0 END), 0) - 
           COALESCE(SUM(CASE WHEN jel.credit > 0 THEN jel.credit ELSE 0 END), 0) as balance
    FROM " . DB_PREFIX . "accounts a
    LEFT JOIN " . DB_PREFIX . "journal_entry_lines jel ON a.id = jel.account_id
    LEFT JOIN " . DB_PREFIX . "journal_entries je ON jel.journal_entry_id = je.id
    WHERE a.is_active = 1
    AND (je.entry_date IS NULL OR je.entry_date <= ?)
    GROUP BY a.id, a.account_code, a.account_name, a.account_type, a.account_subtype
    HAVING total_debits > 0 OR total_credits > 0
    ORDER BY a.account_type, a.account_code
");
$stmt->bind_param('s', $asOfDate);
$stmt->execute();
$trialBalance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totalDebits = 0;
$totalCredits = 0;
$accountTypes = [];

foreach ($trialBalance as $account) {
    $totalDebits += $account['total_debits'];
    $totalCredits += $account['total_credits'];
    
    $type = $account['account_type'];
    if (!isset($accountTypes[$type])) {
        $accountTypes[$type] = ['debits' => 0, 'credits' => 0];
    }
    $accountTypes[$type]['debits'] += $account['total_debits'];
    $accountTypes[$type]['credits'] += $account['total_credits'];
}

// Handle export
if ($export == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="trial_balance_' . $asOfDate . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Account Code', 'Account Name', 'Account Type', 'Debits', 'Credits', 'Balance']);
    
    foreach ($trialBalance as $account) {
        fputcsv($output, [
            $account['account_code'],
            $account['account_name'],
            $account['account_type'],
            $account['total_debits'],
            $account['total_credits'],
            $account['balance']
        ]);
    }
    
    fputcsv($output, ['', '', 'TOTAL', $totalDebits, $totalCredits, '']);
    fclose($output);
    exit;
}

// Set page title
$pageTitle = 'Trial Balance Report';

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Trial Balance Report</h1>
        <p>As of <?php echo date('M d, Y', strtotime($asOfDate)); ?></p>
    </div>
    <div class="page-actions">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="icon-print"></i> Print
        </button>
        <a href="?as_of_date=<?php echo $asOfDate; ?>&export=csv" class="btn btn-secondary">
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
                <label for="as_of_date">As of Date:</label>
                <input type="date" name="as_of_date" id="as_of_date" 
                       value="<?php echo $asOfDate; ?>" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Update Report</button>
        </form>
    </div>
</div>

<!-- Trial Balance Summary -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($totalDebits); ?></h3>
                <p>Total Debits</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($totalCredits); ?></h3>
                <p>Total Credits</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency(abs($totalDebits - $totalCredits)); ?></h3>
                <p>Difference</p>
            </div>
        </div>
    </div>
</div>

<!-- Trial Balance Table -->
<div class="card">
    <div class="card-header">
        <h3>Trial Balance</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Account Code</th>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th class="text-right">Debits</th>
                        <th class="text-right">Credits</th>
                        <th class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $currentType = '';
                    foreach ($trialBalance as $account): 
                        if ($currentType != $account['account_type']):
                            $currentType = $account['account_type'];
                    ?>
                    <tr class="account-type-header">
                        <td colspan="6"><strong><?php echo htmlspecialchars($currentType); ?></strong></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><?php echo htmlspecialchars($account['account_code']); ?></td>
                        <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                        <td><?php echo htmlspecialchars($account['account_subtype']); ?></td>
                        <td class="text-right"><?php echo formatCurrency($account['total_debits']); ?></td>
                        <td class="text-right"><?php echo formatCurrency($account['total_credits']); ?></td>
                        <td class="text-right <?php echo $account['balance'] >= 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo formatCurrency($account['balance']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3"><strong>TOTAL</strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($totalDebits); ?></strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($totalCredits); ?></strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($totalDebits - $totalCredits); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Account Type Summary -->
<div class="card">
    <div class="card-header">
        <h3>Summary by Account Type</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Account Type</th>
                        <th class="text-right">Total Debits</th>
                        <th class="text-right">Total Credits</th>
                        <th class="text-right">Net Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accountTypes as $type => $totals): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($type); ?></td>
                        <td class="text-right"><?php echo formatCurrency($totals['debits']); ?></td>
                        <td class="text-right"><?php echo formatCurrency($totals['credits']); ?></td>
                        <td class="text-right <?php echo ($totals['debits'] - $totals['credits']) >= 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo formatCurrency($totals['debits'] - $totals['credits']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
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

.account-type-header {
    background-color: #f8f9fa;
    font-weight: bold;
}

.account-type-header td {
    padding: 10px 8px;
    border-top: 2px solid #dee2e6;
}

.total-row {
    background-color: #e9ecef;
    font-weight: bold;
    border-top: 2px solid #333;
}

.text-danger {
    color: #dc3545 !important;
}

.text-success {
    color: #28a745 !important;
}

@media print {
    .page-actions { display: none; }
    .card { box-shadow: none; border: 1px solid #ddd; }
}
</style>

<?php include '../../../includes/footer.php'; ?>
