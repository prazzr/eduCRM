-- Database Optimization Script
-- 1. Remove redundant 'role' column from users (3NF normalization)
ALTER TABLE users DROP COLUMN role;

-- 2. Add Indexes for Performance
CREATE INDEX idx_users_name ON users(name);
CREATE INDEX idx_inquiries_email ON inquiries(email);
CREATE INDEX idx_inquiries_status ON inquiries(status);
CREATE INDEX idx_applications_status ON university_applications(status);
CREATE INDEX idx_documents_student_id ON student_documents(student_id);

-- 3. Add Audit Columns
ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE inquiries ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 4. Clean up default seed data if it relies on 'role' column (handled by user_roles now)
-- No data change needed as users are already linked in user_roles
