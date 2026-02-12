# Courses Menu Documentation

## Overview
The Courses module (`modules/lms/courses.php`) allows administrators to define the educational products offered by the consultancy. It acts as the master catalog from which individual Classes are spawned.

## Features

### 1. Course Catalog
- **Master List**: Defines the core subjects (e.g., "IELTS Academic", "PTE", "General English").
- **CRUD Operations**: Admins can Add, Edit, and Delete courses.
- **Validation**: Prevents deleting a course if it has active classes (via confirmation warning).

### 2. Simple Interface
- Designed for quick setup with just Name and Description fields.

## Suggestions for Improvement

1.  **Standardized Fees**:
    - **Suggestion**: Add a "Base Price" field. This would allow the Accounting module to auto-generated invoices when a student enrolls in a course.

2.  **Syllabus & Materials**:
    - **Suggestion**: Allow uploading a "Course Outline" PDF or standard learning materials that are automatically shared with all students enrolled in any class of this course.

3.  **Duration**:
    - **Suggestion**: Add a "Standard Duration" (e.g., 6 weeks).

4.  **Prerequisites**:
    - **Suggestion**: Define if a course requires another course to be completed first (e.g., "Intermediate English" requires "Basic English").
