-- Phase 6: Dynamic Template Channels
-- Revert hardcoded columns from email_templates (if they exist)
ALTER TABLE `email_templates`
DROP COLUMN `is_sms_active`,
DROP COLUMN `sms_content`,
DROP COLUMN `is_whatsapp_active`,
DROP COLUMN `whatsapp_content`;

-- Create new table for dynamic channel configuration
CREATE TABLE IF NOT EXISTS `email_template_channels` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `template_id` INT NOT NULL,
    `channel_type` VARCHAR(50) NOT NULL, -- e.g., 'sms', 'whatsapp', 'push', 'viber'
    `is_active` TINYINT(1) DEFAULT 1,
    `custom_content` TEXT DEFAULT NULL, -- NULL means usage of stripped email body
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`template_id`) REFERENCES `email_templates`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_template_channel` (`template_id`, `channel_type`)
);
