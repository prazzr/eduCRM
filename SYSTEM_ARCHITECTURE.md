# EduCRM System Technical Architecture (Post-Optimization)

This document summarizes the technical state of the EduCRM system following the Senior Developer audit and optimization phase. It serves as an update to the requirements initially outlined in `Technical.pdf`.

## 1. Core Architecture
- **Language**: PHP 8.x
- **Database**: MySQL 8.x
- **Framework**: Custom Vanilla PHP with PDO (Security focused)

## 2. Advanced Multi-Role System
The system has moved from a flat role column to a relational RBAC (Role-Based Access Control) architecture.
- **`roles` Table**: Stores distinct roles (Admin, Counselor, Teacher, Student, Accountant).
- **`user_roles` Table**: Link table supporting **multiple roles per user**.
- **Auth Logic**: Managed in `config.php` via `hasRole()` which checks the session-cached role array.

## 3. Data Integrity & Security
- **Password Management**: 
    - Implemented `generateSecurePassword()` using `random_int` for high-entropy initial credentials.
    - **Uniform Hashing**: All authentication points (Add, Convert, Reset) utilize `PASSWORD_DEFAULT` (Bcrypt) for consistent encryption.
    - **Token-Based Reset**: Implemented `reset_token` and `token_expiry` for secure, time-limited email reset workflows.
- **Financial Validation**: Server-side checks in `student_ledger.php` ensure payment amounts cannot exceed the outstanding invoice balance.
- **PDO Prepared Statements**: Used throughout for comprehensive SQL Injection prevention.

## 4. Feature Modules
### 4.1 Visa Tracking Workflow
- **State Machine**: Tracks students through `Doc Collection` → `Submission` → `Interview` → `Outcome`.
- **Database**: `visa_workflows` table tracks country-specific progress.
- **Hooks**: Integration with `student_logs` for automated audit trails.

### 4.2 CRM & Onboarding
- **Automated Mapping**: Converting an inquiry to a student validates and transfers `intended_country` and `education_level` to the user profile automatically.
- **Status Sync**: Inquiries are marked as `converted` once a valid user account is provisioned.

## 5. Data Model (Key Entities)
| Entity | Description |
| :--- | :--- |
| `users` | Primary user table (extended with student-specific fields). |
| `inquiries` | Lead capture data. |
| `visa_workflows` | Study abroad specific pipeline. |
| `student_fees` | Invoice-based accounting system. |
| `payments` | Transactional records linked to fees. |

---
**Document Status**: Finalized (Senior Review Complete)
