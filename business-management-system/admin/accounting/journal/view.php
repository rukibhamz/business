<?php
/**
 * Business Management System - View Journal Entry
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

// Get journal entry ID
$entryId = (int)($_GET['id'] ?? 0);

if (!$entryId) {
    header('Location: index.php');
    exit;
}

// Get journal entry details
$stmt = $conn->prepare("
    SELECT je.*, u.first_name as created_by_first, u.last_name as created_by_last
    FROM " . DB_PREFIX . "journal_entries je
    LEFT JOIN " . DB_PREFIX . "users u ON je.created_by = u.id
    WHERE je.id = ?
");
$stmt->bind_param('i', $entryId);
$stmt->execute();
$entry = $stmt->get_result()->fetch_assoc();

if (!$entry) {
    header('Location: index.php');
    exit;
}

// Get journal entry lines
$stmt = $conn->prepare("
    SELECT jel.*, a.account_code, a.account_name, a.account_type, a.account_subtype
    FROM " . DB_PREFIX . "journal_entry_lines jel
    JOIN " . DB_PREFIX . "accounts a ON jel.account_id = a.id
    WHERE jel.journal_entry_id = ?
    ORDER BY jel.id
");
$stmt->bind_param('i', $entryId);
$stmt->execute();
$lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totalDebits = 0;
$totalCredits = 0;
foreach ($lines as $line) {
    $totalDebits += $line['debit'];
    $totalCredits += $line['credit'];
}

// Set page title
$pageTitle = 'Journal Entry - ' . $entry['entry_number'];

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Journal Entry #<?php echo htmlspecialchars($entry['entry_number']); ?></h1>
        <p><?php echo htmlspecialchars($entry['description']); ?></p>
    </div>
    <div class="page-actions">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="icon-print"></i> Print
        </button>
        <?php if ($entry['reference_type'] == 'Journal' && hasPermission('accounting.edit')): ?>
        <a href="edit.php?id=<?php echo $entryId; ?>" class="btn btn-warning">
            <i class="icon-edit"></i> Edit
        </a>
        <?php endif; ?>
        <?php if ($entry['reference_type'] == 'Journal' && hasPermission('accounting.delete')): ?>
        <button onclick="deleteEntry(<?php echo $entryId; ?>)" class="btn btn-danger">
            <i class="icon-trash"></i> Delete
        </button>
        <?php endif; ?>
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Journal Entries
        </a>
    </div>
</div>

<!-- Journal Entry Details -->
<div class="card">
    <div class="card-header">
        <h3>Journal Entry Information</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Entry Number:</strong></td>
                        <td><?php echo htmlspecialchars($entry['entry_number']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Entry Date:</strong></td>
                        <td><?php echo date('M d, Y', strtotime($entry['entry_date'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Description:</strong></td>
                        <td><?php echo htmlspecialchars($entry['description']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Reference:</strong></td>
                        <td><?php echo htmlspecialchars($entry['reference'] ?: '-'); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Type:</strong></td>
                        <td>
                            <span class="badge badge-info">
                                <?php echo htmlspecialchars($entry['reference_type'] ?: 'Manual'); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Created By:</strong></td>
                        <td><?php echo htmlspecialchars($entry['created_by_first'] . ' ' . $entry['created_by_last']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Created Date:</strong></td>
                        <td><?php echo date('M d, Y H:i', strtotime($entry['created_at'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Last Updated:</strong></td>
                        <td><?php echo date('M d, Y H:i', strtotime($entry['updated_at'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Journal Entry Lines -->
<div class="card">
    <div class="card-header">
        <h3>Journal Entry Lines</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Account Code</th>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Credit</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($line['account_code']); ?></td>
                        <td><?php echo htmlspecialchars($line['account_name']); ?></td>
                        <td>
                            <span class="badge badge-secondary">
                                <?php echo htmlspecialchars($line['account_type']); ?>
                            </span>
                        </td>
                        <td class="text-right">
                            <?php if ($line['debit'] > 0): ?>
                                <?php echo formatCurrency($line['debit']); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <?php if ($line['credit'] > 0): ?>
                                <?php echo formatCurrency($line['credit']); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($line['description'] ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3"><strong>TOTAL</strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($totalDebits); ?></strong></td>
                        <td class="text-right"><strong><?php echo formatCurrency($totalCredits); ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Balance Verification -->
<div class="card">
    <div class="card-header">
        <h3>Balance Verification</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <div class="balance-item">
                    <h4><?php echo formatCurrency($totalDebits); ?></h4>
                    <p>Total Debits</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="balance-item">
                    <h4><?php echo formatCurrency($totalCredits); ?></h4>
                    <p>Total Credits</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="balance-item">
                    <h4 class="<?php echo abs($totalDebits - $totalCredits) < 0.01 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo formatCurrency(abs($totalDebits - $totalCredits)); ?>
                    </h4>
                    <p>Difference</p>
                </div>
            </div>
        </div>
        
        <?php if (abs($totalDebits - $totalCredits) < 0.01): ?>
        <div class="alert alert-success mt-3">
            <i class="icon-check"></i> <strong>Balanced:</strong> This journal entry is properly balanced.
        </div>
        <?php else: ?>
        <div class="alert alert-danger mt-3">
            <i class="icon-warning"></i> <strong>Unbalanced:</strong> This journal entry is not balanced. Please review the amounts.
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reference Information -->
<?php if ($entry['reference_type'] && $entry['reference_id']): ?>
<div class="card">
    <div class="card-header">
        <h3>Reference Information</h3>
    </div>
    <div class="card-body">
        <p><strong>Reference Type:</strong> <?php echo htmlspecialchars($entry['reference_type']); ?></p>
        <p><strong>Reference ID:</strong> <?php echo $entry['reference_id']; ?></p>
        
        <?php if ($entry['reference_type'] == 'Invoice'): ?>
        <p><strong>Related Document:</strong> 
            <a href="../invoices/view.php?id=<?php echo $entry['reference_id']; ?>" class="text-decoration-none">
                Invoice #<?php echo $entry['reference_id']; ?>
            </a>
        </p>
        <?php elseif ($entry['reference_type'] == 'Payment'): ?>
        <p><strong>Related Document:</strong> 
            <a href="../payments/view.php?id=<?php echo $entry['reference_id']; ?>" class="text-decoration-none">
                Payment #<?php echo $entry['reference_id']; ?>
            </a>
        </p>
        <?php elseif ($entry['reference_type'] == 'Expense'): ?>
        <p><strong>Related Document:</strong> 
            <a href="../expenses/view.php?id=<?php echo $entry['reference_id']; ?>" class="text-decoration-none">
                Expense #<?php echo $entry['reference_id']; ?>
            </a>
        </p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

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
                location.href = 'index.php';
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}
</script>

<style>
.badge-info {
    background-color: #17a2b8;
    color: white;
}

.badge-secondary {
    background-color: #6c757d;
    color: white;
}

.total-row {
    background-color: #e9ecef;
    font-weight: bold;
    border-top: 2px solid #333;
}

.balance-item {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 15px;
}

.balance-item h4 {
    margin: 0 0 8px 0;
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

.balance-item p {
    margin: 0;
    color: #6c757d;
    font-size: 14px;
}

.text-success {
    color: #28a745 !important;
}

.text-danger {
    color: #dc3545 !important;
}

@media print {
    .page-actions { display: none; }
    .card { box-shadow: none; border: 1px solid #ddd; }
}
</style>

<?php include '../../../includes/footer.php'; ?>
