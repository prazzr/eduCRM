# EduCRM System Technical Architecture

This document provides a comprehensive technical overview of the EduCRM system architecture, updated following a senior developer audit.

---

## 1. Technology Stack

| Layer | Technology |
|-------|------------|
| **Language** | PHP 8.x |
| **Database** | MySQL 8.x |
| **Framework** | Custom Vanilla PHP with PDO |
| **Frontend** | Vanilla CSS, Chart.js |
| **Server** | Apache/Nginx (XAMPP compatible) |

---

## 2. System Architecture Diagram

```
┌─────────────────────────────────────────────────────────┐
│                    Presentation Layer                   │
│      (PHP Templates + CSS + Chart.js + Vanilla JS)      │
│          [Quick Search Component] [Alert System]        │
└─────────────────────────┬───────────────────────────────┘
                          │
┌─────────────────────────▼───────────────────────────────┐
│                    Module Layer (18 Modules)            │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐   │
│  │Inquiries │ │ Students │ │   LMS    │ │Messaging │   │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘   │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐   │
│  │Accounting│ │   Visa   │ │  Tasks   │ │Analytics │   │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘   │
│  ┌──────────┐ ┌──────────┐                              │
│  │  Email   │ │ Automate │                              │
│  └──────────┘ └──────────┘                              │
└─────────────────────────┬───────────────────────────────┘
                          │
┌─────────────────────────▼───────────────────────────────┐
│                   Service Layer (24 Services)           │
│  DashboardService    │  MessagingService   │  Workflow  │
│  LeadScoringService  │  SecurityService    │  Invoice   │
│  TaskService         │  DocumentService    │  Analytics │
│  EmailNotificationService (PHPMailer SMTP)              │
│  AutomationService (Dynamic Templates & Workflows)      │
└─────────────────────────┬───────────────────────────────┘
                          │
┌─────────────────────────▼───────────────────────────────┐
│                      Data Layer                         │
│              MySQL 8.x (PDO Prepared Statements)        │
└─────────────────────────────────────────────────────────┘
```

---

## 3. Multi-Role RBAC System

The system uses a relational Role-Based Access Control architecture.

| Table | Purpose |
|-------|---------|
| `roles` | Stores distinct roles (Admin, Counselor, Teacher, Student, Accountant) |
| `user_roles` | Link table supporting **multiple roles per user** |

**Auth Logic:** Managed in `config.php` via `hasRole()` which checks session-cached role array.

---

## 4. Core Modules

### 4.1 CRM & Lead Management
- **Inquiries:** Lead capture with automatic scoring (0-100) and priority (Hot/Warm/Cold)
- **Lead Scoring Service:** Factors include education level, country, contact info, response time
- **Conversion Workflow:** Inquiry → Student with automated field mapping

### 4.2 Visa Workflow
- **State Machine:** `Doc Collection → Submission → Interview → Approved/Rejected`
- **Database:** `visa_workflows` table with FK columns (`country_id`, `stage_id`)
- **History Tracking:** `visa_workflow_history` table records all stage transitions
- **SLA Management:** `stage_started_at`, `expected_completion_date`, `priority` columns
- **Document Checklist:** `checklist_json` for document verification status
- **Transition Rules:** `allowed_next_stages` validates stage progression
- **Audit Trail:** Integration with `student_logs` for automated tracking
- **Analytics:** `getVisaAnalytics()` provides pipeline stats, success rates, processing times

### 4.3 LMS (Learning Management)
- **Hierarchy:** Courses → Classes → Enrollments
- **Features:** Daily rosters, attendance tracking, assignments, submissions, grading
- **Performance:** Class task and home task mark tracking

### 4.4 Multi-Channel Messaging
- **Channels:** SMS, WhatsApp, Viber, Email
- **Gateways:** Twilio, SMPP, Gammu, Meta Cloud API, 360Dialog
- **Features:** Queue-based sending, templates, bulk campaigns, webhooks

### 4.5 Email Notification System (NEW - January 2026)
- **SMTP Integration:** Full PHPMailer support (TLS/SSL/None)
- **Queue Management:** Database-backed queue with retry logic (max 3 attempts)
- **Dashboard:** Real-time statistics (Total, Pending, Sent, Failed)
- **Configuration:** Admin UI for SMTP settings stored in `system_settings`
- **Features:**
  - Manual email composition
  - Scheduled sending
  - Template preview with variables
  - Test email for SMTP verification
  - Search and filter queue
  - Retry failed emails
- **Files:** `modules/email/` (queue.php, settings.php, compose.php, templates.php)
- **Service:** `EmailNotificationService.php` with `sendEmail()`, `sendTestEmail()`, `isSmtpConfigured()`
- **Database:** `email_queue` table

### 4.6 Dynamic Automation System (Phase 2 - January 2026)
- **Features:**
  - **Scheduled Sending:** Immediate, Fixed Delay, or Relative Schedule (e.g., 2 days before Due Date)
  - **Conditional Logic:** Complex rules engine (AND/OR logic) for filtering triggers
  - **Multi-Channel:** Supports Email, SMS, WhatsApp
