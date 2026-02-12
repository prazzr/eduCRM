# Email System Enhancement Plan

> **Document Created:** January 6, 2026  
> **Last Updated:** January 7, 2026  
> **Status:** Phase 2 Complete - Automatic Notifications Implemented  
> **Priority:** High

## Executive Summary

The EduCRM email notification system has a solid foundation with queue management, templates, and automated notifications. The system now includes automatic email notifications for key events including user/student creation (welcome emails with credentials), visa workflow updates, and class enrollments.

---

## Table of Contents

1. [Current State Analysis](#current-state-analysis)
2. [Critical Missing Components](#critical-missing-components)
3. [Enhancement Priorities](#enhancement-priorities)
4. [Implementation Plan](#implementation-plan)
5. [Database Schema Updates](#database-schema-updates)
6. [Code Changes Required](#code-changes-required)
7. [Testing Checklist](#testing-checklist)

---

## Current State Analysis

### ✅ Fully Implemented (Completed January 7, 2026)

| Component | Status | File Location |
|-----------|--------|---------------|
| EmailNotificationService | ✅ Complete | `includes/services/EmailNotificationService.php` |
| Queue Email Function | ✅ Complete | `EmailNotificationService::queueEmail()` |
| Process Queue Function | ✅ Complete | `EmailNotificationService::processQueue()` |
| User Preference Check | ✅ Complete | `EmailNotificationService::isNotificationEnabled()` |
| Cron Job Script | ✅ Complete | `cron/notification_cron.php` |
| Email Queue UI | ✅ Complete | `modules/email/queue.php` |
| Settings UI | ✅ Complete | `modules/email/settings.php` |
| Template Preview | ✅ Complete | `modules/email/templates.php` |
| Compose Email | ✅ Complete | `modules/email/compose.php` |
| **email_queue Table** | ✅ Complete | Database table created |
| **PHPMailer Integration** | ✅ Complete | SMTP sending fully functional |
| **SMTP Configuration** | ✅ Complete | All settings used (host, port, user, pass, encryption) |
| **Test Email Function** | ✅ Complete | `EmailNotificationService::sendTestEmail()` |
| **Navigation Menu** | ✅ Complete | Email menu under Tools section |
| **Welcome Email (Users)** | ✅ Complete | `modules/users/add.php` → `sendWelcomeEmail()` |
| **Welcome Email (Students)** | ✅ Complete | `modules/students/add.php` → `sendWelcomeEmail()` |
| **Workflow Update Email** | ✅ Complete | `modules/visa/update.php` → `sendWorkflowUpdateNotification()` |
| **Enrollment Email** | ✅ Complete | `modules/lms/classroom.php` → `sendEnrollmentNotification()` |

### Email Templates Available

| Template Key | Purpose | Trigger |
|-------------|---------|---------|
| `welcome` | Welcome email with credentials | User/Student creation |
| `profile_update` | Profile change notification | Profile updates |
| `workflow_update` | Visa stage change notification | Visa workflow stage change |
| `document_update` | Document status notification | Document status change |
| `enrollment` | Class enrollment confirmation | Class enrollment |
| `task_assignment` | New task assigned | Task assignment |
| `appointment_reminder` | Appointment reminder | Scheduled reminders |
| `appointment_reminder_client` | Client appointment reminder | Scheduled reminders |
| `task_overdue` | Overdue task alert | Overdue task cron |

### ⚠️ Partially Implemented

| Component | Issue | Impact |
|-----------|-------|--------|
| Template System | Hardcoded in PHP | Cannot edit templates via UI |
| User Preferences | Table schema defined but UI missing | Users cannot manage preferences |

### ❌ Not Yet Implemented

| Component | Priority | Impact |
|-----------|----------|--------|
| `notification_preferences` table | Medium | User cannot control notifications |
| User Preferences UI | Medium | No self-service notification management |
| `email_templates` database table | Medium | Templates not customizable via UI |
| Template Editor UI | Medium | Admins must edit code to change templates |
| Unsubscribe functionality | High | Not GDPR/CAN-SPAM compliant |
| Email analytics/tracking | Low | No open/click tracking |
| Bulk email campaigns | Low | No mass mailing capability |
| Bounce handling | Low | Failed emails not tracked externally |

---

## Critical Missing Components

### 1. Database Tables

#### ✅ `email_queue` Table - COMPLETED
The email_queue table has been created and is fully functional.

#### ❌ `notification_preferences` Table - NOT YET CREATED
```sql
CREATE TABLE `notification_preferences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `email_enabled` BOOLEAN DEFAULT TRUE,
    `task_assignment` BOOLEAN DEFAULT TRUE,
    `task_overdue` BOOLEAN DEFAULT TRUE,
    `task_completed` BOOLEAN DEFAULT TRUE,
    `appointment_reminder` BOOLEAN DEFAULT TRUE,
    `appointment_created` BOOLEAN DEFAULT TRUE,
    `inquiry_assigned` BOOLEAN DEFAULT TRUE,
    `application_update` BOOLEAN DEFAULT TRUE,
    `system_announcements` BOOLEAN DEFAULT TRUE,
    `marketing_emails` BOOLEAN DEFAULT FALSE,
    `digest_frequency` ENUM('instant', 'daily', 'weekly', 'never') DEFAULT 'instant',
    `quiet_hours_start` TIME DEFAULT NULL,
    `quiet_hours_end` TIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### ❌ `email_templates` Table - NOT YET CREATED
```sql
CREATE TABLE `email_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `template_key` VARCHAR(100) NOT NULL UNIQUE,
    `name` VARCHAR(200) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `subject` VARCHAR(500) NOT NULL,
    `body_html` LONGTEXT NOT NULL,
    `body_text` TEXT DEFAULT NULL,
    `variables` JSON DEFAULT NULL,
    `category` ENUM('system', 'notification', 'marketing', 'custom') DEFAULT 'notification',
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2. ✅ SMTP Integration - COMPLETED

PHPMailer has been installed and integrated. The `EmailNotificationService` now:
- Uses all SMTP settings from the database (host, port, username, password, encryption)
- Supports TLS, SSL, or no encryption
- Provides a test email function
- Falls back to PHP mail() if SMTP not configured

**Installed Package:** `phpmailer/phpmailer` v6.12.0

**Key Methods Added:**
- `sendEmail()` - Uses PHPMailer with full SMTP support
- `sendTestEmail()` - Direct test email (bypasses queue)
- `isSmtpConfigured()` - Check if SMTP is set up
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_queue` (`queue_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`queue_id`) REFERENCES `email_queue`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2. SMTP Integration (Priority: HIGH)

Current implementation uses PHP's `mail()` function which is unreliable. Need to integrate PHPMailer for proper SMTP support.

#### Required Changes:

1. **Install PHPMailer via Composer**
   ```bash
   composer require phpmailer/phpmailer
   ```

2. **Update EmailNotificationService::sendEmail()**
   ```php
   private function sendEmail($emailData)
   {
       // Load SMTP settings
       $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
       $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
       
       $mail = new PHPMailer\PHPMailer\PHPMailer(true);
       
       try {
           // SMTP Configuration
           $mail->isSMTP();
           $mail->Host = $settings['smtp_host'] ?? 'localhost';
           $mail->Port = (int)($settings['smtp_port'] ?? 587);
           $mail->SMTPAuth = !empty($settings['smtp_username']);
           $mail->Username = $settings['smtp_username'] ?? '';
           $mail->Password = $settings['smtp_password'] ?? '';
           $mail->SMTPSecure = $settings['smtp_encryption'] ?? 'tls';
           
           // Sender
           $mail->setFrom(
               $settings['smtp_from_email'] ?? 'noreply@educrm.local',
               $settings['smtp_from_name'] ?? 'EduCRM'
           );
           
           // Recipient
           $mail->addAddress($emailData['recipient_email'], $emailData['recipient_name'] ?? '');
           
           // Content
           $mail->isHTML(true);
           $mail->Subject = $emailData['subject'];
           $mail->Body = $emailData['body'];
           $mail->AltBody = strip_tags($emailData['body']);
           
           $mail->send();
           return true;
       } catch (Exception $e) {
           error_log("Email send failed: " . $mail->ErrorInfo);
           return false;
       }
   }
   ```

---

### 3. User Notification Preferences UI (Priority: MEDIUM)

Need to create a preferences page for users to manage their email notifications.

**File to create:** `modules/users/notification_preferences.php`

Features:
- Toggle email notifications on/off globally
- Per-notification-type toggles
- Digest frequency selection (instant, daily, weekly)
- Quiet hours configuration

---

### 4. Template Editor (Priority: MEDIUM)

Allow admins to edit email templates from the UI.

**Files to create:**
- `modules/email/template_edit.php` - Edit template form
- `modules/email/template_preview.php` - Live preview with sample data

Features:
- WYSIWYG HTML editor (TinyMCE or similar)
- Variable insertion helper
- Preview with sample data
- Version history

---

### 5. Unsubscribe Functionality (Priority: HIGH - Legal Compliance)

Required for CAN-SPAM/GDPR compliance.

**Components:**
1. Add unsubscribe link to all emails
2. Create public unsubscribe page
3. Track unsubscribed users
4. Honor unsubscribe requests

**Database addition:**
```sql
ALTER TABLE `users` ADD COLUMN `email_unsubscribed` BOOLEAN DEFAULT FALSE;
ALTER TABLE `users` ADD COLUMN `unsubscribed_at` DATETIME DEFAULT NULL;
```

**File to create:** `public_unsubscribe.php`

---

### 6. Email Analytics (Priority: LOW)

Track email performance metrics.

**Features:**
- Open tracking (pixel tracking)
- Click tracking (link wrapping)
- Bounce handling
- Dashboard with metrics

**Tracking pixel endpoint:** `api/email_track.php`

---

## Enhancement Priorities (Updated)

| Priority | Component | Effort | Status |
|----------|-----------|--------|--------|
| ✅ ~~Critical~~ | ~~Create `email_queue` table~~ | ~~1 hour~~ | **DONE** |
| ✅ ~~Critical~~ | ~~PHPMailer SMTP integration~~ | ~~4 hours~~ | **DONE** |
| ✅ ~~Critical~~ | ~~Email Module UI (queue, settings, compose)~~ | ~~4 hours~~ | **DONE** |
| 🟠 High | Unsubscribe functionality | 4 hours | Not Started |
| 🟡 Medium | Create `notification_preferences` table | 1 hour | Not Started |
| 🟡 Medium | User notification preferences UI | 6 hours | Not Started |
| 🟡 Medium | Template editor | 8 hours | Not Started |
| 🟢 Low | Email analytics | 12 hours | Not Started |
| 🟢 Low | Bulk email campaigns | 16 hours | Not Started |

---

## Implementation Plan

### ✅ Phase 1: Critical Fixes - COMPLETED (January 6, 2026)

1. **Database Setup** ✅
   - Created `email_queue` table
   - Added SMTP settings to system_settings

2. **SMTP Integration** ✅
   - Installed PHPMailer v6.12.0
   - Updated `EmailNotificationService::sendEmail()` with full SMTP support
   - Added `sendTestEmail()` method for configuration testing
   - Added `isSmtpConfigured()` helper method

3. **Email Module UI** ✅
   - Created `modules/email/queue.php` - Email queue management
   - Created `modules/email/settings.php` - SMTP configuration
   - Created `modules/email/compose.php` - Manual email composition
   - Created `modules/email/templates.php` - Template preview
   - Created `modules/email/view_email.php` - AJAX email preview
   - Created `modules/email/test_email.php` - SMTP test endpoint
   - Added Email menu to NavigationService under Tools

### 🔄 Phase 2: User Features (Remaining)

### 🔄 Phase 2: User Features (Remaining)

1. **Notification Preferences Table & UI**
   - Create `notification_preferences` table
   - Create `modules/users/notification_preferences.php`
   - Add link to user profile/settings
   - Update `isNotificationEnabled()` to use database

2. **Unsubscribe System**
   - Add unsubscribe links to email templates
   - Create `public_unsubscribe.php`
   - Add `email_unsubscribed` column to users table
   - Update email sending to check unsubscribe status

### 🔄 Phase 3: Admin Features (Future)

### 🔄 Phase 3: Admin Features (Future)

1. **Template Editor**
   - Create `email_templates` table
   - Create template management UI
   - Integrate WYSIWYG editor
   - Migrate hardcoded templates to database

2. **Email Analytics** (Optional)
   - Implement open tracking
   - Implement click tracking
   - Create analytics dashboard

---

## Database Schema Updates

### Migration Script

Create file: `database/migrations/2026_01_07_email_system.sql`

```sql
-- Email System Enhancement Migration
-- Run this script to set up the complete email system

-- 1. Email Queue Table
CREATE TABLE IF NOT EXISTS `email_queue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `recipient_email` VARCHAR(255) NOT NULL,
    `recipient_name` VARCHAR(255) DEFAULT NULL,
    `subject` VARCHAR(500) NOT NULL,
    `body` LONGTEXT NOT NULL,
    `template` VARCHAR(100) DEFAULT NULL,
    `scheduled_at` DATETIME DEFAULT NULL,
    `status` ENUM('pending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
    `attempts` INT DEFAULT 0,
    `max_attempts` INT DEFAULT 3,
    `error_message` TEXT DEFAULT NULL,
    `sent_at` DATETIME DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_scheduled` (`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Notification Preferences Table
CREATE TABLE IF NOT EXISTS `notification_preferences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `email_enabled` BOOLEAN DEFAULT TRUE,
    `task_assignment` BOOLEAN DEFAULT TRUE,
    `task_overdue` BOOLEAN DEFAULT TRUE,
    `appointment_reminder` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Insert default preferences for existing users
INSERT IGNORE INTO notification_preferences (user_id)
SELECT id FROM users;

-- 4. Email Templates Table
CREATE TABLE IF NOT EXISTS `email_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `template_key` VARCHAR(100) NOT NULL UNIQUE,
    `name` VARCHAR(200) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `subject` VARCHAR(500) NOT NULL,
    `body_html` LONGTEXT NOT NULL,
    `variables` JSON DEFAULT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Add email settings to system_settings if not exists
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('smtp_host', ''),
('smtp_port', '587'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_encryption', 'tls'),
('smtp_from_email', 'noreply@educrm.local'),
('smtp_from_name', 'EduCRM'),
('email_queue_enabled', 'true');
```

---

## Code Changes Required

### Files to Modify

| File | Changes Required |
|------|------------------|
| ~~`includes/services/EmailNotificationService.php`~~ | ~~Add PHPMailer integration~~ ✅ DONE |
| `includes/header.php` | Add notification preferences link |
| `modules/email/templates.php` | Add edit functionality |

### New Files to Create

| File | Purpose | Status |
|------|---------|--------|
| ~~`modules/email/queue.php`~~ | ~~Email queue UI~~ | ✅ DONE |
| ~~`modules/email/settings.php`~~ | ~~SMTP settings~~ | ✅ DONE |
| ~~`modules/email/compose.php`~~ | ~~Manual email~~ | ✅ DONE |
| ~~`modules/email/templates.php`~~ | ~~Template preview~~ | ✅ DONE |
| ~~`modules/email/test_email.php`~~ | ~~SMTP test~~ | ✅ DONE |
| ~~`modules/email/view_email.php`~~ | ~~Email preview~~ | ✅ DONE |
| `modules/users/notification_preferences.php` | User notification settings | Not Started |
| `modules/email/template_edit.php` | Admin template editor | Not Started |
| `public_unsubscribe.php` | Public unsubscribe page | Not Started |
| `api/email_track.php` | Open/click tracking endpoint | Not Started |

---

## Testing Checklist

### Unit Tests

- [x] Email queuing works correctly
- [x] Queue processing sends emails
- [x] Failed emails are retried (max 3 attempts)
- [ ] User preferences are respected (needs notification_preferences table)
- [x] Templates render correctly with variables
- [x] SMTP settings are used properly

### Integration Tests

- [x] SMTP test email function works
- [ ] End-to-end: Task assignment → Email sent
- [ ] End-to-end: Appointment reminder via cron
- [ ] Unsubscribe flow works (not implemented)
- [x] Scheduled emails sent at correct time

### Manual Tests

- [ ] Gmail SMTP configuration
- [ ] Office 365 SMTP configuration
- [ ] SendGrid SMTP configuration
- [ ] Email renders correctly in Gmail, Outlook, Apple Mail
- [ ] Mobile email rendering

---

## Appendix: Quick Start

To get the email system working immediately, run these SQL commands:

```sql
-- Minimum required table for email queue to work
CREATE TABLE IF NOT EXISTS `email_queue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `recipient_email` VARCHAR(255) NOT NULL,
    `recipient_name` VARCHAR(255) DEFAULT NULL,
    `subject` VARCHAR(500) NOT NULL,
    `body` LONGTEXT NOT NULL,
    `template` VARCHAR(100) DEFAULT NULL,
    `scheduled_at` DATETIME DEFAULT NULL,
    `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    `attempts` INT DEFAULT 0,
    `error_message` TEXT DEFAULT NULL,
    `sent_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notification preferences
CREATE TABLE IF NOT EXISTS `notification_preferences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `email_enabled` BOOLEAN DEFAULT TRUE,
    `task_assignment` BOOLEAN DEFAULT TRUE,
    `task_overdue` BOOLEAN DEFAULT TRUE,
    `appointment_reminder` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Then set up a cron job (every 5 minutes):
```bash
*/5 * * * * /usr/bin/php /path/to/CRM/cron/notification_cron.php >> /var/log/educrm_email.log 2>&1
```

Or for Windows Task Scheduler:
```
C:\xampp\php\php.exe C:\xampp\htdocs\CRM\cron\notification_cron.php
```

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-01-06 | System | Initial document creation |
| 1.1 | 2026-01-06 | System | Updated status: email_queue table created, PHPMailer installed, SMTP integration complete, Email module UI complete |
