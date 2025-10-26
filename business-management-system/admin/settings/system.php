<?php
/**
 * Business Management System - System Settings
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
requirePermission('settings.edit');

// Get database connection
$conn = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken($_POST['csrf_token'] ?? '');
    
    $errors = [];
    $success = false;
    
    // Get form data
    $maintenanceMode = isset($_POST['maintenance_mode']) ? 1 : 0;
    $maintenanceMessage = sanitizeInput($_POST['maintenance_message'] ?? '');
    $sessionTimeout = (int)($_POST['session_timeout'] ?? 3600);
    $recordsPerPage = (int)($_POST['records_per_page'] ?? 25);
    $enableRegistration = isset($_POST['enable_registration']) ? 1 : 0;
    $defaultUserRole = (int)($_POST['default_user_role'] ?? 5);
    $enableEmailNotifications = isset($_POST['enable_email_notifications']) ? 1 : 0;
    $enableSMSNotifications = isset($_POST['enable_sms_notifications']) ? 1 : 0;
    $defaultLanguage = sanitizeInput($_POST['default_language'] ?? 'en');
    
    // Validation
    if ($sessionTimeout < 300 || $sessionTimeout > 86400) {
        $errors[] = 'Session timeout must be between 5 minutes and 24 hours';
    }
    
    if ($recordsPerPage < 5 || $recordsPerPage > 100) {
        $errors[] = 'Records per page must be between 5 and 100';
    }
    
    if ($defaultUserRole <= 0) {
        $errors[] = 'Please select a valid default user role';
    }
    
    // If no errors, update settings
    if (empty($errors)) {
        $settings = [
            'maintenance_mode' => $maintenanceMode,
            'maintenance_message' => $maintenanceMessage,
            'session_timeout' => $sessionTimeout,
            'records_per_page' => $recordsPerPage,
            'enable_registration' => $enableRegistration,
            'default_user_role' => $defaultUserRole,
            'enable_email_notifications' => $enableEmailNotifications,
            'enable_sms_notifications' => $enableSMSNotifications,
            'default_language' => $defaultLanguage
        ];
        
        $success = true;
        foreach ($settings as $key => $value) {
            if (!updateSetting($key, $value)) {
                $success = false;
                $errors[] = "Failed to update {$key}";
                break;
            }
        }
        
        if ($success) {
            // Handle maintenance mode
            if ($maintenanceMode) {
                file_put_contents('../../maintenance.lock', date('Y-m-d H:i:s'));
            } else {
                if (file_exists('../../maintenance.lock')) {
                    unlink('../../maintenance.lock');
                }
            }
            
            // Log activity
            logActivity('settings.edit', 'System settings updated');
            
            $_SESSION['success'] = 'System settings updated successfully';
            header('Location: index.php');
            exit;
        }
    }
}

// Handle system actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['system_action'])) {
    requireCSRFToken($_POST['csrf_token'] ?? '');
    
    $action = $_POST['system_action'];
    
    switch ($action) {
        case 'clear_cache':
            clearSystemCache();
            $_SESSION['success'] = 'System cache cleared successfully';
            break;
        case 'backup_database':
            $result = backupDatabase();
            if ($result['success']) {
                $_SESSION['success'] = 'Database backup created: ' . $result['filename'];
            } else {
                $_SESSION['error'] = 'Failed to create database backup: ' . $result['message'];
            }
            break;
    }
    
    header('Location: system.php');
    exit;
}

/**
 * Clear system cache
 */
function clearSystemCache() {
    $cacheDir = '../../cache/';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    logActivity('system.clear_cache', 'System cache cleared');
}

/**
 * Create database backup
 */
