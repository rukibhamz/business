<?php
/**
 * Business Management System - Installation Wizard
 * Phase 1: Core Foundation
 */

// Define installation constant
define('BMS_INSTALL', true);

// Start session
session_start();

// Include helper functions
require_once 'install-functions.php';

// Check if already installed
if (isInstalled()) {
    header('Location: ../admin/');
    exit;
}

// Get current step
$step = getCurrentStep();

// Validate step
if (!isValidStep($step)) {
    $step = 1;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'test_database':
            handleDatabaseTest();
            break;
        case 'install_database':
            handleDatabaseInstall();
            break;
        case 'save_site_config':
            handleSiteConfig();
            break;
        case 'create_admin':
            handleAdminCreation();
            break;
        case 'complete_installation':
            handleInstallationComplete();
            break;
    }
}

/**
 * Handle database connection test
 */
function handleDatabaseTest() {
    $host = sanitizeInput($_POST['db_host'] ?? '');
    $port = (int)($_POST['db_port'] ?? 3306);
    $username = sanitizeInput($_POST['db_username'] ?? '');
    $password = $_POST['db_password'] ?? '';
    $database = sanitizeInput($_POST['db_name'] ?? '');
    
    $result = testDatabaseConnection($host, $port, $username, $password, $database);
    
    $_SESSION['install_message'] = [
        'type' => $result['success'] ? 'success' : 'error',
        'message' => $result['message']
    ];
    
    if ($result['success']) {
        $_SESSION['db_config'] = [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'database' => $database,
            'prefix' => sanitizeInput($_POST['db_prefix'] ?? 'bms_')
        ];
    }
    
    logInstallationActivity('database_test', $result['message'], [
        'host' => $host,
        'port' => $port,
        'database' => $database
    ]);
    
    header('Location: ?step=2');
    exit;
}

/**
 * Handle database installation
 */
function handleDatabaseInstall() {
    if (!isset($_SESSION['db_config'])) {
        $_SESSION['install_message'] = [
            'type' => 'error',
            'message' => 'Database configuration not found. Please test connection first.'
        ];
        header('Location: ?step=2');
        exit;
    }
    
    $dbConfig = $_SESSION['db_config'];
    $result = testDatabaseConnection(
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['database']
    );
    
    if (!$result['success']) {
        $_SESSION['install_message'] = [
            'type' => 'error',
            'message' => $result['message']
        ];
        header('Location: ?step=2');
        exit;
    }
    
    $installResult = installDatabaseSchema($result['pdo'], $dbConfig['prefix']);
    
    $_SESSION['install_message'] = [
        'type' => $installResult['success'] ? 'success' : 'error',
        'message' => $installResult['message']
    ];
    
    if ($installResult['success']) {
        $_SESSION['db_installed'] = true;
        logInstallationActivity('database_install', $installResult['message']);
        header('Location: ?step=3');
    } else {
        logInstallationActivity('database_install_failed', $installResult['message']);
        header('Location: ?step=2');
    }
    exit;
}

/**
 * Handle site configuration
 */
function handleSiteConfig() {
    $siteConfig = [
        'company_name' => sanitizeInput($_POST['company_name'] ?? ''),
        'site_url' => sanitizeInput($_POST['site_url'] ?? ''),
        'admin_email' => sanitizeInput($_POST['admin_email'] ?? ''),
        'currency' => sanitizeInput($_POST['currency'] ?? 'USD'),
        'timezone' => sanitizeInput($_POST['timezone'] ?? 'UTC'),
        'date_format' => sanitizeInput($_POST['date_format'] ?? 'Y-m-d')
    ];
    
    // Validate required fields
    $errors = [];
    if (empty($siteConfig['company_name'])) {
        $errors[] = 'Company name is required';
    }
    if (empty($siteConfig['site_url'])) {
        $errors[] = 'Site URL is required';
    }
    if (empty($siteConfig['admin_email']) || !isValidEmail($siteConfig['admin_email'])) {
        $errors[] = 'Valid admin email is required';
    }
    
    if (!empty($errors)) {
        $_SESSION['install_message'] = [
            'type' => 'error',
            'message' => implode('<br>', $errors)
        ];
        header('Location: ?step=3');
        exit;
    }
    
    $_SESSION['site_config'] = $siteConfig;
    logInstallationActivity('site_config', 'Site configuration saved', $siteConfig);
    header('Location: ?step=4');
    exit;
}

/**
 * Handle admin account creation
 */
