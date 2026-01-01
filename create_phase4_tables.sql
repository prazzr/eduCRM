-- Simplified Phase 4 Tables
-- Works with existing database schema

USE edu_crm;

-- =========================================================================
-- ANALYTICS TABLES
-- =========================================================================

CREATE TABLE IF NOT EXISTS analytics_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(15,2) NOT NULL,
    metric_type ENUM('count', 'revenue', 'percentage', 'duration') DEFAULT 'count',
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_metric_name (metric_name),
    INDEX idx_period (period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS analytics_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE UNIQUE NOT NULL,
    total_inquiries INT DEFAULT 0,
    total_revenue DECIMAL(15,2) DEFAULT 0,
    metrics_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS analytics_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    goal_name VARCHAR(100) NOT NULL,
    goal_type ENUM('inquiries', 'conversions', 'revenue', 'applications') NOT NULL,
    target_value DECIMAL(15,2) NOT NULL,
    current_value DECIMAL(15,2) DEFAULT 0,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    status ENUM('active', 'achieved', 'missed', 'cancelled') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- SECURITY TABLES
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS query_cache (
    cache_key VARCHAR(255) PRIMARY KEY,
    cache_value LONGTEXT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- ADD 2FA FIELDS TO USERS (if not exists)
-- =========================================================================

ALTER TABLE users
ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(32) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS two_factor_enabled BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS last_login_ip VARCHAR(45) NULL;

-- =========================================================================
-- INSERT SAMPLE GOALS
-- =========================================================================

INSERT IGNORE INTO analytics_goals (id, goal_name, goal_type, target_value, period_start, period_end, created_by) VALUES
(1, 'Monthly Inquiry Target', 'inquiries', 500, '2026-01-01', '2026-01-31', 1),
(2, 'Q1 Revenue Target', 'revenue', 500000, '2026-01-01', '2026-03-31', 1);

SELECT 'Phase 4 Tables Created Successfully!' as status;