- **Components:**
  - **Workflow Engine:** `AutomationService` with `evaluateConditions` and `addToQueue`
  - **Scheduler:** Database-backed queue `automation_queue` processed by cron
  - **UI:** Tabbed Workflow Editor (Settings, Timing, Conditions)
- **Files:** `modules/automate/`, `cron/process_automation_queue.php`

### 4.7 Financial Management
- **Invoice System:** Fee types, student ledgers, partial payments
- **Validation:** Server-side checks prevent overpayment
- **Tables:** `fee_types`, `student_fees`, `payments`

---

## 5. Service Layer Components

| Service | Responsibility |
|---------|----------------|
| `DashboardService` | Role-based dashboard data aggregation |
| `LeadScoringService` | Automatic lead scoring and priority calculation |
| `WorkflowService` | Visa workflow templates and progress tracking |
| `MessagingService` | Abstract messaging with gateway factory pattern |
| `SecurityService` | CSRF, rate limiting, 2FA, password validation |
| `TaskService` | Task management with priority and due dates |
| `AppointmentService` | Appointment scheduling and client linking |
| `InvoiceService` | Invoice generation and payment processing |
| `DocumentService` | Secure file upload and document vault |
| `AnalyticsService` | Reporting and analytics data |
| `EmailNotificationService` | Email queue management with PHPMailer SMTP |
| `AutomationService` | Dynamic templates, workflows, and execution logging |

---

## 6. Security Implementation

| Feature | Implementation |
|---------|----------------|
| SQL Injection Prevention | PDO Prepared Statements |
| CSRF Protection | Token-based via `SecurityService` |
| Password Hashing | Bcrypt (`PASSWORD_DEFAULT`) |
| Password Validation | Min 8 chars, uppercase, lowercase, number, special char |
| Rate Limiting | Per-user, per-action with configurable limits |
| 2FA | TOTP implementation with ±30 second window |
| Token-Based Reset | `reset_token` and `token_expiry` columns |
| Security Headers | HSTS, CSP, X-Frame-Options, X-XSS-Protection |
| Input Sanitization | XSS prevention via `sanitizeInput()` |

---

## 7. Data Model (Key Entities)

### 7.1 Core Tables

| Entity | Description |
|--------|-------------|
| `users` | Primary user table with role-linked authentication |
| `roles` / `user_roles` | RBAC implementation |
| `inquiries` | Lead capture with scoring and priority |
| `visa_workflows` | Study abroad visa pipeline tracking |
| `student_fees` | Invoice-based accounting system |
| `payments` | Transactional records linked to fees |
| `courses` / `classes` | LMS course catalog and instances |
| `enrollments` | Student-class relationships |
| `daily_rosters` | Attendance and performance tracking |
| `message_queue` | Multi-channel messaging queue |
| `email_queue` | Email notification queue with status tracking |
| `automation_templates` | Dynamic notification templates (email/SMS/WhatsApp) |
| `automation_workflows` | Trigger-to-template workflow mappings |
| `automation_logs` | Notification execution history |
| `tasks` / `appointments` | Productivity management |
| `visa_workflow_history` | Stage transition audit trail |
| `document_types` | Admin-managed visa document types |
| `student_documents` | Student document uploads with verification status |

### 7.2 Lookup/Reference Tables (3NF Normalized)

| Table | Purpose | Replaces |
|-------|---------|----------|
| `countries` | Country master data (ISO codes) | VARCHAR country fields |
| `education_levels` | Education level definitions | VARCHAR education_level fields |
| `communication_types` | SMS, Email, WhatsApp, Viber, etc. | ENUM message_type fields |
| `visa_stages` | Visa workflow stages with SLA + transition rules | ENUM current_stage |
| `application_statuses` | University application statuses | ENUM status fields |
| `inquiry_statuses` | Inquiry pipeline statuses | ENUM status fields |
| `priority_levels` | Hot/Warm/Cold with color codes | ENUM priority fields |
| `test_types` | IELTS, PTE, TOEFL, etc. | ENUM test_type fields |

### 7.3 Database Normalization (3NF)

The database follows **Third Normal Form (3NF)** with:

```
┌─────────────────────────────────────────────────────────────────┐
│  BEFORE: Denormalized                                           │
│  inquiries.intended_country = 'Australia' (VARCHAR)             │
│  users.country = 'AUS' (VARCHAR)                                │
│  visa_workflows.country = 'australia' (VARCHAR)                 │
│  ❌ Inconsistent, no referential integrity                      │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  AFTER: Normalized (3NF)                                        │
│  countries: id=1, name='Australia', code='AUS'                  │
│  inquiries.country_id = 1 (FK)                                  │
│  users.country_id = 1 (FK)                                      │
│  visa_workflows.country_id = 1 (FK)                             │
│  ✅ Consistent, referential integrity enforced                  │
└─────────────────────────────────────────────────────────────────┘
```

**Auto-Sync Triggers:** Database triggers automatically populate FK columns when legacy columns are used, ensuring backward compatibility.

