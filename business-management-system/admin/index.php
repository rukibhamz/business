<?php
/**
 * Business Management System - Admin Dashboard
 * Phase 1: Core Foundation
 */

// Define system constant
define('BMS_SYSTEM', true);

// Include required files
require_once '../config/config.php';
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require login
requireLogin();

// Get current user
$currentUser = getCurrentUser();

// Get system statistics
$systemStats = getSystemStats();
$systemStatus = getSystemStatus();

// Get recent activity
$db = getDB();
$recentActivity = $db->fetchAll(
    "SELECT al.*, u.first_name, u.last_name 
     FROM " . $db->getTableName('activity_logs') . " al 
     LEFT JOIN " . $db->getTableName('users') . " u ON al.user_id = u.id 
     ORDER BY al.created_at DESC 
     LIMIT 10"
);

// Get user notifications
$notifications = getUserNotifications($currentUser['id'], true);

// Set page title
$pageTitle = 'Dashboard';

// Include header
include 'includes/header.php';
?>

<div class="dashboard">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-content">
            <h1>Welcome back, <?php echo htmlspecialchars($currentUser['first_name']); ?>!</h1>
            <p>Here's what's happening with your business today.</p>
        </div>
        <div class="welcome-actions">
            <button class="btn btn-primary" onclick="showQuickActions()">
                <i class="icon-plus"></i>
                Quick Actions
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="icon-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $systemStats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-change positive">
                    <i class="icon-arrow-up"></i>
                    +<?php echo $systemStats['active_users']; ?> active
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="icon-bell"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $systemStats['unread_notifications']; ?></div>
                <div class="stat-label">Unread Notifications</div>
                <div class="stat-change <?php echo $systemStats['unread_notifications'] > 0 ? 'negative' : 'neutral'; ?>">
                    <?php if ($systemStats['unread_notifications'] > 0): ?>
                    <i class="icon-arrow-up"></i>
                    New notifications
                    <?php else: ?>
                    <i class="icon-check"></i>
                    All caught up
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="icon-activity"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $systemStats['total_activity_logs']; ?></div>
                <div class="stat-label">Activity Logs</div>
                <div class="stat-change neutral">
                    <i class="icon-clock"></i>
                    System activity
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="icon-database"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $systemStatus['database'] ? 'Online' : 'Offline'; ?></div>
                <div class="stat-label">System Status</div>
                <div class="stat-change <?php echo $systemStatus['database'] ? 'positive' : 'negative'; ?>">
                    <i class="icon-<?php echo $systemStatus['database'] ? 'check' : 'warning'; ?>"></i>
                    <?php echo $systemStatus['database'] ? 'All systems operational' : 'Issues detected'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="dashboard-grid">
        <!-- Recent Activity -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="icon-activity"></i> Recent Activity</h3>
                <a href="#" class="card-action">View All</a>
            </div>
            <div class="card-content">
                <?php if (!empty($recentActivity)): ?>
                <div class="activity-list">
                    <?php foreach ($recentActivity as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="icon-<?php echo $activity['action'] === 'user.login' ? 'login' : 'activity'; ?>"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-description">
                                <?php if ($activity['first_name']): ?>
                                <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                                <?php else: ?>
                                <strong>System</strong>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($activity['description']); ?>
                            </div>
                            <div class="activity-time">
                                <?php echo timeAgo($activity['created_at']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="icon-activity"></i>
                    <p>No recent activity</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notifications -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="icon-bell"></i> Notifications</h3>
                <a href="#" class="card-action">View All</a>
            </div>
            <div class="card-content">
                <?php if (!empty($notifications)): ?>
                <div class="notification-list">
                    <?php foreach (array_slice($notifications, 0, 5) as $notification): ?>
                    <div class="notification-item">
                        <div class="notification-icon">
                            <i class="icon-<?php echo getNotificationClass($notification['type']); ?>"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                            <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                            <div class="notification-time"><?php echo timeAgo($notification['created_at']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="icon-bell"></i>
                    <p>No notifications</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- System Status -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="icon-monitor"></i> System Status</h3>
            </div>
            <div class="card-content">
                <div class="status-list">
                    <div class="status-item">
                        <div class="status-label">Database</div>
                        <div class="status-value">
                            <span class="status-indicator <?php echo $systemStatus['database'] ? 'online' : 'offline'; ?>"></span>
                            <?php echo $systemStatus['database'] ? 'Online' : 'Offline'; ?>
                        </div>
                    </div>
                    <div class="status-item">
                        <div class="status-label">Cache</div>
                        <div class="status-value">
                            <span class="status-indicator <?php echo $systemStatus['cache'] ? 'online' : 'offline'; ?>"></span>
                            <?php echo $systemStatus['cache'] ? 'Online' : 'Offline'; ?>
                        </div>
                    </div>
                    <div class="status-item">
                        <div class="status-label">Uploads</div>
                        <div class="status-value">
                            <span class="status-indicator <?php echo $systemStatus['uploads'] ? 'online' : 'offline'; ?>"></span>
                            <?php echo $systemStatus['uploads'] ? 'Online' : 'Offline'; ?>
                        </div>
                    </div>
                    <div class="status-item">
                        <div class="status-label">Logs</div>
                        <div class="status-value">
                            <span class="status-indicator <?php echo $systemStatus['logs'] ? 'online' : 'offline'; ?>"></span>
                            <?php echo $systemStatus['logs'] ? 'Online' : 'Offline'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="icon-zap"></i> Quick Actions</h3>
            </div>
            <div class="card-content">
                <div class="quick-actions">
                    <button class="quick-action-btn" onclick="showUserManagement()">
                        <i class="icon-users"></i>
                        <span>Manage Users</span>
                    </button>
                    <button class="quick-action-btn" onclick="showSettings()">
                        <i class="icon-settings"></i>
                        <span>System Settings</span>
                    </button>
                    <button class="quick-action-btn" onclick="showReports()">
                        <i class="icon-chart"></i>
                        <span>View Reports</span>
                    </button>
                    <button class="quick-action-btn" onclick="showHelp()">
                        <i class="icon-help"></i>
                        <span>Get Help</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Installation Success Message (only show once) -->
    <?php if (!isset($_SESSION['installation_shown'])): ?>
    <div class="installation-success">
        <div class="success-content">
            <div class="success-icon">
                <i class="icon-check-circle"></i>
            </div>
            <div class="success-text">
                <h3>Installation Complete!</h3>
                <p>Your Business Management System has been successfully installed and is ready to use.</p>
            </div>
            <button class="btn btn-secondary" onclick="dismissInstallationMessage()">
                <i class="icon-close"></i>
                Dismiss
            </button>
        </div>
    </div>
    <?php $_SESSION['installation_shown'] = true; ?>
    <?php endif; ?>
</div>

<script>
// Page-specific JavaScript
function initializePage() {
    // Initialize dashboard functionality
    initializeDashboard();
}

function initializeDashboard() {
    // Auto-refresh stats every 30 seconds
    setInterval(refreshStats, 30000);
    
    // Initialize tooltips
    initializeTooltips();
}

function refreshStats() {
    // This would make an AJAX call to refresh stats
    console.log('Refreshing dashboard stats...');
}

function showQuickActions() {
    // Show quick actions modal or dropdown
    console.log('Showing quick actions...');
}

function showUserManagement() {
    // Navigate to user management
    console.log('Opening user management...');
    showAlert('User management module coming soon!', 'info');
}

function showSettings() {
    // Navigate to settings
    console.log('Opening settings...');
    showAlert('Settings module coming soon!', 'info');
}

function showReports() {
    // Navigate to reports
    console.log('Opening reports...');
    showAlert('Reports module coming soon!', 'info');
}

function showHelp() {
    // Show help modal or navigate to help
    console.log('Opening help...');
    showAlert('Help documentation coming soon!', 'info');
}

function dismissInstallationMessage() {
    const message = document.querySelector('.installation-success');
    if (message) {
        message.style.display = 'none';
    }
}

// Initialize dashboard when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Add any additional initialization here
    console.log('Dashboard initialized');
});
</script>

<?php include 'includes/footer.php'; ?>
