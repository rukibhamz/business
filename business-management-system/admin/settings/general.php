<?php
/**
 * Business Management System - General Settings
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
    $companyName = sanitizeInput($_POST['company_name'] ?? '');
    $companyEmail = sanitizeInput($_POST['company_email'] ?? '');
    $companyPhone = sanitizeInput($_POST['company_phone'] ?? '');
    $companyAddress = sanitizeInput($_POST['company_address'] ?? '');
    $siteUrl = sanitizeInput($_POST['site_url'] ?? '');
    $timezone = sanitizeInput($_POST['timezone'] ?? 'UTC');
    $dateFormat = sanitizeInput($_POST['date_format'] ?? 'Y-m-d');
    $timeFormat = sanitizeInput($_POST['time_format'] ?? 'H:i:s');
    $currency = sanitizeInput($_POST['currency'] ?? 'USD');
    $currencySymbol = sanitizeInput($_POST['currency_symbol'] ?? '$');
    $fiscalYearStart = sanitizeInput($_POST['fiscal_year_start'] ?? '1');
    
    // Validation
    if (empty($companyName)) {
        $errors[] = 'Company name is required';
    }
    
    if (empty($companyEmail)) {
        $errors[] = 'Company email is required';
    } elseif (!isValidEmail($companyEmail)) {
        $errors[] = 'Invalid company email format';
    }
    
    if (empty($siteUrl)) {
        $errors[] = 'Site URL is required';
    } elseif (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid site URL format';
    }
    
    // If no errors, update settings
    if (empty($errors)) {
        $settings = [
            'company_name' => $companyName,
            'company_email' => $companyEmail,
            'company_phone' => $companyPhone,
            'company_address' => $companyAddress,
            'site_url' => $siteUrl,
            'timezone' => $timezone,
            'date_format' => $dateFormat,
            'time_format' => $timeFormat,
            'currency' => $currency,
            'currency_symbol' => $currencySymbol,
            'fiscal_year_start' => $fiscalYearStart
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
            logActivity('settings.edit', 'General settings updated');
            
            $_SESSION['success'] = 'General settings updated successfully';
            header('Location: index.php');
            exit;
        }
    }
}

// Handle logo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['company_logo'])) {
    requireCSRFToken($_POST['csrf_token'] ?? '');
    
    if ($_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = handleLogoUpload($_FILES['company_logo']);
        if ($uploadResult['success']) {
            updateSetting('company_logo', $uploadResult['filename']);
            $_SESSION['success'] = 'Company logo updated successfully';
        } else {
            $_SESSION['error'] = $uploadResult['message'];
        }
        header('Location: general.php');
        exit;
    }
}

/**
 * Handle logo upload
 */
function handleLogoUpload($file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size too large. Maximum 5MB allowed.'];
    }
    
    $uploadDir = '../../uploads/logos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'logo.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'message' => 'Failed to upload logo.'];
    }
}

// Get current settings
$currentSettings = [
    'company_name' => getSetting('company_name', ''),
    'company_email' => getSetting('company_email', ''),
    'company_phone' => getSetting('company_phone', ''),
    'company_address' => getSetting('company_address', ''),
    'site_url' => getSetting('site_url', ''),
    'timezone' => getSetting('timezone', 'UTC'),
    'date_format' => getSetting('date_format', 'Y-m-d'),
    'time_format' => getSetting('time_format', 'H:i:s'),
    'currency' => getSetting('currency', 'USD'),
    'currency_symbol' => getSetting('currency_symbol', '$'),
    'fiscal_year_start' => getSetting('fiscal_year_start', '1'),
    'company_logo' => getSetting('company_logo', '')
];

