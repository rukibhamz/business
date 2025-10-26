<?php
/**
 * Business Management System - View User
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

// Get user ID
$userId = (int)($_GET['id'] ?? 0);

if ($userId <= 0) {
    $_SESSION['error'] = 'Invalid user ID';
    header('Location: index.php');
    exit;
}

// Get user data
$stmt = $conn->prepare("
    SELECT u.*, r.name as role_name, r.is_system_role
    FROM " . DB_PREFIX . "users u 
    LEFT JOIN " . DB_PREFIX . "roles r ON u.role_id = r.id 
    WHERE u.id = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    $_SESSION['error'] = 'User not found';
    header('Location: index.php');
    exit;
}

// Get user's recent activity
$stmt = $conn->prepare("
    SELECT action, description, created_at, ip_address
    FROM " . DB_PREFIX . "activity_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$recentActivity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get login statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_logins,
        MAX(created_at) as last_login_time,
        COUNT(DISTINCT DATE(created_at)) as login_days
    FROM " . DB_PREFIX . "activity_logs 
    WHERE user_id = ? AND action = 'login'
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$loginStats = $stmt->get_result()->fetch_assoc();

// Include header
include '../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>User Details</h1>
        <p>View user information and activity</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Users
        </a>
        <?php if (hasPermission('users.edit')): ?>
        <a href="edit.php?id=<?php echo $userId; ?>" class="btn btn-warning">
            <i class="icon-edit"></i> Edit User
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- User Information -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>User Information</h3>
            </div>
            <div class="card-body">
                <div class="user-profile">
                    <div class="profile-picture">
                        <?php if (!empty($user['profile_picture'])): ?>
                        <img src="../../uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                             alt="Profile Picture" class="profile-img">
                        <?php else: ?>
                        <div class="profile-placeholder">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                        <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                        <div class="profile-badges">
                            <span class="badge badge-<?php echo $user['is_system_role'] ? 'primary' : 'secondary'; ?>">
                                <?php echo htmlspecialchars($user['role_name']); ?>
                            </span>
                            <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <h4>Account Details</h4>
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Username:</strong></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Phone:</strong></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?: 'Not provided'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Role:</strong></td>
                                <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h4>Account Statistics</h4>
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>User ID:</strong></td>
                                <td>#<?php echo $user['id']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Created:</strong></td>
                                <td><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Last Updated:</strong></td>
                                <td><?php echo date('M d, Y H:i', strtotime($user['updated_at'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Last Login:</strong></td>
                                <td>
                                    <?php if ($user['last_login']): ?>
                                        <?php echo date('M d, Y H:i', strtotime($user['last_login'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Never</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Total Logins:</strong></td>
                                <td><?php echo number_format($loginStats['total_logins']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Login Days:</strong></td>
                                <td><?php echo number_format($loginStats['login_days']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h3>Recent Activity</h3>
            </div>
            <div class="card-body">
                <?php if (empty($recentActivity)): ?>
                <div class="empty-state">
                    <i class="icon-activity"></i>
                    <h4>No recent activity</h4>
                    <p>This user hasn't performed any actions recently.</p>
                </div>
                <?php else: ?>
                <div class="activity-list">
                    <?php foreach ($recentActivity as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="icon-<?php echo getActivityIcon($activity['action']); ?>"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-description">
                                <?php echo htmlspecialchars($activity['description']); ?>
                            </div>
                            <div class="activity-meta">
                                <span class="activity-time">
                                    <?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?>
                                </span>
                                <?php if ($activity['ip_address']): ?>
                                <span class="activity-ip">
                                    IP: <?php echo htmlspecialchars($activity['ip_address']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Actions Sidebar -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>Actions</h3>
            </div>
            <div class="card-body">
                <div class="action-buttons">
                    <?php if (hasPermission('users.edit')): ?>
                    <a href="edit.php?id=<?php echo $userId; ?>" class="btn btn-warning btn-block">
                        <i class="icon-edit"></i> Edit User
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('users.edit') && $userId != $_SESSION['user_id']): ?>
                    <button class="btn btn-<?php echo $user['is_active'] ? 'secondary' : 'success'; ?> btn-block" 
                            onclick="toggleStatus(<?php echo $userId; ?>, <?php echo $user['is_active'] ? 0 : 1; ?>)">
                        <i class="icon-<?php echo $user['is_active'] ? 'pause' : 'play'; ?>"></i>
                        <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?> User
                    </button>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('users.delete') && $userId != $_SESSION['user_id'] && $user['role_id'] != 1): ?>
                    <button class="btn btn-danger btn-block" onclick="deleteUser(<?php echo $userId; ?>)">
                        <i class="icon-trash"></i> Delete User
                    </button>
                    <?php endif; ?>
                    
                    <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="btn btn-info btn-block">
                        <i class="icon-mail"></i> Send Email
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="card">
            <div class="card-header">
                <h3>Quick Stats</h3>
            </div>
            <div class="card-body">
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($loginStats['total_logins']); ?></div>
                    <div class="stat-label">Total Logins</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($loginStats['login_days']); ?></div>
                    <div class="stat-label">Active Days</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">
                        <?php 
                        $daysSinceCreated = floor((time() - strtotime($user['created_at'])) / (60 * 60 * 24));
                        echo $daysSinceCreated;
                        ?>
                    </div>
                    <div class="stat-label">Days Since Created</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete_user');
        formData.append('user_id', userId);
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
        
        fetch('index.php', {
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

<style>
.user-profile {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}

.profile-picture {
    margin-right: 20px;
}

.profile-img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #dee2e6;
}

.profile-placeholder {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background-color: #6c757d;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
}

.profile-info h2 {
    margin: 0 0 5px 0;
    color: #333;
}

.profile-badges {
    margin-top: 10px;
}

.profile-badges .badge {
    margin-right: 5px;
}

.activity-list {
    max-height: 400px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    padding: 10px 0;
    border-bottom: 1px solid #f1f3f4;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    flex-shrink: 0;
}

.activity-content {
    flex: 1;
}

.activity-description {
    font-weight: 500;
    margin-bottom: 4px;
}

.activity-meta {
    font-size: 12px;
    color: #6c757d;
}

.activity-time {
    margin-right: 10px;
}

.action-buttons .btn {
    margin-bottom: 10px;
}

.action-buttons .btn:last-child {
    margin-bottom: 0;
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

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}
</style>

<?php
/**
 * Get activity icon based on action type
 */
function getActivityIcon($action) {
    $icons = [
        'login' => 'log-in',
        'logout' => 'log-out',
        'users.create' => 'user-plus',
        'users.edit' => 'user-edit',
        'users.delete' => 'user-minus',
        'password_change' => 'key',
        'profile_update' => 'user-check'
    ];
    
    return $icons[$action] ?? 'activity';
}

include '../includes/footer.php';
?>
