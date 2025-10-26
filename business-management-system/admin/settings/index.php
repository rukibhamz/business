<?php
/**
 * Business Management System - Settings Dashboard
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
requirePermission('settings.view');

// Get database connection
$conn = getDB();

// Include header
include '../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>System Settings</h1>
        <p>Configure and manage system-wide settings</p>
    </div>
</div>

<div class="settings-grid">
    <!-- General Settings -->
    <div class="settings-card">
        <div class="settings-icon">
            <i class="icon-settings"></i>
        </div>
        <div class="settings-content">
            <h3>General Settings</h3>
            <p>Company information, site configuration, and basic preferences</p>
            <div class="settings-actions">
                <a href="general.php" class="btn btn-primary">
                    <i class="icon-edit"></i> Configure
                </a>
            </div>
        </div>
    </div>
    
    <!-- Email Settings -->
    <div class="settings-card">
        <div class="settings-icon">
            <i class="icon-mail"></i>
        </div>
        <div class="settings-content">
            <h3>Email Settings</h3>
            <p>SMTP configuration, email templates, and notification preferences</p>
            <div class="settings-actions">
                <a href="email.php" class="btn btn-primary">
                    <i class="icon-edit"></i> Configure
                </a>
            </div>
        </div>
    </div>
    
    <!-- System Settings -->
    <div class="settings-card">
        <div class="settings-icon">
            <i class="icon-server"></i>
        </div>
        <div class="settings-content">
            <h3>System Settings</h3>
            <p>Security, performance, maintenance mode, and system preferences</p>
            <div class="settings-actions">
                <a href="system.php" class="btn btn-primary">
                    <i class="icon-edit"></i> Configure
                </a>
            </div>
        </div>
    </div>
    
    <!-- Tax Configuration -->
    <div class="settings-card">
        <div class="settings-icon">
            <i class="icon-percent"></i>
        </div>
        <div class="settings-content">
            <h3>Tax Configuration</h3>
            <p>Tax rates, tax types, and tax calculation settings</p>
            <div class="settings-actions">
                <a href="#" class="btn btn-secondary" disabled>
                    <i class="icon-lock"></i> Coming Soon
                </a>
            </div>
        </div>
    </div>
    
    <!-- Payment Gateways -->
    <div class="settings-card">
        <div class="settings-icon">
            <i class="icon-credit-card"></i>
        </div>
        <div class="settings-content">
            <h3>Payment Gateways</h3>
            <p>Payment processor configuration and gateway settings</p>
            <div class="settings-actions">
                <a href="#" class="btn btn-secondary" disabled>
                    <i class="icon-lock"></i> Coming Soon
                </a>
            </div>
        </div>
    </div>
    
    <!-- Backup & Restore -->
    <div class="settings-card">
        <div class="settings-icon">
            <i class="icon-download"></i>
        </div>
        <div class="settings-content">
            <h3>Backup & Restore</h3>
            <p>Database backups, file backups, and system restoration</p>
            <div class="settings-actions">
                <a href="#" class="btn btn-secondary" disabled>
                    <i class="icon-lock"></i> Coming Soon
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Quick Settings Overview -->
<div class="card">
    <div class="card-header">
        <h3>Current Settings Overview</h3>
    </div>
    <div class="card-body">
        <div class="settings-overview">
            <div class="overview-item">
                <div class="overview-label">Company Name</div>
                <div class="overview-value"><?php echo htmlspecialchars(getSetting('company_name', 'Not Set')); ?></div>
            </div>
            <div class="overview-item">
                <div class="overview-label">Site URL</div>
                <div class="overview-value"><?php echo htmlspecialchars(getSetting('site_url', 'Not Set')); ?></div>
            </div>
            <div class="overview-item">
                <div class="overview-label">Timezone</div>
                <div class="overview-value"><?php echo htmlspecialchars(getSetting('timezone', 'UTC')); ?></div>
            </div>
            <div class="overview-item">
                <div class="overview-label">Currency</div>
                <div class="overview-value"><?php echo htmlspecialchars(getSetting('currency', 'USD')); ?></div>
            </div>
            <div class="overview-item">
                <div class="overview-label">Email Protocol</div>
                <div class="overview-value"><?php echo htmlspecialchars(getSetting('email_protocol', 'mail')); ?></div>
            </div>
            <div class="overview-item">
                <div class="overview-label">Maintenance Mode</div>
                <div class="overview-value">
                    <span class="badge badge-<?php echo getSetting('maintenance_mode') == '1' ? 'warning' : 'success'; ?>">
                        <?php echo getSetting('maintenance_mode') == '1' ? 'Enabled' : 'Disabled'; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Information -->
<div class="card">
    <div class="card-header">
        <h3>System Information</h3>
    </div>
    <div class="card-body">
        <div class="system-info">
            <div class="info-item">
                <div class="info-label">PHP Version</div>
                <div class="info-value"><?php echo PHP_VERSION; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Server Software</div>
                <div class="info-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Memory Limit</div>
                <div class="info-value"><?php echo ini_get('memory_limit'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Max Execution Time</div>
                <div class="info-value"><?php echo ini_get('max_execution_time'); ?> seconds</div>
            </div>
            <div class="info-item">
                <div class="info-label">Upload Max Filesize</div>
                <div class="info-value"><?php echo ini_get('upload_max_filesize'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Post Max Size</div>
                <div class="info-value"><?php echo ini_get('post_max_size'); ?></div>
            </div>
        </div>
    </div>
</div>

<style>
.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.settings-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    transition: all 0.3s ease;
    display: flex;
    align-items: flex-start;
}

.settings-card:hover {
    border-color: #007bff;
    box-shadow: 0 4px 12px rgba(0,123,255,0.15);
    transform: translateY(-2px);
}

.settings-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #007bff, #0056b3);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    flex-shrink: 0;
}

.settings-icon i {
    font-size: 24px;
    color: white;
}

.settings-content {
    flex: 1;
}

.settings-content h3 {
    margin: 0 0 8px 0;
    color: #333;
    font-size: 18px;
}

.settings-content p {
    margin: 0 0 15px 0;
    color: #6c757d;
    font-size: 14px;
    line-height: 1.4;
}

.settings-actions {
    margin-top: auto;
}

.settings-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.overview-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
    border-left: 4px solid #007bff;
}

.overview-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.overview-value {
    font-size: 16px;
    font-weight: 500;
    color: #333;
}

.system-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.info-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
}

.info-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.info-value {
    font-size: 14px;
    font-weight: 500;
    color: #333;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>

<?php include '../includes/footer.php'; ?>
