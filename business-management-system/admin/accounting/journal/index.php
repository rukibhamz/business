<?php
/**
 * Business Management System - Journal Entries List
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
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$referenceType = $_GET['reference_type'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

if (!empty($startDate)) {
    $whereConditions[] = 'je.entry_date >= ?';
    $params[] = $startDate;
    $paramTypes .= 's';
}

if (!empty($endDate)) {
    $whereConditions[] = 'je.entry_date <= ?';
    $params[] = $endDate;
    $paramTypes .= 's';
}

if (!empty($referenceType)) {
    $whereConditions[] = 'je.reference_type = ?';
    $params[] = $referenceType;
    $paramTypes .= 's';
}

if (!empty($search)) {
    $whereConditions[] = '(je.entry_number LIKE ? OR je.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $paramTypes .= 'ss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get journal entries
$query = "
    SELECT je.*, u.first_name as created_by_first, u.last_name as created_by_last,
           COUNT(jel.id) as line_count,
           SUM(jel.debit) as total_debits,
           SUM(jel.credit) as total_credits
    FROM " . DB_PREFIX . "journal_entries je
    LEFT JOIN " . DB_PREFIX . "users u ON je.created_by = u.id
    LEFT JOIN " . DB_PREFIX . "journal_entry_lines jel ON je.id = jel.journal_entry_id
    {$whereClause}
    GROUP BY je.id
    ORDER BY je.entry_date DESC, je.created_at DESC
    LIMIT 50
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$journalEntries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totalEntries = count($journalEntries);
$totalDebits = 0;
$totalCredits = 0;
$referenceTypeTotals = [];

foreach ($journalEntries as $entry) {
    $totalDebits += $entry['total_debits'];
    $totalCredits += $entry['total_credits'];
    
    $type = $entry['reference_type'] ?: 'Manual';
    if (!isset($referenceTypeTotals[$type])) {
        $referenceTypeTotals[$type] = 0;
    }
    $referenceTypeTotals[$type]++;
}

// Set page title
$pageTitle = 'Journal Entries';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Journal Entries</h1>
        <p>Manage accounting journal entries and transactions</p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('accounting.create')): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Add Journal Entry
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo $totalEntries; ?></h3>
                <p>Total Entries</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($totalDebits); ?></h3>
                <p>Total Debits</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($totalCredits); ?></h3>
                <p>Total Credits</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency(abs($totalDebits - $totalCredits)); ?></h3>
                <p>Difference</p>
            </div>
        </div>
    </div>
</div>

<!-- Reference Type Summary -->
<?php if (!empty($referenceTypeTotals)): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3>Entries by Type</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($referenceTypeTotals as $type => $count): ?>
                    <div class="col-md-2">
                        <div class="type-summary">
                            <h4><?php echo htmlspecialchars($type); ?></h4>
                            <p><?php echo $count; ?> entries</p>
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
                <label for="reference_type">Type:</label>
                <select name="reference_type" id="reference_type" class="form-control">
                    <option value="">All Types</option>
                    <option value="Invoice" <?php echo $referenceType == 'Invoice' ? 'selected' : ''; ?>>Invoice</option>
                    <option value="Payment" <?php echo $referenceType == 'Payment' ? 'selected' : ''; ?>>Payment</option>
                    <option value="Expense" <?php echo $referenceType == 'Expense' ? 'selected' : ''; ?>>Expense</option>
                    <option value="Journal" <?php echo $referenceType == 'Journal' ? 'selected' : ''; ?>>Manual Journal</option>
                </select>
            </div>
            <div class="form-group">
                <label for="search">Search:</label>
                <input type="text" name="search" id="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Entry #, description" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="index.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>
</div>

<!-- Journal Entries Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Entry #</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th>Lines</th>
                        <th class="text-right">Debits</th>
                        <th class="text-right">Credits</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($journalEntries)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted">No journal entries found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($journalEntries as $entry): ?>
                        <tr>
                            <td>
                                <a href="view.php?id=<?php echo $entry['id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($entry['entry_number']); ?>
                                </a>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($entry['entry_date'])); ?></td>
                            <td><?php echo htmlspecialchars($entry['description']); ?></td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo htmlspecialchars($entry['reference_type'] ?: 'Manual'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-secondary"><?php echo $entry['line_count']; ?></span>
                            </td>
                            <td class="text-right"><?php echo formatCurrency($entry['total_debits']); ?></td>
                            <td class="text-right"><?php echo formatCurrency($entry['total_credits']); ?></td>
                            <td><?php echo htmlspecialchars($entry['created_by_first'] . ' ' . $entry['created_by_last']); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="view.php?id=<?php echo $entry['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="icon-eye"></i>
                                    </a>
                                    
                                    <?php if (hasPermission('accounting.edit') && $entry['reference_type'] == 'Journal'): ?>
                                    <a href="edit.php?id=<?php echo $entry['id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary" title="Edit">
                                        <i class="icon-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('accounting.delete') && $entry['reference_type'] == 'Journal'): ?>
                                    <button onclick="deleteEntry(<?php echo $entry['id']; ?>)" 
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
function deleteEntry(entryId) {
    if (confirm('Are you sure you want to delete this journal entry? This action cannot be undone.')) {
        fetch('delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'entry_id=' + entryId + '&csrf_token=' + document.querySelector('meta[name="csrf-token"]').getAttribute('content')
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

.type-summary {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 10px;
}

.type-summary h4 {
    margin: 0 0 8px 0;
    font-size: 14px;
    color: #6c757d;
    text-transform: uppercase;
}

.type-summary p {
    margin: 0;
    font-size: 18px;
    font-weight: bold;
    color: #333;
}

.badge-info {
    background-color: #17a2b8;
    color: white;
}

.badge-secondary {
    background-color: #6c757d;
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
