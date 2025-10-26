<?php
/**
 * Business Management System - Add Account
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
    $accountCode = sanitizeInput($_POST['account_code'] ?? '');
    $accountName = sanitizeInput($_POST['account_name'] ?? '');
    $accountType = sanitizeInput($_POST['account_type'] ?? '');
    $accountSubtype = sanitizeInput($_POST['account_subtype'] ?? '');
    $parentAccountId = !empty($_POST['parent_account_id']) ? (int)$_POST['parent_account_id'] : null;
    $description = sanitizeInput($_POST['description'] ?? '');
    $openingBalance = (float)($_POST['opening_balance'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($accountCode)) {
        $errors[] = 'Account code is required';
    } elseif (!preg_match('/^[0-9A-Za-z\-]+$/', $accountCode)) {
        $errors[] = 'Account code can only contain letters, numbers, and hyphens';
    } else {
        // Check if account code already exists
        $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "accounts WHERE account_code = ?");
        $stmt->bind_param('s', $accountCode);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $errors[] = 'Account code already exists';
        }
    }
    
    if (empty($accountName)) {
        $errors[] = 'Account name is required';
    }
    
    if (empty($accountType)) {
        $errors[] = 'Account type is required';
    }
    
    if (empty($accountSubtype)) {
        $errors[] = 'Account subtype is required';
    }
    
    // Validate parent account
    if ($parentAccountId) {
        $stmt = $conn->prepare("
            SELECT id, account_type 
            FROM " . DB_PREFIX . "accounts 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->bind_param('i', $parentAccountId);
        $stmt->execute();
        $parentAccount = $stmt->get_result()->fetch_assoc();
        
        if (!$parentAccount) {
            $errors[] = 'Invalid parent account selected';
        } elseif ($parentAccount['account_type'] != $accountType) {
            $errors[] = 'Parent account must be of the same type';
        }
    }
    
    // If no errors, create account
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Insert account
            $stmt = $conn->prepare("
                INSERT INTO " . DB_PREFIX . "accounts 
                (account_code, account_name, account_type, account_subtype, parent_account_id, 
                 description, opening_balance, current_balance, is_active, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $userId = $_SESSION['user_id'];
            $stmt->bind_param('ssssisddii', 
                $accountCode, $accountName, $accountType, $accountSubtype, $parentAccountId,
                $description, $openingBalance, $openingBalance, $isActive, $userId
            );
            
            if ($stmt->execute()) {
                $accountId = $conn->getConnection()->lastInsertId();
                
                // Create journal entry for opening balance if not zero
                if ($openingBalance != 0) {
                    $lines = [];
                    
                    if ($openingBalance > 0) {
                        // Positive opening balance
                        if (in_array($accountType, ['Asset', 'Expense'])) {
                            $lines[] = [
                                'account_id' => $accountId,
                                'debit' => $openingBalance,
                                'credit' => 0,
                                'description' => 'Opening balance for ' . $accountName
                            ];
                            $lines[] = [
                                'account_id' => 3010, // Owner Equity
                                'debit' => 0,
                                'credit' => $openingBalance,
                                'description' => 'Opening balance equity'
                            ];
                        } else {
                            $lines[] = [
                                'account_id' => $accountId,
                                'debit' => 0,
                                'credit' => $openingBalance,
                                'description' => 'Opening balance for ' . $accountName
                            ];
                            $lines[] = [
                                'account_id' => 1010, // Cash
                                'debit' => $openingBalance,
                                'credit' => 0,
                                'description' => 'Opening balance cash'
                            ];
                        }
                    } else {
                        // Negative opening balance
                        $absBalance = abs($openingBalance);
                        if (in_array($accountType, ['Asset', 'Expense'])) {
                            $lines[] = [
                                'account_id' => $accountId,
                                'debit' => 0,
                                'credit' => $absBalance,
                                'description' => 'Opening balance for ' . $accountName
                            ];
                            $lines[] = [
                                'account_id' => 1010, // Cash
                                'debit' => $absBalance,
                                'credit' => 0,
                                'description' => 'Opening balance cash'
                            ];
                        } else {
                            $lines[] = [
                                'account_id' => $accountId,
                                'debit' => $absBalance,
                                'credit' => 0,
                                'description' => 'Opening balance for ' . $accountName
                            ];
                            $lines[] = [
                                'account_id' => 3010, // Owner Equity
                                'debit' => 0,
                                'credit' => $absBalance,
                                'description' => 'Opening balance equity'
                            ];
                        }
                    }
                    
                    createJournalEntry(
                        date('Y-m-d'),
                        'Opening balance for account ' . $accountCode . ' - ' . $accountName,
                        $lines,
                        'System',
                        $accountId,
                        'account'
                    );
                }
                
                // Log activity
                logActivity('accounting.create', "Account created: {$accountCode} - {$accountName}", [
                    'account_id' => $accountId,
                    'account_code' => $accountCode,
                    'account_name' => $accountName,
                    'account_type' => $accountType
                ]);
                
                $conn->commit();
                $success = true;
                $_SESSION['success'] = 'Account created successfully';
                
                header('Location: index.php');
                exit;
            } else {
                $errors[] = 'Failed to create account. Please try again.';
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error creating account: ' . $e->getMessage();
        }
    }
}

// Get parent accounts for dropdown
$parentAccounts = $conn->query("
    SELECT id, account_code, account_name, account_type 
    FROM " . DB_PREFIX . "accounts 
    WHERE is_active = 1 AND account_subtype != 'Header'
    ORDER BY account_type, account_code
")->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = 'Add Account';

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Add New Account</h1>
        <p>Create a new account in your chart of accounts</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Accounts
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
                <h3>Account Information</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="form-horizontal">
                    <?php csrfField(); ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="account_code" class="required">Account Code</label>
                            <input type="text" id="account_code" name="account_code" 
                                   value="<?php echo htmlspecialchars($accountCode ?? ''); ?>" 
                                   required class="form-control" pattern="[0-9A-Za-z\-]+">
                            <small class="form-text">Unique code for this account (e.g., 1040, CASH-001)</small>
                        </div>
                        <div class="form-group">
                            <label for="account_name" class="required">Account Name</label>
                            <input type="text" id="account_name" name="account_name" 
                                   value="<?php echo htmlspecialchars($accountName ?? ''); ?>" 
                                   required class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="account_type" class="required">Account Type</label>
                            <select id="account_type" name="account_type" required class="form-control">
                                <option value="">Select Type</option>
                                <option value="Asset" <?php echo ($accountType ?? '') == 'Asset' ? 'selected' : ''; ?>>Asset</option>
                                <option value="Liability" <?php echo ($accountType ?? '') == 'Liability' ? 'selected' : ''; ?>>Liability</option>
                                <option value="Equity" <?php echo ($accountType ?? '') == 'Equity' ? 'selected' : ''; ?>>Equity</option>
                                <option value="Income" <?php echo ($accountType ?? '') == 'Income' ? 'selected' : ''; ?>>Income</option>
                                <option value="Expense" <?php echo ($accountType ?? '') == 'Expense' ? 'selected' : ''; ?>>Expense</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="account_subtype" class="required">Account Subtype</label>
                            <select id="account_subtype" name="account_subtype" required class="form-control">
                                <option value="">Select Subtype</option>
                                <!-- Options will be populated by JavaScript -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="parent_account_id">Parent Account</label>
                        <select id="parent_account_id" name="parent_account_id" class="form-control">
                            <option value="">No Parent Account</option>
                            <?php foreach ($parentAccounts as $parent): ?>
                            <option value="<?php echo $parent['id']; ?>" 
                                    data-type="<?php echo $parent['account_type']; ?>"
                                    <?php echo ($parentAccountId ?? 0) == $parent['id'] ? 'selected' : ''; ?>>
                                <?php echo $parent['account_code'] . ' - ' . $parent['account_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Optional: Select a parent account to create a sub-account</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" 
                                  class="form-control" rows="3"
                                  placeholder="Optional description of this account's purpose"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="opening_balance">Opening Balance</label>
                            <input type="number" id="opening_balance" name="opening_balance" 
                                   value="<?php echo $openingBalance ?? 0; ?>" 
                                   step="0.01" class="form-control">
                            <small class="form-text">Initial balance for this account (can be zero)</small>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <div class="form-check">
                                <input type="checkbox" id="is_active" name="is_active" 
                                       <?php echo ($isActive ?? 1) ? 'checked' : ''; ?> class="form-check-input">
                                <label for="is_active" class="form-check-label">Active</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="icon-plus"></i> Create Account
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
                <h3>Account Types Guide</h3>
            </div>
            <div class="card-body">
                <div class="account-type-guide">
                    <div class="type-item">
                        <h4 class="type-asset">Assets</h4>
                        <p>Resources owned by the business (Cash, Inventory, Equipment)</p>
                        <ul>
                            <li>Current Assets</li>
                            <li>Non-Current Assets</li>
                            <li>Fixed Assets</li>
                        </ul>
                    </div>
                    
                    <div class="type-item">
                        <h4 class="type-liability">Liabilities</h4>
                        <p>Debts and obligations (Accounts Payable, Loans)</p>
                        <ul>
                            <li>Current Liabilities</li>
                            <li>Long-term Liabilities</li>
                        </ul>
                    </div>
                    
                    <div class="type-item">
                        <h4 class="type-equity">Equity</h4>
                        <p>Owner's claim on business assets</p>
                        <ul>
                            <li>Owner Equity</li>
                            <li>Retained Earnings</li>
                        </ul>
                    </div>
                    
                    <div class="type-item">
                        <h4 class="type-income">Income</h4>
                        <p>Revenue from business operations</p>
                        <ul>
                            <li>Operating Income</li>
                            <li>Other Income</li>
                        </ul>
                    </div>
                    
                    <div class="type-item">
                        <h4 class="type-expense">Expenses</h4>
                        <p>Costs incurred in business operations</p>
                        <ul>
                            <li>Operating Expenses</li>
                            <li>Cost of Goods Sold</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Account subtype options
const subtypeOptions = {
    'Asset': [
        { value: 'Current Asset', text: 'Current Asset' },
        { value: 'Non-Current Asset', text: 'Non-Current Asset' },
        { value: 'Fixed Asset', text: 'Fixed Asset' }
    ],
    'Liability': [
        { value: 'Current Liability', text: 'Current Liability' },
        { value: 'Long-term Liability', text: 'Long-term Liability' }
    ],
    'Equity': [
        { value: 'Owner Equity', text: 'Owner Equity' },
        { value: 'Retained Earnings', text: 'Retained Earnings' },
        { value: 'Current Earnings', text: 'Current Earnings' }
    ],
    'Income': [
        { value: 'Operating Income', text: 'Operating Income' },
        { value: 'Other Income', text: 'Other Income' }
    ],
    'Expense': [
        { value: 'Operating Expense', text: 'Operating Expense' },
        { value: 'Cost of Goods Sold', text: 'Cost of Goods Sold' },
        { value: 'Other Expense', text: 'Other Expense' }
    ]
};

// Update subtype options when account type changes
document.getElementById('account_type').addEventListener('change', function() {
    const accountType = this.value;
    const subtypeSelect = document.getElementById('account_subtype');
    
    // Clear existing options
    subtypeSelect.innerHTML = '<option value="">Select Subtype</option>';
    
    // Add new options
    if (subtypeOptions[accountType]) {
        subtypeOptions[accountType].forEach(option => {
            const optionElement = document.createElement('option');
            optionElement.value = option.value;
            optionElement.textContent = option.text;
            subtypeSelect.appendChild(optionElement);
        });
    }
    
    // Filter parent accounts
    filterParentAccounts(accountType);
});

// Filter parent accounts by type
function filterParentAccounts(accountType) {
    const parentSelect = document.getElementById('parent_account_id');
    const options = parentSelect.querySelectorAll('option');
    
    options.forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
        } else {
            const optionType = option.getAttribute('data-type');
            option.style.display = optionType === accountType ? 'block' : 'none';
        }
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const accountType = document.getElementById('account_type').value;
    if (accountType) {
        filterParentAccounts(accountType);
    }
});
</script>

<style>
.account-type-guide .type-item {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.account-type-guide .type-item:last-child {
    border-bottom: none;
}

.account-type-guide h4 {
    margin: 0 0 8px 0;
    font-size: 16px;
    font-weight: 600;
}

.type-asset { color: #007bff; }
.type-liability { color: #dc3545; }
.type-equity { color: #6f42c1; }
.type-income { color: #28a745; }
.type-expense { color: #fd7e14; }

.account-type-guide ul {
    margin: 8px 0 0 0;
    padding-left: 20px;
    font-size: 14px;
    color: #6c757d;
}

.account-type-guide li {
    margin-bottom: 4px;
}

.form-check {
    margin-top: 8px;
}

.form-check-input {
    margin-right: 8px;
}
</style>

<?php include '../../../includes/footer.php'; ?>