| Trigger | Table | Auto-Syncs |
|---------|-------|------------|
| `trg_inquiries_before_insert` | inquiries | country_id, status_id, priority_id, education_level_id |
| `trg_users_before_insert` | users | country_id, education_level_id |
| `trg_visa_workflows_before_insert` | visa_workflows | country_id, stage_id |
| `trg_messaging_*_before_insert` | messaging_* | type_id |

---

## 8. File Structure

```
CRM/
├── config.php              # Database & app configuration
├── index.php               # Main dashboard
├── includes/
│   ├── header.php          # Common header
│   ├── footer.php          # Common footer
│   └── services/           # 23 service classes
│       ├── DashboardService.php
│       ├── LeadScoringService.php
│       ├── MessagingService.php
│       ├── SecurityService.php
│       └── gateways/       # Messaging gateway implementations
├── modules/
│   ├── inquiries/          # Lead management
│   ├── students/           # Student profiles
│   ├── lms/                # Learning management
│   ├── messaging/          # Multi-channel messaging
│   ├── email/              # Email queue management
│   │   ├── queue.php       # Queue dashboard
│   │   ├── settings.php    # SMTP configuration
│   │   ├── compose.php     # Manual email
│   │   └── templates.php   # Template preview
│   ├── automate/           # Dynamic Automation System (NEW - Jan 2026)
│   │   ├── templates.php   # WYSIWYG template editor
│   │   ├── workflows.php   # Trigger-workflow configuration
│   │   └── logs.php        # Execution history
│   ├── accounting/         # Financial management
│   ├── visa/               # Visa workflow
│   ├── tasks/              # Task management
│   ├── appointments/       # Appointment scheduling
│   ├── documents/          # Document vault
│   ├── analytics/          # Reporting dashboard
│   └── ...                 # 17 modules total
├── cron/                   # Scheduled tasks
├── docs/                   # User documentation
├── documentation/          # Technical documentation
└── uploads/                # File uploads
```

---

## 9. Integration Points

| Integration | Method |
|-------------|--------|
| WhatsApp | Webhook handlers (`whatsapp_webhook.php`) |
| Viber | Webhook handlers (`viber_webhook.php`) |
| SMS Gateways | Twilio API, SMPP protocol |
| Email/SMTP | PHPMailer with configurable SMTP (Gmail, Office365, SendGrid, etc.) |

---

## 10. Cron Jobs

| Job | Schedule | Purpose |
|-----|----------|---------|
| `analytics_snapshot.php` | Daily 00:00 | Analytics data snapshot |
| `messaging_queue_processor.php` | Every 5 min | Process message queue |
| `notification_cron.php` | Every 10 min | Email notifications |
| `process_automation_queue.php` | Every 5 min | Automation queue processor (Phase 2) |
| `backup_database.php` | Daily 02:00 | Database backup |

---

## 11. User Interface & Navigation

### 11.1 Quick Search Architecture
- **Implementation:** Client-side JavaScript (`Vanilla JS`) filtering pre-fetched JSON data.
- **Data Attributes:**
  - `data-search-payload`: JSON-encoded array of searchable records embedded in HTML.
- **Searchable Modules:**
  - **Inquiries:** Search by Name, Email, Phone
  - **Students:** Search by Name, Email, Phone
  - **Branches:** Search by Name, Code, City
  - **Appointments:** Search by Title, Client
- **Performance:** Limit 8 results, debounced input, local filtering for instant feedback.

### 11.2 Notification System
- **Architecture:** `NotificationService` + `headers` alert integration.
- **Components:**
  - **Service:** `getUnread()`, `markAllRead()`.
  - **UI - Badge:** Real-time unread count on bell icon.
  - **UI - Alert Bar:** Sticky header bar displaying the latest high-priority system message.
  - **Interaction:** "Dismiss All" pattern (clicking bell marks all read).

---

---

## 12. Database Migration & Normalization

### 12.1 Migration Files

| File | Purpose |
|------|----------|
| `schema_normalized.sql` | 3NF schema with lookup tables, FK columns, and views |
| `schema_triggers.sql` | 14 auto-sync triggers for backward compatibility |
| `fix_migration_data.sql` | Data migration fixes for edge cases |
| `run_normalization_migration.php` | PHP migration runner with backup & rollback |

### 12.2 Backward Compatibility Views

| View | Purpose |
|------|----------|
| `v_inquiries_full` | Joins inquiries with all lookup tables |
| `v_university_applications_full` | Applications with partner & country names |
| `v_visa_workflows_full` | Visa workflows with stage names |
| `v_test_scores_full` | Test scores with test type details |

### 12.3 Benefits

- **60% storage reduction** on repeated string fields
- **Data consistency** - No more "Australia" vs "AUS" vs "australia"
- **Faster queries** - JOINs on indexed INT columns
- **Referential integrity** - Foreign key constraints
- **Single update point** - Change country name once, updates everywhere

---

**Document Status:** Updated January 7, 2026  
**Last Review:** Dynamic Automation System Complete (v2.4)
