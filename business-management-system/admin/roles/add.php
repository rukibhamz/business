<?php
/**
 * Business Management System - Add Role
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
requirePermission('roles.create');

// Get database connection
$conn = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken($_POST['csrf_token'] ?? '');
    
    $errors = [];
    $success = false;
    
    // Get form data
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $copyFromRoleId = (int)($_POST['copy_from_role'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Role name is required';
    } elseif (strlen($name) < 3) {
        $errors[] = 'Role name must be at least 3 characters';
    } else {
        // Check if role name already exists
        $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "roles WHERE name = ?");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $errors[] = 'Role name already exists';
        }
    }
    
    if (strlen($description) > 500) {
        $errors[] = 'Description must be less than 500 characters';
    }
    
    // If no errors, create role
    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "roles 
            (name, description, is_active, is_system_role, created_at, updated_at) 
            VALUES (?, ?, ?, 0, NOW(), NOW())
        ");
        
        $stmt->bind_param('ssi', $name, $description, $isActive);
        
        if ($stmt->execute()) {
            $roleId = $conn->insert_id;
            
            // Copy permissions if specified
            if ($copyFromRoleId > 0) {
                $stmt = $conn->prepare("
                    INSERT INTO " . DB_PREFIX . "role_permissions (role_id, permission_id, created_at)
                    SELECT ?, permission_id, NOW()
                    FROM " . DB_PREFIX . "role_permissions 
                    WHERE role_id = ?
                ");
                $stmt->bind_param('ii', $roleId, $copyFromRoleId);
                $stmt->execute();
            }
            
            // Log activity
            logActivity('roles.create', "Role created: {$name}", [
                'role_id' => $roleId,
                'name' => $name,
                'copied_from' => $copyFromRoleId
            ]);
            
            $success = true;
            $_SESSION['success'] = 'Role created successfully';
            
            // Redirect to permissions page if no permissions were copied
            if ($copyFromRoleId == 0) {
                header('Location: permissions.php?role_id=' . $roleId);
                exit;
            } else {
                header('Location: index.php');
                exit;
            }
        } else {
            $errors[] = 'Failed to create role. Please try again.';
        }
    }
}

// Get existing roles for copy dropdown
$rolesQuery = "SELECT id, name FROM " . DB_PREFIX . "roles WHERE is_active = 1 ORDER BY name";
$existingRoles = $conn->query($rolesQuery)->fetch_all(MYSQLI_ASSOC);

// Include header
include '../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Add New Role</h1>
        <p>Create a new user role with specific permissions</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Roles
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
                           value="<?php echo htmlspecialchars($name ?? ''); ?>" 
                           required class="form-control" minlength="3" maxlength="100">
                    <small class="form-text">A unique name for this role (e.g., "Manager", "Editor")</small>
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
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" 
                          class="form-control" rows="3" maxlength="500"
                          placeholder="Describe the purpose and responsibilities of this role"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                <small class="form-text">Optional description of this role's purpose</small>
            </div>
            
            <div class="form-group">
                <label for="copy_from_role">Copy Permissions From</label>
                <select id="copy_from_role" name="copy_from_role" class="form-control">
                    <option value="">Start with no permissions</option>
                    <?php foreach ($existingRoles as $role): ?>
                    <option value="<?php echo $role['id']; ?>" 
                            <?php echo ($copyFromRoleId ?? 0) == $role['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($role['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text">Optionally copy permissions from an existing role</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="icon-plus"></i> Create Role
                </button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<!-- Role Information -->
<div class="card">
    <div class="card-header">
        <h3>About Roles</h3>
    </div>
    <div class="card-body">
        <div class="info-section">
            <h4>What are Roles?</h4>
            <p>Roles define what users can and cannot do in the system. Each role has a specific set of permissions that determine access to different features and functions.</p>
            
            <h4>System vs Custom Roles</h4>
            <ul>
                <li><strong>System Roles:</strong> Built-in roles (Super Admin, Admin, etc.) that cannot be deleted</li>
                <li><strong>Custom Roles:</strong> User-created roles that can be modified or deleted</li>
            </ul>
            
            <h4>Permission Management</h4>
            <p>After creating a role, you can assign specific permissions to it. Permissions are organized by modules (Users, Settings, Accounting, etc.) for easy management.</p>
        </div>
    </div>
</div>

<style>
.form-check {
    margin-top: 8px;
}

.form-check-input {
    margin-right: 8px;
}

.info-section h4 {
    color: #333;
    margin-top: 20px;
    margin-bottom: 10px;
}

.info-section h4:first-child {
    margin-top: 0;
}

.info-section ul {
    margin-left: 20px;
}

.info-section li {
    margin-bottom: 5px;
}
</style>

<?php include '../includes/footer.php'; ?>
