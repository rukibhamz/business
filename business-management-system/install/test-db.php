<?php
/**
 * Database Installation Test Script
 * Run this to test database installation manually
 */

require_once 'install-functions.php';

// Test database connection
$host = 'localhost';
$port = 3306;
$username = 'root';
$password = '';
$database = 'bms_test';

echo "Testing database connection...\n";
$result = testDatabaseConnection($host, $port, $username, $password, $database);

if ($result['success']) {
    echo "✓ Database connection successful!\n";
    
    echo "Installing database schema...\n";
    $installResult = installDatabaseSchema($result['pdo'], 'bms_');
    
    if ($installResult['success']) {
        echo "✓ Database schema installed!\n";
        
        echo "Verifying tables...\n";
        $verifyResult = verifyDatabaseTables($result['pdo'], 'bms_');
        
        if ($verifyResult['success']) {
            echo "✓ All tables verified successfully!\n";
            echo "Tables created: " . $verifyResult['tables_created'] . "\n";
            echo "Roles count: " . $verifyResult['roles_count'] . "\n";
        } else {
            echo "✗ Verification failed: " . $verifyResult['message'] . "\n";
        }
    } else {
        echo "✗ Installation failed: " . $installResult['message'] . "\n";
    }
} else {
    echo "✗ Database connection failed: " . $result['message'] . "\n";
}

