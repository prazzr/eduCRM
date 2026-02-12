# Students Menu Documentation

## Overview
The Students module (`modules/students/list.php`) manages the directory of enrolled students/clients. It serves as the gateway to detailed student profiles and academic history.

## Features

### 1. Student Directory
- **List View**: Displays students with their Avatar, Name, ID, Email, Phone, Country, and Education Level.
- **Teacher View**: Teachers only see students enrolled in *their* specific classes, ensuring data privacy.
- **Admin/Counselor View**: Full access to the entire student database.

### 2. Search & Discovery
- **Quick Search**: Filters by Name, Email, or Phone.
- **Details**: Shows Country and Education Level at a glance to help distinguish students with similar names.

### 3. Profile Management
- **View Profile**: The primary action links to `profile.php`, which acts as a comprehensive dashboard for that specific student (likely showing classes, payments, visa status, etc.).
- **Edit/Delete**: Standard CRUD operations.

## Suggestions for Improvement

1.  **Missing Bulk Actions**:
    - Unlike Inquiries and Tasks, the Student list **lacks a Bulk Action toolbar**.
    - **Suggestion**: Add bulk actions for:
        - **Email**: "Send Announcement to Selected Students".
        - **Enrollment**: "Add Selected Students to Class".

2.  **Advanced Filtering**:
    - Currently, you can only search by text.
    - **Suggestion**: Add dropdown filters for:
        - **Intake Year/Month**: "Show Jan 2024 Intake".
        - **Country/Destination**: "Show all students going to Canada".
        - **Status**: active vs Alumni.

3.  **Student ID Cards**:
    - **Suggestion**: Add a feature to generate/print Student ID cards directly from the list or profile action menu.

4.  **Financial Status Indicator**:
    - **Suggestion**: Add a visual indicator (e.g., a red dot) if the student has overdue fees directly in the list view.
