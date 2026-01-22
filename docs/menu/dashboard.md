# Dashboard Menu Documentation

## Overview
The Dashboard is the landing page for all users (`index.php`). It provides a role-specific high-level overview of the system, including key metrics, upcoming activities, and performance analytics.

## Features

### Global Features
- **Dynamic Welcome Banner**: Personalized greeting with the stored user name.
- **Responsive Layout**: Uses a grid layout that adapts to screen size (`stats-grid`, two-column sections).
- **Navigation Integration**: Seamless integration with the sidebar and top header via `templates/header.php`.

### Role-Specific Views

#### 1. Administrators, Counselors, & Branch Managers
- **Key Metrics Tiles**:
  - **Hot Leads**: Count of leads with 'hot' priority.
  - **Warm Leads**: Count of leads with 'warm' priority.
  - **New Inquiries**: Total count of new inquiries.
  - **Pending Tasks**: Number of incomplete tasks assigned to the user.
  - **Appointments**: Count of upcoming appointments.
  - **Visa Processing**: Number of students currently in the visa pipeline.
- **Quick Lists**:
  - **Pending Tasks**: Shows the 5 most recent pending tasks with priority badges and overdue warnings.
  - **Upcoming Appointments**: Shows the next 5 appointments with a "Today" indicator.
- **Analytics Charts**:
  - **Visa Pipeline**: Bar chart showing students at different visa stages.
  - **Inquiry Status**: Doughnut chart visualizing the distribution of inquiry statuses.
- **Financial Overview** (Admin/Branch Manager only):
  - Displays Total Revenue and Outstanding Balance.

#### 2. Teachers
- **My Assigned Classes**:
  - A table listing classes assigned to the teacher.
  - **Action**: "Today's Roster" button linking directly to the classroom view (`modules/lms/classroom.php`).

#### 3. Students
- **Student Metrics**:
  - **My Classes**: Count of enrolled classes.
  - **Visa Status**: Current stage in the visa process.
  - **Balance Due**: outstanding financial balance.
- **Performance Analytics**:
  - **Attendance**: Doughnut chart (Present, Late, Absent).
  - **Performance**: Bar chart comparing Class vs. Home performance averages.

## Suggestions for Improvement

1.  **Performance Optimization (Lazy Loading)**:
    - Currently, all dashboard data is fetched synchronously on page load. As the dataset grows (e.g., thousands of inquiries), this could slow down the initial render.
    - **Suggestion**: Implement partial loading or AJAX calls for the Chart widgets so the main page content loads instantly while heavy analytics load in the background.

2.  **Interactive Charts**:
    - The charts are currently static visualizations.
    - **Suggestion**: Make chart segments clickable. For example, clicking the "Hot Leads" segment in a chart could redirect the user to the Inquiries list filtered by "Hot" priority.

3.  **Quick Actions**:
    - While there are "View All" links, there are no direct "Create" actions on the dashboard.
    - **Suggestion**: Add a "Quick Actions" speed dial or button group (e.g., "New Inquiry", "New Task") directly on the dashboard for faster workflow entry.

4.  **Consolidated Notification Center**:
    - Notifications are in the header, but the dashboard could feature a "Recent Activity Feed" showing system-wide updates relevant to the user (e.g., "John Doe submitted an application").

5.  **Kanban Widget**:
    - The "Pending Tasks" list is a simple table.
    - **Suggestion**: Replace with a mini Kanban board widget for better visualization of work in progress.
