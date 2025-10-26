<?php
/**
 * Business Management System - Cash Flow Report
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
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$export = $_GET['export'] ?? '';

// Get cash flow data
$stmt = $conn->prepare("
    SELECT 
        'Operating Activities' as category,
        'Cash from Customers' as description,
        COALESCE(SUM(CASE WHEN jel.account_id IN (1100) AND jel.credit > 0 THEN jel.credit ELSE 0 END), 0) as inflow,
        0 as outflow
    FROM " . DB_PREFIX . "journal_entry_lines jel
    JOIN " . DB_PREFIX . "journal_entries je ON jel.journal_entry_id = je.id
    WHERE je.entry_date BETWEEN ? AND ?
    
    UNION ALL
    
    SELECT 
        'Operating Activities' as category,
        'Cash Paid to Suppliers' as description,
        0 as inflow,
        COALESCE(SUM(CASE WHEN jel.account_id IN (2000, 2100) AND jel.debit > 0 THEN jel.debit ELSE 0 END), 0) as outflow
    FROM " . DB_PREFIX . "journal_entry_lines jel
    JOIN " . DB_PREFIX . "journal_entries je ON jel.journal_entry_id = je.id
    WHERE je.entry_date BETWEEN ? AND ?
    
    UNION ALL
    
    SELECT 
        'Operating Activities' as category,
        'Operating Expenses Paid' as description,
        0 as inflow,
        COALESCE(SUM(CASE WHEN jel.account_id BETWEEN 5000 AND 5999 AND jel.debit > 0 THEN jel.debit ELSE 0 END), 0) as outflow
    FROM " . DB_PREFIX . "journal_entry_lines jel
    JOIN " . DB_PREFIX . "journal_entries je ON jel.journal_entry_id = je.id
    WHERE je.entry_date BETWEEN ? AND ?
    
    UNION ALL
    
    SELECT 
        'Investing Activities' as category,
        'Asset Purchases' as description,
        0 as inflow,
        COALESCE(SUM(CASE WHEN jel.account_id BETWEEN 1500 AND 1999 AND jel.debit > 0 THEN jel.debit ELSE 0 END), 0) as outflow
    FROM " . DB_PREFIX . "journal_entry_lines jel
    JOIN " . DB_PREFIX . "journal_entries je ON jel.journal_entry_id = je.id
    WHERE je.entry_date BETWEEN ? AND ?
    
    UNION ALL
    
    SELECT 
        'Financing Activities' as category,
        'Loan Proceeds' as description,
        COALESCE(SUM(CASE WHEN jel.account_id BETWEEN 2200 AND 2999 AND jel.credit > 0 THEN jel.credit ELSE 0 END), 0) as inflow,
        0 as outflow
    FROM " . DB_PREFIX . "journal_entry_lines jel
    JOIN " . DB_PREFIX . "journal_entries je ON jel.journal_entry_id = je.id
    WHERE je.entry_date BETWEEN ? AND ?
    
    UNION ALL
    
    SELECT 
        'Financing Activities' as category,
        'Loan Payments' as description,
        0 as inflow,
        COALESCE(SUM(CASE WHEN jel.account_id BETWEEN 2200 AND 2999 AND jel.debit > 0 THEN jel.debit ELSE 0 END), 0) as outflow
    FROM " . DB_PREFIX . "journal_entry_lines jel
    JOIN " . DB_PREFIX . "journal_entries je ON jel.journal_entry_id = je.id
    WHERE je.entry_date BETWEEN ? AND ?
    
    ORDER BY category, description
");

$stmt->bind_param('ssssssssss', $startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate);
$stmt->execute();
$cashFlowData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$operatingInflow = 0;
$operatingOutflow = 0;
$investingInflow = 0;
$investingOutflow = 0;
$financingInflow = 0;
$financingOutflow = 0;

foreach ($cashFlowData as $item) {
    switch ($item['category']) {
        case 'Operating Activities':
            $operatingInflow += $item['inflow'];
            $operatingOutflow += $item['outflow'];
            break;
        case 'Investing Activities':
            $investingInflow += $item['inflow'];
            $investingOutflow += $item['outflow'];
            break;
        case 'Financing Activities':
            $financingInflow += $item['inflow'];
            $financingOutflow += $item['outflow'];
            break;
    }
}

$netOperatingCashFlow = $operatingInflow - $operatingOutflow;
$netInvestingCashFlow = $investingInflow - $investingOutflow;
$netFinancingCashFlow = $financingInflow - $financingOutflow;
$netCashFlow = $netOperatingCashFlow + $netInvestingCashFlow + $netFinancingCashFlow;

// Get beginning and ending cash balances
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(CASE WHEN jel.debit > 0 THEN jel.debit ELSE 0 END), 0) - 
           COALESCE(SUM(CASE WHEN jel.credit > 0 THEN jel.credit ELSE 0 END), 0) as balance
    FROM " . DB_PREFIX . "journal_entry_lines jel
    JOIN " . DB_PREFIX . "journal_entries je ON jel.journal_entry_id = je.id
    WHERE jel.account_id IN (1010, 1020) AND je.entry_date < ?
");
$stmt->bind_param('s', $startDate);
$stmt->execute();
$beginningCash = $stmt->get_result()->fetch_assoc()['balance'];

$endingCash = $beginningCash + $netCashFlow;

// Handle export
if ($export == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="cash_flow_' . $startDate . '_' . $endDate . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Category', 'Description', 'Inflow', 'Outflow', 'Net']);
    
    foreach ($cashFlowData as $item) {
        $net = $item['inflow'] - $item['outflow'];
        fputcsv($output, [$item['category'], $item['description'], $item['inflow'], $item['outflow'], $net]);
    }
    
    fputcsv($output, ['', 'NET OPERATING CASH FLOW', '', '', $netOperatingCashFlow]);
    fputcsv($output, ['', 'NET INVESTING CASH FLOW', '', '', $netInvestingCashFlow]);
    fputcsv($output, ['', 'NET FINANCING CASH FLOW', '', '', $netFinancingCashFlow]);
    fputcsv($output, ['', 'NET CASH FLOW', '', '', $netCashFlow]);
    fputcsv($output, ['', 'BEGINNING CASH', '', '', $beginningCash]);
    fputcsv($output, ['', 'ENDING CASH', '', '', $endingCash]);
    
    fclose($output);
    exit;
}

// Set page title
$pageTitle = 'Cash Flow Report';

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Cash Flow Report</h1>
        <p>Period: <?php echo date('M d, Y', strtotime($startDate)); ?> to <?php echo date('M d, Y', strtotime($endDate)); ?></p>
    </div>
    <div class="page-actions">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="icon-print"></i> Print
        </button>
        <a href="?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&export=csv" class="btn btn-secondary">
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

<!-- Cash Flow Summary -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($netOperatingCashFlow); ?></h3>
                <p>Operating Cash Flow</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($netInvestingCashFlow); ?></h3>
                <p>Investing Cash Flow</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($netFinancingCashFlow); ?></h3>
                <p>Financing Cash Flow</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($netCashFlow); ?></h3>
                <p>Net Cash Flow</p>
            </div>
        </div>
    </div>
</div>

<!-- Cash Balances -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3>Cash Balances</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h4><?php echo formatCurrency($beginningCash); ?></h4>
                        <p>Beginning Cash</p>
                    </div>
                    <div class="col-md-6">
                        <h4><?php echo formatCurrency($endingCash); ?></h4>
                        <p>Ending Cash</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3>Cash Flow Analysis</h3>
            </div>
            <div class="card-body">
                <div class="cash-flow-analysis">
                    <?php if ($netCashFlow > 0): ?>
                        <div class="alert alert-success">
                            <strong>Positive Cash Flow:</strong> The business generated positive cash flow during this period.
                        </div>
                    <?php elseif ($netCashFlow < 0): ?>
                        <div class="alert alert-warning">
                            <strong>Negative Cash Flow:</strong> The business had negative cash flow during this period.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <strong>Neutral Cash Flow:</strong> Cash flow was balanced during this period.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cash Flow Statement -->
<div class="card">
    <div class="card-header">
        <h3>Cash Flow Statement</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Description</th>
                        <th class="text-right">Inflow</th>
                        <th class="text-right">Outflow</th>
                        <th class="text-right">Net</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $currentCategory = '';
                    foreach ($cashFlowData as $item): 
                        if ($currentCategory != $item['category']):
                            $currentCategory = $item['category'];
                    ?>
                    <tr class="category-header">
                        <td colspan="5"><strong><?php echo htmlspecialchars($currentCategory); ?></strong></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td></td>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td class="text-right">
                            <?php if ($item['inflow'] > 0): ?>
                                <?php echo formatCurrency($item['inflow']); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <?php if ($item['outflow'] > 0): ?>
                                <?php echo formatCurrency($item['outflow']); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-right <?php echo ($item['inflow'] - $item['outflow']) >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo formatCurrency($item['inflow'] - $item['outflow']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="2"><strong>NET OPERATING CASH FLOW</strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($operatingInflow); ?></strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($operatingOutflow); ?></strong></td>
                        <td class="text-right"><strong class="<?php echo $netOperatingCashFlow >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatCurrency($netOperatingCashFlow); ?></strong></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="2"><strong>NET INVESTING CASH FLOW</strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($investingInflow); ?></strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($investingOutflow); ?></strong></td>
                        <td class="text-right"><strong class="<?php echo $netInvestingCashFlow >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatCurrency($netInvestingCashFlow); ?></strong></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="2"><strong>NET FINANCING CASH FLOW</strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($financingInflow); ?></strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($financingOutflow); ?></strong></td>
                        <td class="text-right"><strong class="<?php echo $netFinancingCashFlow >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatCurrency($netFinancingCashFlow); ?></strong></td>
                    </tr>
                    <tr class="grand-total-row">
                        <td colspan="2"><strong>NET CASH FLOW</strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($operatingInflow + $investingInflow + $financingInflow); ?></strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($operatingOutflow + $investingOutflow + $financingOutflow); ?></strong></td>
                        <td class="text-right"><strong class="<?php echo $netCashFlow >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatCurrency($netCashFlow); ?></strong></td>
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

.category-header {
    background-color: #f8f9fa;
    font-weight: bold;
}

.category-header td {
    padding: 10px 8px;
    border-top: 2px solid #dee2e6;
}

.total-row {
    background-color: #e9ecef;
    font-weight: bold;
}

.grand-total-row {
    background-color: #343a40;
    color: white;
    font-weight: bold;
    border-top: 2px solid #333;
}

.text-success {
    color: #28a745 !important;
}

.text-danger {
    color: #dc3545 !important;
}

.cash-flow-analysis h4 {
    margin: 0 0 5px 0;
    font-size: 18px;
    font-weight: bold;
}

.cash-flow-analysis p {
    margin: 0;
    color: #6c757d;
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
