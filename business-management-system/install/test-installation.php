<?php
/**
 * Simple Database Installation Test
 * Run this to test if database tables can be created
 */

// Database connection settings - UPDATE THESE FOR YOUR SETUP
$host = 'localhost';
$port = 3306;
$username = 'root';
$password = '';
$database = 'bms_test';

echo "Testing database installation...\n";
echo "Host: $host\n";
echo "Database: $database\n\n";

try {
    // Connect to database
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    echo "✓ Database connection successful!\n\n";
    
    // Read SQL file
    $sqlFile = __DIR__ . '/database.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    echo "✓ SQL file loaded (" . strlen($sql) . " bytes)\n\n";
    
    // Split and execute SQL statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $executedCount = 0;
    
    echo "Executing SQL statements...\n";
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^(--|\/\*)/', $statement)) {
            try {
                $pdo->exec($statement);
                $executedCount++;
                echo "✓ Statement $executedCount executed\n";
            } catch (PDOException $e) {
                echo "✗ Statement failed: " . $e->getMessage() . "\n";
                echo "Statement: " . substr($statement, 0, 100) . "...\n";
                throw $e;
            }
        }
    }
    
    echo "\n✓ All statements executed successfully! ($executedCount statements)\n\n";
    
    // Verify tables were created
    echo "Verifying tables...\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $requiredTables = ['bms_users', 'bms_roles', 'bms_permissions', 'bms_role_permissions', 'bms_settings', 'bms_activity_logs'];
    $missingTables = array_diff($requiredTables, $tables);
    
    if (empty($missingTables)) {
        echo "✓ All required tables created!\n";
        echo "Tables: " . implode(', ', $tables) . "\n\n";
        
        // Check if roles have data
        $stmt = $pdo->query("SELECT COUNT(*) FROM bms_roles");
        $roleCount = $stmt->fetchColumn();
        echo "✓ Roles table has $roleCount records\n";
        
        echo "\n🎉 Database installation test PASSED!\n";
    } else {
        echo "✗ Missing tables: " . implode(', ', $missingTables) . "\n";
        echo "Created tables: " . implode(', ', $tables) . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "\nPlease check:\n";
    echo "1. Database server is running\n";
    echo "2. Database '$database' exists\n";
    echo "3. User '$username' has proper permissions\n";
    echo "4. SQL file exists and is readable\n";
}

