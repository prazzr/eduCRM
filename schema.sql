-- Database Schema for Education Consultancy CRM
-- Consolidated Version (Includes Core, LMS, CRM, Accounting, Roles, Logs)

CREATE DATABASE IF NOT EXISTS edu_crm;
USE edu_crm;

-- =========================================================================
-- 1. USER MANAGEMENT & ROLES
-- =========================================================================

-- Roles Table (Normalized roles)
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE -- admin, teacher, counselor, student, accountant
);

-- Users Table
-- 'role' column kept for backward compatibility/simplicity, but 'user_roles' is primary for multi-auth.
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    country VARCHAR(100), -- Added from system_test_suite reference
    education_level VARCHAR(50), -- Added from system_test_suite reference
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User Roles (Many-to-Many)
CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- System Logs (Audit Trail)
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================================================================
-- 2. CRM (INQUIRIES, PARTNERS, APPLICATIONS)
-- =========================================================================

-- Inquiries (Pre-onboarding Leads)
CREATE TABLE IF NOT EXISTS inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    intended_country VARCHAR(100),
    intended_course VARCHAR(50),
    education_level VARCHAR(50),
    status ENUM('new', 'contacted', 'converted', 'closed') DEFAULT 'new',
    assigned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- Partners / Universities
CREATE TABLE IF NOT EXISTS partners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type ENUM('university', 'college', 'agent', 'other') DEFAULT 'university',
    country VARCHAR(100),
    website VARCHAR(255),
    contact_email VARCHAR(100),
    commission_rate DECIMAL(5, 2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- University Applications
CREATE TABLE IF NOT EXISTS university_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    university_name VARCHAR(150) NOT NULL,
    course_name VARCHAR(150),
    country VARCHAR(100),
    status ENUM('applied', 'offer_received', 'offer_accepted', 'visa_lodged', 'visa_granted', 'rejected') DEFAULT 'applied',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Visa Workflows (Detailed Visa Stages)
CREATE TABLE IF NOT EXISTS visa_workflows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    country VARCHAR(100),
    current_stage VARCHAR(50) DEFAULT 'Doc Collection', -- Doc Collection, Submission, Interview, Approved, Rejected
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Test Scores (IELTS, PTE, etc.)
CREATE TABLE IF NOT EXISTS test_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    test_type ENUM('IELTS', 'PTE', 'SAT', 'TOEFL') NOT NULL,
    overall_score DECIMAL(3, 1) NOT NULL,
    listening DECIMAL(3, 1),
    reading DECIMAL(3, 1),
    writing DECIMAL(3, 1),
    speaking DECIMAL(3, 1),
    test_date DATE,
    report_file VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Student Documents (Vault)
CREATE TABLE IF NOT EXISTS student_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Centralized Attachments (Secure Downloads)
CREATE TABLE IF NOT EXISTS attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT, -- Owner
    file_name VARCHAR(255) NOT NULL, -- Original Name
    file_path VARCHAR(255) NOT NULL, -- Storage Path/Name
    file_mime VARCHAR(100),
    file_size INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Student Communication Logs
CREATE TABLE IF NOT EXISTS student_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    author_id INT NOT NULL,
    type ENUM('call', 'email', 'meeting', 'note') DEFAULT 'note',
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =========================================================================
-- 3. LMS (COURSES, CLASSES, ROSTERS)
-- =========================================================================

-- Courses (Catalog)
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Classes (Instances)
CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    teacher_id INT,
    name VARCHAR(100),
    schedule_info VARCHAR(255),
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    FOREIGN KEY (course_id) REFERENCES courses(id),
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Enrollments
CREATE TABLE IF NOT EXISTS enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

-- Class Materials / Tasks
CREATE TABLE IF NOT EXISTS class_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_path VARCHAR(255),
    type ENUM('assignment', 'reading', 'notice') DEFAULT 'notice',
    due_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Submissions
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(255),
    comments TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    grade VARCHAR(10),
    FOREIGN KEY (material_id) REFERENCES class_materials(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Daily Roster
CREATE TABLE IF NOT EXISTS daily_rosters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    roster_date DATE NOT NULL,
    topic VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Daily Performance
CREATE TABLE IF NOT EXISTS daily_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    roster_id INT NOT NULL,
    student_id INT NOT NULL,
    attendance ENUM('present', 'absent', 'late') DEFAULT 'present',
    class_task_mark DECIMAL(5,2) DEFAULT 0,
    home_task_mark DECIMAL(5,2) DEFAULT 0,
    remarks TEXT,
    FOREIGN KEY (roster_id) REFERENCES daily_rosters(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (roster_id, student_id)
);

-- =========================================================================
-- 4. ACCOUNTING
-- =========================================================================

-- Fee Types
CREATE TABLE IF NOT EXISTS fee_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    default_amount DECIMAL(10, 2) DEFAULT 0.00
);

-- Student Fees (Invoices)
CREATE TABLE IF NOT EXISTS student_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    fee_type_id INT,
    description VARCHAR(255),
    amount DECIMAL(10, 2) NOT NULL,
    due_date DATE,
    status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_type_id) REFERENCES fee_types(id) ON DELETE SET NULL
);

-- Payments
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_fee_id INT,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50),
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    remarks TEXT,
    FOREIGN KEY (student_fee_id) REFERENCES student_fees(id) ON DELETE SET NULL
);

-- =========================================================================
-- 5. SEED DATA
-- =========================================================================

INSERT IGNORE INTO roles (name) VALUES ('admin'), ('teacher'), ('student'), ('counselor'), ('accountant');

-- Default Admin
-- Default Admin
INSERT INTO users (name, email, password_hash) VALUES 
('System Admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); 
-- Password: 'password'

-- Link Admin Role
SET @admin_id = LAST_INSERT_ID();
SET @admin_role_id = (SELECT id FROM roles WHERE name = 'admin');
INSERT INTO user_roles (user_id, role_id) VALUES (@admin_id, @admin_role_id);
