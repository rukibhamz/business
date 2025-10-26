<?php
// Step 3: Site Configuration
$siteConfig = $_SESSION['site_config'] ?? [];
$timezones = getTimezoneList();
$currencies = getCurrencyList();
?>

<form method="POST" class="install-form">
    <input type="hidden" name="action" value="save_site_config">
    
    <div class="form-group">
        <label for="company_name">Company Name *</label>
        <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($siteConfig['company_name'] ?? ''); ?>" required>
        <small>Your business or organization name</small>
    </div>

    <div class="form-group">
        <label for="site_url">Site URL *</label>
        <input type="url" id="site_url" name="site_url" value="<?php echo htmlspecialchars($siteConfig['site_url'] ?? ''); ?>" required>
        <small>Full URL to your website (e.g., https://yourdomain.com)</small>
    </div>

    <div class="form-group">
        <label for="admin_email">Admin Email *</label>
        <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($siteConfig['admin_email'] ?? ''); ?>" required>
        <small>Primary email address for system notifications</small>
    </div>

    <div class="form-group">
        <label for="currency">Default Currency</label>
        <select id="currency" name="currency">
            <?php foreach ($currencies as $code => $name): ?>
            <option value="<?php echo $code; ?>" <?php echo ($siteConfig['currency'] ?? 'NGN') === $code ? 'selected' : ''; ?>>
                <?php echo $name; ?>
            </option>
            <?php endforeach; ?>
        </select>
        <small>Default currency for financial transactions</small>
    </div>

    <div class="form-group">
        <label for="timezone">Timezone</label>
        <select id="timezone" name="timezone">
            <?php foreach ($timezones as $tz => $name): ?>
            <option value="<?php echo $tz; ?>" <?php echo ($siteConfig['timezone'] ?? 'Africa/Lagos') === $tz ? 'selected' : ''; ?>>
                <?php echo $name; ?>
            </option>
            <?php endforeach; ?>
        </select>
        <small>System timezone for date and time display</small>
    </div>

    <div class="form-group">
        <label for="date_format">Date Format</label>
        <select id="date_format" name="date_format">
            <option value="Y-m-d" <?php echo ($siteConfig['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : ''; ?>>2024-01-15 (Y-m-d)</option>
            <option value="d-m-Y" <?php echo ($siteConfig['date_format'] ?? '') === 'd-m-Y' ? 'selected' : ''; ?>>15-01-2024 (d-m-Y)</option>
            <option value="m/d/Y" <?php echo ($siteConfig['date_format'] ?? '') === 'm/d/Y' ? 'selected' : ''; ?>>01/15/2024 (m/d/Y)</option>
            <option value="d/m/Y" <?php echo ($siteConfig['date_format'] ?? '') === 'd/m/Y' ? 'selected' : ''; ?>>15/01/2024 (d/m/Y)</option>
        </select>
        <small>How dates should be displayed throughout the system</small>
    </div>

    <div class="form-actions">
        <a href="?step=2" class="btn btn-secondary">Previous</a>
        <button type="submit" class="btn btn-primary">Save Configuration</button>
    </div>
</form>