function handleAdminCreation() {
    if (!isset($_SESSION['db_config']) || !isset($_SESSION['site_config'])) {
        $_SESSION['install_message'] = [
            'type' => 'error',
            'message' => 'Previous steps not completed. Please start over.'
        ];
        header('Location: ?step=1');
        exit;
    }
    
    $adminData = [
        'first_name' => sanitizeInput($_POST['first_name'] ?? ''),
        'last_name' => sanitizeInput($_POST['last_name'] ?? ''),
        'email' => sanitizeInput($_POST['email'] ?? ''),
        'username' => sanitizeInput($_POST['username'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? ''
    ];
    
    // Validate admin data
    $errors = [];
    if (empty($adminData['first_name'])) {
        $errors[] = 'First name is required';
    }
    if (empty($adminData['last_name'])) {
        $errors[] = 'Last name is required';
    }
    if (empty($adminData['email']) || !isValidEmail($adminData['email'])) {
        $errors[] = 'Valid email address is required';
    }
    $username = $adminData['username'];
    if (!is_string($username) || strlen($username) < 4) {
        $errors[] = 'Username must be at least 4 characters';
    }
    if (empty($adminData['password'])) {
        $errors[] = 'Password is required';
    }
    if ($adminData['password'] !== $adminData['confirm_password']) {
        $errors[] = 'Passwords do not match';
    }
    
    // Check password strength
    $passwordStrength = validatePasswordStrength($adminData['password']);
    if (!$passwordStrength['is_valid']) {
        $errors[] = 'Password is too weak: ' . implode(', ', $passwordStrength['messages']);
    }
    
    if (!empty($errors)) {
        $_SESSION['install_message'] = [
            'type' => 'error',
            'message' => implode('<br>', $errors)
        ];
        header('Location: ?step=4');
        exit;
    }
    
    $_SESSION['admin_data'] = $adminData;
    logInstallationActivity('admin_creation', 'Admin account data prepared', [
        'email' => $adminData['email'],
        'username' => $adminData['username']
    ]);
    header('Location: ?step=5');
    exit;
}

/**
 * Handle installation completion
 */
function handleInstallationComplete() {
    if (!isset($_SESSION['db_config']) || !isset($_SESSION['site_config']) || !isset($_SESSION['admin_data'])) {
        $_SESSION['install_message'] = [
            'type' => 'error',
            'message' => 'Previous steps not completed. Please start over.'
        ];
        header('Location: ?step=1');
        exit;
    }
    
    try {
        // Test database connection
        $dbConfig = $_SESSION['db_config'];
        $result = testDatabaseConnection(
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['database']
        );
        
        if (!$result['success']) {
            throw new Exception('Database connection failed: ' . $result['message']);
        }
        
        $pdo = $result['pdo'];
        
        // Create admin user
        $adminResult = createAdminUser($pdo, $_SESSION['admin_data'], $dbConfig['prefix']);
        if (!$adminResult['success']) {
            throw new Exception($adminResult['message']);
        }
        
        // Update site settings
        $siteSettings = [
            'site_name' => $_SESSION['site_config']['company_name'],
            'site_url' => $_SESSION['site_config']['site_url'],
            'admin_email' => $_SESSION['site_config']['admin_email'],
            'default_currency' => $_SESSION['site_config']['currency'],
            'timezone' => $_SESSION['site_config']['timezone'],
            'date_format' => $_SESSION['site_config']['date_format']
        ];
        
        $settingsResult = updateSiteSettings($pdo, $siteSettings, $dbConfig['prefix']);
        if (!$settingsResult['success']) {
            throw new Exception($settingsResult['message']);
        }
        
        // Create configuration file
        $configData = array_merge($dbConfig, $_SESSION['site_config']);
        if (!createConfigFile($configData)) {
            throw new Exception('Failed to create configuration file');
        }
        
        // Create installed lock file
        if (!createInstalledLock()) {
            throw new Exception('Failed to create installation lock file');
        }
        
        // Clear installation session data
        unset($_SESSION['db_config']);
        unset($_SESSION['site_config']);
        unset($_SESSION['admin_data']);
        unset($_SESSION['install_message']);
        
        logInstallationActivity('installation_complete', 'Installation completed successfully');
        
        // Redirect to admin panel
        header('Location: ../admin/');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['install_message'] = [
            'type' => 'error',
            'message' => 'Installation failed: ' . $e->getMessage()
        ];
        logInstallationActivity('installation_failed', $e->getMessage());
        header('Location: ?step=5');
        exit;
    }
}

// Get step title and description
$stepTitle = getStepTitle($step);
$stepDescription = getStepDescription($step);
$totalSteps = getTotalSteps();

// Get installation message if any
$installMessage = $_SESSION['install_message'] ?? null;
if ($installMessage) {
    unset($_SESSION['install_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Management System - Installation</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/x-icon" href="../public/images/logo.png">
</head>
<body>
    <div class="install-container">
        <!-- Header -->
        <div class="install-header">
            <div class="logo">
                <h1>Business Management System</h1>
                <p>Installation Wizard</p>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo ($step / $totalSteps) * 100; ?>%"></div>
            </div>
            <div class="progress-text">
                Step <?php echo $step; ?> of <?php echo $totalSteps; ?>: <?php echo $stepTitle; ?>
            </div>
        </div>

        <!-- Installation Message -->
        <?php if ($installMessage): ?>
        <div class="message message-<?php echo $installMessage['type']; ?>">
            <?php echo $installMessage['message']; ?>
        </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="install-content">
            <div class="step-header">
                <h2><?php echo $stepTitle; ?></h2>
                <p><?php echo $stepDescription; ?></p>
            </div>

            <div class="step-content">
                <?php
                // Include step file
                $stepFile = "steps/step-{$step}.php";
                if (file_exists($stepFile)) {
                    include $stepFile;
                } else {
                    echo '<div class="error">Step file not found: ' . $stepFile . '</div>';
                }
                ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="install-footer">
            <p>&copy; <?php echo date('Y'); ?> Business Management System. All rights reserved.</p>
            <p>Version 1.0.0 | Phase 1: Core Foundation</p>
        </div>
    </div>

    <script src="assets/script.js"></script>
</body>
</html>