// Include header
include '../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>General Settings</h1>
        <p>Configure company information and basic system preferences</p>
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
    <!-- Company Information -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Company Information</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="form-horizontal">
                    <?php csrfField(); ?>
                    
                    <div class="form-group">
                        <label for="company_name" class="required">Company Name</label>
                        <input type="text" id="company_name" name="company_name" 
                               value="<?php echo htmlspecialchars($companyName ?? $currentSettings['company_name']); ?>" 
                               required class="form-control">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="company_email" class="required">Company Email</label>
                            <input type="email" id="company_email" name="company_email" 
                                   value="<?php echo htmlspecialchars($companyEmail ?? $currentSettings['company_email']); ?>" 
                                   required class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="company_phone">Company Phone</label>
                            <input type="tel" id="company_phone" name="company_phone" 
                                   value="<?php echo htmlspecialchars($companyPhone ?? $currentSettings['company_phone']); ?>" 
                                   class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="company_address">Company Address</label>
                        <textarea id="company_address" name="company_address" 
                                  class="form-control" rows="3"><?php echo htmlspecialchars($companyAddress ?? $currentSettings['company_address']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="site_url" class="required">Site URL</label>
                        <input type="url" id="site_url" name="site_url" 
                               value="<?php echo htmlspecialchars($siteUrl ?? $currentSettings['site_url']); ?>" 
                               required class="form-control">
                        <small class="form-text">The full URL of your website (e.g., https://yourdomain.com)</small>
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
        
        <!-- Regional Settings -->
        <div class="card">
            <div class="card-header">
                <h3>Regional Settings</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="form-horizontal">
                    <?php csrfField(); ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="timezone" class="required">Timezone</label>
                            <select id="timezone" name="timezone" required class="form-control">
                                <option value="UTC" <?php echo ($timezone ?? $currentSettings['timezone']) == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                <option value="America/New_York" <?php echo ($timezone ?? $currentSettings['timezone']) == 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                                <option value="America/Chicago" <?php echo ($timezone ?? $currentSettings['timezone']) == 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                                <option value="America/Denver" <?php echo ($timezone ?? $currentSettings['timezone']) == 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                                <option value="America/Los_Angeles" <?php echo ($timezone ?? $currentSettings['timezone']) == 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                                <option value="Europe/London" <?php echo ($timezone ?? $currentSettings['timezone']) == 'Europe/London' ? 'selected' : ''; ?>>London</option>
                                <option value="Europe/Paris" <?php echo ($timezone ?? $currentSettings['timezone']) == 'Europe/Paris' ? 'selected' : ''; ?>>Paris</option>
                                <option value="Asia/Tokyo" <?php echo ($timezone ?? $currentSettings['timezone']) == 'Asia/Tokyo' ? 'selected' : ''; ?>>Tokyo</option>
                                <option value="Asia/Shanghai" <?php echo ($timezone ?? $currentSettings['timezone']) == 'Asia/Shanghai' ? 'selected' : ''; ?>>Shanghai</option>
                                <option value="Africa/Lagos" <?php echo ($timezone ?? $currentSettings['timezone']) == 'Africa/Lagos' ? 'selected' : ''; ?>>Lagos</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="currency" class="required">Currency</label>
                            <select id="currency" name="currency" required class="form-control">
                                <option value="USD" <?php echo ($currency ?? $currentSettings['currency']) == 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                <option value="EUR" <?php echo ($currency ?? $currentSettings['currency']) == 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                <option value="GBP" <?php echo ($currency ?? $currentSettings['currency']) == 'GBP' ? 'selected' : ''; ?>>GBP - British Pound</option>
                                <option value="NGN" <?php echo ($currency ?? $currentSettings['currency']) == 'NGN' ? 'selected' : ''; ?>>NGN - Nigerian Naira</option>
                                <option value="CAD" <?php echo ($currency ?? $currentSettings['currency']) == 'CAD' ? 'selected' : ''; ?>>CAD - Canadian Dollar</option>
                                <option value="AUD" <?php echo ($currency ?? $currentSettings['currency']) == 'AUD' ? 'selected' : ''; ?>>AUD - Australian Dollar</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="currency_symbol">Currency Symbol</label>
                            <input type="text" id="currency_symbol" name="currency_symbol" 
                                   value="<?php echo htmlspecialchars($currencySymbol ?? $currentSettings['currency_symbol']); ?>" 
                                   class="form-control" maxlength="5">
                        </div>
                        <div class="form-group">
                            <label for="fiscal_year_start">Fiscal Year Start Month</label>
                            <select id="fiscal_year_start" name="fiscal_year_start" class="form-control">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($fiscalYearStart ?? $currentSettings['fiscal_year_start']) == $i ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_format">Date Format</label>
                            <select id="date_format" name="date_format" class="form-control">
                                <option value="Y-m-d" <?php echo ($dateFormat ?? $currentSettings['date_format']) == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                <option value="d/m/Y" <?php echo ($dateFormat ?? $currentSettings['date_format']) == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                <option value="m/d/Y" <?php echo ($dateFormat ?? $currentSettings['date_format']) == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                <option value="d-m-Y" <?php echo ($dateFormat ?? $currentSettings['date_format']) == 'd-m-Y' ? 'selected' : ''; ?>>DD-MM-YYYY</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="time_format">Time Format</label>
                            <select id="time_format" name="time_format" class="form-control">
                                <option value="H:i:s" <?php echo ($timeFormat ?? $currentSettings['time_format']) == 'H:i:s' ? 'selected' : ''; ?>>24 Hour (HH:MM:SS)</option>
                                <option value="h:i A" <?php echo ($timeFormat ?? $currentSettings['time_format']) == 'h:i A' ? 'selected' : ''; ?>>12 Hour (HH:MM AM/PM)</option>
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
    
    <!-- Company Logo -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>Company Logo</h3>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?php csrfField(); ?>
                    
                    <div class="logo-upload">
                        <?php if (!empty($currentSettings['company_logo'])): ?>
                        <div class="current-logo">
                            <img src="../../uploads/logos/<?php echo htmlspecialchars($currentSettings['company_logo']); ?>" 
                                 alt="Current Logo" class="logo-preview">
                            <p class="logo-info">Current logo</p>
                        </div>
                        <?php else: ?>
                        <div class="no-logo">
                            <i class="icon-image"></i>
                            <p>No logo uploaded</p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="upload-area">
                            <input type="file" id="company_logo" name="company_logo" 
                                   accept="image/jpeg,image/png,image/gif" class="form-control">
                            <small class="form-text">JPG, PNG, or GIF. Maximum 5MB.</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="icon-upload"></i> Upload Logo
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Settings Preview -->
        <div class="card">
            <div class="card-header">
                <h3>Settings Preview</h3>
            </div>
            <div class="card-body">
                <div class="preview-item">
                    <strong>Date:</strong> <?php echo date($currentSettings['date_format']); ?>
                </div>
                <div class="preview-item">
                    <strong>Time:</strong> <?php echo date($currentSettings['time_format']); ?>
                </div>
                <div class="preview-item">
                    <strong>Currency:</strong> <?php echo $currentSettings['currency_symbol']; ?>100.00
                </div>
                <div class="preview-item">
                    <strong>Timezone:</strong> <?php echo $currentSettings['timezone']; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-update currency symbol based on currency selection
document.getElementById('currency').addEventListener('change', function() {
    const currencySymbols = {
        'USD': '$',
        'EUR': '€',
        'GBP': '£',
        'NGN': '₦',
        'CAD': 'C$',
        'AUD': 'A$'
    };
    
    const symbol = currencySymbols[this.value] || '$';
    document.getElementById('currency_symbol').value = symbol;
});
</script>

<style>
.logo-upload {
    text-align: center;
}

.current-logo {
    margin-bottom: 20px;
}

.logo-preview {
    max-width: 200px;
    max-height: 100px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 10px;
}

.logo-info {
    margin-top: 10px;
    color: #6c757d;
    font-size: 12px;
}

.no-logo {
    margin-bottom: 20px;
    padding: 40px 20px;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 5px;
}

.no-logo i {
    font-size: 48px;
    color: #6c757d;
    margin-bottom: 10px;
}

.upload-area {
    margin-bottom: 15px;
}

.preview-item {
    padding: 8px 0;
    border-bottom: 1px solid #f1f3f4;
}

.preview-item:last-child {
    border-bottom: none;
}

.preview-item strong {
    color: #333;
    margin-right: 10px;
}
</style>

<?php include '../includes/footer.php'; ?>
