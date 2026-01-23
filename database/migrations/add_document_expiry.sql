-- Phase 1: Document Expiry Tracking
-- Migration to add expiry date tracking to documents

-- Add expiry tracking columns to documents table
ALTER TABLE documents 
ADD COLUMN expiry_date DATE NULL AFTER description,
ADD COLUMN expiry_alert_sent TINYINT(1) DEFAULT 0 AFTER expiry_date,
ADD COLUMN expiry_alert_days INT DEFAULT 30 AFTER expiry_alert_sent;

-- Create index for efficient querying of expiring documents
CREATE INDEX idx_documents_expiry ON documents(expiry_date, expiry_alert_sent);

-- Create document expiry alerts tracking table
CREATE TABLE IF NOT EXISTS document_expiry_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    alert_type ENUM('30_days', '14_days', '7_days', 'expired') NOT NULL,
    sent_at DATETIME NOT NULL,
    sent_to INT NOT NULL,
    channel ENUM('email', 'notification', 'sms') DEFAULT 'notification',
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (sent_to) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_alert (document_id, alert_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add index for faster lookups
CREATE INDEX idx_expiry_alerts_document ON document_expiry_alerts(document_id);
CREATE INDEX idx_expiry_alerts_sent_to ON document_expiry_alerts(sent_to);
