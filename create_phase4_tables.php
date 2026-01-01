<?php
/**
 * Create Phase 4 Tables
 * Run this to create all Phase 4 database tables
 */

require_once 'config.php';

echo "========================================\n";
echo "Creating Phase 4 Tables\n";
echo "========================================\n\n";

$tables = [
    'analytics_metrics' => "
        CREATE TABLE IF NOT EXISTS analytics_metrics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            metric_name VARCHAR(100) NOT NULL,
            metric_value DECIMAL(15,2) NOT NULL,
            metric_type ENUM('count', 'revenue', 'percentage', 'duration') DEFAULT 'count',
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_metric_name (metric_name),
            INDEX idx_period (period_start, period_end)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    'analytics_snapshots' => "
        CREATE TABLE IF NOT EXISTS analytics_snapshots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            snapshot_date DATE UNIQUE NOT NULL,
            total_inquiries INT DEFAULT 0,
            total_revenue DECIMAL(15,2) DEFAULT 0,
            metrics_json JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    'analytics_goals' => "
        CREATE TABLE IF NOT EXISTS analytics_goals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            goal_name VARCHAR(100) NOT NULL,
            goal_type ENUM('inquiries', 'conversions', 'revenue', 'applications') NOT NULL,
            target_value DECIMAL(15,2) NOT NULL,
            current_value DECIMAL(15,2) DEFAULT 0,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            status ENUM('active', 'achieved', 'missed', 'cancelled') DEFAULT 'active',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    'security_logs' => "
        CREATE TABLE IF NOT EXISTS security_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            metadata JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user_action (user_id, action),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    'query_cache' => "
        CREATE TABLE IF NOT EXISTS query_cache (
            cache_key VARCHAR(255) PRIMARY KEY,
            cache_value LONGTEXT NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    'performance_logs' => "
        CREATE TABLE IF NOT EXISTS performance_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_url VARCHAR(255),
            execution_time DECIMAL(10,4),
            memory_usage INT,
            query_count INT,
            user_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_page (page_url),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "
];

$success = 0;
$errors = 0;

foreach ($tables as $tableName => $sql) {
    echo "Creating table: $tableName... ";
    try {
        $pdo->exec($sql);
        echo "✅ Success\n";
        $success++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "⚠️  Already exists\n";
            $success++;
        } else {
            echo "❌ Error: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
}

// Add 2FA fields to users table
echo "\nAdding 2FA fields to users table... ";
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(32) DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_enabled BOOLEAN DEFAULT FALSE");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_ip VARCHAR(45) NULL");
    echo "✅ Success\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        echo "⚠️  Already exists\n";
    } else {
        echo "❌ Error: " . $e->getMessage() . "\n";
        $errors++;
    }
}

// Insert sample goals
echo "\nInserting sample goals... ";
try {
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO analytics_goals 
        (id, goal_name, goal_type, target_value, period_start, period_end, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([1, 'Monthly Inquiry Target', 'inquiries', 500, '2026-01-01', '2026-01-31', 1]);
    $stmt->execute([2, 'Q1 Revenue Target', 'revenue', 500000, '2026-01-01', '2026-03-31', 1]);
    echo "✅ Success\n";
} catch (PDOException $e) {
    echo "⚠️  " . $e->getMessage() . "\n";
}

echo "\n========================================\n";
echo "Summary\n";
echo "========================================\n";
echo "Tables created: $success\n";
echo "Errors: $errors\n";

// Verify
echo "\n========================================\n";
echo "Verification\n";
echo "========================================\n";

foreach (array_keys($tables) as $tableName) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
    if ($stmt->rowCount() > 0) {
        echo "✅ $tableName exists\n";
    } else {
        echo "❌ $tableName missing\n";
    }
}

echo "\n========================================\n";
if ($errors > 0) {
    echo "⚠️  Completed with $errors errors\n";
    exit(1);
} else {
    echo "✅ All Phase 4 tables created successfully!\n";
    exit(0);
}
