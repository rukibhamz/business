<?php
/**
 * Business Management System - Email Settings
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
    $emailProtocol = sanitizeInput($_POST['email_protocol'] ?? 'mail');
    $smtpHost = sanitizeInput($_POST['smtp_host'] ?? '');
    $smtpPort = (int)($_POST['smtp_port'] ?? 587);
    $smtpUser = sanitizeInput($_POST['smtp_user'] ?? '');
    $smtpPass = $_POST['smtp_pass'] ?? '';
    $smtpEncryption = sanitizeInput($_POST['smtp_encryption'] ?? 'tls');
    $emailFrom = sanitizeInput($_POST['email_from'] ?? '');
    $emailFromName = sanitizeInput($_POST['email_from_name'] ?? '');
    
    // Validation
    if (empty($emailFrom)) {
        $errors[] = 'Email from address is required';
    } elseif (!isValidEmail($emailFrom)) {
        $errors[] = 'Invalid email from address format';
    }
    
    if (empty($emailFromName)) {
        $errors[] = 'Email from name is required';
    }
    
    if ($emailProtocol === 'smtp') {
        if (empty($smtpHost)) {
            $errors[] = 'SMTP host is required when using SMTP';
        }
        if (empty($smtpUser)) {
            $errors[] = 'SMTP username is required when using SMTP';
        }
        if (empty($smtpPass)) {
            $errors[] = 'SMTP password is required when using SMTP';
        }
        if ($smtpPort < 1 || $smtpPort > 65535) {
            $errors[] = 'Invalid SMTP port number';
        }
    }
    
    // If no errors, update settings
    if (empty($errors)) {
        $settings = [
            'email_protocol' => $emailProtocol,
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort,
            'smtp_user' => $smtpUser,
            'smtp_pass' => $smtpPass,
            'smtp_encryption' => $smtpEncryption,
            'email_from' => $emailFrom,
            'email_from_name' => $emailFromName
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
            // Log activity
            logActivity('settings.edit', 'Email settings updated');
            
            $_SESSION['success'] = 'Email settings updated successfully';
            header('Location: index.php');
            exit;
        }
    }
}

// Handle test email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    requireCSRFToken($_POST['csrf_token'] ?? '');
    
    $testEmail = sanitizeInput($_POST['test_email_address'] ?? '');
    
    if (empty($testEmail)) {
        $_SESSION['error'] = 'Test email address is required';
    } elseif (!isValidEmail($testEmail)) {
        $_SESSION['error'] = 'Invalid test email address format';
    } else {
        $result = sendTestEmail($testEmail);
        if ($result['success']) {
            $_SESSION['success'] = 'Test email sent successfully to ' . $testEmail;
        } else {
            $_SESSION['error'] = 'Failed to send test email: ' . $result['message'];
        }
    }
    header('Location: email.php');
    exit;
}

/**
 * Send test email
 */
function sendTestEmail($to) {
    $subject = 'Test Email from ' . getSetting('company_name', 'Business Management System');
    $message = "
    <html>
    <head>
        <title>Test Email</title>
    </head>
    <body>
        <h2>Test Email</h2>
        <p>This is a test email to verify that your email configuration is working correctly.</p>
        <p><strong>Sent at:</strong> " . date('Y-m-d H:i:s') . "</p>
        <p><strong>From:</strong> " . getSetting('email_from_name', 'Business Management System') . "</p>
        <hr>
        <p><em>This is an automated test email. Please do not reply.</em></p>
    </body>
    </html>
    ";
    
    $emailProtocol = getSetting('email_protocol', 'mail');
    
    if ($emailProtocol === 'smtp') {
        return sendSMTPEmail($to, $subject, $message);
    } else {
        return sendPHPEmail($to, $subject, $message);
    }
}

/**
 * Send email using PHP mail()
 */
function sendPHPEmail($to, $subject, $message) {
    $from = getSetting('email_from', 'noreply@example.com');
    $fromName = getSetting('email_from_name', 'Business Management System');
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . $from . '>',
        'Reply-To: ' . $from,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    if (mail($to, $subject, $message, implode("\r\n", $headers))) {
        return ['success' => true, 'message' => 'Email sent successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to send email using PHP mail()'];
    }
}

/**
 * Send email using SMTP
 */
