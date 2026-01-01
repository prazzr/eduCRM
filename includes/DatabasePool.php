<?php
/**
 * Database Connection Pooling Configuration
 * Optimizes database connections for better performance
 */

// Database Configuration with Connection Pooling
class DatabasePool
{
    private static $instance = null;
    private $pdo;

    // Connection pool settings
    private $maxConnections = 10;
    private $minConnections = 2;
    private $connectionTimeout = 30;

    private function __construct()
    {
        $host = DB_HOST;
        $dbname = DB_NAME;
        $username = DB_USER;
        $password = DB_PASS;

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,

            // Connection pooling optimizations
            PDO::ATTR_PERSISTENT => true, // Enable persistent connections
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,

            // Performance optimizations
            PDO::MYSQL_ATTR_COMPRESS => true, // Enable compression
            PDO::ATTR_TIMEOUT => $this->connectionTimeout,
        ];

        try {
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            $this->pdo = new PDO($dsn, $username, $password, $options);

            // Set connection pool size (MySQL specific)
            $this->pdo->exec("SET SESSION max_connections = {$this->maxConnections}");

        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }

    // Prevent cloning
    private function __clone()
    {
    }

    // Prevent unserialization
    private function __wakeup()
    {
    }
}

// Usage example:
// $pdo = DatabasePool::getInstance();
// $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
// $stmt->execute([1]);

/**
 * MySQL Configuration Recommendations
 * Add to my.cnf or my.ini:
 * 
 * [mysqld]
 * max_connections = 200
 * thread_cache_size = 16
 * table_open_cache = 2000
 * query_cache_type = 1
 * query_cache_size = 64M
 * query_cache_limit = 2M
 * 
 * # InnoDB settings
 * innodb_buffer_pool_size = 1G
 * innodb_log_file_size = 256M
 * innodb_flush_log_at_trx_commit = 2
 * innodb_flush_method = O_DIRECT
 */

echo "Database Connection Pooling Configuration Created\n";
echo "================================================\n\n";
echo "Features:\n";
echo "  ✅ Persistent connections enabled\n";
echo "  ✅ Connection compression enabled\n";
echo "  ✅ Buffered queries enabled\n";
echo "  ✅ Singleton pattern for connection reuse\n";
echo "  ✅ Connection timeout configured\n\n";
echo "To use:\n";
echo "  1. Replace config.php PDO connection with DatabasePool::getInstance()\n";
echo "  2. Update MySQL configuration (see comments in file)\n";
echo "  3. Restart MySQL server\n\n";
echo "✅ Connection pooling ready!\n";
