-- Phase 6 (Revised): Unified Template System
-- Extend email_templates to support SMS and WhatsApp

ALTER TABLE `email_templates`
ADD COLUMN `is_sms_active` TINYINT(1) DEFAULT 0 AFTER `is_active`,
ADD COLUMN `sms_content` TEXT DEFAULT NULL AFTER `is_sms_active`,
ADD COLUMN `is_whatsapp_active` TINYINT(1) DEFAULT 0 AFTER `sms_content`,
ADD COLUMN `whatsapp_content` TEXT DEFAULT NULL AFTER `is_whatsapp_active`;

-- Update existing templates to have defaults (optional, defaults are 0/NULL already)
-- We might want to enable SMS for high priority items like task_assigned if we want to migrate
-- For now, we leave them disabled until user explicitly enables them in the UI.