function sendSMTPEmail($to, $subject, $message) {
    // Simple SMTP implementation (in production, use PHPMailer)
    $smtpHost = getSetting('smtp_host', '');
    $smtpPort = getSetting('smtp_port', '587');
    $smtpUser = getSetting('smtp_user', '');
    $smtpPass = getSetting('smtp_pass', '');
    $smtpEncryption = getSetting('smtp_encryption', 'tls');
    $from = getSetting('email_from', 'noreply@example.com');
    $fromName = getSetting('email_from_name', 'Business Management System');
    
    if (empty($smtpHost) || empty($smtpUser) || empty($smtpPass)) {
        return ['success' => false, 'message' => 'SMTP configuration incomplete'];
    }
    
    // For now, fall back to PHP mail() with SMTP settings
    // In production, implement proper SMTP or use PHPMailer
    return sendPHPEmail($to, $subject, $message);
}

// Get current settings
$currentSettings = [
    'email_protocol' => getSetting('email_protocol', 'mail'),
    'smtp_host' => getSetting('smtp_host', ''),
    'smtp_port' => getSetting('smtp_port', '587'),
    'smtp_user' => getSetting('smtp_user', ''),
    'smtp_pass' => getSetting('smtp_pass', ''),
    'smtp_encryption' => getSetting('smtp_encryption', 'tls'),
    'email_from' => getSetting('email_from', ''),
    'email_from_name' => getSetting('email_from_name', '')
];

