-- Phase 3A: Document Management System Migration
-- Date: 2026-01-01
-- Description: Adds document storage, versioning, and template support

USE edu_crm;

-- =========================================================================
-- 1. DOCUMENTS TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('inquiry', 'student', 'application', 'general') NOT NULL,
    entity_id INT,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size INT NOT NULL,
    uploaded_by INT NOT NULL,
    category VARCHAR(100),
    description TEXT,
    is_template BOOLEAN DEFAULT FALSE,
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_category (category),
    INDEX idx_uploaded_by (uploaded_by)
);

-- =========================================================================
-- 2. DOCUMENT VERSIONS TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS document_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    version_number INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    uploaded_by INT NOT NULL,
    change_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_document (document_id),
    UNIQUE KEY unique_version (document_id, version_number)
);

-- =========================================================================
-- 3. DOCUMENT TEMPLATES TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS document_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    file_path VARCHAR(500) NOT NULL,
    category VARCHAR(100),
    variables JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_category (category),
    INDEX idx_active (is_active)
);

-- =========================================================================
-- 4. UPDATE SYSTEM SETTINGS
-- =========================================================================

INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('document_max_size', '10485760', 'int', 'Maximum document size in bytes (10MB)'),
('document_allowed_types', 'pdf,doc,docx,jpg,jpeg,png,xlsx,xls', 'string', 'Allowed document file types'),
('document_storage_path', 'uploads/documents/', 'string', 'Document storage path'),
('enable_virus_scan', 'false', 'boolean', 'Enable virus scanning for uploads'),
('enable_document_versioning', 'true', 'boolean', 'Enable document version control')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- =========================================================================
-- 5. CREATE DEFAULT DOCUMENT CATEGORIES
-- =========================================================================

-- Categories will be managed in the application
-- Common categories: passport, visa, certificates, transcripts, 
-- offer_letters, contracts, applications, financial_documents

-- =========================================================================
-- MIGRATION COMPLETE
-- =========================================================================
