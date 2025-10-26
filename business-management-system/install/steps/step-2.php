<?php
// Step 2: Database Configuration
$dbConfig = $_SESSION['db_config'] ?? [];
?>

<form method="POST" class="install-form">
    <input type="hidden" name="action" value="test_database">
    
    <div class="form-group">
        <label for="db_host">Database Host</label>
        <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($dbConfig['host'] ?? 'localhost'); ?>" required>
        <small>Usually 'localhost' for local installations</small>
    </div>

    <div class="form-group">
        <label for="db_port">Database Port</label>
        <input type="number" id="db_port" name="db_port" value="<?php echo htmlspecialchars($dbConfig['port'] ?? '3306'); ?>" required>
        <small>Default MySQL port is 3306</small>
    </div>

    <div class="form-group">
        <label for="db_name">Database Name</label>
        <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($dbConfig['database'] ?? ''); ?>" required>
        <small>Name of the database to use</small>
    </div>

    <div class="form-group">
        <label for="db_username">Database Username</label>
        <input type="text" id="db_username" name="db_username" value="<?php echo htmlspecialchars($dbConfig['username'] ?? ''); ?>" required>
        <small>Database username</small>
    </div>

    <div class="form-group">
        <label for="db_password">Database Password</label>
        <input type="password" id="db_password" name="db_password" value="<?php echo htmlspecialchars($dbConfig['password'] ?? ''); ?>">
        <small>Database password (leave empty if no password)</small>
    </div>

    <div class="form-group">
        <label for="db_prefix">Table Prefix</label>
        <input type="text" id="db_prefix" name="db_prefix" value="<?php echo htmlspecialchars($dbConfig['prefix'] ?? 'bms_'); ?>" required>
        <small>Prefix for all database tables (e.g., bms_)</small>
    </div>

    <div class="form-actions">
        <a href="?step=1" class="btn btn-secondary">Previous</a>
        <button type="submit" class="btn btn-primary">Test Connection</button>
    </div>
</form>

<?php if (isset($_SESSION['db_config'])): ?>
<form method="POST" class="install-form" style="margin-top: 20px;">
    <input type="hidden" name="action" value="install_database">
    
    <div class="form-group">
        <h3>Install Database Schema</h3>
        <p>Database connection successful! Click the button below to install the database schema.</p>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-success">Install Database</button>
    </div>
</form>
<?php endif; ?>