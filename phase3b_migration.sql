-- Phase 3B: Multi-Gateway Messaging System Migration
-- Date: 2026-01-01
-- Description: Universal messaging platform supporting SMS (Twilio/SMPP/Gammu), WhatsApp, Viber

USE edu_crm;

-- =========================================================================
-- 1. MESSAGING GATEWAYS (Plug & Play Gateway Configuration)
-- =========================================================================

CREATE TABLE IF NOT EXISTS messaging_gateways (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('sms', 'whatsapp', 'viber', 'email') DEFAULT 'sms',
    provider ENUM('twilio', 'smpp', 'gammu', 'whatsapp_business', 'viber_bot', 'smtp') NOT NULL,
    config JSON NOT NULL COMMENT 'Gateway-specific configuration (credentials, endpoints, etc.)',
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    priority INT DEFAULT 0 COMMENT 'Higher priority = preferred gateway',
    daily_limit INT DEFAULT 1000 COMMENT 'Maximum messages per day',
    daily_sent INT DEFAULT 0 COMMENT 'Messages sent today',
    cost_per_message DECIMAL(10, 4) DEFAULT 0.0000,
    total_sent INT DEFAULT 0,
    total_delivered INT DEFAULT 0,
    total_failed INT DEFAULT 0,
    last_used_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_active (is_active),
    INDEX idx_priority (priority DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 2. MESSAGING QUEUE (Universal Message Queue)
-- =========================================================================

CREATE TABLE IF NOT EXISTS messaging_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gateway_id INT,
    message_type ENUM('sms', 'whatsapp', 'viber', 'email') DEFAULT 'sms',
    recipient VARCHAR(255) NOT NULL COMMENT 'Phone number, WhatsApp ID, Viber ID, or email',
    message TEXT NOT NULL,
    template_id INT,
    entity_type VARCHAR(50) COMMENT 'inquiry, student, application, etc.',
    entity_id INT,
    status ENUM('pending', 'processing', 'sent', 'delivered', 'failed', 'cancelled') DEFAULT 'pending',
    scheduled_at DATETIME COMMENT 'When to send (NULL = send immediately)',
    sent_at DATETIME,
    delivered_at DATETIME,
    error_message TEXT,
    gateway_message_id VARCHAR(255) COMMENT 'External gateway message ID for tracking',
    cost DECIMAL(10, 4),
    retry_count INT DEFAULT 0,
    max_retries INT DEFAULT 3,
    metadata JSON COMMENT 'Additional data (campaign_id, user preferences, etc.)',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gateway_id) REFERENCES messaging_gateways(id) ON DELETE SET NULL,
    FOREIGN KEY (template_id) REFERENCES messaging_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_type (message_type),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_recipient (recipient)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 3. MESSAGING TEMPLATES (Multi-Channel Templates)
-- =========================================================================

CREATE TABLE IF NOT EXISTS messaging_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    message_type ENUM('sms', 'whatsapp', 'viber', 'email') DEFAULT 'sms',
    category VARCHAR(50) COMMENT 'appointment, task, welcome, reminder, etc.',
    subject VARCHAR(255) COMMENT 'For email/WhatsApp',
    content TEXT NOT NULL,
    variables JSON COMMENT 'Array of variable names: ["name", "date", "time"]',
    is_active BOOLEAN DEFAULT TRUE,
    usage_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_type (message_type),
    INDEX idx_active (is_active),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 4. MESSAGING CONTACTS (Contact List Management)
-- =========================================================================

CREATE TABLE IF NOT EXISTS messaging_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20),
    email VARCHAR(255),
    whatsapp_number VARCHAR(20),
    viber_id VARCHAR(100),
    entity_type VARCHAR(50) COMMENT 'student, inquiry, custom',
    entity_id INT COMMENT 'Link to student/inquiry if applicable',
    tags JSON COMMENT 'Array of tags for filtering',
    custom_fields JSON COMMENT 'Additional custom data',
    is_active BOOLEAN DEFAULT TRUE,
    opt_out_sms BOOLEAN DEFAULT FALSE,
    opt_out_whatsapp BOOLEAN DEFAULT FALSE,
    opt_out_viber BOOLEAN DEFAULT FALSE,
    opt_out_email BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_phone (phone_number),
    INDEX idx_email (email),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 5. MESSAGING CAMPAIGNS (Bulk Campaign Management)
