<?php
/**
 * Manual Migration Runner for Phase 4A & 4D
 * Fixes failed migrations
 */

require_once 'config.php';

echo "========================================\n";
echo "Phase 4 Migration Fix\n";
echo "========================================\n\n";

$migrations = [
    'Phase 4A: Analytics Tables' => 'phase4a_migration.sql',
    'Phase 4D: Security & Performance' => 'phase4d_migration.sql'
];

$totalSuccess = 0;
$totalErrors = 0;

foreach ($migrations as $name => $file) {
    echo "Running: $name\n";
    echo "File: $file\n";
    echo "----------------------------------------\n";

    if (!file_exists($file)) {
        echo "❌ Error: File not found: $file\n\n";
        continue;
    }

    $sql = file_get_contents($file);

    // Split by semicolon but preserve them in statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $success = 0;
    $errors = 0;

    foreach ($statements as $stmt) {
        // Skip empty statements, comments, and USE statements
        if (
            empty($stmt) ||
            stripos($stmt, 'USE ') === 0 ||
            stripos($stmt, 'SELECT ') === 0 ||
            stripos($stmt, '--') === 0 ||
            strlen(trim($stmt)) < 5
        ) {
            continue;
        }

        try {
            $pdo->exec($stmt);
            $success++;
            echo ".";
        } catch (PDOException $e) {
            // Ignore "already exists" errors
            if (
                strpos($e->getMessage(), 'Duplicate') !== false ||
                strpos($e->getMessage(), 'already exists') !== false
            ) {
                echo "s"; // Skip
                continue;
            }

            echo "\n❌ Error: " . $e->getMessage() . "\n";
            echo "Statement: " . substr($stmt, 0, 100) . "...\n";
            $errors++;
        }
    }

    echo "\n";
    echo "✅ Success: $success statements\n";
    if ($errors > 0) {
        echo "❌ Errors: $errors statements\n";
    }
    echo "\n";

    $totalSuccess += $success;
    $totalErrors += $errors;
}

echo "========================================\n";
echo "Migration Summary\n";
echo "========================================\n";
echo "Total Success: $totalSuccess statements\n";
echo "Total Errors: $totalErrors statements\n";

// Verify tables were created
echo "\n========================================\n";
echo "Verification\n";
echo "========================================\n";

$tables = [
    'analytics_metrics',
    'analytics_snapshots',
    'analytics_goals',
    'security_logs',
    'query_cache',
    'performance_logs'
];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Table exists: $table\n";
        } else {
            echo "❌ Table missing: $table\n";
        }
    } catch (PDOException $e) {
        echo "❌ Error checking $table: " . $e->getMessage() . "\n";
    }
}

// Check if 2FA fields were added to users table
echo "\nChecking users table updates...\n";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'two_factor_secret'");
    if ($stmt->rowCount() > 0) {
        echo "✅ 2FA fields added to users table\n";
    } else {
        echo "❌ 2FA fields missing from users table\n";
    }
} catch (PDOException $e) {
    echo "❌ Error checking users table: " . $e->getMessage() . "\n";
}

echo "\n========================================\n";
echo "Migration Fix Complete!\n";
echo "========================================\n";

if ($totalErrors > 0) {
    echo "\n⚠️  Some errors occurred. Please review above.\n";
    exit(1);
} else {
    echo "\n✅ All migrations successful!\n";
    exit(0);
}
