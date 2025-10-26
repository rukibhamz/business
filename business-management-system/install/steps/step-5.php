<?php
/**
 * Step 5: Installation Complete
 */

// Check if all required data is available
$canComplete = isset($_SESSION['db_config']) && 
               isset($_SESSION['site_config']) && 
               isset($_SESSION['admin_data']) && 
               ($_SESSION['db_installed'] ?? false);

if (!$canComplete) {
    // Redirect to appropriate step
    if (!isset($_SESSION['db_config'])) {
        header('Location: ?step=2');
    } elseif (!($_SESSION['db_installed'] ?? false)) {
        header('Location: ?step=2');
    } elseif (!isset($_SESSION['site_config'])) {
        header('Location: ?step=3');
    } elseif (!isset($_SESSION['admin_data'])) {
        header('Location: ?step=4');
    }
    exit;
}

// Get configuration data
$dbConfig = $_SESSION['db_config'];
$siteConfig = $_SESSION['site_config'];
$adminData = $_SESSION['admin_data'];
?>

<div class="installation-complete">
    <div class="success-header">
        <div class="success-icon">
            <i class="icon-check-circle"></i>
        </div>
        <h2>Installation Complete!</h2>
        <p>Your Business Management System has been successfully installed and configured.</p>
    </div>

    <div class="completion-summary">
        <h3>Installation Summary</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-icon">
                    <i class="icon-database"></i>
                </div>
                <div class="summary-content">
                    <h4>Database</h4>
                    <p>Database schema installed successfully</p>
                    <small>Host: <?php echo htmlspecialchars($dbConfig['host']); ?> | Database: <?php echo htmlspecialchars($dbConfig['database']); ?></small>
                </div>
            </div>

            <div class="summary-item">
                <div class="summary-icon">
                    <i class="icon-settings"></i>
                </div>
                <div class="summary-content">
                    <h4>Site Configuration</h4>
                    <p>Business settings configured</p>
                    <small><?php echo htmlspecialchars($siteConfig['company_name']); ?> | <?php echo $siteConfig['currency']; ?></small>
                </div>
            </div>

            <div class="summary-item">
                <div class="summary-icon">
                    <i class="icon-user"></i>
                </div>
                <div class="summary-content">
                    <h4>Admin Account</h4>
                    <p>Administrator account created</p>
                    <small>Username: <?php echo htmlspecialchars($adminData['username']); ?> | Email: <?php echo htmlspecialchars($adminData['email']); ?></small>
                </div>
            </div>

            <div class="summary-item">
                <div class="summary-icon">
                    <i class="icon-shield"></i>
                </div>
                <div class="summary-content">
                    <h4>Security</h4>
                    <p>Security measures implemented</p>
                    <small>Password hashed | CSRF protection | Session security</small>
                </div>
            </div>
        </div>
    </div>

    <div class="next-steps">
        <h3>What's Next?</h3>
        <div class="steps-list">
            <div class="step-item">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h4>Access Your Admin Panel</h4>
                    <p>Click the button below to go to your admin dashboard and start managing your business.</p>
                </div>
            </div>
            <div class="step-item">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h4>Explore the System</h4>
                    <p>Familiarize yourself with the dashboard, settings, and available modules.</p>
                </div>
            </div>
            <div class="step-item">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h4>Configure Additional Settings</h4>
                    <p>Set up email notifications, backup schedules, and other system preferences.</p>
                </div>
            </div>
            <div class="step-item">
                <div class="step-number">4</div>
                <div class="step-content">
                    <h4>Add Your Data</h4>
                    <p>Start adding your business data, users, and configuring modules as needed.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="login-credentials">
        <h3>Your Login Credentials</h3>
        <div class="credentials-box">
            <div class="credential-item">
                <strong>Admin Panel URL:</strong>
                <span><?php echo htmlspecialchars($siteConfig['site_url']); ?>/admin/</span>
            </div>
            <div class="credential-item">
                <strong>Username:</strong>
                <span><?php echo htmlspecialchars($adminData['username']); ?></span>
            </div>
            <div class="credential-item">
                <strong>Email:</strong>
                <span><?php echo htmlspecialchars($adminData['email']); ?></span>
            </div>
            <div class="credential-item">
                <strong>Password:</strong>
                <span class="password-hidden">••••••••</span>
                <small>Use the password you created during setup</small>
            </div>
        </div>
        <div class="security-warning">
            <i class="icon-warning"></i>
            <strong>Important:</strong> Save these credentials in a secure location. You can change your password after logging in.
        </div>
    </div>

    <div class="completion-actions">
        <form method="POST" action="" style="display: inline;">
            <input type="hidden" name="action" value="complete_installation">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <button type="submit" class="btn btn-success btn-large">
                <i class="icon-rocket"></i> Complete Installation & Go to Admin Panel
            </button>
        </form>
    </div>

    <div class="post-installation-info">
        <h3>Post-Installation Information</h3>
        <div class="info-grid">
            <div class="info-item">
                <h4><i class="icon-shield"></i> Security</h4>
                <ul>
                    <li>Installation directory will be automatically locked</li>
                    <li>Configuration files are protected</li>
                    <li>Regular security updates recommended</li>
                    <li>Backup your database regularly</li>
                </ul>
            </div>
            <div class="info-item">
                <h4><i class="icon-cog"></i> Maintenance</h4>
                <ul>
                    <li>Check system logs regularly</li>
                    <li>Monitor disk space usage</li>
                    <li>Update system when new versions are available</li>
                    <li>Test backups periodically</li>
                </ul>
            </div>
            <div class="info-item">
                <h4><i class="icon-support"></i> Support</h4>
                <ul>
                    <li>Documentation available in admin panel</li>
                    <li>System logs help with troubleshooting</li>
                    <li>Check system status in admin dashboard</li>
                    <li>Contact support if needed</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="system-info">
        <h3>System Information</h3>
        <div class="info-table">
            <div class="info-row">
                <span class="label">System Version:</span>
                <span class="value">1.0.0 (Phase 1)</span>
            </div>
            <div class="info-row">
                <span class="label">PHP Version:</span>
                <span class="value"><?php echo PHP_VERSION; ?></span>
            </div>
            <div class="info-row">
                <span class="label">Database:</span>
                <span class="value">MySQL/MariaDB</span>
            </div>
            <div class="info-row">
                <span class="label">Installation Date:</span>
                <span class="value"><?php echo date('Y-m-d H:i:s'); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Installation Directory:</span>
                <span class="value"><?php echo dirname(__DIR__); ?></span>
            </div>
        </div>
    </div>
