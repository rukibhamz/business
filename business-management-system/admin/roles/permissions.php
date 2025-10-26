<?php
/**
 * Business Management System - Manage Role Permissions
 * Phase 2: User Management & Settings System
 */

// Define system constant
define('BMS_SYSTEM', true);

// Start session
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';

// Check authentication and permissions
requireLogin();
requirePermission('roles.manage_permissions');

// Get database connection
$conn = getDB();

// Get role ID
$roleId = (int)($_GET['role_id'] ?? 0);

if ($roleId <= 0) {
    $_SESSION['error'] = 'Invalid role ID';
    header('Location: index.php');
    exit;
}

// Get role data
$stmt = $conn->prepare("SELECT * FROM " . DB_PREFIX . "roles WHERE id = ?");
$stmt->bind_param('i', $roleId);
$stmt->execute();
$role = $stmt->get_result()->fetch_assoc();

if (!$role) {
    $_SESSION['error'] = 'Role not found';
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken($_POST['csrf_token'] ?? '');
    
    $errors = [];
    $success = false;
    
    // Get selected permissions
    $selectedPermissions = $_POST['permissions'] ?? [];
    
    // Validate permissions
    if (!is_array($selectedPermissions)) {
        $errors[] = 'Invalid permissions data';
    }
    
    // If no errors, update permissions
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete existing permissions for this role
            $stmt = $conn->prepare("DELETE FROM " . DB_PREFIX . "role_permissions WHERE role_id = ?");
            $stmt->bind_param('i', $roleId);
            $stmt->execute();
            
            // Insert new permissions
            if (!empty($selectedPermissions)) {
                $stmt = $conn->prepare("
                    INSERT INTO " . DB_PREFIX . "role_permissions (role_id, permission_id, created_at) 
                    VALUES (?, ?, NOW())
                ");
                
                foreach ($selectedPermissions as $permissionId) {
                    $permissionId = (int)$permissionId;
                    if ($permissionId > 0) {
                        $stmt->bind_param('ii', $roleId, $permissionId);
                        $stmt->execute();
                    }
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Log activity
            logActivity('roles.manage_permissions', "Permissions updated for role: {$role['name']}", [
                'role_id' => $roleId,
                'permissions_count' => count($selectedPermissions)
            ]);
            
            $success = true;
            $_SESSION['success'] = 'Permissions updated successfully';
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $errors[] = 'Failed to update permissions. Please try again.';
        }
    }
}

// Get all permissions grouped by module
$permissionsQuery = "
    SELECT p.*, 
           CASE 
               WHEN p.module = 'dashboard' THEN 1
               WHEN p.module = 'users' THEN 2
               WHEN p.module = 'roles' THEN 3
               WHEN p.module = 'settings' THEN 4
               WHEN p.module = 'activity' THEN 5
               WHEN p.module = 'accounting' THEN 6
               WHEN p.module = 'events' THEN 7
               WHEN p.module = 'properties' THEN 8
               WHEN p.module = 'inventory' THEN 9
               WHEN p.module = 'utilities' THEN 10
               ELSE 99
           END as module_order
    FROM " . DB_PREFIX . "permissions p 
    ORDER BY module_order, p.name
";
$permissions = $conn->query($permissionsQuery)->fetch_all(MYSQLI_ASSOC);

// Group permissions by module
$permissionsByModule = [];
foreach ($permissions as $permission) {
    $permissionsByModule[$permission['module']][] = $permission;
}

// Get current role permissions
$stmt = $conn->prepare("SELECT permission_id FROM " . DB_PREFIX . "role_permissions WHERE role_id = ?");
$stmt->bind_param('i', $roleId);
$stmt->execute();
$rolePermissions = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'permission_id');

