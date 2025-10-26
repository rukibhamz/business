<?php
/**
 * Business Management System - Add Journal Entry
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
requirePermission('accounting.create');

// Get database connection
$conn = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken($_POST['csrf_token'] ?? '');
    
    $errors = [];
    $success = false;
    
    // Get form data
    $entryNumber = generateJournalNumber();
    $entryDate = sanitizeInput($_POST['entry_date'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $reference = sanitizeInput($_POST['reference'] ?? '');
    
    // Get journal lines
    $lines = [];
    $lineCount = (int)($_POST['line_count'] ?? 0);
    
    for ($i = 1; $i <= $lineCount; $i++) {
        $accountId = (int)($_POST["account_id_{$i}"] ?? 0);
        $debit = (float)($_POST["debit_{$i}"] ?? 0);
        $credit = (float)($_POST["credit_{$i}"] ?? 0);
        $lineDescription = sanitizeInput($_POST["line_description_{$i}"] ?? '');
        
        if ($accountId > 0 && ($debit > 0 || $credit > 0)) {
            $lines[] = [
                'account_id' => $accountId,
                'debit' => $debit,
                'credit' => $credit,
                'description' => $lineDescription
            ];
        }
    }
    
    // Validation
    if (empty($entryDate)) {
        $errors[] = 'Entry date is required';
    }
    
    if (empty($description)) {
        $errors[] = 'Description is required';
    }
    
    if (count($lines) < 2) {
        $errors[] = 'At least 2 journal lines are required';
    }
    
    // Check if debits equal credits
    $totalDebits = 0;
    $totalCredits = 0;
    foreach ($lines as $line) {
        $totalDebits += $line['debit'];
        $totalCredits += $line['credit'];
    }
    
    if (abs($totalDebits - $totalCredits) > 0.01) {
        $errors[] = 'Total debits must equal total credits';
    }
    
    // If no errors, create journal entry
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Insert journal entry
            $stmt = $conn->prepare("
                INSERT INTO " . DB_PREFIX . "journal_entries 
                (entry_number, entry_date, description, reference, reference_type, created_by) 
                VALUES (?, ?, ?, ?, 'Journal', ?)
            ");
            
            $userId = $_SESSION['user_id'];
            $stmt->bind_param('ssssi', $entryNumber, $entryDate, $description, $reference, $userId);
            $stmt->execute();
            $entryId = $conn->getConnection()->lastInsertId();
            
            // Insert journal lines
            foreach ($lines as $line) {
                $stmt = $conn->prepare("
                    INSERT INTO " . DB_PREFIX . "journal_entry_lines 
                    (journal_entry_id, account_id, debit, credit, description) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('iidds', $entryId, $line['account_id'], $line['debit'], $line['credit'], $line['description']);
                $stmt->execute();
                
                // Update account balance
                updateAccountBalance($line['account_id'], $line['debit'], $line['credit']);
            }
            
            // Log activity
            logActivity('accounting.create', "Journal entry created: {$entryNumber}", [
                'entry_id' => $entryId,
                'entry_number' => $entryNumber,
                'description' => $description,
                'line_count' => count($lines)
            ]);
            
            $conn->commit();
            $success = true;
            $_SESSION['success'] = 'Journal entry created successfully!';
            
            header('Location: view.php?id=' . $entryId);
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error creating journal entry: ' . $e->getMessage();
        }
    }
}

// Get accounts for dropdown
$accounts = $conn->query("
    SELECT id, account_code, account_name, account_type, account_subtype 
    FROM " . DB_PREFIX . "accounts 
    WHERE is_active = 1 
    ORDER BY account_code
")->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = 'Add Journal Entry';

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Add Journal Entry</h1>
        <p>Create a manual journal entry</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Journal Entries
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <h4>Please correct the following errors:</h4>
    <ul>
        <?php foreach ($errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Journal Entry Information</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="journal-form">
                    <?php csrfField(); ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Entry Number</label>
                                <input type="text" name="entry_number" value="<?php echo generateJournalNumber(); ?>" readonly class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Entry Date</label>
                                <input type="date" name="entry_date" value="<?php echo date('Y-m-d'); ?>" required class="form-control">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required">Description</label>
                                <input type="text" name="description" required class="form-control" placeholder="Brief description of the entry">
                            </div>
                            
                            <div class="form-group">
                                <label>Reference</label>
                                <input type="text" name="reference" class="form-control" placeholder="Optional reference number">
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="journal-lines">
                        <h4>Journal Lines</h4>
                        <p class="text-muted">Add at least 2 lines. Total debits must equal total credits.</p>
                        
                        <div id="journal-lines-container">
                            <!-- Journal lines will be added here by JavaScript -->
                        </div>
                        
                        <div class="form-group">
                            <button type="button" id="add-line" class="btn btn-outline-primary">
                                <i class="icon-plus"></i> Add Line
                            </button>
                        </div>
                        
                        <div class="totals-summary">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Total Debits: <span id="total-debits">₦0.00</span></strong>
                                </div>
                                <div class="col-md-6">
                                    <strong>Total Credits: <span id="total-credits">₦0.00</span></strong>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <strong>Difference: <span id="difference">₦0.00</span></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="icon-save"></i> Create Journal Entry
                        </button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>Journal Entry Guidelines</h3>
            </div>
            <div class="card-body">
                <div class="guidelines">
                    <h4>Double-Entry Rules</h4>
                    <ul>
                        <li>Every transaction must have at least 2 lines</li>
                        <li>Total debits must equal total credits</li>
                        <li>Each line must have either a debit or credit amount</li>
                        <li>Account balances are updated automatically</li>
                    </ul>
                    
                    <h4>Account Types</h4>
                    <ul>
                        <li><strong>Assets:</strong> Normal debit balance</li>
                        <li><strong>Liabilities:</strong> Normal credit balance</li>
                        <li><strong>Equity:</strong> Normal credit balance</li>
                        <li><strong>Income:</strong> Normal credit balance</li>
                        <li><strong>Expenses:</strong> Normal debit balance</li>
                    </ul>
                    
                    <h4>Common Entries</h4>
                    <ul>
                        <li>Asset purchases</li>
                        <li>Loan transactions</li>
                        <li>Adjusting entries</li>
                        <li>Corrections</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Quick Tips</h3>
            </div>
            <div class="card-body">
                <ul class="tips-list">
                    <li>Use clear, descriptive descriptions</li>
                    <li>Include reference numbers for traceability</li>
                    <li>Verify account codes before submitting</li>
                    <li>Check that debits equal credits</li>
                    <li>Review entries before posting</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
let lineCount = 0;
const accounts = <?php echo json_encode($accounts); ?>;

function addJournalLine() {
    lineCount++;
    const container = document.getElementById('journal-lines-container');
    
    const lineHtml = `
        <div class="journal-line" data-line="${lineCount}">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Account</label>
                        <select name="account_id_${lineCount}" class="form-control account-select" required>
                            <option value="">Select Account</option>
                            ${accounts.map(account => 
                                `<option value="${account.id}">${account.account_code} - ${account.account_name}</option>`
                            ).join('')}
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Debit</label>
                        <input type="number" name="debit_${lineCount}" step="0.01" min="0" class="form-control debit-input" placeholder="0.00">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Credit</label>
                        <input type="number" name="credit_${lineCount}" step="0.01" min="0" class="form-control credit-input" placeholder="0.00">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="line_description_${lineCount}" class="form-control" placeholder="Line description">
                    </div>
                </div>
                <div class="col-md-1">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-line" title="Remove Line">
                            <i class="icon-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', lineHtml);
    updateTotals();
}

function removeJournalLine(lineNumber) {
    const line = document.querySelector(`[data-line="${lineNumber}"]`);
    if (line) {
        line.remove();
        updateTotals();
    }
}

function updateTotals() {
    let totalDebits = 0;
    let totalCredits = 0;
    
    document.querySelectorAll('.debit-input').forEach(input => {
        totalDebits += parseFloat(input.value) || 0;
    });
    
    document.querySelectorAll('.credit-input').forEach(input => {
        totalCredits += parseFloat(input.value) || 0;
    });
    
    document.getElementById('total-debits').textContent = '₦' + totalDebits.toFixed(2);
    document.getElementById('total-credits').textContent = '₦' + totalCredits.toFixed(2);
    
    const difference = totalDebits - totalCredits;
    const differenceElement = document.getElementById('difference');
    differenceElement.textContent = '₦' + difference.toFixed(2);
    
    if (Math.abs(difference) < 0.01) {
        differenceElement.className = 'text-success';
    } else {
        differenceElement.className = 'text-danger';
    }
}

// Event listeners
document.getElementById('add-line').addEventListener('click', addJournalLine);

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-line')) {
        const lineNumber = e.target.closest('.journal-line').dataset.line;
        removeJournalLine(lineNumber);
    }
});

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('debit-input') || e.target.classList.contains('credit-input')) {
        updateTotals();
    }
});

// Add initial lines
addJournalLine();
addJournalLine();
</script>

<style>
.guidelines h4 {
    margin: 15px 0 8px 0;
    font-size: 14px;
    color: #333;
    font-weight: 600;
}

.guidelines ul {
    margin: 0 0 15px 0;
    padding-left: 20px;
}

.guidelines li {
    margin-bottom: 4px;
    font-size: 14px;
    color: #6c757d;
}

.tips-list {
    margin: 0;
    padding-left: 20px;
}

.tips-list li {
    margin-bottom: 8px;
    font-size: 14px;
    color: #6c757d;
}

.required::after {
    content: " *";
    color: #dc3545;
}

.journal-line {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    background: #f8f9fa;
}

.totals-summary {
    background: #e9ecef;
    padding: 15px;
    border-radius: 8px;
    margin-top: 20px;
}

.totals-summary .row {
    margin-bottom: 10px;
}

.totals-summary .row:last-child {
    margin-bottom: 0;
}

.text-success {
    color: #28a745 !important;
}

.text-danger {
    color: #dc3545 !important;
}

.form-actions {
    text-align: center;
    padding: 20px 0;
    border-top: 1px solid #dee2e6;
    margin-top: 20px;
}

.form-actions .btn {
    margin: 0 10px;
}
</style>

<?php include '../../../includes/footer.php'; ?>
