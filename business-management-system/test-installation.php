<?php
/**
 * Business Management System - Installation Test Script
 * Phase 1: Core Foundation
 * 
 * This script tests the basic functionality of the BMS system
 * Run this after installation to verify everything is working
 */

// Define system constant
define('BMS_SYSTEM', true);

echo "<h1>Business Management System - Installation Test</h1>";
echo "<p>Testing system components...</p>";

$tests = [];
$passed = 0;
$total = 0;

// Test 1: Check if config file exists
$total++;
if (file_exists('config/config.php')) {
    $tests[] = "✅ Config file exists";
    $passed++;
} else {
    $tests[] = "❌ Config file missing - Run installation first";
}

// Test 2: Check if database connection works
$total++;
try {
    require_once 'config/config.php';
    require_once 'config/database.php';
    
    $db = getDB();
    if ($db->testConnection()) {
        $tests[] = "✅ Database connection successful";
        $passed++;
    } else {
        $tests[] = "❌ Database connection failed";
    }
} catch (Exception $e) {
    $tests[] = "❌ Database error: " . $e->getMessage();
}

// Test 3: Check if required tables exist
$total++;
try {
    if (isset($db)) {
        $tables = ['users', 'roles', 'settings', 'activity_logs'];
        $allTablesExist = true;
        
        foreach ($tables as $table) {
            $exists = $db->exists('information_schema.tables', 
                'table_schema = ? AND table_name = ?', 
                [DB_NAME, DB_PREFIX . $table]
            );
            if (!$exists) {
                $allTablesExist = false;
                break;
            }
        }
        
        if ($allTablesExist) {
            $tests[] = "✅ All required tables exist";
            $passed++;
        } else {
            $tests[] = "❌ Some required tables are missing";
        }
    } else {
        $tests[] = "❌ Cannot test tables - database not connected";
    }
} catch (Exception $e) {
    $tests[] = "❌ Table check error: " . $e->getMessage();
}

// Test 4: Check if admin user exists
$total++;
try {
    if (isset($db)) {
        $adminCount = $db->count('users', 'role_id = 1');
        if ($adminCount > 0) {
            $tests[] = "✅ Admin user exists";
            $passed++;
        } else {
            $tests[] = "❌ No admin user found";
        }
    } else {
        $tests[] = "❌ Cannot check admin user - database not connected";
    }
} catch (Exception $e) {
    $tests[] = "❌ Admin user check error: " . $e->getMessage();
}

// Test 5: Check directory permissions
$total++;
$directories = ['config', 'uploads', 'cache', 'logs'];
$allWritable = true;

foreach ($directories as $dir) {
    if (!is_writable($dir)) {
        $allWritable = false;
        break;
    }
}

if ($allWritable) {
    $tests[] = "✅ All directories are writable";
    $passed++;
} else {
    $tests[] = "❌ Some directories are not writable";
}

// Test 6: Check PHP extensions
$total++;
$requiredExtensions = ['mysqli', 'mbstring', 'curl', 'gd', 'json', 'openssl', 'zip'];
$allExtensionsLoaded = true;

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $allExtensionsLoaded = false;
        break;
    }
}

if ($allExtensionsLoaded) {
    $tests[] = "✅ All required PHP extensions are loaded";
    $passed++;
} else {
    $tests[] = "❌ Some required PHP extensions are missing";
}

// Test 7: Check if .htaccess is working
$total++;
if (file_exists('.htaccess')) {
    $tests[] = "✅ .htaccess file exists";
    $passed++;
} else {
    $tests[] = "❌ .htaccess file missing";
}

// Test 8: Check if install directory is blocked
$total++;
if (file_exists('installed.lock')) {
    $tests[] = "✅ Installation lock file exists";
    $passed++;
} else {
    $tests[] = "❌ Installation lock file missing";
}

// Display results
echo "<h2>Test Results</h2>";
echo "<p><strong>Passed:</strong> $passed / $total tests</p>";

if ($passed === $total) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>🎉 All tests passed! Your installation is working correctly.</h3>";
    echo "<p>You can now access your admin panel at: <a href='admin/'>Admin Panel</a></p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>⚠️ Some tests failed. Please check the issues below:</h3>";
    echo "</div>";
}

echo "<h3>Detailed Results:</h3>";
echo "<ul>";
foreach ($tests as $test) {
    echo "<li>$test</li>";
}
echo "</ul>";

// System information
echo "<h3>System Information</h3>";
echo "<ul>";
echo "<li><strong>PHP Version:</strong> " . PHP_VERSION . "</li>";
echo "<li><strong>Server:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</li>";
echo "<li><strong>Document Root:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</li>";
echo "<li><strong>Current Directory:</strong> " . getcwd() . "</li>";
echo "<li><strong>Memory Limit:</strong> " . ini_get('memory_limit') . "</li>";
echo "<li><strong>Max Execution Time:</strong> " . ini_get('max_execution_time') . " seconds</li>";
echo "</ul>";

// Clean up
if (isset($db)) {
    unset($db);
}

echo "<hr>";
echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>";
echo "<p><a href='admin/'>Go to Admin Panel</a> | <a href='README.md'>View Documentation</a></p>";
?>