-- =========================================================================

CREATE TABLE IF NOT EXISTS messaging_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    message_type ENUM('sms', 'whatsapp', 'viber', 'email') DEFAULT 'sms',
    template_id INT,
    message TEXT NOT NULL,
    total_recipients INT DEFAULT 0,
    sent_count INT DEFAULT 0,
    delivered_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    status ENUM('draft', 'scheduled', 'processing', 'completed', 'cancelled') DEFAULT 'draft',
    scheduled_at DATETIME,
    started_at DATETIME,
    completed_at DATETIME,
    total_cost DECIMAL(10, 2) DEFAULT 0.00,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES messaging_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_type (message_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 6. MESSAGING CAMPAIGN RECIPIENTS (Campaign Target List)
-- =========================================================================

CREATE TABLE IF NOT EXISTS messaging_campaign_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    contact_id INT,
    recipient VARCHAR(255) NOT NULL,
    message_id INT COMMENT 'Link to messaging_queue',
    status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
    error_message TEXT,
    sent_at DATETIME,
    FOREIGN KEY (campaign_id) REFERENCES messaging_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES messaging_contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (message_id) REFERENCES messaging_queue(id) ON DELETE SET NULL,
    INDEX idx_campaign (campaign_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 7. INSERT DEFAULT TEMPLATES
-- =========================================================================

INSERT INTO messaging_templates (name, message_type, category, content, variables, created_by) VALUES
('Appointment Reminder', 'sms', 'appointment', 'Hi {name}, reminder: You have an appointment with {counselor} on {date} at {time}. Location: {location}. Reply CONFIRM to confirm.', '["name", "counselor", "date", "time", "location"]', 1),
('Task Due Reminder', 'sms', 'task', 'Hi {name}, your task "{task_title}" is due on {due_date}. Please complete it soon.', '["name", "task_title", "due_date"]', 1),
('Welcome Message', 'sms', 'welcome', 'Welcome to {company}! We are excited to help you with your {course} application. Your counselor {counselor} will contact you soon.', '["company", "course", "counselor"]', 1),
('Application Status Update', 'sms', 'application', 'Hi {name}, your application status has been updated to: {status}. For details, please contact {counselor}.', '["name", "status", "counselor"]', 1),
('Payment Reminder', 'sms', 'payment', 'Hi {name}, this is a reminder that your payment of {amount} is due on {due_date}. Please make the payment to avoid delays.', '["name", "amount", "due_date"]', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- =========================================================================
-- 8. UPDATE SYSTEM SETTINGS
-- =========================================================================

INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('messaging_enabled', 'true', 'boolean', 'Enable messaging system'),
('default_gateway_type', 'sms', 'string', 'Default messaging type (sms, whatsapp, viber)'),
('messaging_retry_limit', '3', 'int', 'Maximum retry attempts for failed messages'),
('messaging_retry_delay', '300', 'int', 'Delay between retries in seconds'),
('messaging_daily_limit', '1000', 'int', 'Global daily message limit'),
('messaging_rate_limit', '10', 'int', 'Messages per minute rate limit'),
('sms_sender_name', 'EduCRM', 'string', 'Default SMS sender name'),
('messaging_queue_batch_size', '50', 'int', 'Number of messages to process per batch')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =========================================================================
-- MIGRATION COMPLETE
-- =========================================================================

SELECT 'Phase 3B Migration Complete!' as status,
       'Created 6 tables for universal messaging platform' as message,
       'Supports: SMS (Twilio/SMPP/Gammu), WhatsApp, Viber' as features;
