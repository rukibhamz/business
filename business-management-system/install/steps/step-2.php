<?php
/**
 * Step 2: Database Configuration
 */

// Get database configuration from session if available
$dbConfig = $_SESSION['db_config'] ?? [
    'host' => 'localhost',
    'port' => 3306,
    'username' => '',
    'password' => '',
    'database' => '',
    'prefix' => 'bms_'
];

// Check if database is already installed
$dbInstalled = $_SESSION['db_installed'] ?? false;
?>

<div class="database-config">
    <div class="config-header">
        <h3><i class="icon-database"></i> Database Configuration</h3>
        <p>Enter your database connection details. Make sure you have created an empty database for this installation.</p>
    </div>

    <?php if ($dbInstalled): ?>
    <div class="success-message">
        <i class="icon-check"></i>
        <strong>Database installed successfully!</strong>
        <p>All tables have been created and default data has been inserted.</p>
    </div>
    <?php endif; ?>

    <form id="database-form" method="POST" action="">
        <input type="hidden" name="action" value="test_database">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="form-grid">
            <div class="form-group">
                <label for="db_host">Database Host *</label>
                <input type="text" 
                       id="db_host" 
                       name="db_host" 
                       value="<?php echo htmlspecialchars($dbConfig['host']); ?>" 
                       placeholder="localhost" 
                       required>
                <small>Usually 'localhost' for most hosting providers</small>
            </div>

            <div class="form-group">
                <label for="db_port">Database Port *</label>
                <input type="number" 
                       id="db_port" 
                       name="db_port" 
                       value="<?php echo $dbConfig['port']; ?>" 
                       placeholder="3306" 
                       min="1" 
                       max="65535" 
                       required>
                <small>Default MySQL port is 3306</small>
            </div>

            <div class="form-group">
                <label for="db_name">Database Name *</label>
                <input type="text" 
                       id="db_name" 
                       name="db_name" 
                       value="<?php echo htmlspecialchars($dbConfig['database']); ?>" 
                       placeholder="bms_database" 
                       required>
                <small>Create an empty database in your hosting control panel</small>
            </div>

            <div class="form-group">
                <label for="db_username">Database Username *</label>
                <input type="text" 
                       id="db_username" 
                       name="db_username" 
                       value="<?php echo htmlspecialchars($dbConfig['username']); ?>" 
                       placeholder="db_user" 
                       required>
                <small>Username with access to the database</small>
            </div>

            <div class="form-group">
                <label for="db_password">Database Password *</label>
                <div class="password-input">
                    <input type="password" 
                           id="db_password" 
                           name="db_password" 
                           value="<?php echo htmlspecialchars($dbConfig['password']); ?>" 
                           placeholder="Enter password" 
                           required>
                    <button type="button" class="toggle-password" onclick="togglePassword('db_password')">
                        <i class="icon-eye"></i>
                    </button>
                </div>
                <small>Password for the database user</small>
            </div>

            <div class="form-group">
                <label for="db_prefix">Table Prefix</label>
                <input type="text" 
                       id="db_prefix" 
                       name="db_prefix" 
                       value="<?php echo htmlspecialchars($dbConfig['prefix']); ?>" 
                       placeholder="bms_" 
                       pattern="[a-zA-Z0-9_]+$">
                <small>Prefix for all database tables (letters, numbers, and underscores only)</small>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="testConnection()">
                <i class="icon-test"></i> Test Connection
            </button>
            
            <?php if ($dbInstalled): ?>
            <a href="?step=3" class="btn btn-primary">
                <i class="icon-arrow-right"></i> Continue to Site Configuration
            </a>
            <?php else: ?>
            <button type="submit" class="btn btn-primary" id="install-db-btn" disabled>
                <i class="icon-database"></i> Install Database
            </button>
            <?php endif; ?>
        </div>
    </form>

    <div id="connection-result" class="connection-result" style="display: none;">
        <!-- Connection test results will be displayed here -->
    </div>
</div>

<div class="database-help">
    <h4>Database Setup Help</h4>
    <div class="help-content">
        <div class="help-item">
            <strong>Creating a Database:</strong>
            <ul>
                <li><strong>cPanel:</strong> Go to MySQL Databases, create a new database and user</li>
                <li><strong>Plesk:</strong> Go to Databases, create a new MySQL database</li>
                <li><strong>DirectAdmin:</strong> Go to MySQL Management, create database and user</li>
                <li><strong>Local (XAMPP/WAMP):</strong> Open phpMyAdmin, create a new database</li>
            </ul>
        </div>
        <div class="help-item">
            <strong>Common Issues:</strong>
            <ul>
                <li><strong>Access Denied:</strong> Check username and password</li>
                <li><strong>Database Not Found:</strong> Make sure the database exists</li>
                <li><strong>Connection Refused:</strong> Check host and port settings</li>
                <li><strong>Timeout:</strong> Check if MySQL service is running</li>
            </ul>
        </div>
        <div class="help-item">
            <strong>Security Notes:</strong>
            <ul>
                <li>Use a strong password for your database user</li>
                <li>Don't use 'root' as database username in production</li>
                <li>Consider using a unique table prefix for security</li>
                <li>Regularly backup your database</li>
            </ul>
        </div>
    </div>
</div>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'icon-eye-off';
    } else {
        field.type = 'password';
        icon.className = 'icon-eye';
    }
}

function testConnection() {
    const form = document.getElementById('database-form');
    const formData = new FormData(form);
    const resultDiv = document.getElementById('connection-result');
    const installBtn = document.getElementById('install-db-btn');
    
    // Show loading state
    resultDiv.innerHTML = '<div class="loading"><i class="icon-spinner"></i> Testing connection...</div>';
    resultDiv.style.display = 'block';
    installBtn.disabled = true;
    
    // Make AJAX request
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Check if response contains success message
        if (data.includes('Database connection successful')) {
            resultDiv.innerHTML = '<div class="success-message"><i class="icon-check"></i> Database connection successful! You can now install the database.</div>';
            installBtn.disabled = false;
        } else {
            resultDiv.innerHTML = '<div class="error-message"><i class="icon-warning"></i> Database connection failed. Please check your credentials.</div>';
            installBtn.disabled = true;
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="error-message"><i class="icon-warning"></i> Connection test failed: ' + error.message + '</div>';
        installBtn.disabled = true;
    });
}

// Auto-enable install button if database is already installed
<?php if ($dbInstalled): ?>
document.getElementById('install-db-btn').disabled = false;
<?php endif; ?>
</script>
