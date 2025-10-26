<?php
/**
 * Business Management System - User Management
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
requirePermission('users.view');

// Get database connection
$conn = getDB();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCSRFToken($_POST['csrf_token'] ?? '');
    
    switch ($_POST['action']) {
        case 'delete_user':
            handleDeleteUser();
            break;
        case 'toggle_status':
            handleToggleStatus();
            break;
    }
    exit;
}

/**
 * Handle user deletion
 */
function handleDeleteUser() {
    global $conn;
    
    if (!hasPermission('users.delete')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }
    
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        return;
    }
    
    // Prevent deleting own account
    if ($userId == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
        return;
    }
    
    // Prevent deleting Super Admin
    $stmt = $conn->prepare("SELECT role_id FROM " . DB_PREFIX . "users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result && $result['role_id'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete Super Admin account']);
        return;
    }
    
    // Soft delete (set is_active = 0)
    $stmt = $conn->prepare("UPDATE " . DB_PREFIX . "users SET is_active = 0, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $userId);
    
    if ($stmt->execute()) {
        logActivity('users.delete', 'User deleted', ['user_id' => $userId]);
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
    }
}

/**
 * Handle user status toggle
 */
function handleToggleStatus() {
    global $conn;
    
    if (!hasPermission('users.edit')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }
    
    $userId = (int)($_POST['user_id'] ?? 0);
    $status = (int)($_POST['status'] ?? 0);
    
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        return;
    }
    
    // Prevent deactivating own account
    if ($userId == $_SESSION['user_id'] && $status == 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot deactivate your own account']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE " . DB_PREFIX . "users SET is_active = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('ii', $status, $userId);
    
    if ($stmt->execute()) {
        $action = $status ? 'activated' : 'deactivated';
        logActivity('users.edit', "User {$action}", ['user_id' => $userId]);
        echo json_encode(['success' => true, 'message' => "User {$action} successfully"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
    }
}

// Get pagination parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(10, min(100, (int)($_GET['limit'] ?? getSetting('records_per_page', 25))));
$offset = ($page - 1) * $limit;

// Get search and filter parameters
$search = sanitizeInput($_GET['search'] ?? '');
$roleFilter = (int)($_GET['role'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$sortBy = sanitizeInput($_GET['sort'] ?? 'id');
$sortOrder = strtoupper($_GET['order'] ?? 'ASC');

// Validate sort parameters
$allowedSorts = ['id', 'first_name', 'last_name', 'email', 'username', 'created_at', 'last_login'];
if (!in_array($sortBy, $allowedSorts)) {
    $sortBy = 'id';
}
if (!in_array($sortOrder, ['ASC', 'DESC'])) {
    $sortOrder = 'ASC';
}

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

// Search condition
if (!empty($search)) {
    $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $paramTypes .= 'ssss';
}

// Role filter
if ($roleFilter > 0) {
    $whereConditions[] = "u.role_id = ?";
    $params[] = $roleFilter;
    $paramTypes .= 'i';
}

// Status filter
if ($statusFilter !== '') {
    $whereConditions[] = "u.is_active = ?";
    $params[] = (int)$statusFilter;
    $paramTypes .= 'i';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countQuery = "
    SELECT COUNT(*) as total 
    FROM " . DB_PREFIX . "users u 
    LEFT JOIN " . DB_PREFIX . "roles r ON u.role_id = r.id 
    {$whereClause}
";

$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$totalUsers = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalUsers / $limit);

// Get users
$usersQuery = "
    SELECT u.*, r.name as role_name, r.is_system_role
    FROM " . DB_PREFIX . "users u 
    LEFT JOIN " . DB_PREFIX . "roles r ON u.role_id = r.id 
    {$whereClause}
    ORDER BY u.{$sortBy} {$sortOrder}
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$paramTypes .= 'ii';

$stmt = $conn->prepare($usersQuery);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get roles for filter dropdown
$rolesQuery = "SELECT id, name FROM " . DB_PREFIX . "roles ORDER BY name";
$roles = $conn->query($rolesQuery)->fetch_all(MYSQLI_ASSOC);

// Include header
include '../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>User Management</h1>
        <p>Manage system users and their permissions</p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('users.create')): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Add New User
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters and Search -->
<div class="card">
    <div class="card-header">
        <h3>Search & Filter</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="filter-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name, email, or username">
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>" <?php echo $roleFilter == $role['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All Status</option>
                        <option value="1" <?php echo $statusFilter === '1' ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $statusFilter === '0' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
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

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h3>Users (<?php echo number_format($totalUsers); ?> total)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($users)): ?>
        <div class="empty-state">
            <i class="icon-users"></i>
            <h3>No users found</h3>
            <p>No users match your search criteria.</p>
            <?php if (hasPermission('users.create')): ?>
            <a href="add.php" class="btn btn-primary">Add First User</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'id', 'order' => $sortBy == 'id' && $sortOrder == 'ASC' ? 'DESC' : 'ASC'])); ?>">
                                ID <?php echo $sortBy == 'id' ? ($sortOrder == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'first_name', 'order' => $sortBy == 'first_name' && $sortOrder == 'ASC' ? 'DESC' : 'ASC'])); ?>">
                                Name <?php echo $sortBy == 'first_name' ? ($sortOrder == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'email', 'order' => $sortBy == 'email' && $sortOrder == 'ASC' ? 'DESC' : 'ASC'])); ?>">
                                Email <?php echo $sortBy == 'email' ? ($sortOrder == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'username', 'order' => $sortBy == 'username' && $sortOrder == 'ASC' ? 'DESC' : 'ASC'])); ?>">
                                Username <?php echo $sortBy == 'username' ? ($sortOrder == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'last_login', 'order' => $sortBy == 'last_login' && $sortOrder == 'ASC' ? 'DESC' : 'ASC'])); ?>">
                                Last Login <?php echo $sortBy == 'last_login' ? ($sortOrder == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td>
                            <div class="user-info">
                                <?php if (!empty($user['profile_picture'])): ?>
                                <img src="../../uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                     alt="Profile" class="user-avatar">
                                <?php else: ?>
                                <div class="user-avatar-placeholder">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                </div>
                                <?php endif; ?>
                                <div class="user-details">
                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $user['is_system_role'] ? 'primary' : 'secondary'; ?>">
                                <?php echo htmlspecialchars($user['role_name']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['last_login']): ?>
                                <?php echo date('M d, Y H:i', strtotime($user['last_login'])); ?>
                            <?php else: ?>
                                <span class="text-muted">Never</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if (hasPermission('users.view')): ?>
                                <a href="view.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" title="View">
                                    <i class="icon-eye"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (hasPermission('users.edit')): ?>
                                <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="icon-edit"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (hasPermission('users.edit') && $user['id'] != $_SESSION['user_id']): ?>
                                <button class="btn btn-sm btn-<?php echo $user['is_active'] ? 'secondary' : 'success'; ?>" 
                                        onclick="toggleStatus(<?php echo $user['id']; ?>, <?php echo $user['is_active'] ? 0 : 1; ?>)" 
                                        title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                    <i class="icon-<?php echo $user['is_active'] ? 'pause' : 'play'; ?>"></i>
                                </button>
                                <?php endif; ?>
                                
                                <?php if (hasPermission('users.delete') && $user['id'] != $_SESSION['user_id'] && $user['role_id'] != 1): ?>
                                <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)" title="Delete">
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
function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete_user');
        formData.append('user_id', userId);
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
            alert('An error occurred while deleting the user.');
        });
    }
}

function toggleStatus(userId, newStatus) {
    const action = newStatus ? 'activate' : 'deactivate';
    if (confirm(`Are you sure you want to ${action} this user?`)) {
        const formData = new FormData();
        formData.append('action', 'toggle_status');
        formData.append('user_id', userId);
        formData.append('status', newStatus);
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
            alert('An error occurred while updating the user status.');
        });
    }
}
</script>

<?php include '../includes/footer.php'; ?>
