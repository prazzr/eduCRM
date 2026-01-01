-- Phase 4A: Advanced Analytics Dashboard - Database Schema
-- Date: 2026-01-01
-- Description: Analytics tables for metrics, snapshots, and goals

USE edu_crm;

-- =========================================================================
-- 1. ANALYTICS METRICS TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS analytics_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(15,2) NOT NULL,
    metric_type ENUM('count', 'revenue', 'percentage', 'duration') DEFAULT 'count',
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    entity_type VARCHAR(50) COMMENT 'inquiry, student, application, counselor',
    entity_id INT COMMENT 'Related entity ID',
    metadata JSON COMMENT 'Additional metric data',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_metric_name (metric_name),
    INDEX idx_period (period_start, period_end),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 2. ANALYTICS SNAPSHOTS TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS analytics_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE UNIQUE NOT NULL,
    total_inquiries INT DEFAULT 0,
    total_students INT DEFAULT 0,
    total_applications INT DEFAULT 0,
    total_revenue DECIMAL(15,2) DEFAULT 0,
    conversion_rate DECIMAL(5,2) DEFAULT 0 COMMENT 'Inquiry to student conversion %',
    avg_response_time INT DEFAULT 0 COMMENT 'Average response time in minutes',
    active_counselors INT DEFAULT 0,
    pending_tasks INT DEFAULT 0,
    upcoming_appointments INT DEFAULT 0,
    metrics_json JSON COMMENT 'Additional snapshot data',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_snapshot_date (snapshot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 3. ANALYTICS GOALS TABLE
-- =========================================================================

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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_goal_type (goal_type),
    INDEX idx_period (period_start, period_end),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- 4. ADD PERFORMANCE INDEXES TO EXISTING TABLES
-- =========================================================================

-- Optimize inquiry queries
CREATE INDEX IF NOT EXISTS idx_inquiry_status_date ON inquiries(status, created_at);
CREATE INDEX IF NOT EXISTS idx_inquiry_counselor_status ON inquiries(counselor_id, status);
CREATE INDEX IF NOT EXISTS idx_inquiry_source ON inquiries(source);

-- Optimize student queries
CREATE INDEX IF NOT EXISTS idx_student_counselor ON students(counselor_id, status);
CREATE INDEX IF NOT EXISTS idx_student_enrollment ON students(enrollment_date);

-- Optimize application queries
CREATE INDEX IF NOT EXISTS idx_application_status ON applications(status, created_at);
CREATE INDEX IF NOT EXISTS idx_application_student ON applications(student_id, status);

-- Optimize task queries
CREATE INDEX IF NOT EXISTS idx_task_assigned_status ON tasks(assigned_to, status);
CREATE INDEX IF NOT EXISTS idx_task_due_date ON tasks(due_date, status);

-- Optimize appointment queries
CREATE INDEX IF NOT EXISTS idx_appointment_counselor_date ON appointments(counselor_id, appointment_date);
CREATE INDEX IF NOT EXISTS idx_appointment_status ON appointments(status, appointment_date);

-- =========================================================================
-- 5. INSERT SAMPLE GOALS
-- =========================================================================

INSERT INTO analytics_goals (goal_name, goal_type, target_value, period_start, period_end, created_by) VALUES
('Monthly Inquiry Target', 'inquiries', 500, '2026-01-01', '2026-01-31', 1),
('Q1 Conversion Goal', 'conversions', 150, '2026-01-01', '2026-03-31', 1),
('Q1 Revenue Target', 'revenue', 500000, '2026-01-01', '2026-03-31', 1),
('Monthly Application Goal', 'applications', 200, '2026-01-01', '2026-01-31', 1);

-- =========================================================================
-- 6. CREATE INITIAL SNAPSHOT
-- =========================================================================

INSERT INTO analytics_snapshots (
    snapshot_date,
    total_inquiries,
    total_students,
    total_applications,
    total_revenue,
    conversion_rate
)
SELECT 
    CURDATE(),
    (SELECT COUNT(*) FROM inquiries),
    (SELECT COUNT(*) FROM students),
    (SELECT COUNT(*) FROM applications),
    (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'),
    (SELECT ROUND((COUNT(DISTINCT s.id) * 100.0 / NULLIF(COUNT(DISTINCT i.id), 0)), 2)
     FROM inquiries i
     LEFT JOIN students s ON i.id = s.inquiry_id)
ON DUPLICATE KEY UPDATE
    total_inquiries = VALUES(total_inquiries),
    total_students = VALUES(total_students),
    total_applications = VALUES(total_applications),
    total_revenue = VALUES(total_revenue),
    conversion_rate = VALUES(conversion_rate);

-- =========================================================================
-- MIGRATION COMPLETE
-- =========================================================================

SELECT 'Phase 4A Migration Complete!' as status,
       'Created 3 analytics tables and optimized indexes' as message,
       (SELECT COUNT(*) FROM analytics_goals) as sample_goals;
