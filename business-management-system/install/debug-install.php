<?php
/**
 * Minimal Database Installation Test
 * This will help debug the database installation issue
 */

// Start session
session_start();

// Simple database test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_db'])) {
    $host = $_POST['db_host'] ?? 'localhost';
    $port = (int)($_POST['db_port'] ?? 3306);
    $username = $_POST['db_username'] ?? 'root';
    $password = $_POST['db_password'] ?? '';
    $database = $_POST['db_name'] ?? 'bms_test';
    
    $message = '';
    $success = false;
    
    try {
        // Test connection
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        $message .= "✓ Database connection successful!\n";
        
        // Read and execute SQL
        $sqlFile = __DIR__ . '/database.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("SQL file not found: $sqlFile");
        }
        
        $sql = file_get_contents($sqlFile);
        $message .= "✓ SQL file loaded (" . strlen($sql) . " bytes)\n";
        
        // Execute statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $executedCount = 0;
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^(--|\/\*)/', $statement)) {
                $pdo->exec($statement);
                $executedCount++;
            }
        }
        
        $message .= "✓ Executed $executedCount SQL statements\n";
        
        // Verify tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        $requiredTables = ['bms_users', 'bms_roles', 'bms_permissions', 'bms_role_permissions', 'bms_settings', 'bms_activity_logs'];
        $missingTables = array_diff($requiredTables, $tables);
        
        if (empty($missingTables)) {
            $message .= "✓ All required tables created!\n";
            $message .= "Tables: " . implode(', ', $tables) . "\n";
            $success = true;
        } else {
            $message .= "✗ Missing tables: " . implode(', ', $missingTables) . "\n";
            $message .= "Created tables: " . implode(', ', $tables) . "\n";
        }
        
    } catch (Exception $e) {
        $message = "✗ Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Installation Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { padding: 8px; width: 300px; }
        button { padding: 10px 20px; background: #007cba; color: white; border: none; cursor: pointer; }
        .message { margin: 20px 0; padding: 15px; background: #f0f0f0; white-space: pre-line; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <h1>Database Installation Test</h1>
    
    <?php if (isset($message)): ?>
    <div class="message <?php echo $success ? 'success' : 'error'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label>Database Host:</label>
            <input type="text" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Database Port:</label>
            <input type="number" name="db_port" value="<?php echo htmlspecialchars($_POST['db_port'] ?? '3306'); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Database Name:</label>
            <input type="text" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'bms_test'); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Username:</label>
            <input type="text" name="db_username" value="<?php echo htmlspecialchars($_POST['db_username'] ?? 'root'); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Password:</label>
            <input type="password" name="db_password" value="<?php echo htmlspecialchars($_POST['db_password'] ?? ''); ?>">
        </div>
        
        <button type="submit" name="test_db">Test Database Installation</button>
    </form>
    
    <p><strong>Instructions:</strong></p>
    <ul>
        <li>Make sure your database server is running</li>
        <li>Create a test database (e.g., 'bms_test')</li>
        <li>Enter your database credentials above</li>
        <li>Click "Test Database Installation" to run the test</li>
    </ul>
</body>
</html>
