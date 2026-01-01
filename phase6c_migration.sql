-- Phase 6C Migration: Smart Features
-- CMST Priority 2/3 Features

USE edu_crm;

-- =========================================================================
-- COMMUNICATION CREDITS TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS communication_credits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    credit_type ENUM('sms', 'email', 'whatsapp', 'viber') NOT NULL,
    credits_available INT DEFAULT 0,
    credits_used INT DEFAULT 0,
    last_recharged_at DATETIME,
    low_credit_threshold INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_credit_type (credit_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initialize credit balances
INSERT INTO communication_credits (credit_type, credits_available, low_credit_threshold) VALUES
('sms', 1000, 100),
('email', 5000, 500),
('whatsapp', 2000, 200),
('viber', 1000, 100);

-- =========================================================================
-- COMMUNICATION USAGE LOGS TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS communication_usage_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    type ENUM('sms', 'email', 'whatsapp', 'viber') NOT NULL,
    subject VARCHAR(255),
    message TEXT,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    credits_consumed INT DEFAULT 1,
    sent_at DATETIME,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_type (user_id, type),
    INDEX idx_sent_at (sent_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- SYSTEM SETTINGS TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'int', 'boolean', 'json', 'time') DEFAULT 'string',
    description VARCHAR(255),
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('eod_report_enabled', 'true', 'boolean', 'Enable End of Day reports'),
('eod_report_time', '18:00', 'time', 'Time to send EOD reports'),
('eod_report_recipients', '["admin@example.com"]', 'json', 'Email recipients for EOD reports'),
('low_credit_alert_enabled', 'true', 'boolean', 'Enable low credit alerts'),
('low_credit_alert_recipients', '["admin@example.com"]', 'json', 'Recipients for low credit alerts'),
('bulk_action_limit', '100', 'int', 'Maximum items for bulk actions'),
('session_timeout', '3600', 'int', 'Session timeout in seconds'),
('max_file_upload_size', '10485760', 'int', 'Max file upload size in bytes (10MB)'),
('date_format', 'Y-m-d', 'string', 'Default date format'),
('time_format', 'H:i:s', 'string', 'Default time format'),
('timezone', 'UTC', 'string', 'System timezone'),
('currency', 'USD', 'string', 'Default currency'),
('items_per_page', '20', 'int', 'Default items per page in lists');

-- =========================================================================
-- SAVED SEARCHES TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS saved_searches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    search_name VARCHAR(100) NOT NULL,
    search_type ENUM('inquiry', 'student', 'application', 'task', 'appointment') NOT NULL,
    search_criteria JSON NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (search_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- QR ATTENDANCE SESSIONS TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS qr_attendance_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    session_date DATE NOT NULL,
    qr_token VARCHAR(64) NOT NULL,
    qr_code_path VARCHAR(255),
    expires_at DATETIME NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_class_date (class_id, session_date),
    INDEX idx_token (qr_token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- QR ATTENDANCE SCANS TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS qr_attendance_scans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    scanned_at DATETIME NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES qr_attendance_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_session_student (session_id, student_id),
    INDEX idx_session (session_id),
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- BULK ACTION LOGS TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS bulk_action_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type ENUM('email', 'sms', 'status_update', 'assignment', 'export') NOT NULL,
    entity_type ENUM('inquiry', 'student', 'task', 'appointment') NOT NULL,
    entity_ids JSON NOT NULL,
    action_data JSON,
    total_items INT NOT NULL,
    successful_items INT DEFAULT 0,
    failed_items INT DEFAULT 0,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error_log TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (action_type),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- VERIFICATION
-- =========================================================================

SELECT 'Phase 6C Migration Complete!' as status;

SELECT 
    'Communication Credits' as feature,
    COUNT(*) as credit_types
FROM communication_credits;

SELECT 
    'System Settings' as feature,
    COUNT(*) as settings_count
FROM system_settings;

SELECT 
    'Tables Created' as info,
    COUNT(*) as table_count
FROM information_schema.tables
WHERE table_schema = 'edu_crm'
AND table_name IN (
    'communication_credits',
    'communication_usage_logs',
    'system_settings',
    'saved_searches',
    'qr_attendance_sessions',
    'qr_attendance_scans',
    'bulk_action_logs'
);
