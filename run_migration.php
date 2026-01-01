<?php
/**
 * Direct Migration Execution
 */

require_once 'config.php';

echo "Executing Phase 1 Migration...\n\n";

try {
    // 1. ALTER inquiries table
    echo "1. Adding lead scoring fields to inquiries...\n";
    try {
        $pdo->exec("ALTER TABLE inquiries ADD COLUMN priority ENUM('hot', 'warm', 'cold') DEFAULT 'warm' AFTER status");
        echo "   ✓ Added priority column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "   ⚠ priority column already exists\n";
        } else
            throw $e;
    }

    try {
        $pdo->exec("ALTER TABLE inquiries ADD COLUMN score INT DEFAULT 0 AFTER priority");
        echo "   ✓ Added score column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "   ⚠ score column already exists\n";
        } else
            throw $e;
    }

    try {
        $pdo->exec("ALTER TABLE inquiries ADD COLUMN last_contact_date DATETIME AFTER score");
        echo "   ✓ Added last_contact_date column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "   ⚠ last_contact_date column already exists\n";
        } else
            throw $e;
    }

    try {
        $pdo->exec("ALTER TABLE inquiries ADD INDEX idx_priority (priority)");
        echo "   ✓ Added priority index\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "   ⚠ priority index already exists\n";
        } else
            throw $e;
    }

    try {
        $pdo->exec("ALTER TABLE inquiries ADD INDEX idx_score (score DESC)");
        echo "   ✓ Added score index\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "   ⚠ score index already exists\n";
        } else
            throw $e;
    }

    // 2. CREATE tasks table
    echo "\n2. Creating tasks table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        assigned_to INT NOT NULL,
        created_by INT NOT NULL,
        related_entity_type ENUM('inquiry', 'student', 'application', 'class', 'general') NOT NULL DEFAULT 'general',
        related_entity_id INT,
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        due_date DATETIME,
        completed_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_assigned (assigned_to, status),
        INDEX idx_due_date (due_date),
        INDEX idx_status (status),
        INDEX idx_priority (priority),
        INDEX idx_entity (related_entity_type, related_entity_id)
    )");
    echo "   ✓ Tasks table created\n";

    // 3. CREATE appointments table
    echo "\n3. Creating appointments table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT,
        inquiry_id INT,
        counselor_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        appointment_date DATETIME NOT NULL,
        duration_minutes INT DEFAULT 30,
        location VARCHAR(255),
        meeting_link VARCHAR(255),
        status ENUM('scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
        reminder_sent BOOLEAN DEFAULT FALSE,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE,
        FOREIGN KEY (counselor_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_date (appointment_date),
        INDEX idx_counselor (counselor_id, appointment_date),
        INDEX idx_status (status),
        INDEX idx_student (student_id),
        INDEX idx_inquiry (inquiry_id)
    )");
    echo "   ✓ Appointments table created\n";

    // 4. CREATE system_settings table
    echo "\n4. Creating system_settings table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        setting_type ENUM('string', 'int', 'boolean', 'json') DEFAULT 'string',
        description VARCHAR(255),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_key (setting_key)
    )");
    echo "   ✓ System settings table created\n";

    // 5. INSERT default settings
    echo "\n5. Inserting default settings...\n";
    $pdo->exec("INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
        ('eod_report_time', '18:00', 'string', 'End of Day report generation time (HH:MM)'),
        ('eod_report_enabled', 'false', 'boolean', 'Enable automated EOD reports'),
        ('eod_report_recipients', '[]', 'json', 'Email addresses for EOD reports'),
        ('lead_scoring_enabled', 'true', 'boolean', 'Enable automatic lead scoring'),
        ('appointment_reminder_hours', '24', 'int', 'Hours before appointment to send reminder'),
        ('task_overdue_notification', 'true', 'boolean', 'Send notifications for overdue tasks')
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    echo "   ✓ Default settings inserted\n";

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✓ MIGRATION COMPLETED SUCCESSFULLY!\n";
    echo str_repeat("=", 60) . "\n";

} catch (PDOException $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
