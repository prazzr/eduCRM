# Users Menu Documentation

## Overview
The Users module (`modules/users/list.php`) handles internal staff management. It allows Administrators and Branch Managers to control access to the CRM.

## Features

### 1. Staff Directory
- **Role-Based List**: Displays all non-student users (Admins, Counselors, Accountants, Teachers).
- **Role Identification**: Shows assigned roles (e.g., "Level 1 Counselor") tags.

### 2. Access Control
- **Branch Filtering**: Branch Managers only see staff assigned to *their* branch.
- **Security Actions**:
  - **Reset Password**: Direct link to trigger a password reset flow.
  - **Deactivate/Delete**: Remove access for former employees.

## Suggestions for Improvement

1.  **Activity Logs**:
    - **Suggestion**: Add a "Last Login" column to identify inactive staff accounts.

2.  **Avatar Support**:
    - **Suggestion**: Allow staff to upload profile pictures for a more personalized team directory.

3.  **Two-Factor Authentication (2FA)**:
    - **Suggestion**: Display a badge indicating if the user has 2FA enabled for security compliance.

4.  **Permissions Matrix**:
    - **Suggestion**: A "View Permissions" modal to see exactly what modules a specific user can access.