</div>

<div class="final-notes">
    <h3>Final Notes</h3>
    <div class="notes-content">
        <div class="note-item">
            <i class="icon-info"></i>
            <p><strong>Installation Lock:</strong> The system will create an <code>installed.lock</code> file to prevent re-installation. This is normal and required for security.</p>
        </div>
        <div class="note-item">
            <i class="icon-warning"></i>
            <p><strong>Directory Access:</strong> After installation, the <code>/install</code> directory will be automatically blocked for security reasons.</p>
        </div>
        <div class="note-item">
            <i class="icon-check"></i>
            <p><strong>Ready to Use:</strong> Your Business Management System is now ready for use. You can start adding your business data and configuring modules.</p>
        </div>
    </div>
</div>

<script>
// Auto-submit form after 5 seconds if user doesn't click
let autoSubmitTimer = setTimeout(function() {
    document.querySelector('form[method="POST"]').submit();
}, 5000);

// Clear timer if user interacts
document.addEventListener('click', function() {
    clearTimeout(autoSubmitTimer);
});

// Show countdown
let countdown = 5;
const countdownElement = document.createElement('div');
countdownElement.className = 'countdown-notice';
countdownElement.innerHTML = '<i class="icon-clock"></i> Auto-redirecting to admin panel in <span id="countdown">5</span> seconds...';
document.querySelector('.completion-actions').appendChild(countdownElement);

const countdownInterval = setInterval(function() {
    countdown--;
    document.getElementById('countdown').textContent = countdown;
    if (countdown <= 0) {
        clearInterval(countdownInterval);
    }
}, 1000);
</script>
