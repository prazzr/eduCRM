-- Phase 2B: Email Notification System
-- Date: 2025-12-31
-- Description: Adds email queue and notification preferences

USE edu_crm;

-- =========================================================================
-- 1. EMAIL QUEUE TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255),
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    template VARCHAR(100),
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    scheduled_at DATETIME,
    sent_at DATETIME,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_at)
);

-- =========================================================================
-- 2. NOTIFICATION PREFERENCES TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS notification_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    task_assignment BOOLEAN DEFAULT TRUE,
    task_overdue BOOLEAN DEFAULT TRUE,
    appointment_reminder BOOLEAN DEFAULT TRUE,
    daily_digest BOOLEAN DEFAULT FALSE,
    email_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user (user_id)
);

-- =========================================================================
-- 3. UPDATE SYSTEM SETTINGS
-- =========================================================================

INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('smtp_enabled', 'false', 'boolean', 'Enable SMTP for email sending'),
('smtp_host', '', 'string', 'SMTP server host'),
('smtp_port', '587', 'int', 'SMTP server port'),
('smtp_username', '', 'string', 'SMTP username'),
('smtp_password', '', 'string', 'SMTP password (encrypted)'),
('smtp_from_email', 'noreply@educrm.local', 'string', 'From email address'),
('smtp_from_name', 'EduCRM', 'string', 'From name'),
('notification_batch_size', '50', 'int', 'Number of emails to send per batch'),
('email_queue_enabled', 'true', 'boolean', 'Enable email queue system')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =========================================================================
-- 4. CREATE DEFAULT NOTIFICATION PREFERENCES FOR EXISTING USERS
-- =========================================================================

INSERT INTO notification_preferences (user_id, task_assignment, task_overdue, appointment_reminder, daily_digest, email_enabled)
SELECT id, TRUE, TRUE, TRUE, FALSE, TRUE
FROM users
WHERE id NOT IN (SELECT user_id FROM notification_preferences)
ON DUPLICATE KEY UPDATE user_id = user_id;

-- =========================================================================
-- MIGRATION COMPLETE
-- =========================================================================
