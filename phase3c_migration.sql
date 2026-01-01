-- Phase 3C: WhatsApp Integration - Minor Database Updates
-- Date: 2026-01-01
-- Description: Add WhatsApp-specific fields to existing tables

USE edu_crm;

-- =========================================================================
-- 1. ADD WHATSAPP TEMPLATE FIELDS
-- =========================================================================

ALTER TABLE messaging_templates
ADD COLUMN whatsapp_template_id VARCHAR(255) COMMENT 'WhatsApp approved template ID',
ADD COLUMN whatsapp_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' COMMENT 'WhatsApp template approval status',
ADD COLUMN whatsapp_language VARCHAR(10) DEFAULT 'en' COMMENT 'Template language code',
ADD COLUMN whatsapp_category VARCHAR(50) COMMENT 'WhatsApp template category';

-- =========================================================================
-- 2. ADD MEDIA SUPPORT TO QUEUE
-- =========================================================================

ALTER TABLE messaging_queue
ADD COLUMN media_url VARCHAR(500) COMMENT 'URL to media file (image, video, document)',
ADD COLUMN media_type VARCHAR(50) COMMENT 'Media MIME type',
ADD COLUMN media_caption TEXT COMMENT 'Caption for media message',
ADD COLUMN interactive_data JSON COMMENT 'Interactive buttons/lists data';

-- =========================================================================
-- 3. ADD WHATSAPP SESSION TRACKING
-- =========================================================================

CREATE TABLE IF NOT EXISTS whatsapp_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT,
    whatsapp_number VARCHAR(20) NOT NULL,
    session_status ENUM('active', 'expired', 'closed') DEFAULT 'active',
    last_message_at DATETIME,
    expires_at DATETIME,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES messaging_contacts(id) ON DELETE SET NULL,
    INDEX idx_whatsapp_number (whatsapp_number),
    INDEX idx_status (session_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 4. ADD INCOMING MESSAGES TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS whatsapp_incoming_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    whatsapp_message_id VARCHAR(255) UNIQUE,
    from_number VARCHAR(20) NOT NULL,
    to_number VARCHAR(20) NOT NULL,
    message_type ENUM('text', 'image', 'video', 'document', 'audio', 'location', 'button_reply') DEFAULT 'text',
    message_text TEXT,
    media_url VARCHAR(500),
    media_type VARCHAR(50),
    button_payload VARCHAR(255),
    contact_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    replied_to BOOLEAN DEFAULT FALSE,
    received_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES messaging_contacts(id) ON DELETE SET NULL,
    INDEX idx_from_number (from_number),
    INDEX idx_received_at (received_at),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 5. UPDATE DEFAULT TEMPLATES FOR WHATSAPP
-- =========================================================================

UPDATE messaging_templates 
SET whatsapp_status = 'approved',
    whatsapp_category = 'UTILITY'
WHERE category IN ('appointment', 'task', 'welcome', 'application', 'payment');

-- =========================================================================
-- 6. UPDATE SYSTEM SETTINGS
-- =========================================================================

INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('whatsapp_enabled', 'true', 'boolean', 'Enable WhatsApp messaging'),
('whatsapp_default_gateway', 'twilio_whatsapp', 'string', 'Default WhatsApp gateway (twilio_whatsapp, whatsapp_business, 360dialog)'),
('whatsapp_session_timeout', '86400', 'int', 'WhatsApp session timeout in seconds (24 hours)'),
('whatsapp_webhook_verify_token', '', 'string', 'WhatsApp webhook verification token')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =========================================================================
-- MIGRATION COMPLETE
-- =========================================================================

SELECT 'Phase 3C Migration Complete!' as status,
       'Added WhatsApp support to existing tables' as message,
       'Created 2 new tables for sessions and incoming messages' as details;
