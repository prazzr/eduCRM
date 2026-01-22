<?php
require_once 'app/bootstrap.php';

$tables = ['users', 'inquiries', 'tasks', 'appointments', 'documents', 'classes', 'courses'];
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
        echo "[$table] Error: " . $e->getMessage() . "\n";
    }
}
