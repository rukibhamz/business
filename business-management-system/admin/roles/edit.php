<?php
/**
 * Business Management System - Edit Role
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
requirePermission('roles.edit');

// Get database connection
$conn = getDB();

// Get role ID
$roleId = (int)($_GET['id'] ?? 0);

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

// Check if it's a system role
$isSystemRole = $role['is_system_role'];

// Get user count for this role
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "users WHERE role_id = ?");
$stmt->bind_param('i', $roleId);
$stmt->execute();
$userCount = $stmt->get_result()->fetch_assoc()['count'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken($_POST['csrf_token'] ?? '');
    
    $errors = [];
    $success = false;
    
    // Get form data
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Role name is required';
    } elseif (strlen($name) < 3) {
        $errors[] = 'Role name must be at least 3 characters';
    } else {
        // Check if role name already exists (excluding current role)
        $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "roles WHERE name = ? AND id != ?");
        $stmt->bind_param('si', $name, $roleId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $errors[] = 'Role name already exists';
        }
    }
    
    if (strlen($description) > 500) {
        $errors[] = 'Description must be less than 500 characters';
    }
    
    // Security check for system roles
    if ($isSystemRole && $name !== $role['name']) {
        $errors[] = 'Cannot change the name of system roles';
    }
    
    // If no errors, update role
    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE " . DB_PREFIX . "roles 
            SET name = ?, description = ?, is_active = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->bind_param('ssii', $name, $description, $isActive, $roleId);
        
        if ($stmt->execute()) {
            // Log activity
            logActivity('roles.edit', "Role updated: {$name}", [
                'role_id' => $roleId,
                'name' => $name,
                'is_system_role' => $isSystemRole
            ]);
            
            $success = true;
            $_SESSION['success'] = 'Role updated successfully';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Failed to update role. Please try again.';
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Edit Role</h1>
        <p>Modify role information and settings</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Roles
        </a>
        <?php if (hasPermission('roles.manage_permissions')): ?>
        <a href="permissions.php?role_id=<?php echo $roleId; ?>" class="btn btn-info">
            <i class="icon-key"></i> Manage Permissions
        </a>
        <?php endif; ?>
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
    <!-- Role Information -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Role Information</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="form-horizontal">
                    <?php csrfField(); ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name" class="required">Role Name</label>
                            <input type="text" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($name ?? $role['name']); ?>" 
                                   required class="form-control" minlength="3" maxlength="100"
                                   <?php echo $isSystemRole ? 'readonly' : ''; ?>>
                            <?php if ($isSystemRole): ?>
                            <small class="form-text text-muted">System role names cannot be changed</small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <div class="form-check">
                                <input type="checkbox" id="is_active" name="is_active" 
                                       <?php echo ($isActive ?? $role['is_active']) ? 'checked' : ''; ?> 
                                       class="form-check-input">
                                <label for="is_active" class="form-check-label">Active</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" 
                                  class="form-control" rows="3" maxlength="500"
                                  placeholder="Describe the purpose and responsibilities of this role"><?php echo htmlspecialchars($description ?? $role['description']); ?></textarea>
                        <small class="form-text">Optional description of this role's purpose</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="icon-save"></i> Update Role
                        </button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Role Statistics -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>Role Statistics</h3>
            </div>
            <div class="card-body">
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($userCount); ?></div>
                    <div class="stat-label">Users Assigned</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">
                        <?php 
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "role_permissions WHERE role_id = ?");
                        $stmt->bind_param('i', $roleId);
                        $stmt->execute();
                        $permissionCount = $stmt->get_result()->fetch_assoc()['count'];
                        echo number_format($permissionCount);
                        ?>
                    </div>
                    <div class="stat-label">Permissions</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">
                        <?php echo $isSystemRole ? 'System' : 'Custom'; ?>
                    </div>
                    <div class="stat-label">Role Type</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">
                        <?php echo date('M d, Y', strtotime($role['created_at'])); ?>
                    </div>
                    <div class="stat-label">Created</div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3>Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="action-buttons">
                    <?php if (hasPermission('roles.manage_permissions')): ?>
                    <a href="permissions.php?role_id=<?php echo $roleId; ?>" class="btn btn-info btn-block">
                        <i class="icon-key"></i> Manage Permissions
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!$isSystemRole && $userCount == 0 && hasPermission('roles.delete')): ?>
                    <button class="btn btn-danger btn-block" onclick="deleteRole(<?php echo $roleId; ?>)">
                        <i class="icon-trash"></i> Delete Role
                    </button>
                    <?php endif; ?>
                    
                    <a href="index.php" class="btn btn-secondary btn-block">
                        <i class="icon-arrow-left"></i> Back to Roles
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Warning Messages -->
        <?php if ($isSystemRole): ?>
        <div class="card">
            <div class="card-header">
                <h3>System Role Notice</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="icon-warning"></i>
                    <strong>System Role</strong><br>
                    This is a system role that cannot be deleted. Some properties may be restricted.
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($userCount > 0): ?>
        <div class="card">
            <div class="card-header">
                <h3>Users Assigned</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="icon-info"></i>
                    <strong><?php echo number_format($userCount); ?> user(s)</strong> are assigned to this role.
                    <?php if (!$isSystemRole): ?>
                    <br><small>This role cannot be deleted while users are assigned to it.</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function deleteRole(roleId) {
    if (confirm('Are you sure you want to delete this role? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete_role');
        formData.append('role_id', roleId);
        formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
        
        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'index.php';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the role.');
        });
    }
}
</script>

<style>
.form-check {
    margin-top: 8px;
}

.form-check-input {
    margin-right: 8px;
}

.stat-item {
    text-align: center;
    padding: 15px 0;
    border-bottom: 1px solid #f1f3f4;
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.action-buttons .btn {
    margin-bottom: 10px;
}

.action-buttons .btn:last-child {
    margin-bottom: 0;
}

.form-control[readonly] {
    background-color: #f8f9fa;
    opacity: 0.6;
}
</style>

<?php include '../includes/footer.php'; ?>
