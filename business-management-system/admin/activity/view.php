<?php
/**
 * Business Management System - View Activity Log Detail
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
requirePermission('activity.view');

// Get database connection
$conn = getDB();

// Get log ID
$logId = (int)($_GET['id'] ?? 0);

if ($logId <= 0) {
    $_SESSION['error'] = 'Invalid log ID';
    header('Location: index.php');
    exit;
}

// Get log data
$stmt = $conn->prepare("
    SELECT al.*, u.first_name, u.last_name, u.username, u.email, r.name as role_name
    FROM " . DB_PREFIX . "activity_logs al 
    LEFT JOIN " . DB_PREFIX . "users u ON al.user_id = u.id 
    LEFT JOIN " . DB_PREFIX . "roles r ON u.role_id = r.id 
    WHERE al.id = ?
");
$stmt->bind_param('i', $logId);
$stmt->execute();
$log = $stmt->get_result()->fetch_assoc();

if (!$log) {
    $_SESSION['error'] = 'Activity log not found';
    header('Location: index.php');
    exit;
}

// Parse additional data if available
$additionalData = null;
if (!empty($log['additional_data'])) {
    $additionalData = json_decode($log['additional_data'], true);
}

// Include header
include '../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Activity Log Detail</h1>
        <p>View detailed information about this activity log entry</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Activity Logs
        </a>
    </div>
</div>

<div class="row">
    <!-- Log Information -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Activity Information</h3>
            </div>
            <div class="card-body">
                <div class="log-detail">
                    <div class="detail-item">
                        <div class="detail-label">Log ID</div>
                        <div class="detail-value">#<?php echo $log['id']; ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Action</div>
                        <div class="detail-value">
                            <span class="badge badge-<?php echo getActionBadgeClass($log['action']); ?>">
                                <?php echo htmlspecialchars($log['action']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Description</div>
                        <div class="detail-value"><?php echo htmlspecialchars($log['description']); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Date & Time</div>
                        <div class="detail-value">
                            <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                            <small class="text-muted">(<?php echo timeAgo($log['created_at']); ?>)</small>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">IP Address</div>
                        <div class="detail-value">
                            <code><?php echo htmlspecialchars($log['ip_address']); ?></code>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">User Agent</div>
                        <div class="detail-value">
                            <div class="user-agent">
                                <?php echo htmlspecialchars($log['user_agent']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Additional Data -->
        <?php if ($additionalData): ?>
        <div class="card">
            <div class="card-header">
                <h3>Additional Data</h3>
            </div>
            <div class="card-body">
                <div class="additional-data">
                    <pre><?php echo htmlspecialchars(json_encode($additionalData, JSON_PRETTY_PRINT)); ?></pre>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- User Information -->
    <div class="col-md-4">
        <?php if ($log['user_id']): ?>
        <div class="card">
            <div class="card-header">
                <h3>User Information</h3>
            </div>
            <div class="card-body">
                <div class="user-detail">
                    <div class="user-avatar">
                        <?php if (!empty($log['profile_picture'])): ?>
                        <img src="../../uploads/profiles/<?php echo htmlspecialchars($log['profile_picture']); ?>" 
                             alt="Profile Picture" class="avatar-img">
                        <?php else: ?>
                        <div class="avatar-placeholder">
                            <?php echo strtoupper(substr($log['first_name'], 0, 1) . substr($log['last_name'], 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></h4>
                        <p class="text-muted">@<?php echo htmlspecialchars($log['username']); ?></p>
                        <p class="text-muted"><?php echo htmlspecialchars($log['email']); ?></p>
                        <span class="badge badge-secondary"><?php echo htmlspecialchars($log['role_name']); ?></span>
                    </div>
                </div>
                
                <div class="user-actions">
                    <a href="../users/view.php?id=<?php echo $log['user_id']; ?>" class="btn btn-info btn-block">
                        <i class="icon-eye"></i> View User Profile
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h3>System Activity</h3>
            </div>
            <div class="card-body">
                <div class="system-info">
                    <i class="icon-server"></i>
                    <h4>System Generated</h4>
                    <p>This activity was generated by the system, not by a specific user.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Related Activities -->
        <div class="card">
            <div class="card-header">
                <h3>Related Activities</h3>
            </div>
            <div class="card-body">
                <?php
                // Get related activities (same user, same day)
                $relatedQuery = "
                    SELECT al.*, u.first_name, u.last_name, u.username
                    FROM " . DB_PREFIX . "activity_logs al 
                    LEFT JOIN " . DB_PREFIX . "users u ON al.user_id = u.id 
                    WHERE al.id != ? AND DATE(al.created_at) = DATE(?) 
                    ORDER BY al.created_at DESC 
                    LIMIT 5
                ";
                $stmt = $conn->prepare($relatedQuery);
                $stmt->bind_param('is', $logId, $log['created_at']);
                $stmt->execute();
                $relatedLogs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                ?>
                
                <?php if (empty($relatedLogs)): ?>
                <div class="empty-state">
                    <i class="icon-activity"></i>
                    <p>No related activities found</p>
                </div>
                <?php else: ?>
                <div class="related-activities">
                    <?php foreach ($relatedLogs as $relatedLog): ?>
                    <div class="related-item">
                        <div class="related-action">
                            <span class="badge badge-sm badge-<?php echo getActionBadgeClass($relatedLog['action']); ?>">
                                <?php echo htmlspecialchars($relatedLog['action']); ?>
                            </span>
                        </div>
                        <div class="related-description">
                            <?php echo htmlspecialchars($relatedLog['description']); ?>
                        </div>
                        <div class="related-time">
                            <?php echo date('H:i:s', strtotime($relatedLog['created_at'])); ?>
                        </div>
                        <div class="related-link">
                            <a href="view.php?id=<?php echo $relatedLog['id']; ?>" class="btn btn-sm btn-outline-primary">
                                View
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * Get badge class based on action type
 */
