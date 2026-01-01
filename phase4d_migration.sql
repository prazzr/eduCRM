-- Phase 4D: Security & Performance - Database Schema
-- Date: 2026-01-01

USE edu_crm;

-- =========================================================================
-- 1. SECURITY LOGS TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_action (user_id, action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 2. ADD 2FA FIELDS TO USERS TABLE
-- =========================================================================

ALTER TABLE users
ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(32) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS two_factor_enabled BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS last_login_ip VARCHAR(45) NULL,
ADD COLUMN IF NOT EXISTS failed_login_attempts INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS account_locked_until TIMESTAMP NULL;

-- =========================================================================
-- 3. QUERY CACHE TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS query_cache (
    cache_key VARCHAR(255) PRIMARY KEY,
    cache_value LONGTEXT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 4. PERFORMANCE MONITORING TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS performance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_url VARCHAR(255),
    execution_time DECIMAL(10,4),
    memory_usage INT,
    query_count INT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_page (page_url),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- MIGRATION COMPLETE
-- =========================================================================

SELECT 'Phase 4D Migration Complete!' as status,
       'Created security and performance tables' as message;
