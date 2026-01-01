-- Phase 6B Migration: Workflow Automation System
-- CMST Priority 2 Features

USE edu_crm;

-- =========================================================================
-- WORKFLOW TEMPLATES TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS workflow_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category ENUM('visa', 'admission', 'onboarding', 'custom') DEFAULT 'custom',
    country VARCHAR(100),
    visa_type VARCHAR(100),
    description TEXT,
    estimated_days INT DEFAULT 90,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category),
    INDEX idx_country (country),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- WORKFLOW STEPS TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS workflow_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    step_order INT NOT NULL,
    step_name VARCHAR(200) NOT NULL,
    description TEXT,
    estimated_days INT DEFAULT 7,
    required_documents JSON,
    auto_create_task BOOLEAN DEFAULT FALSE,
    task_title VARCHAR(200),
    task_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES workflow_templates(id) ON DELETE CASCADE,
    INDEX idx_template (template_id, step_order),
    UNIQUE KEY unique_template_step (template_id, step_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- STUDENT WORKFLOW PROGRESS TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS student_workflow_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    template_id INT NOT NULL,
    current_step_id INT,
    status ENUM('not_started', 'in_progress', 'completed', 'on_hold', 'cancelled') DEFAULT 'not_started',
    started_at DATETIME,
    completed_at DATETIME,
    notes TEXT,
    assigned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES workflow_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (current_step_id) REFERENCES workflow_steps(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_student (student_id),
    INDEX idx_template (template_id),
    INDEX idx_status (status),
    INDEX idx_assigned (assigned_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- WORKFLOW STEP COMPLETION TABLE
-- =========================================================================

CREATE TABLE IF NOT EXISTS workflow_step_completions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    progress_id INT NOT NULL,
    step_id INT NOT NULL,
    completed_at DATETIME,
    completed_by INT,
    notes TEXT,
    documents_uploaded JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (progress_id) REFERENCES student_workflow_progress(id) ON DELETE CASCADE,
    FOREIGN KEY (step_id) REFERENCES workflow_steps(id) ON DELETE CASCADE,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_progress (progress_id),
    INDEX idx_step (step_id),
    UNIQUE KEY unique_progress_step (progress_id, step_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- INSERT PRE-BUILT WORKFLOW TEMPLATES
-- =========================================================================

-- Template 1: Australia Student Visa (500)
INSERT INTO workflow_templates (name, category, country, visa_type, description, estimated_days, is_active) VALUES
('Australia Student Visa (Subclass 500)', 'visa', 'Australia', '500', 'Complete workflow for Australian student visa application', 90, TRUE);

SET @aus_template_id = LAST_INSERT_ID();

INSERT INTO workflow_steps (template_id, step_order, step_name, description, estimated_days, required_documents, auto_create_task, task_title) VALUES
(@aus_template_id, 1, 'Document Collection', 'Gather all required documents for visa application', 7, '["Passport", "CoE", "Financial proof", "English test results", "Health insurance"]', TRUE, 'Collect visa documents'),
(@aus_template_id, 2, 'GTE Statement Preparation', 'Prepare Genuine Temporary Entrant statement', 3, '["GTE statement draft"]', TRUE, 'Draft GTE statement'),
(@aus_template_id, 3, 'Health Insurance', 'Obtain Overseas Student Health Cover (OSHC)', 1, '["OSHC certificate"]', TRUE, 'Purchase OSHC'),
(@aus_template_id, 4, 'Application Submission', 'Submit visa application online via ImmiAccount', 1, '["Completed application form", "Payment receipt"]', TRUE, 'Submit visa application'),
(@aus_template_id, 5, 'Biometrics Appointment', 'Attend biometrics collection appointment', 7, '["Biometrics confirmation"]', TRUE, 'Schedule biometrics'),
(@aus_template_id, 6, 'Health Examination', 'Complete required health examinations', 7, '["Health examination results"]', TRUE, 'Book health exam'),
(@aus_template_id, 7, 'Decision Awaited', 'Wait for visa decision from Department of Home Affairs', 60, '[]', FALSE, NULL),
(@aus_template_id, 8, 'Visa Grant', 'Visa granted - prepare for travel', 3, '["Visa grant letter"]', TRUE, 'Prepare for departure');

-- Template 2: UK Student Visa (Tier 4)
INSERT INTO workflow_templates (name, category, country, visa_type, description, estimated_days, is_active) VALUES
('UK Student Visa (Tier 4)', 'visa', 'United Kingdom', 'Tier 4', 'Complete workflow for UK Tier 4 student visa application', 60, TRUE);

SET @uk_template_id = LAST_INSERT_ID();

INSERT INTO workflow_steps (template_id, step_order, step_name, description, estimated_days, required_documents, auto_create_task, task_title) VALUES
(@uk_template_id, 1, 'CAS Confirmation', 'Obtain Confirmation of Acceptance for Studies from university', 7, '["CAS letter"]', TRUE, 'Request CAS from university'),
(@uk_template_id, 2, 'Document Preparation', 'Gather all required supporting documents', 5, '["Passport", "Financial evidence", "English test", "Academic transcripts"]', TRUE, 'Collect visa documents'),
(@uk_template_id, 3, 'Online Application', 'Complete visa application online', 1, '["Application form", "Payment receipt"]', TRUE, 'Submit online application'),
(@uk_template_id, 4, 'Biometrics & Interview', 'Attend visa application center for biometrics', 7, '["Appointment confirmation"]', TRUE, 'Book VAC appointment'),
(@uk_template_id, 5, 'TB Test (if required)', 'Complete tuberculosis test if from listed country', 3, '["TB test certificate"]', TRUE, 'Schedule TB test'),
(@uk_template_id, 6, 'Decision Awaited', 'Wait for visa decision', 30, '[]', FALSE, NULL),
(@uk_template_id, 7, 'Visa Collection', 'Collect passport with visa', 2, '["Visa vignette"]', TRUE, 'Collect passport');

-- Template 3: Canada Study Permit
INSERT INTO workflow_templates (name, category, country, visa_type, description, estimated_days, is_active) VALUES
('Canada Study Permit', 'visa', 'Canada', 'Study Permit', 'Complete workflow for Canadian study permit application', 75, TRUE);

SET @can_template_id = LAST_INSERT_ID();

INSERT INTO workflow_steps (template_id, step_order, step_name, description, estimated_days, required_documents, auto_create_task, task_title) VALUES
(@can_template_id, 1, 'Letter of Acceptance', 'Obtain acceptance letter from DLI', 7, '["LOA from DLI"]', TRUE, 'Request LOA'),
(@can_template_id, 2, 'GIC & Financial Proof', 'Set up GIC account and gather financial documents', 5, '["GIC certificate", "Bank statements"]', TRUE, 'Open GIC account'),
(@can_template_id, 3, 'Document Collection', 'Gather all required documents', 5, '["Passport", "Photos", "Language test", "Academic records"]', TRUE, 'Collect documents'),
(@can_template_id, 4, 'Online Application', 'Submit study permit application via IRCC portal', 2, '["Application form", "Payment receipt"]', TRUE, 'Submit application'),
(@can_template_id, 5, 'Biometrics', 'Provide biometrics at VAC', 7, '["Biometrics confirmation"]', TRUE, 'Schedule biometrics'),
(@can_template_id, 6, 'Medical Examination', 'Complete medical exam if required', 7, '["Medical exam results"]', TRUE, 'Book medical exam'),
(@can_template_id, 7, 'Decision Awaited', 'Wait for study permit decision', 35, '[]', FALSE, NULL),
(@can_template_id, 8, 'Permit Approval', 'Receive study permit approval', 2, '["Approval letter", "Port of entry letter"]', TRUE, 'Prepare for travel');

-- Template 4: USA F-1 Visa
INSERT INTO workflow_templates (name, category, country, visa_type, description, estimated_days, is_active) VALUES
('USA F-1 Student Visa', 'visa', 'United States', 'F-1', 'Complete workflow for US F-1 student visa application', 60, TRUE);

SET @usa_template_id = LAST_INSERT_ID();

INSERT INTO workflow_steps (template_id, step_order, step_name, description, estimated_days, required_documents, auto_create_task, task_title) VALUES
(@usa_template_id, 1, 'I-20 Form', 'Obtain I-20 form from university', 7, '["I-20 form"]', TRUE, 'Request I-20'),
(@usa_template_id, 2, 'SEVIS Fee Payment', 'Pay SEVIS I-901 fee online', 1, '["SEVIS payment receipt"]', TRUE, 'Pay SEVIS fee'),
(@usa_template_id, 3, 'DS-160 Form', 'Complete DS-160 online application form', 2, '["DS-160 confirmation"]', TRUE, 'Complete DS-160'),
(@usa_template_id, 4, 'Visa Fee Payment', 'Pay visa application fee', 1, '["Visa fee receipt"]', TRUE, 'Pay visa fee'),
(@usa_template_id, 5, 'Interview Scheduling', 'Schedule visa interview appointment', 3, '["Interview appointment"]', TRUE, 'Schedule interview'),
(@usa_template_id, 6, 'Document Preparation', 'Prepare all documents for interview', 5, '["Financial documents", "Academic records", "Ties to home country"]', TRUE, 'Prepare interview docs'),
(@usa_template_id, 7, 'Visa Interview', 'Attend visa interview at US Embassy/Consulate', 1, '["Interview attendance"]', TRUE, 'Attend interview'),
(@usa_template_id, 8, 'Passport Processing', 'Wait for passport with visa', 7, '[]', FALSE, NULL),
(@usa_template_id, 9, 'Visa Received', 'Collect passport with F-1 visa', 2, '["F-1 visa"]', TRUE, 'Prepare for departure');

-- Template 5: New Zealand Student Visa
INSERT INTO workflow_templates (name, category, country, visa_type, description, estimated_days, is_active) VALUES
('New Zealand Student Visa', 'visa', 'New Zealand', 'Student Visa', 'Complete workflow for New Zealand student visa application', 45, TRUE);

SET @nz_template_id = LAST_INSERT_ID();

INSERT INTO workflow_steps (template_id, step_order, step_name, description, estimated_days, required_documents, auto_create_task, task_title) VALUES
(@nz_template_id, 1, 'Offer of Place', 'Obtain offer of place from NZ institution', 7, '["Offer letter"]', TRUE, 'Request offer letter'),
(@nz_template_id, 2, 'Financial Evidence', 'Prepare proof of funds', 3, '["Bank statements", "Scholarship letter"]', TRUE, 'Gather financial proof'),
(@nz_template_id, 3, 'Document Collection', 'Gather all required documents', 5, '["Passport", "Photos", "Police certificate", "Medical certificate"]', TRUE, 'Collect documents'),
(@nz_template_id, 4, 'Online Application', 'Submit visa application via Immigration NZ', 2, '["Application form", "Payment receipt"]', TRUE, 'Submit application'),
(@nz_template_id, 5, 'Medical & X-ray', 'Complete medical and chest X-ray', 5, '["Medical certificate", "X-ray results"]', TRUE, 'Book medical exam'),
(@nz_template_id, 6, 'Decision Awaited', 'Wait for visa decision', 20, '[]', FALSE, NULL),
(@nz_template_id, 7, 'Visa Grant', 'Visa granted - prepare for travel', 3, '["Visa approval"]', TRUE, 'Prepare for departure');

-- =========================================================================
-- VERIFICATION
-- =========================================================================

SELECT 'Phase 6B Migration Complete!' as status;

SELECT 
    COUNT(*) as total_templates,
    SUM(CASE WHEN category = 'visa' THEN 1 ELSE 0 END) as visa_templates
FROM workflow_templates;

SELECT 
    wt.name as template_name,
    COUNT(ws.id) as step_count
FROM workflow_templates wt
LEFT JOIN workflow_steps ws ON wt.id = ws.template_id
GROUP BY wt.id, wt.name;
