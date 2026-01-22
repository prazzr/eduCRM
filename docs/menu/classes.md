# Classes Menu Documentation

## Overview
The Classes module (`modules/lms/classes.php`) is the operational heart of the Learning Management System (LMS). It defines specific batches or sections of a general Course.

## Features

### 1. Class Management
- **Create Class**: Admin/Teachers can create new classes.
- **Smart Assignment**: Uses searchable dropdowns to link a class to a specific **Course** (e.g., IELTS) and **Teacher**.
- **Role-Based View**:
  - **Admins**: See all classes.
  - **Teachers**: See only classes assigned to them.
  - **Students**: See only classes they are actively enrolled in.

### 2. Classroom Access
- **Manage Classroom**: The "Action" button leads to `classroom.php`, which likely handles daily operations like Attendance taking and Grade recording (not detailed in this list view but implied).

## Suggestions for Improvement

1.  **Schedule & Timings**:
    - Currently, only "Start Date" is captured.
    - **Suggestion**: Add fields for Days (Mon/Wed/Fri) and Time (10:00 AM - 11:30 AM) to generate a proper timetable.

2.  **Enrollment Capacity**:
    - **Suggestion**: Add a "Max Capacity" field (e.g., 20 students) to prevent overbooking a physical room.

3.  **Status**:
    - Classes eventually finish.
    - **Suggestion**: Add a status field (Upcoming, Active, Completed, Archived) to filter out old batches.

4.  **Student Count**:
    - **Suggestion**: Display the number of currently enrolled students in the list view (e.g., "12/20 Enrolled").
