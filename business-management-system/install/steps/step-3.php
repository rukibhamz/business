<?php
/**
 * Step 3: Site Configuration
 */

// Get site configuration from session if available
$siteConfig = $_SESSION['site_config'] ?? [
    'company_name' => '',
    'site_url' => '',
    'admin_email' => '',
    'currency' => 'USD',
    'timezone' => 'UTC',
    'date_format' => 'Y-m-d'
];

// Auto-detect site URL if not set
if (empty($siteConfig['site_url'])) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname($_SERVER['PHP_SELF'] ?? '');
    $path = str_replace('/install', '', $path);
    $siteConfig['site_url'] = $protocol . '://' . $host . $path;
}

// Get available options
$currencies = getCurrencies();
$timezones = getTimezones();
$dateFormats = getDateFormats();
?>

<div class="site-config">
    <div class="config-header">
        <h3><i class="icon-settings"></i> Site Configuration</h3>
        <p>Configure your business information and system settings.</p>
    </div>

    <form id="site-config-form" method="POST" action="">
        <input type="hidden" name="action" value="save_site_config">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="form-section">
            <h4><i class="icon-building"></i> Business Information</h4>
            
            <div class="form-group">
                <label for="company_name">Company/Business Name *</label>
                <input type="text" 
                       id="company_name" 
                       name="company_name" 
                       value="<?php echo htmlspecialchars($siteConfig['company_name']); ?>" 
                       placeholder="Your Company Name" 
                       required>
                <small>This will be displayed throughout the system</small>
            </div>

            <div class="form-group">
                <label for="site_url">Site URL *</label>
                <input type="url" 
                       id="site_url" 
                       name="site_url" 
                       value="<?php echo htmlspecialchars($siteConfig['site_url']); ?>" 
                       placeholder="https://yourdomain.com" 
                       required>
                <small>Full URL where your system will be accessible</small>
            </div>

            <div class="form-group">
                <label for="admin_email">Admin Email Address *</label>
                <input type="email" 
                       id="admin_email" 
                       name="admin_email" 
                       value="<?php echo htmlspecialchars($siteConfig['admin_email']); ?>" 
                       placeholder="admin@yourcompany.com" 
                       required>
                <small>Primary email address for system notifications</small>
            </div>
        </div>

        <div class="form-section">
            <h4><i class="icon-globe"></i> Regional Settings</h4>
            
            <div class="form-group">
                <label for="currency">Default Currency *</label>
                <select id="currency" name="currency" required>
                    <option value="">Select Currency</option>
                    <?php foreach ($currencies as $code => $name): ?>
                    <option value="<?php echo $code; ?>" 
                            <?php echo $siteConfig['currency'] === $code ? 'selected' : ''; ?>>
                        <?php echo $code; ?> - <?php echo $name; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small>Primary currency for all financial operations</small>
            </div>

            <div class="form-group">
                <label for="timezone">Timezone *</label>
                <select id="timezone" name="timezone" required>
                    <option value="">Select Timezone</option>
                    <?php foreach ($timezones as $region => $cities): ?>
                    <optgroup label="<?php echo $region; ?>">
                        <?php foreach ($cities as $city): ?>
                        <option value="<?php echo $region . '/' . $city; ?>" 
                                <?php echo $siteConfig['timezone'] === ($region . '/' . $city) ? 'selected' : ''; ?>>
                            <?php echo $city; ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
                <small>Timezone for all date and time operations</small>
            </div>

            <div class="form-group">
                <label for="date_format">Date Format *</label>
                <select id="date_format" name="date_format" required>
                    <option value="">Select Date Format</option>
                    <?php foreach ($dateFormats as $format => $example): ?>
                    <option value="<?php echo $format; ?>" 
                            <?php echo $siteConfig['date_format'] === $format ? 'selected' : ''; ?>>
                        <?php echo $example; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small>How dates will be displayed throughout the system</small>
            </div>
        </div>

        <div class="form-section">
            <h4><i class="icon-info"></i> Configuration Preview</h4>
            <div class="preview-box">
                <div class="preview-item">
                    <strong>Company Name:</strong>
                    <span id="preview-company"><?php echo htmlspecialchars($siteConfig['company_name']); ?></span>
                </div>
                <div class="preview-item">
                    <strong>Site URL:</strong>
                    <span id="preview-url"><?php echo htmlspecialchars($siteConfig['site_url']); ?></span>
                </div>
                <div class="preview-item">
                    <strong>Admin Email:</strong>
                    <span id="preview-email"><?php echo htmlspecialchars($siteConfig['admin_email']); ?></span>
                </div>
                <div class="preview-item">
                    <strong>Currency:</strong>
                    <span id="preview-currency"><?php echo $siteConfig['currency']; ?></span>
                </div>
                <div class="preview-item">
                    <strong>Timezone:</strong>
                    <span id="preview-timezone"><?php echo $siteConfig['timezone']; ?></span>
                </div>
                <div class="preview-item">
                    <strong>Date Format:</strong>
                    <span id="preview-date"><?php echo $siteConfig['date_format']; ?></span>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="?step=2" class="btn btn-secondary">
                <i class="icon-arrow-left"></i> Back to Database Setup
            </a>
            
            <button type="submit" class="btn btn-primary">
                <i class="icon-arrow-right"></i> Continue to Admin Account
            </button>
        </div>
    </form>
</div>

<div class="config-help">
    <h4>Configuration Help</h4>
    <div class="help-content">
        <div class="help-item">
            <strong>Company Name:</strong>
            <p>This will be used in emails, reports, and throughout the system interface. You can change this later in settings.</p>
        </div>
        <div class="help-item">
            <strong>Site URL:</strong>
            <p>Make sure this matches your actual domain. This is used for generating links in emails and notifications.</p>
        </div>
        <div class="help-item">
            <strong>Currency:</strong>
            <p>Choose your primary business currency. This can be changed later, but it will affect all existing financial data.</p>
        </div>
        <div class="help-item">
            <strong>Timezone:</strong>
            <p>Select the timezone where your business operates. All times will be displayed in this timezone.</p>
        </div>
        <div class="help-item">
            <strong>Date Format:</strong>
            <p>Choose how dates should be displayed. This affects all date displays in the system.</p>
        </div>
    </div>
</div>

<script>
// Update preview as user types
document.addEventListener('DOMContentLoaded', function() {
    const inputs = ['company_name', 'site_url', 'admin_email', 'currency', 'timezone', 'date_format'];
    
    inputs.forEach(function(inputId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById('preview-' + inputId.replace('_', '-'));
        
        if (input && preview) {
            input.addEventListener('input', function() {
                preview.textContent = this.value || 'Not set';
            });
        }
    });
});

// Form validation
document.getElementById('site-config-form').addEventListener('submit', function(e) {
    const requiredFields = ['company_name', 'site_url', 'admin_email', 'currency', 'timezone', 'date_format'];
    let isValid = true;
    
    requiredFields.forEach(function(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fill in all required fields.');
    }
});
</script>
