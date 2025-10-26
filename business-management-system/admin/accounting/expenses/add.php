<?php
/**
 * Business Management System - Add Expense
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
    $expenseNumber = generateExpenseNumber();
    $expenseDate = sanitizeInput($_POST['expense_date'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $accountId = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null;
    $amount = (float)($_POST['amount'] ?? 0);
    $paymentMethod = sanitizeInput($_POST['payment_method'] ?? '');
    $reference = sanitizeInput($_POST['reference'] ?? '');
    $vendorName = sanitizeInput($_POST['vendor_name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $taxAmount = (float)($_POST['tax_amount'] ?? 0);
    $isBillable = isset($_POST['is_billable']) ? 1 : 0;
    $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $status = sanitizeInput($_POST['status'] ?? 'Pending');
    
    // Handle file upload
    $receiptFile = null;
    if (!empty($_FILES['receipt_file']['name'])) {
        $uploadDir = '../../../../uploads/expenses/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExt = pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION);
        $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (in_array(strtolower($fileExt), $allowedExts)) {
            $fileName = uniqid() . '.' . $fileExt;
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $filePath)) {
                $receiptFile = 'uploads/expenses/' . $fileName;
            }
        } else {
            $errors[] = 'Invalid file type. Only JPG, PNG, and PDF files are allowed.';
        }
    }
    
    // Validation
    if ($categoryId <= 0) {
        $errors[] = 'Please select a category';
    }
    
    if (empty($expenseDate)) {
        $errors[] = 'Expense date is required';
    }
    
    if ($amount <= 0) {
        $errors[] = 'Expense amount must be greater than zero';
    }
    
    if (empty($paymentMethod)) {
        $errors[] = 'Payment method is required';
    }
    
    if (empty($description)) {
        $errors[] = 'Description is required';
    }
    
    if ($taxAmount < 0) {
        $errors[] = 'Tax amount cannot be negative';
    }
    
    // If no errors, create expense
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Insert expense
            $stmt = $conn->prepare("
                INSERT INTO " . DB_PREFIX . "expenses 
                (expense_number, expense_date, category_id, account_id, amount, 
                 payment_method, reference, vendor_name, description, receipt_file,
                 tax_amount, is_billable, customer_id, status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $userId = $_SESSION['user_id'];
            $stmt->bind_param('ssiidsssssdissi',
                $expenseNumber, $expenseDate, $categoryId, $accountId, $amount,
                $paymentMethod, $reference, $vendorName, $description, $receiptFile,
                $taxAmount, $isBillable, $customerId, $status, $userId
            );
            $stmt->execute();
            $expenseId = $conn->getConnection()->lastInsertId();
            
            // Create journal entry if approved
            if ($status == 'Approved') {
                $expenseAccountId = $accountId ?? 5000; // Default expense account
                
                $lines = [
                    [
                        'account_id' => $expenseAccountId,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => 'Expense - ' . $description
                    ],
                    [
                        'account_id' => 1010, // Cash or bank account
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => 'Payment for expense'
                    ]
                ];
                
                createJournalEntry(
                    $expenseDate,
                    'Expense ' . $expenseNumber . ' - ' . $vendorName,
                    $lines,
                    'Expense',
                    $expenseId,
                    'expense'
                );
            }
            
            // Log activity
            logActivity('accounting.create', "Expense created: {$expenseNumber}", [
                'expense_id' => $expenseId,
                'expense_number' => $expenseNumber,
                'category_id' => $categoryId,
                'amount' => $amount,
                'vendor_name' => $vendorName
            ]);
            
            $conn->commit();
            $success = true;
            $_SESSION['success'] = 'Expense recorded successfully!';
            
            header('Location: view.php?id=' . $expenseId);
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error recording expense: ' . $e->getMessage();
        }
    }
}

// Get expense categories for dropdown
$categories = $conn->query("
    SELECT id, category_name 
    FROM " . DB_PREFIX . "expense_categories 
    WHERE is_active = 1 
    ORDER BY category_name
")->fetch_all(MYSQLI_ASSOC);

// Get expense accounts for dropdown
$expenseAccounts = $conn->query("
    SELECT id, account_code, account_name 
    FROM " . DB_PREFIX . "accounts 
    WHERE account_type = 'Expense' 
    AND is_active = 1
    ORDER BY account_code
")->fetch_all(MYSQLI_ASSOC);

// Get customers for billable expenses
$customers = $conn->query("
    SELECT id, first_name, last_name, company_name, customer_type 
    FROM " . DB_PREFIX . "customers 
    WHERE is_active = 1 
    ORDER BY company_name, first_name, last_name
")->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = 'Add Expense';

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Add Expense</h1>
        <p>Record a new business expense</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Expenses
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
                <h3>Expense Information</h3>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?php csrfField(); ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Expense Number</label>
                                <input type="text" name="expense_number" value="<?php echo generateExpenseNumber(); ?>" readonly class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Expense Date</label>
                                <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Category</label>
                                <select name="category_id" required class="form-control">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo ($categoryId ?? 0) == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">
                                    <a href="categories.php" target="_blank">Manage Categories</a>
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label>Expense Account</label>
                                <select name="account_id" class="form-control">
                                    <option value="">Select Account</option>
                                    <?php foreach ($expenseAccounts as $account): ?>
                                    <option value="<?php echo $account['id']; ?>" 
                                            <?php echo ($accountId ?? 0) == $account['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required">Amount</label>
                                <input type="number" name="amount" step="0.01" min="0" required class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label>Tax Amount</label>
                                <input type="number" name="tax_amount" step="0.01" value="0" min="0" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Payment Method</label>
                                <select name="payment_method" required class="form-control">
                                    <option value="">Select Method</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Credit Card">Credit Card</option>
                                    <option value="Check">Check</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Reference</label>
                                <input type="text" name="reference" placeholder="Receipt #, Invoice #, etc." class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label>Vendor Name</label>
                                <input type="text" name="vendor_name" placeholder="Supplier or vendor name" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Description</label>
                        <textarea name="description" rows="3" required class="form-control" placeholder="Detailed description of the expense"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Attach Receipt</label>
                        <input type="file" name="receipt_file" accept="image/*,.pdf" class="form-control">
                        <small class="form-text text-muted">Supported: JPG, PNG, PDF (Max 5MB)</small>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" name="is_billable" value="1" id="is_billable" class="form-check-input">
                            <label for="is_billable" class="form-check-label">Billable to Customer</label>
                        </div>
                    </div>
                    
                    <div class="form-group" id="customer-group" style="display:none;">
                        <label>Customer</label>
                        <select name="customer_id" class="form-control">
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>">
                                <?php echo getCustomerDisplayName($customer); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="status" value="Pending" class="btn btn-secondary">
                            <i class="icon-save"></i> Save as Pending
                        </button>
                        <button type="submit" name="status" value="Approved" class="btn btn-primary">
                            <i class="icon-check"></i> Save & Approve
                        </button>
                        <a href="index.php" class="btn btn-link">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>Expense Guidelines</h3>
            </div>
            <div class="card-body">
                <div class="guidelines">
                    <h4>Required Information</h4>
                    <ul>
                        <li>Expense date</li>
                        <li>Category</li>
                        <li>Amount</li>
                        <li>Payment method</li>
                        <li>Description</li>
                    </ul>
                    
                    <h4>Optional Information</h4>
                    <ul>
                        <li>Receipt attachment</li>
                        <li>Vendor name</li>
                        <li>Reference number</li>
                        <li>Tax amount</li>
                        <li>Customer (if billable)</li>
                    </ul>
                    
                    <h4>Approval Process</h4>
                    <p>Expenses can be saved as pending for later approval, or approved immediately. Approved expenses are automatically recorded in the accounting system.</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Quick Tips</h3>
            </div>
            <div class="card-body">
                <ul class="tips-list">
                    <li>Always attach receipts when available</li>
                    <li>Use specific descriptions for better tracking</li>
                    <li>Mark billable expenses for client billing</li>
                    <li>Include reference numbers for traceability</li>
                    <li>Review amounts before submitting</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelector('input[name="is_billable"]').addEventListener('change', function() {
    document.getElementById('customer-group').style.display = this.checked ? 'block' : 'none';
});
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

.form-actions {
    text-align: center;
    padding: 20px 0;
    border-top: 1px solid #dee2e6;
    margin-top: 20px;
}

.form-actions .btn {
    margin: 0 10px;
}

.form-check {
    margin-top: 8px;
}

.form-check-input {
    margin-right: 8px;
}
</style>

<?php include '../../../includes/footer.php'; ?>