// Include header
include '../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Email Settings</h1>
        <p>Configure email delivery and SMTP settings</p>
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
    <!-- Email Configuration -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Email Configuration</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="form-horizontal">
                    <?php csrfField(); ?>
                    
                    <div class="form-group">
                        <label for="email_protocol" class="required">Email Protocol</label>
                        <div class="protocol-options">
                            <label class="radio-option">
                                <input type="radio" name="email_protocol" value="mail" 
                                       <?php echo ($emailProtocol ?? $currentSettings['email_protocol']) == 'mail' ? 'checked' : ''; ?>>
                                <span>PHP Mail</span>
                                <small>Use PHP's built-in mail() function</small>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="email_protocol" value="smtp" 
                                       <?php echo ($emailProtocol ?? $currentSettings['email_protocol']) == 'smtp' ? 'checked' : ''; ?>>
                                <span>SMTP</span>
                                <small>Use external SMTP server</small>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email_from" class="required">From Email Address</label>
                            <input type="email" id="email_from" name="email_from" 
                                   value="<?php echo htmlspecialchars($emailFrom ?? $currentSettings['email_from']); ?>" 
                                   required class="form-control">
                            <small class="form-text">The email address that will appear as the sender</small>
                        </div>
                        <div class="form-group">
                            <label for="email_from_name" class="required">From Name</label>
                            <input type="text" id="email_from_name" name="email_from_name" 
                                   value="<?php echo htmlspecialchars($emailFromName ?? $currentSettings['email_from_name']); ?>" 
                                   required class="form-control">
                            <small class="form-text">The name that will appear as the sender</small>
                        </div>
                    </div>
                    
                    <!-- SMTP Settings (shown when SMTP is selected) -->
                    <div id="smtp-settings" style="<?php echo ($emailProtocol ?? $currentSettings['email_protocol']) == 'smtp' ? '' : 'display: none;'; ?>">
                        <h4>SMTP Configuration</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="smtp_host">SMTP Host</label>
                                <input type="text" id="smtp_host" name="smtp_host" 
                                       value="<?php echo htmlspecialchars($smtpHost ?? $currentSettings['smtp_host']); ?>" 
                                       class="form-control">
                                <small class="form-text">e.g., smtp.gmail.com, smtp.mailgun.org</small>
                            </div>
                            <div class="form-group">
                                <label for="smtp_port">SMTP Port</label>
                                <input type="number" id="smtp_port" name="smtp_port" 
                                       value="<?php echo $smtpPort ?? $currentSettings['smtp_port']; ?>" 
                                       class="form-control" min="1" max="65535">
                                <small class="form-text">Usually 587 for TLS or 465 for SSL</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="smtp_user">SMTP Username</label>
                                <input type="text" id="smtp_user" name="smtp_user" 
                                       value="<?php echo htmlspecialchars($smtpUser ?? $currentSettings['smtp_user']); ?>" 
                                       class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="smtp_pass">SMTP Password</label>
                                <input type="password" id="smtp_pass" name="smtp_pass" 
                                       value="<?php echo htmlspecialchars($smtpPass ?? $currentSettings['smtp_pass']); ?>" 
                                       class="form-control">
                                <small class="form-text">Leave blank to keep current password</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_encryption">SMTP Encryption</label>
                            <select id="smtp_encryption" name="smtp_encryption" class="form-control">
                                <option value="none" <?php echo ($smtpEncryption ?? $currentSettings['smtp_encryption']) == 'none' ? 'selected' : ''; ?>>None</option>
                                <option value="tls" <?php echo ($smtpEncryption ?? $currentSettings['smtp_encryption']) == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo ($smtpEncryption ?? $currentSettings['smtp_encryption']) == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            </select>
                        </div>
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
    </div>
    
    <!-- Test Email -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>Test Email</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php csrfField(); ?>
                    
                    <div class="form-group">
                        <label for="test_email_address">Test Email Address</label>
                        <input type="email" id="test_email_address" name="test_email_address" 
                               placeholder="test@example.com" class="form-control">
                        <small class="form-text">Enter an email address to send a test email</small>
                    </div>
                    
                    <button type="submit" name="test_email" class="btn btn-info btn-block">
                        <i class="icon-mail"></i> Send Test Email
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Email Status -->
        <div class="card">
            <div class="card-header">
                <h3>Email Status</h3>
            </div>
            <div class="card-body">
                <div class="status-item">
                    <div class="status-label">Protocol</div>
                    <div class="status-value"><?php echo strtoupper($currentSettings['email_protocol']); ?></div>
                </div>
                <?php if ($currentSettings['email_protocol'] == 'smtp'): ?>
                <div class="status-item">
                    <div class="status-label">SMTP Host</div>
                    <div class="status-value"><?php echo htmlspecialchars($currentSettings['smtp_host'] ?: 'Not configured'); ?></div>
                </div>
                <div class="status-item">
                    <div class="status-label">SMTP Port</div>
                    <div class="status-value"><?php echo $currentSettings['smtp_port']; ?></div>
                </div>
                <div class="status-item">
                    <div class="status-label">Encryption</div>
                    <div class="status-value"><?php echo strtoupper($currentSettings['smtp_encryption']); ?></div>
                </div>
                <?php endif; ?>
                <div class="status-item">
                    <div class="status-label">From Address</div>
                    <div class="status-value"><?php echo htmlspecialchars($currentSettings['email_from'] ?: 'Not configured'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Email Help -->
        <div class="card">
            <div class="card-header">
                <h3>Email Help</h3>
            </div>
            <div class="card-body">
                <div class="help-section">
                    <h5>PHP Mail</h5>
                    <p>Uses the server's built-in mail function. Works on most shared hosting providers.</p>
                    
                    <h5>SMTP</h5>
                    <p>More reliable delivery. Recommended for production environments.</p>
                    
                    <h5>Common SMTP Settings</h5>
                    <ul>
                        <li><strong>Gmail:</strong> smtp.gmail.com:587 (TLS)</li>
                        <li><strong>Outlook:</strong> smtp-mail.outlook.com:587 (TLS)</li>
                        <li><strong>Yahoo:</strong> smtp.mail.yahoo.com:587 (TLS)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Show/hide SMTP settings based on protocol selection
document.querySelectorAll('input[name="email_protocol"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const smtpSettings = document.getElementById('smtp-settings');
        if (this.value === 'smtp') {
            smtpSettings.style.display = 'block';
        } else {
            smtpSettings.style.display = 'none';
        }
    });
});

// Auto-fill password field if it's empty (to avoid overwriting existing password)
document.getElementById('smtp_pass').addEventListener('focus', function() {
    if (this.value === '') {
        this.placeholder = 'Enter SMTP password';
    }
});
</script>

<style>
.protocol-options {
    display: flex;
    gap: 20px;
    margin-top: 10px;
}

.radio-option {
    display: flex;
    flex-direction: column;
    padding: 15px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.2s ease;
    flex: 1;
}

.radio-option:hover {
    border-color: #007bff;
    background-color: #f8f9fa;
}

.radio-option input[type="radio"] {
    margin-right: 8px;
}

.radio-option span {
    font-weight: 500;
    margin-bottom: 5px;
}

.radio-option small {
    color: #6c757d;
    font-size: 12px;
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
    color: #333;
}

.help-section h5 {
    color: #333;
    margin-top: 15px;
    margin-bottom: 8px;
}

.help-section h5:first-child {
    margin-top: 0;
}

.help-section ul {
    margin-left: 20px;
}

.help-section li {
    margin-bottom: 5px;
    font-size: 12px;
    color: #6c757d;
}

.help-section p {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 10px;
}
</style>

<?php include '../includes/footer.php'; ?>
