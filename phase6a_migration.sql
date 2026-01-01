-- Phase 6A Migration: Lead Priority & Scoring System (FIXED)
-- CMST Priority 1 Features

USE edu_crm;

-- =========================================================================
-- CHECK AND ADD COLUMNS TO INQUIRIES (IF NOT EXISTS)
-- =========================================================================

-- Add score column if not exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'edu_crm' AND TABLE_NAME = 'inquiries' AND COLUMN_NAME = 'score');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE inquiries ADD COLUMN score INT DEFAULT 0 AFTER priority',
    'SELECT "score column already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add last_contact_date column if not exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'edu_crm' AND TABLE_NAME = 'inquiries' AND COLUMN_NAME = 'last_contact_date');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE inquiries ADD COLUMN last_contact_date DATETIME AFTER score',
    'SELECT "last_contact_date column already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add engagement_count column if not exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'edu_crm' AND TABLE_NAME = 'inquiries' AND COLUMN_NAME = 'engagement_count');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE inquiries ADD COLUMN engagement_count INT DEFAULT 0 AFTER last_contact_date',
    'SELECT "engagement_count column already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes
CREATE INDEX IF NOT EXISTS idx_priority ON inquiries(priority);
CREATE INDEX IF NOT EXISTS idx_score ON inquiries(score DESC);
CREATE INDEX IF NOT EXISTS idx_last_contact ON inquiries(last_contact_date);
CREATE INDEX IF NOT EXISTS idx_inquiry_priority_score ON inquiries(priority, score DESC);
CREATE INDEX IF NOT EXISTS idx_inquiry_status_priority ON inquiries(status, priority);

-- =========================================================================
-- SCORING RULES TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS scoring_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_name VARCHAR(100) NOT NULL,
    rule_type ENUM('recency', 'response', 'value', 'education', 'engagement') NOT NULL,
    points INT NOT NULL,
    condition_json JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (rule_type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- INQUIRY SCORE HISTORY TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS inquiry_score_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inquiry_id INT NOT NULL,
    old_score INT,
    new_score INT,
    old_priority ENUM('hot', 'warm', 'cold'),
    new_priority ENUM('hot', 'warm', 'cold'),
    reason VARCHAR(255),
    changed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_inquiry (inquiry_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- INSERT DEFAULT SCORING RULES (IF NOT EXISTS)
-- =========================================================================

INSERT IGNORE INTO scoring_rules (rule_name, rule_type, points, condition_json, is_active) VALUES
-- Recency Rules
('Recent Contact (< 24h)', 'recency', 20, '{"hours": 24}', TRUE),
('Recent Contact (< 7 days)', 'recency', 10, '{"days": 7}', TRUE),
('Old Lead (> 30 days)', 'recency', -15, '{"days": 30}', TRUE),
('Very Old Lead (> 90 days)', 'recency', -30, '{"days": 90}', TRUE),

-- Response Rules
('High Response Rate (>80%)', 'response', 15, '{"rate": 0.8}', TRUE),
('Medium Response Rate (>50%)', 'response', 5, '{"rate": 0.5}', TRUE),
('Low Response Rate (<30%)', 'response', -10, '{"rate": 0.3}', TRUE),

-- Value Rules
('High Value Course (>$10k)', 'value', 25, '{"min_value": 10000}', TRUE),
('Medium Value Course (>$5k)', 'value', 10, '{"min_value": 5000}', TRUE),
('Low Value Course (<$2k)', 'value', -5, '{"max_value": 2000}', TRUE),

-- Education Rules
('Education Level Match', 'education', 15, '{"match": true}', TRUE),
('Overqualified', 'education', -5, '{"overqualified": true}', TRUE),

-- Engagement Rules
('High Engagement (5+ interactions)', 'engagement', 20, '{"min_interactions": 5}', TRUE),
('Medium Engagement (3+ interactions)', 'engagement', 10, '{"min_interactions": 3}', TRUE),
('Low Engagement (<2 interactions)', 'engagement', -10, '{"max_interactions": 1}', TRUE);

-- =========================================================================
-- UPDATE EXISTING INQUIRIES
-- =========================================================================

-- Set last_contact_date to created_at for existing inquiries
UPDATE inquiries 
SET last_contact_date = created_at 
WHERE last_contact_date IS NULL;

-- Initialize engagement_count to 0 for existing inquiries
UPDATE inquiries 
SET engagement_count = 0 
WHERE engagement_count IS NULL;

-- =========================================================================
-- VERIFICATION
-- =========================================================================

SELECT 'Phase 6A Migration Complete!' as status;

SELECT 
    COUNT(*) as total_inquiries, 
    SUM(CASE WHEN priority = 'hot' THEN 1 ELSE 0 END) as hot_count,
    SUM(CASE WHEN priority = 'warm' THEN 1 ELSE 0 END) as warm_count,
    SUM(CASE WHEN priority = 'cold' THEN 1 ELSE 0 END) as cold_count
FROM inquiries;
