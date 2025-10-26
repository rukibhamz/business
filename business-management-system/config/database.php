<?php
/**
 * Business Management System - Database Connection Class
 * Phase 1: Core Foundation
 */

// Prevent direct access
if (!defined('BMS_SYSTEM')) {
    die('Direct access not allowed');
}

class Database {
    private static $instance = null;
    private $connection = null;
    private $host;
    private $port;
    private $database;
    private $username;
    private $password;
    private $prefix;
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->host = DB_HOST;
        $this->port = DB_PORT;
        $this->database = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->prefix = DB_PREFIX;
        
        $this->connect();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Establish database connection
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch (PDOException $e) {
            $this->logError('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed. Please check your configuration.');
        }
    }
    
    /**
     * Get PDO connection
     */
    public function getConnection() {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }
    
    /**
     * Execute a prepared statement
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logError('Query failed: ' . $e->getMessage() . ' | SQL: ' . $sql);
            throw new Exception('Database query failed.');
        }
    }
    
    /**
     * Fetch single row
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Insert data and return last insert ID
     */
    public function insert($table, $data) {
        $table = $this->prefix . $table;
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        
        return $this->getConnection()->lastInsertId();
    }
    
    /**
     * Update data
     */
    public function update($table, $data, $where, $whereParams = []) {
        $table = $this->prefix . $table;
        $setParts = [];
        
        foreach ($data as $key => $value) {
            $setParts[] = "{$key} = :{$key}";
        }
        
        $setClause = implode(', ', $setParts);
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        
        $params = array_merge($data, $whereParams);
        $this->query($sql, $params);
        
        return $this->getConnection()->rowCount();
    }
    
    /**
     * Delete data
     */
    public function delete($table, $where, $params = []) {
        $table = $this->prefix . $table;
        $sql = "DELETE FROM {$table} WHERE {$where}";
        
        $this->query($sql, $params);
        return $this->getConnection()->rowCount();
    }
    
    /**
     * Count records
     */
    public function count($table, $where = '', $params = []) {
        $table = $this->prefix . $table;
        $sql = "SELECT COUNT(*) as count FROM {$table}";
        
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }
        
        $result = $this->fetch($sql, $params);
        return (int)$result['count'];
    }
    
    /**
     * Check if record exists
     */
    public function exists($table, $where, $params = []) {
        return $this->count($table, $where, $params) > 0;
    }
    
    /**
     * Get table prefix
     */
    public function getPrefix() {
        return $this->prefix;
    }
    
    /**
     * Get table name with prefix
     */
    public function getTableName($table) {
        return $this->prefix . $table;
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->getConnection()->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->getConnection()->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->getConnection()->rollback();
    }
    
    /**
     * Execute transaction
     */
    public function transaction($callback) {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Get database info
     */
    public function getInfo() {
        try {
            $version = $this->fetch("SELECT VERSION() as version");
            $status = $this->fetch("SHOW STATUS LIKE 'Uptime'");
            
            return [
                'version' => $version['version'],
                'uptime' => $status['Value'] ?? 'Unknown',
                'host' => $this->host,
                'database' => $this->database,
                'charset' => 'utf8mb4'
            ];
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Test connection
     */
    public function testConnection() {
        try {
            $this->getConnection()->query("SELECT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get last error
     */
    public function getLastError() {
        $errorInfo = $this->getConnection()->errorInfo();
        return $errorInfo[2] ?? 'No error';
    }
    
    /**
     * Log error
     */
    private function logError($message) {
        $logFile = '../logs/database.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}\n";
        
        if (is_writable('../logs')) {
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Database helper functions
 */

/**
 * Get database instance
 */
function getDB() {
    return Database::getInstance();
}

/**
 * Quick query helper
 */
function dbQuery($sql, $params = []) {
    return getDB()->query($sql, $params);
}

/**
 * Quick fetch helper
 */
function dbFetch($sql, $params = []) {
    return getDB()->fetch($sql, $params);
}

/**
 * Quick fetch all helper
 */
function dbFetchAll($sql, $params = []) {
    return getDB()->fetchAll($sql, $params);
}

/**
 * Quick insert helper
 */
function dbInsert($table, $data) {
    return getDB()->insert($table, $data);
}

/**
 * Quick update helper
 */
function dbUpdate($table, $data, $where, $whereParams = []) {
    return getDB()->update($table, $data, $where, $whereParams);
}

/**
 * Quick delete helper
 */
function dbDelete($table, $where, $params = []) {
    return getDB()->delete($table, $where, $params);
}

/**
 * Quick count helper
 */
function dbCount($table, $where = '', $params = []) {
    return getDB()->count($table, $where, $params);
}

/**
 * Quick exists helper
 */
function dbExists($table, $where, $params = []) {
    return getDB()->exists($table, $where, $params);
}
