<?php
/**
 * Business Management System - Roles Management
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
requirePermission('roles.view');

// Get database connection
$conn = getDB();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCSRFToken($_POST['csrf_token'] ?? '');
    
    switch ($_POST['action']) {
        case 'delete_role':
            handleDeleteRole();
            break;
    }
    exit;
}

/**
 * Handle role deletion
 */
function handleDeleteRole() {
    global $conn;
    
    if (!hasPermission('roles.delete')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }
    
    $roleId = (int)($_POST['role_id'] ?? 0);
    
    if ($roleId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid role ID']);
        return;
    }
    
    // Check if role exists and is not a system role
    $stmt = $conn->prepare("SELECT name, is_system_role FROM " . DB_PREFIX . "roles WHERE id = ?");
    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    $role = $stmt->get_result()->fetch_assoc();
    
    if (!$role) {
        echo json_encode(['success' => false, 'message' => 'Role not found']);
        return;
    }
    
    if ($role['is_system_role']) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete system role']);
        return;
    }
    
    // Check if role has users assigned
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "users WHERE role_id = ?");
    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    $userCount = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($userCount > 0) {
        echo json_encode(['success' => false, 'message' => "Cannot delete role. {$userCount} user(s) are assigned to this role."]);
        return;
    }
    
    // Delete role permissions first
    $stmt = $conn->prepare("DELETE FROM " . DB_PREFIX . "role_permissions WHERE role_id = ?");
    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    
    // Delete role
    $stmt = $conn->prepare("DELETE FROM " . DB_PREFIX . "roles WHERE id = ?");
    $stmt->bind_param('i', $roleId);
    
    if ($stmt->execute()) {
        logActivity('roles.delete', "Role deleted: {$role['name']}", ['role_id' => $roleId]);
        echo json_encode(['success' => true, 'message' => 'Role deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete role']);
    }
}

// Get pagination parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(10, min(100, (int)($_GET['limit'] ?? getSetting('records_per_page', 25))));
$offset = ($page - 1) * $limit;

// Get search parameter
$search = sanitizeInput($_GET['search'] ?? '');

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

// Search condition
if (!empty($search)) {
    $whereConditions[] = "(r.name LIKE ? OR r.description LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
    $paramTypes .= 'ss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countQuery = "
    SELECT COUNT(*) as total 
    FROM " . DB_PREFIX . "roles r 
    {$whereClause}
";

$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$totalRoles = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRoles / $limit);

// Get roles with user counts
$rolesQuery = "
    SELECT r.*, 
           COUNT(u.id) as user_count
    FROM " . DB_PREFIX . "roles r 
    LEFT JOIN " . DB_PREFIX . "users u ON r.id = u.role_id 
    {$whereClause}
    GROUP BY r.id 
    ORDER BY r.is_system_role DESC, r.name ASC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$paramTypes .= 'ii';

$stmt = $conn->prepare($rolesQuery);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$roles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Include header
include '../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Roles & Permissions</h1>
        <p>Manage user roles and their permissions</p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('roles.create')): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Add New Role
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Search -->
<div class="card">
    <div class="card-header">
        <h3>Search Roles</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="filter-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by role name or description">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="index.php" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Roles Table -->
<div class="card">
    <div class="card-header">
        <h3>Roles (<?php echo number_format($totalRoles); ?> total)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($roles)): ?>
        <div class="empty-state">
            <i class="icon-shield"></i>
            <h3>No roles found</h3>
            <p>No roles match your search criteria.</p>
            <?php if (hasPermission('roles.create')): ?>
            <a href="add.php" class="btn btn-primary">Add First Role</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>Users</th>
                        <th>Type</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                    <tr>
                        <td><?php echo $role['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($role['name']); ?></strong>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($role['description'] ?: 'No description'); ?>
                        </td>
                        <td>
                            <span class="badge badge-info"><?php echo number_format($role['user_count']); ?></span>
                        </td>
                        <td>
                            <?php if ($role['is_system_role']): ?>
                            <span class="badge badge-primary">System</span>
                            <?php else: ?>
                            <span class="badge badge-secondary">Custom</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo date('M d, Y', strtotime($role['created_at'])); ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if (hasPermission('roles.edit')): ?>
                                <a href="edit.php?id=<?php echo $role['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="icon-edit"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (hasPermission('roles.manage_permissions')): ?>
                                <a href="permissions.php?role_id=<?php echo $role['id']; ?>" class="btn btn-sm btn-info" title="Manage Permissions">
                                    <i class="icon-key"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (hasPermission('roles.delete') && !$role['is_system_role'] && $role['user_count'] == 0): ?>
                                <button class="btn btn-sm btn-danger" onclick="deleteRole(<?php echo $role['id']; ?>)" title="Delete">
                                    <i class="icon-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn btn-secondary">
                <i class="icon-chevron-left"></i> Previous
            </a>
            <?php endif; ?>
            
            <span class="pagination-info">
                Page <?php echo $page; ?> of <?php echo $totalPages; ?>
            </span>
            
            <?php if ($page < $totalPages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-secondary">
                Next <i class="icon-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
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

<?php include '../includes/footer.php'; ?>
