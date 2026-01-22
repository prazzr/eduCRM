# Tasks Menu Documentation

## Overview
The Tasks module (`modules/tasks/list.php`) allows users to manage their daily work items. It supports creating, editing, and tracking tasks related to various entities (Inquiries, Students, Applications, Classes).

## Features

### 1. Task Management
- **List View**: Displays tasks in a responsive table.
- **Columns**: Title, Priority, Status, Related Entity (e.g., Student #123), Due Date, and Actions.
- **Visual Indicators**:
  - **Priority Badges**: Color-coded (Red for Urgent, App-Orange for High, etc.).
  - **Overdue Warning**: Highlights overdue dates in red with an explicit "Overdue!" label.
  - **Status Badges**: Visual distinction between Pending, In Progress, and Completed.

### 2. Filtering & Search
- **Server-Side Filters**:
  - Status (Pending, In Progress, Completed, Cancelled)
  - Priority (Urgent, High, Medium, Low)
  - Entity Type (Inquiry, Student, Application, Class, General)
  - Assigned To (Admin only selector)
- **Client-Side Quick Search (Alpine.js)**:
  - An instant search bar that filters visible results by title, priority, or status without reloading the page.
  - Includes a results dropdown with keyboard navigation support.

### 3. Bulk Actions
- **Selection**: Checkbox based multi-selection with "Select All" capability.
- **Actions Available**:
  - **Assign To**: Reassign multiple tasks to a specific user (Admin only).
  - **Change Priority**: Bulk update priority.
  - **Change Status**: Bulk update status (e.g., mark 5 tasks as Completed at once).
  - **Delete**: Bulk delete (Admin only).
- **UI Interaction**: The bulk action toolbar usage is context-aware, appearing only when items are selected. It uses a custom Modal system for confirmation.

### 4. Role-Based Access Control
- **Admins**: Can see ALL tasks and filter by any user. Can assign tasks to others.
- **Standard Users**: Can only see tasks assigned to themselves.

## Suggestions for Improvement

1.  **Pagination is Missing**:
    - The current implementation fetches `getAllTasks` or `getUserTasks`. If a user has thousands of closed tasks, this page will become very slow and heavy.
    - **Suggestion**: Implement server-side pagination (e.g., 20 or 50 items per page).

2.  **Kanban Board View**:
    - Tasks are naturally suited for a board layout (ToDo -> In Progress -> Done).
    - **Suggestion**: Add a toggle to switch between "List View" and "Board View" to allow visual drag-and-drop status updates.

3.  **Date Range Filtering**:
    - Current filters cover status and priority well, but there is no specific "Due Date Range" filter (e.g., "Next 7 Days").
    - **Suggestion**: Add a date picker range filter to help users focus on immediate deadlines.

4.  **Recurring Tasks**:
    - There is no evidence of recurring task logic (e.g., "Follow up every Monday").
    - **Suggestion**: Add a "Recurrence" field to the Add/Edit Task form for periodic reminders.

5.  **related Entity Linking**:
    - The "Related To" column shows text like "student #123".
    - **Suggestion**: Make these hyperlinks (e.g., clicking "Student #123" should go to `modules/students/profile.php?id=123`).
