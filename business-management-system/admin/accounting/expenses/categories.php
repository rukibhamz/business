<?php
/**
 * Business Management System - Expense Categories Management
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
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $categoryName = sanitizeInput($_POST['category_name'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $defaultAccountId = !empty($_POST['default_account_id']) ? (int)$_POST['default_account_id'] : null;
                
                if (empty($categoryName)) {
                    $errors[] = 'Category name is required';
                } else {
                    // Check if category already exists
                    $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "expense_categories WHERE category_name = ?");
                    $stmt->bind_param('s', $categoryName);
                    $stmt->execute();
                    if ($stmt->get_result()->fetch_assoc()) {
                        $errors[] = 'Category name already exists';
                    }
                }
                
                if (empty($errors)) {
                    $stmt = $conn->prepare("
                        INSERT INTO " . DB_PREFIX . "expense_categories 
                        (category_name, description, default_account_id) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->bind_param('ssi', $categoryName, $description, $defaultAccountId);
                    
                    if ($stmt->execute()) {
                        $success = true;
                        $_SESSION['success'] = 'Category added successfully';
                        header('Location: categories.php');
                        exit;
                    } else {
                        $errors[] = 'Failed to add category';
                    }
                }
                break;
                
            case 'edit':
                $categoryId = (int)($_POST['category_id'] ?? 0);
                $categoryName = sanitizeInput($_POST['category_name'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $defaultAccountId = !empty($_POST['default_account_id']) ? (int)$_POST['default_account_id'] : null;
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($categoryName)) {
                    $errors[] = 'Category name is required';
                }
                
                if (empty($errors)) {
                    $stmt = $conn->prepare("
                        UPDATE " . DB_PREFIX . "expense_categories 
                        SET category_name = ?, description = ?, default_account_id = ?, is_active = ?
                        WHERE id = ?
                    ");
                    $stmt->bind_param('ssiii', $categoryName, $description, $defaultAccountId, $isActive, $categoryId);
                    
                    if ($stmt->execute()) {
                        $success = true;
                        $_SESSION['success'] = 'Category updated successfully';
                        header('Location: categories.php');
                        exit;
                    } else {
                        $errors[] = 'Failed to update category';
                    }
                }
                break;
                
            case 'delete':
                $categoryId = (int)($_POST['category_id'] ?? 0);
                
                // Check if category has expenses
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "expenses WHERE category_id = ?");
                $stmt->bind_param('i', $categoryId);
                $stmt->execute();
                $expenseCount = $stmt->get_result()->fetch_assoc()['count'];
                
                if ($expenseCount > 0) {
                    $errors[] = 'Cannot delete category with existing expenses';
                } else {
                    $stmt = $conn->prepare("DELETE FROM " . DB_PREFIX . "expense_categories WHERE id = ?");
                    $stmt->bind_param('i', $categoryId);
                    
                    if ($stmt->execute()) {
                        $success = true;
                        $_SESSION['success'] = 'Category deleted successfully';
                        header('Location: categories.php');
                        exit;
                    } else {
                        $errors[] = 'Failed to delete category';
                    }
                }
                break;
        }
    }
}

// Get categories
$categories = $conn->query("
    SELECT ec.*, a.account_code, a.account_name,
           COUNT(e.id) as expense_count
    FROM " . DB_PREFIX . "expense_categories ec
    LEFT JOIN " . DB_PREFIX . "accounts a ON ec.default_account_id = a.id
    LEFT JOIN " . DB_PREFIX . "expenses e ON ec.id = e.category_id
    GROUP BY ec.id
    ORDER BY ec.category_name
")->fetch_all(MYSQLI_ASSOC);

// Get expense accounts for dropdown
$expenseAccounts = $conn->query("
    SELECT id, account_code, account_name 
    FROM " . DB_PREFIX . "accounts 
    WHERE account_type = 'Expense' 
    AND is_active = 1
    ORDER BY account_code
")->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = 'Expense Categories';

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Expense Categories</h1>
        <p>Manage expense categories and their default accounts</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" data-toggle="modal" data-target="#addCategoryModal">
            <i class="icon-plus"></i> Add Category
        </button>
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

<!-- Categories Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Default Account</th>
                        <th>Expense Count</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">No categories found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($category['description'] ?: '-'); ?></td>
                            <td>
                                <?php if ($category['account_code']): ?>
                                    <?php echo htmlspecialchars($category['account_code'] . ' - ' . $category['account_name']); ?>
                                <?php else: ?>
                                    <span class="text-muted">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info"><?php echo $category['expense_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $category['is_active'] ? 'badge-success' : 'badge-secondary'; ?>">
                                    <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)" 
                                            class="btn btn-sm btn-outline-secondary" title="Edit">
                                        <i class="icon-edit"></i>
                                    </button>
                                    <?php if ($category['expense_count'] == 0): ?>
                                    <button onclick="deleteCategory(<?php echo $category['id']; ?>)" 
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

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Expense Category</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="required">Category Name</label>
                        <input type="text" name="category_name" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Default Account</label>
                        <select name="default_account_id" class="form-control">
                            <option value="">Select Account</option>
                            <?php foreach ($expenseAccounts as $account): ?>
                            <option value="<?php echo $account['id']; ?>">
                                <?php echo htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Expense Category</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="category_id" id="edit_category_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="required">Category Name</label>
                        <input type="text" name="category_name" id="edit_category_name" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" rows="3" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Default Account</label>
                        <select name="default_account_id" id="edit_default_account_id" class="form-control">
                            <option value="">Select Account</option>
                            <?php foreach ($expenseAccounts as $account): ?>
                            <option value="<?php echo $account['id']; ?>">
                                <?php echo htmlspecialchars($account['account_code'] . ' - ' . $account['account_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="edit_is_active" class="form-check-input">
                            <label for="edit_is_active" class="form-check-label">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCategory(category) {
    document.getElementById('edit_category_id').value = category.id;
    document.getElementById('edit_category_name').value = category.category_name;
    document.getElementById('edit_description').value = category.description || '';
    document.getElementById('edit_default_account_id').value = category.default_account_id || '';
    document.getElementById('edit_is_active').checked = category.is_active == 1;
    
    $('#editCategoryModal').modal('show');
}

function deleteCategory(categoryId) {
    if (confirm('Are you sure you want to delete this category?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="${document.querySelector('meta[name="csrf-token"]').getAttribute('content')}">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="category_id" value="${categoryId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<style>
.badge-info {
    background-color: #17a2b8;
    color: white;
}

.badge-success {
    background-color: #28a745;
    color: white;
}

.badge-secondary {
    background-color: #6c757d;
    color: white;
}

.required::after {
    content: " *";
    color: #dc3545;
}

.btn-group .btn {
    margin-right: 2px;
}

.btn-group .btn:last-child {
    margin-right: 0;
}
</style>

<?php include '../../../includes/footer.php'; ?>