function backupDatabase() {
    global $conn;
    
    try {
        $backupDir = '../../backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . $filename;
        
        // Get database name
        $dbName = DB_NAME;
        
        // Create backup using mysqldump (if available)
        $command = "mysqldump -h " . DB_HOST . " -u " . DB_USER . " -p" . DB_PASS . " " . $dbName . " > " . $filepath;
        
        // For now, create a simple backup file
        $backupContent = "-- Database Backup\n";
        $backupContent .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
        $backupContent .= "-- Database: " . $dbName . "\n\n";
        
        // Get all tables
        $tables = $conn->query("SHOW TABLES")->fetch_all(MYSQLI_NUM);
        
        foreach ($tables as $table) {
            $tableName = $table[0];
            $backupContent .= "-- Table: {$tableName}\n";
            
            // Get table structure
            $createTable = $conn->query("SHOW CREATE TABLE `{$tableName}`")->fetch_assoc();
            $backupContent .= $createTable['Create Table'] . ";\n\n";
            
            // Get table data
            $data = $conn->query("SELECT * FROM `{$tableName}`");
            while ($row = $data->fetch_assoc()) {
                $values = array_map(function($value) use ($conn) {
                    return $conn->real_escape_string($value);
                }, array_values($row));
                
                $backupContent .= "INSERT INTO `{$tableName}` VALUES ('" . implode("', '", $values) . "');\n";
            }
            $backupContent .= "\n";
        }
        
        if (file_put_contents($filepath, $backupContent)) {
            logActivity('system.backup', 'Database backup created', ['filename' => $filename]);
            return ['success' => true, 'filename' => $filename];
        } else {
            return ['success' => false, 'message' => 'Failed to write backup file'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Get current settings
$currentSettings = [
    'maintenance_mode' => getSetting('maintenance_mode', '0'),
    'maintenance_message' => getSetting('maintenance_message', ''),
    'session_timeout' => getSetting('session_timeout', '3600'),
    'records_per_page' => getSetting('records_per_page', '25'),
    'enable_registration' => getSetting('enable_registration', '0'),
    'default_user_role' => getSetting('default_user_role', '5'),
    'enable_email_notifications' => getSetting('enable_email_notifications', '1'),
    'enable_sms_notifications' => getSetting('enable_sms_notifications', '0'),
    'default_language' => getSetting('default_language', 'en')
];

// Get roles for default user role dropdown
$rolesQuery = "SELECT id, name FROM " . DB_PREFIX . "roles WHERE is_active = 1 ORDER BY name";
$roles = $conn->query($rolesQuery)->fetch_all(MYSQLI_ASSOC);

// Get system information
$systemInfo = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'disk_space' => disk_free_space('../../'),
    'mysql_version' => $conn->getConnection()->getAttribute(PDO::ATTR_SERVER_VERSION)
];

// Include header
include '../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>System Settings</h1>
        <p>Configure system behavior, security, and maintenance options</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Settings
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

<div class="row">
    <!-- System Configuration -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>System Configuration</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="form-horizontal">
                    <?php csrfField(); ?>
                    
                    <!-- Maintenance Mode -->
                    <div class="form-group">
                        <label>Maintenance Mode</label>
                        <div class="form-check">
                            <input type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                   <?php echo ($maintenanceMode ?? $currentSettings['maintenance_mode']) ? 'checked' : ''; ?> 
                                   class="form-check-input">
                            <label for="maintenance_mode" class="form-check-label">Enable maintenance mode</label>
                        </div>
                        <small class="form-text">When enabled, only administrators can access the system</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="maintenance_message">Maintenance Message</label>
                        <textarea id="maintenance_message" name="maintenance_message" 
                                  class="form-control" rows="3"
                                  placeholder="System is currently under maintenance. Please check back later."><?php echo htmlspecialchars($maintenanceMessage ?? $currentSettings['maintenance_message']); ?></textarea>
                    </div>
                    
                    <!-- Session Settings -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="session_timeout">Session Timeout (seconds)</label>
                            <input type="number" id="session_timeout" name="session_timeout" 
                                   value="<?php echo $sessionTimeout ?? $currentSettings['session_timeout']; ?>" 
                                   class="form-control" min="300" max="86400">
                            <small class="form-text">How long users stay logged in (300-86400 seconds)</small>
                        </div>
                        <div class="form-group">
                            <label for="records_per_page">Records Per Page</label>
                            <input type="number" id="records_per_page" name="records_per_page" 
                                   value="<?php echo $recordsPerPage ?? $currentSettings['records_per_page']; ?>" 
                                   class="form-control" min="5" max="100">
                            <small class="form-text">Default number of records per page (5-100)</small>
                        </div>
                    </div>
                    
                    <!-- User Registration -->
                    <div class="form-group">
                        <label>User Registration</label>
                        <div class="form-check">
                            <input type="checkbox" id="enable_registration" name="enable_registration" 
                                   <?php echo ($enableRegistration ?? $currentSettings['enable_registration']) ? 'checked' : ''; ?> 
                                   class="form-check-input">
                            <label for="enable_registration" class="form-check-label">Enable user registration</label>
                        </div>
                        <small class="form-text">Allow new users to register accounts</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="default_user_role">Default User Role</label>
                        <select id="default_user_role" name="default_user_role" class="form-control">
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>" 
                                    <?php echo ($defaultUserRole ?? $currentSettings['default_user_role']) == $role['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Role assigned to new user registrations</small>
                    </div>
                    
                    <!-- Notifications -->
                    <div class="form-group">
                        <label>Notifications</label>
                        <div class="form-check">
                            <input type="checkbox" id="enable_email_notifications" name="enable_email_notifications" 
                                   <?php echo ($enableEmailNotifications ?? $currentSettings['enable_email_notifications']) ? 'checked' : ''; ?> 
                                   class="form-check-input">
                            <label for="enable_email_notifications" class="form-check-label">Enable email notifications</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="enable_sms_notifications" name="enable_sms_notifications" 
                                   <?php echo ($enableSMSNotifications ?? $currentSettings['enable_sms_notifications']) ? 'checked' : ''; ?> 
                                   class="form-check-input">
                            <label for="enable_sms_notifications" class="form-check-label">Enable SMS notifications</label>
                        </div>
                    </div>
                    
                    <!-- Language -->
                    <div class="form-group">
                        <label for="default_language">Default Language</label>
                        <select id="default_language" name="default_language" class="form-control">
                            <option value="en" <?php echo ($defaultLanguage ?? $currentSettings['default_language']) == 'en' ? 'selected' : ''; ?>>English</option>
                            <option value="es" <?php echo ($defaultLanguage ?? $currentSettings['default_language']) == 'es' ? 'selected' : ''; ?>>Spanish</option>
                            <option value="fr" <?php echo ($defaultLanguage ?? $currentSettings['default_language']) == 'fr' ? 'selected' : ''; ?>>French</option>
                            <option value="de" <?php echo ($defaultLanguage ?? $currentSettings['default_language']) == 'de' ? 'selected' : ''; ?>>German</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="icon-save"></i> Save Settings
                        </button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- System Actions -->
        <div class="card">
            <div class="card-header">
                <h3>System Actions</h3>
            </div>
            <div class="card-body">
                <div class="system-actions">
                    <form method="POST" style="display: inline;">
                        <?php csrfField(); ?>
                        <input type="hidden" name="system_action" value="clear_cache">
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to clear the system cache?')">
                            <i class="icon-trash"></i> Clear Cache
                        </button>
                    </form>
                    
                    <form method="POST" style="display: inline;">
                        <?php csrfField(); ?>
                        <input type="hidden" name="system_action" value="backup_database">
                        <button type="submit" class="btn btn-info" onclick="return confirm('Are you sure you want to create a database backup?')">
                            <i class="icon-download"></i> Backup Database
                        </button>
                    </form>
                    
                    <a href="../activity/index.php" class="btn btn-secondary">
                        <i class="icon-activity"></i> View System Logs
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- System Information -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>System Information</h3>
            </div>
            <div class="card-body">
                <div class="system-info">
                    <div class="info-item">
                        <div class="info-label">PHP Version</div>
                        <div class="info-value"><?php echo $systemInfo['php_version']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Server Software</div>
                        <div class="info-value"><?php echo htmlspecialchars($systemInfo['server_software']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">MySQL Version</div>
                        <div class="info-value"><?php echo htmlspecialchars($systemInfo['mysql_version']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Memory Limit</div>
                        <div class="info-value"><?php echo $systemInfo['memory_limit']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Max Execution Time</div>
                        <div class="info-value"><?php echo $systemInfo['max_execution_time']; ?> seconds</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Upload Max Filesize</div>
                        <div class="info-value"><?php echo $systemInfo['upload_max_filesize']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Post Max Size</div>
                        <div class="info-value"><?php echo $systemInfo['post_max_size']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Available Disk Space</div>
                        <div class="info-value"><?php echo formatBytes($systemInfo['disk_space']); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- System Status -->
        <div class="card">
            <div class="card-header">
                <h3>System Status</h3>
            </div>
            <div class="card-body">
                <div class="status-item">
                    <div class="status-label">Maintenance Mode</div>
                    <div class="status-value">
                        <span class="badge badge-<?php echo $currentSettings['maintenance_mode'] == '1' ? 'warning' : 'success'; ?>">
                            <?php echo $currentSettings['maintenance_mode'] == '1' ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-label">User Registration</div>
                    <div class="status-value">
                        <span class="badge badge-<?php echo $currentSettings['enable_registration'] == '1' ? 'success' : 'secondary'; ?>">
                            <?php echo $currentSettings['enable_registration'] == '1' ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-label">Email Notifications</div>
                    <div class="status-value">
                        <span class="badge badge-<?php echo $currentSettings['enable_email_notifications'] == '1' ? 'success' : 'secondary'; ?>">
                            <?php echo $currentSettings['enable_email_notifications'] == '1' ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-label">SMS Notifications</div>
                    <div class="status-value">
                        <span class="badge badge-<?php echo $currentSettings['enable_sms_notifications'] == '1' ? 'success' : 'secondary'; ?>">
                            <?php echo $currentSettings['enable_sms_notifications'] == '1' ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>

<style>
.form-check {
    margin-top: 8px;
}

.form-check-input {
    margin-right: 8px;
}

.system-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.system-actions .btn {
    margin-bottom: 10px;
}

.system-info {
    space-y: 15px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f1f3f4;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 14px;
    font-weight: 500;
    color: #333;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f1f3f4;
}

.status-item:last-child {
    border-bottom: none;
}

.status-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-value {
    font-weight: 500;
}
</style>

<?php include '../includes/footer.php'; ?>
