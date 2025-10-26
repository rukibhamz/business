<?php
/**
 * Business Management System - Hall Settings
 * Phase 4: Hall Booking System Module
 */

// Define system constant
define('BMS_SYSTEM', true);

// Start session
session_start();

// Include required files
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/csrf.php';
require_once '../../../includes/hall-functions.php';

// Check authentication and permissions
requireLogin();
requirePermission('halls.settings');

// Get database connection
$conn = getDB();

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        // Get form data
        $settings = [
            'service_fee_percentage' => (float)($_POST['service_fee_percentage'] ?? 0),
            'service_fee_fixed' => (float)($_POST['service_fee_fixed'] ?? 0),
            'tax_rate' => (float)($_POST['tax_rate'] ?? 0),
            'min_deposit_percentage' => (float)($_POST['min_deposit_percentage'] ?? 0),
            'max_installments' => (int)($_POST['max_installments'] ?? 1),
            'booking_confirmation_email' => isset($_POST['booking_confirmation_email']) ? 1 : 0,
            'payment_reminder_email' => isset($_POST['payment_reminder_email']) ? 1 : 0,
            'booking_reminder_email' => isset($_POST['booking_reminder_email']) ? 1 : 0,
            'auto_generate_invoice' => isset($_POST['auto_generate_invoice']) ? 1 : 0,
            'auto_create_journal_entry' => isset($_POST['auto_create_journal_entry']) ? 1 : 0,
            'default_currency' => trim($_POST['default_currency'] ?? 'NGN'),
            'booking_timeout_minutes' => (int)($_POST['booking_timeout_minutes'] ?? 15),
            'enable_promo_codes' => isset($_POST['enable_promo_codes']) ? 1 : 0,
            'default_booking_advance_days' => (int)($_POST['default_booking_advance_days'] ?? 30),
            'enable_online_booking' => isset($_POST['enable_online_booking']) ? 1 : 0,
            'require_customer_registration' => isset($_POST['require_customer_registration']) ? 1 : 0
        ];
        
        // Validation
        if ($settings['service_fee_percentage'] < 0 || $settings['service_fee_percentage'] > 100) {
            $errors[] = 'Service fee percentage must be between 0 and 100';
        }
        
        if ($settings['service_fee_fixed'] < 0) {
            $errors[] = 'Service fee fixed amount cannot be negative';
        }
        
        if ($settings['tax_rate'] < 0 || $settings['tax_rate'] > 100) {
            $errors[] = 'Tax rate must be between 0 and 100';
        }
        
        if ($settings['min_deposit_percentage'] < 0 || $settings['min_deposit_percentage'] > 100) {
            $errors[] = 'Minimum deposit percentage must be between 0 and 100';
        }
        
        if ($settings['max_installments'] < 1 || $settings['max_installments'] > 12) {
            $errors[] = 'Maximum installments must be between 1 and 12';
        }
        
        if ($settings['booking_timeout_minutes'] < 5 || $settings['booking_timeout_minutes'] > 60) {
            $errors[] = 'Booking timeout must be between 5 and 60 minutes';
        }
        
        if ($settings['default_booking_advance_days'] < 1 || $settings['default_booking_advance_days'] > 365) {
            $errors[] = 'Default booking advance days must be between 1 and 365';
        }
        
        // Update settings if no errors
        if (empty($errors)) {
            $conn->begin_transaction();
            
            try {
                foreach ($settings as $key => $value) {
                    $stmt = $conn->prepare("
                        INSERT INTO " . DB_PREFIX . "hall_settings (setting_key, setting_value, updated_at) 
                        VALUES (?, ?, NOW())
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
                    ");
                    $stmt->bind_param('ss', $key, $value);
                    $stmt->execute();
                }
                
                $conn->commit();
                $success = true;
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Failed to update settings. Please try again.';
            }
        }
    }
}

// Get current settings
$currentSettings = [];
$stmt = $conn->query("SELECT setting_key, setting_value FROM " . DB_PREFIX . "hall_settings");
while ($row = $stmt->fetch_assoc()) {
    $currentSettings[$row['setting_key']] = $row['setting_value'];
}

