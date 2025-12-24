# EduCRM System Walkthrough

This guide explains how to use the newly implemented CRM, LMS, and Accounting system. All modules now support **Full CRUD** (Create, Read, Update, Delete) for streamlined management.

## 1. Getting Started
- **URL**: `http://localhost/CRM/`
- **Default Admin**: `admin@example.com` / `password`

## 2. Core Modules

### CRM (Inquiry & Conversion)
**Goal**: Capture leads and onboard them securely.
1. **Add Inquiry**: Go to **Inquiries > New Inquiry**.
2. **Convert to Student**: On the Inquiry List, click **Convert to Student**. 
   - **Data Mapping**: Fields like "Intended Country" and "Education Level" are automatically mapped to the student profile.
   - **Secure Password**: The system generates a strong random password. **Copy this immediately** to share with the student.

### User Management (Staff & Students)
**Goal**: Manage system users and staff roles.
1. **Add Staff/Direct Student**: Go to **Users > Add New User**.
2. **Assign Roles**: Select roles such as `Teacher`, `Counselor`, `Accountant`, or `Student`.
4. **Password Reset**: Admins have two options for managing user credentials:
   - **Direct Reset**: Set a new password for the user immediately.
   - **Email Reset**: Generate a secure token and "send" a reset link to the user's email.
5. **Security (Uniform Hashing)**: All passwords use high-security `Bcrypt` hashing (via PHP `PASSWORD_DEFAULT`) to ensure consistent data protection across the platform.

### Visa Tracking Workflow
**Goal**: Track the study abroad visa process.
1. **Pipeline**: Go to **Visa Tracking** to see students and their destination countries.
2. **Update Stage**: Move students through stages: `Doc Collection` → `Submission` → `Interview` → `Outcome`.
3. **Logs**: Every status change is logged in the student's communication timeline.

### LMS (Learning Management)
**Goal**: Manage classes and assignments.
1. **Create Course**: Go to **LMS > Manage Courses**.
2. **Create Class**: Go to **LMS > Manage Classes**. Assign a Teacher (from the Staff list).
3. **Classroom View**: 
   - **Enroll Students**: Select students to add them to the batch.
   - **Post Material**: Teachers upload assignments or notices.
   - **Submissions**: Students upload work; teachers grade them.

### Accounting (Fees & Payments)
**Goal**: Track student finances with integrity.
1. **Fee Structure**: Define fee types (e.g., "Visa Processing Fee").
2. **Assign Fee**: Invoice students for specific amounts.
3. **Record Payment**: Record payments via Cash/Bank. The system **validates** that payments do not exceed the total due.

## 3. Roles & Permissions (Multi-Role)
Users can now hold multiple roles simultaneously:
- **Admin**: Full system management, user creation, financial analytics.
- **Counselor**: Inquiry management and **Visa Tracking**.
- **Teacher**: Classroom management and material posting.
- **Student**: Access to materials, submissions, and financial ledger.
- **Accountant**: Focused access to fee structures and payment recording.

## 4. Next Steps
- Use the **Dashboard** widgets (Visa Pipeline, Financial Overview) for real-time monitoring.
- Ensure all staff are added via the **Users** module with correct roles.