function getActionBadgeClass($action) {
    $classes = [
        'login' => 'success',
        'logout' => 'secondary',
        'users.create' => 'primary',
        'users.edit' => 'warning',
        'users.delete' => 'danger',
        'roles.create' => 'primary',
        'roles.edit' => 'warning',
        'roles.delete' => 'danger',
        'settings.edit' => 'info',
        'password_change' => 'warning',
        'profile_update' => 'info'
    ];
    
    return $classes[$action] ?? 'secondary';
}

/**
 * Calculate time ago
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}
?>

<style>
.log-detail {
    space-y: 20px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 15px 0;
    border-bottom: 1px solid #f1f3f4;
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 500;
    color: #333;
    min-width: 120px;
}

.detail-value {
    flex: 1;
    text-align: right;
    color: #6c757d;
}

.user-agent {
    max-width: 300px;
    word-wrap: break-word;
    font-size: 12px;
    line-height: 1.4;
}

.additional-data {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 15px;
}

.additional-data pre {
    margin: 0;
    font-size: 12px;
    color: #333;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.user-detail {
    text-align: center;
    margin-bottom: 20px;
}

.user-avatar {
    margin-bottom: 15px;
}

.avatar-img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #dee2e6;
}

.avatar-placeholder {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background-color: #6c757d;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
    margin: 0 auto;
}

.user-info h4 {
    margin: 0 0 5px 0;
    color: #333;
}

.user-info p {
    margin: 0 0 5px 0;
    font-size: 14px;
}

.system-info {
    text-align: center;
    padding: 20px;
}

.system-info i {
    font-size: 48px;
    color: #6c757d;
    margin-bottom: 15px;
}

.system-info h4 {
    margin: 0 0 10px 0;
    color: #333;
}

.system-info p {
    margin: 0;
    color: #6c757d;
    font-size: 14px;
}

.related-activities {
    space-y: 15px;
}

.related-item {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f1f3f4;
}

.related-item:last-child {
    border-bottom: none;
}

.related-action {
    margin-right: 10px;
}

.related-description {
    flex: 1;
    font-size: 12px;
    color: #6c757d;
    margin-right: 10px;
}

.related-time {
    font-size: 11px;
    color: #999;
    margin-right: 10px;
    min-width: 60px;
}

.related-link {
    margin-left: auto;
}

.empty-state {
    text-align: center;
    padding: 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 32px;
    margin-bottom: 10px;
    opacity: 0.5;
}

code {
    background-color: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
}
</style>

<?php include '../includes/footer.php'; ?>