// Set default values
$defaults = [
    'service_fee_percentage' => '2.5',
    'service_fee_fixed' => '0',
    'tax_rate' => '7.5',
    'min_deposit_percentage' => '30',
    'max_installments' => '3',
    'booking_confirmation_email' => '1',
    'payment_reminder_email' => '1',
    'booking_reminder_email' => '1',
    'auto_generate_invoice' => '1',
    'auto_create_journal_entry' => '1',
    'default_currency' => 'NGN',
    'booking_timeout_minutes' => '15',
    'enable_promo_codes' => '1',
    'default_booking_advance_days' => '30',
    'enable_online_booking' => '1',
    'require_customer_registration' => '0'
];

// Merge with current settings
$settings = array_merge($defaults, $currentSettings);

// Set page title
$pageTitle = 'Hall Settings';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Hall Settings</h1>
        <p>Configure hall booking system settings</p>
    </div>
    <div class="page-actions">
        <a href="../index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Halls
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h4><i class="icon-exclamation-triangle"></i> Please correct the following errors:</h4>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="icon-check-circle"></i> Settings updated successfully!
                </div>
                <?php endif; ?>
                
                <form method="POST" class="settings-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <!-- Pricing Settings -->
                    <div class="settings-section">
                        <h3><i class="icon-tags"></i> Pricing Settings</h3>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="service_fee_percentage">Service Fee Percentage (%)</label>
                                    <input type="number" name="service_fee_percentage" id="service_fee_percentage" 
                                           value="<?php echo htmlspecialchars($settings['service_fee_percentage']); ?>" 
                                           step="0.1" min="0" max="100" class="form-control">
                                    <small class="form-text text-muted">Percentage of total booking amount</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="service_fee_fixed">Service Fee Fixed Amount (₦)</label>
                                    <input type="number" name="service_fee_fixed" id="service_fee_fixed" 
                                           value="<?php echo htmlspecialchars($settings['service_fee_fixed']); ?>" 
                                           step="0.01" min="0" class="form-control">
                                    <small class="form-text text-muted">Fixed amount added to all bookings</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tax_rate">Tax Rate (%)</label>
                                    <input type="number" name="tax_rate" id="tax_rate" 
                                           value="<?php echo htmlspecialchars($settings['tax_rate']); ?>" 
                                           step="0.1" min="0" max="100" class="form-control">
                                    <small class="form-text text-muted">VAT or sales tax rate</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="default_currency">Default Currency</label>
                                    <select name="default_currency" id="default_currency" class="form-control">
                                        <option value="NGN" <?php echo $settings['default_currency'] == 'NGN' ? 'selected' : ''; ?>>NGN - Nigerian Naira</option>
                                        <option value="USD" <?php echo $settings['default_currency'] == 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                        <option value="EUR" <?php echo $settings['default_currency'] == 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                        <option value="GBP" <?php echo $settings['default_currency'] == 'GBP' ? 'selected' : ''; ?>>GBP - British Pound</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Settings -->
                    <div class="settings-section">
                        <h3><i class="icon-credit-card"></i> Payment Settings</h3>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="min_deposit_percentage">Minimum Deposit Percentage (%)</label>
                                    <input type="number" name="min_deposit_percentage" id="min_deposit_percentage" 
                                           value="<?php echo htmlspecialchars($settings['min_deposit_percentage']); ?>" 
                                           step="1" min="0" max="100" class="form-control">
                                    <small class="form-text text-muted">Minimum deposit for partial payments</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="max_installments">Maximum Installments</label>
                                    <input type="number" name="max_installments" id="max_installments" 
                                           value="<?php echo htmlspecialchars($settings['max_installments']); ?>" 
                                           step="1" min="1" max="12" class="form-control">
                                    <small class="form-text text-muted">Maximum number of payment installments</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="booking_timeout_minutes">Booking Timeout (minutes)</label>
                            <input type="number" name="booking_timeout_minutes" id="booking_timeout_minutes" 
                                   value="<?php echo htmlspecialchars($settings['booking_timeout_minutes']); ?>" 
                                   step="1" min="5" max="60" class="form-control">
                            <small class="form-text text-muted">Time limit for online payment completion</small>
                        </div>
                    </div>
                    
                    <!-- Booking Settings -->
                    <div class="settings-section">
                        <h3><i class="icon-calendar"></i> Booking Settings</h3>
                        
                        <div class="form-group">
                            <label for="default_booking_advance_days">Default Booking Advance Days</label>
                            <input type="number" name="default_booking_advance_days" id="default_booking_advance_days" 
                                   value="<?php echo htmlspecialchars($settings['default_booking_advance_days']); ?>" 
                                   step="1" min="1" max="365" class="form-control">
                            <small class="form-text text-muted">How many days in advance bookings can be made</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="enable_online_booking" id="enable_online_booking" 
                                           class="form-check-input" 
                                           <?php echo $settings['enable_online_booking'] ? 'checked' : ''; ?>>
                                    <label for="enable_online_booking" class="form-check-label">
                                        Enable Online Booking
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="require_customer_registration" id="require_customer_registration" 
                                           class="form-check-input" 
                                           <?php echo $settings['require_customer_registration'] ? 'checked' : ''; ?>>
                                    <label for="require_customer_registration" class="form-check-label">
                                        Require Customer Registration
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" name="enable_promo_codes" id="enable_promo_codes" 
                                   class="form-check-input" 
                                   <?php echo $settings['enable_promo_codes'] ? 'checked' : ''; ?>>
                            <label for="enable_promo_codes" class="form-check-label">
                                Enable Promo Codes and Discounts
                            </label>
                        </div>
                    </div>
                    
                    <!-- Email Settings -->
                    <div class="settings-section">
                        <h3><i class="icon-envelope"></i> Email Settings</h3>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" name="booking_confirmation_email" id="booking_confirmation_email" 
                                           class="form-check-input" 
                                           <?php echo $settings['booking_confirmation_email'] ? 'checked' : ''; ?>>
                                    <label for="booking_confirmation_email" class="form-check-label">
                                        Booking Confirmation Emails
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" name="payment_reminder_email" id="payment_reminder_email" 
                                           class="form-check-input" 
                                           <?php echo $settings['payment_reminder_email'] ? 'checked' : ''; ?>>
                                    <label for="payment_reminder_email" class="form-check-label">
                                        Payment Reminder Emails
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" name="booking_reminder_email" id="booking_reminder_email" 
                                           class="form-check-input" 
                                           <?php echo $settings['booking_reminder_email'] ? 'checked' : ''; ?>>
                                    <label for="booking_reminder_email" class="form-check-label">
                                        Booking Reminder Emails
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Accounting Integration -->
                    <div class="settings-section">
                        <h3><i class="icon-calculator"></i> Accounting Integration</h3>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="auto_generate_invoice" id="auto_generate_invoice" 
                                           class="form-check-input" 
                                           <?php echo $settings['auto_generate_invoice'] ? 'checked' : ''; ?>>
                                    <label for="auto_generate_invoice" class="form-check-label">
                                        Auto-generate Invoices
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="auto_create_journal_entry" id="auto_create_journal_entry" 
                                           class="form-check-input" 
                                           <?php echo $settings['auto_create_journal_entry'] ? 'checked' : ''; ?>>
                                    <label for="auto_create_journal_entry" class="form-check-label">
                                        Auto-create Journal Entries
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="icon-save"></i> Save Settings
                        </button>
                        <a href="../index.php" class="btn btn-secondary">
                            <i class="icon-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="icon-info-circle"></i> Settings Information</h5>
            </div>
            <div class="card-body">
                <p>Configure the hall booking system according to your business requirements.</p>
                
                <h6>Key Settings:</h6>
                <ul class="list-unstyled">
                    <li><i class="icon-check text-success"></i> <strong>Pricing:</strong> Set service fees and tax rates</li>
                    <li><i class="icon-check text-success"></i> <strong>Payments:</strong> Configure deposit and installment options</li>
                    <li><i class="icon-check text-success"></i> <strong>Booking:</strong> Control advance booking and online access</li>
                    <li><i class="icon-check text-success"></i> <strong>Email:</strong> Enable automated notifications</li>
                    <li><i class="icon-check text-success"></i> <strong>Accounting:</strong> Integrate with financial system</li>
                </ul>
                
                <div class="alert alert-info">
                    <i class="icon-lightbulb"></i>
                    <strong>Tip:</strong> Changes take effect immediately for new bookings.
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.settings-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.settings-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.settings-section h3 {
    margin-bottom: 20px;
    color: #333;
}

.form-check {
    margin-bottom: 15px;
}

.form-check-label {
    font-weight: 500;
}
</style>

<?php include '../../includes/footer.php'; ?>
