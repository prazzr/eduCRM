-- Phase 6: Centralized Messaging Templates

-- 1. Create the centralized_templates table
CREATE TABLE IF NOT EXISTS centralized_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_key VARCHAR(50) NOT NULL,
    
    -- Email Channel
    email_subject VARCHAR(255),
    email_body TEXT,
    is_email_enabled BOOLEAN DEFAULT TRUE,
    
    -- SMS Channel
    sms_body TEXT,
    is_sms_enabled BOOLEAN DEFAULT FALSE,
    
    -- WhatsApp Channel
    whatsapp_body TEXT,
    is_whatsapp_enabled BOOLEAN DEFAULT FALSE,
    
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_event (event_key),
    FOREIGN KEY (event_key) REFERENCES notification_events(event_key) ON DELETE CASCADE
);

-- 2. Populate with Defaults (Migrate from Hardcoded/Legacy)

-- Task Assignment
INSERT INTO centralized_templates (event_key, email_subject, email_body, is_email_enabled, sms_body, is_sms_enabled)
VALUES (
    'task_assigned', 
    'New Task Assigned: {task_title}', 
    '<div style="font-family: Arial, sans-serif;"><h2>New Task Assigned</h2><p>Hi {name},</p><p>You have been assigned a new task: <strong>{task_title}</strong>.</p><p>Due Date: {due_date}</p><p><a href="{task_url}">View Task</a></p></div>',
    1,
    'Hi {name}, new task "{task_title}" assigned to you. Due: {due_date}.',
    1
) ON DUPLICATE KEY UPDATE email_subject=VALUES(email_subject);

-- Task Overdue
INSERT INTO centralized_templates (event_key, email_subject, email_body, is_email_enabled, sms_body, is_sms_enabled)
VALUES (
    'task_overdue', 
    'Task Overdue: {task_title}', 
    '<div style="font-family: Arial, sans-serif;"><h2 style="color:red">Task Overdue</h2><p>Hi {name},</p><p>The task <strong>{task_title}</strong> is now overdue.</p><p>Due Date: {due_date}</p><p><a href="{task_url}">View Task</a></p></div>',
    1,
    'Alert: Task "{task_title}" is OVERDUE. Please check immediately.',
    1
) ON DUPLICATE KEY UPDATE email_subject=VALUES(email_subject);

-- Appointment Reminder
INSERT INTO centralized_templates (event_key, email_subject, email_body, is_email_enabled, sms_body, is_sms_enabled)
VALUES (
    'appointment_reminder', 
    'Reminder: Appointment with {client_name}', 
    '<div style="font-family: Arial, sans-serif;"><h2>Appointment Reminder</h2><p>Hi {name},</p><p>Reminder for your appointment with {client_name} at {appointment_date}.</p><p>Location: {location}</p></div>',
    1,
    'Reminder: Appointment with {client_name} is coming up at {appointment_date}.',
    1
) ON DUPLICATE KEY UPDATE email_subject=VALUES(email_subject);

-- Welcome Email
INSERT INTO centralized_templates (event_key, email_subject, email_body, is_email_enabled, sms_body, is_sms_enabled)
VALUES (
    'welcome_email', 
    'Welcome to EduCRM, {name}!', 
    '<div style="font-family: Arial, sans-serif;"><h2>Welcome!</h2><p>Hi {name},</p><p>Welcome to EduCRM. Your account has been created successfully.</p><p>Username: {email}</p><p><a href="{login_url}">Login Here</a></p></div>',
    1,
    'Welcome to EduCRM, {name}! Check your email for login details.',
    0
) ON DUPLICATE KEY UPDATE email_subject=VALUES(email_subject);
