<?php
require_once 'app/bootstrap.php';

$tables = ['applications', 'visa_processes', 'partners', 'branches'];
$column = 'updated_at';

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array($column, $columns)) {
            echo "[$table] '$column' missing. Adding...\n";
            $pdo->exec("ALTER TABLE $table ADD COLUMN $column DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
            echo "[$table] Fixed.\n";
        } else {
            echo "[$table] OK.\n";
        }
    } catch (PDOException $e) {
        // Table might not exist or be named differently, just log it
        echo "[$table] Note: " . $e->getMessage() . "\n";
    }
}