// Include header
include '../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Manage Permissions</h1>
        <p>Assign permissions to role: <strong><?php echo htmlspecialchars($role['name']); ?></strong></p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Roles
        </a>
        <a href="edit.php?id=<?php echo $roleId; ?>" class="btn btn-warning">
            <i class="icon-edit"></i> Edit Role
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

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="icon-check"></i> Permissions updated successfully!
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Role Permissions</h3>
        <p>Select the permissions to assign to this role. Permissions are organized by module for easy management.</p>
    </div>
    <div class="card-body">
        <form method="POST" id="permissions-form">
            <?php csrfField(); ?>
            
            <div class="permissions-container">
                <?php foreach ($permissionsByModule as $module => $modulePermissions): ?>
                <div class="module-section">
                    <div class="module-header">
                        <h4>
                            <i class="icon-<?php echo getModuleIcon($module); ?>"></i>
                            <?php echo ucfirst($module); ?> Module
                        </h4>
                        <div class="module-actions">
                            <button type="button" class="btn btn-sm btn-primary" onclick="selectAllModule('<?php echo $module; ?>')">
                                Select All
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="deselectAllModule('<?php echo $module; ?>')">
                                Deselect All
                            </button>
                        </div>
                    </div>
                    
                    <div class="permissions-grid">
                        <?php foreach ($modulePermissions as $permission): ?>
                        <div class="permission-item">
                            <label class="permission-label">
                                <input type="checkbox" 
                                       name="permissions[]" 
                                       value="<?php echo $permission['id']; ?>"
                                       data-module="<?php echo $module; ?>"
                                       <?php echo in_array($permission['id'], $rolePermissions) ? 'checked' : ''; ?>
                                       class="permission-checkbox">
                                <div class="permission-content">
                                    <div class="permission-name"><?php echo htmlspecialchars($permission['display_name']); ?></div>
                                    <?php if (!empty($permission['description'])): ?>
                                    <div class="permission-description"><?php echo htmlspecialchars($permission['description']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="icon-save"></i> Update Permissions
                </button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<!-- Permission Summary -->
<div class="card">
    <div class="card-header">
        <h3>Permission Summary</h3>
    </div>
    <div class="card-body">
        <div class="permission-summary">
            <div class="summary-item">
                <span class="summary-label">Total Permissions:</span>
                <span class="summary-value"><?php echo count($permissions); ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Selected Permissions:</span>
                <span class="summary-value" id="selected-count"><?php echo count($rolePermissions); ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Modules:</span>
                <span class="summary-value"><?php echo count($permissionsByModule); ?></span>
            </div>
        </div>
    </div>
</div>

<script>
// Update selected count
function updateSelectedCount() {
    const selected = document.querySelectorAll('.permission-checkbox:checked').length;
    document.getElementById('selected-count').textContent = selected;
}

// Select all permissions in a module
function selectAllModule(module) {
    const checkboxes = document.querySelectorAll(`input[data-module="${module}"]`);
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    updateSelectedCount();
}

// Deselect all permissions in a module
function deselectAllModule(module) {
    const checkboxes = document.querySelectorAll(`input[data-module="${module}"]`);
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    updateSelectedCount();
}

// Add event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Update count on page load
    updateSelectedCount();
    
    // Add change listeners to all checkboxes
    const checkboxes = document.querySelectorAll('.permission-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
    
    // Add select all / deselect all buttons
    const selectAllBtn = document.createElement('button');
    selectAllBtn.type = 'button';
    selectAllBtn.className = 'btn btn-sm btn-success';
    selectAllBtn.textContent = 'Select All';
    selectAllBtn.onclick = function() {
        document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = true);
        updateSelectedCount();
    };
    
    const deselectAllBtn = document.createElement('button');
    deselectAllBtn.type = 'button';
    deselectAllBtn.className = 'btn btn-sm btn-danger';
    deselectAllBtn.textContent = 'Deselect All';
    deselectAllBtn.onclick = function() {
        document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = false);
        updateSelectedCount();
    };
    
    // Add buttons to form actions
    const formActions = document.querySelector('.form-actions');
    formActions.insertBefore(deselectAllBtn, formActions.firstChild);
    formActions.insertBefore(selectAllBtn, formActions.firstChild);
});
</script>

<style>
.permissions-container {
    max-height: 600px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 20px;
}

.module-section {
    margin-bottom: 30px;
}

.module-section:last-child {
    margin-bottom: 0;
}

.module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f1f3f4;
}

.module-header h4 {
    margin: 0;
    color: #333;
    display: flex;
    align-items: center;
}

.module-header h4 i {
    margin-right: 8px;
    color: #6c757d;
}

.module-actions {
    display: flex;
    gap: 5px;
}

.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 10px;
}

.permission-item {
    border: 1px solid #e9ecef;
    border-radius: 5px;
    transition: all 0.2s ease;
}

.permission-item:hover {
    border-color: #007bff;
    box-shadow: 0 2px 4px rgba(0,123,255,0.1);
}

.permission-label {
    display: block;
    padding: 12px;
    cursor: pointer;
    margin: 0;
}

.permission-label:hover {
    background-color: #f8f9fa;
}

.permission-checkbox {
    margin-right: 10px;
    transform: scale(1.2);
}

.permission-content {
    display: inline-block;
    vertical-align: top;
    width: calc(100% - 30px);
}

.permission-name {
    font-weight: 500;
    color: #333;
    margin-bottom: 4px;
}

.permission-description {
    font-size: 12px;
    color: #6c757d;
    line-height: 1.4;
}

.permission-summary {
    display: flex;
    justify-content: space-around;
    text-align: center;
}

.summary-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.summary-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.summary-value {
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

.form-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
    display: flex;
    gap: 10px;
    align-items: center;
}
</style>

<?php
/**
 * Get module icon based on module name
 */
function getModuleIcon($module) {
    $icons = [
        'dashboard' => 'home',
        'users' => 'users',
        'roles' => 'shield',
        'settings' => 'settings',
        'activity' => 'activity',
        'accounting' => 'dollar-sign',
        'events' => 'calendar',
        'properties' => 'home',
        'inventory' => 'package',
        'utilities' => 'zap'
    ];
    
    return $icons[$module] ?? 'circle';
}

include '../includes/footer.php';
?>
