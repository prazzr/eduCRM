# Visa Tracking Menu Documentation

## Overview
The Visa Tracking module (`modules/visa/list.php`) is a critical component for managing the complex visa application process. It introduces Service Level Agreement (SLA) tracking and stage-based workflows.

## Features

### 1. Pipeline Overview
- **Summary Cards**: A row of stats cards at the top showing the volume of applications in each stage (e.g., "Submission: 12", "Approved: 45").
- **Overdue Alert**: A dedicated Red card highlights the number of applications that have breached their expected completion date.

### 2. Workflow Management
- **Stages**: Tracks progress through customized visa stages (e.g., Document Collection, Submission, Interview).
- **SLA Tracking**:
  - **Expected Date**: Each application has a due date.
  - **Overdue Logic**: If `Current Date > Due Date` and status is not final, the row turns red and shows an "OVERDUE" badge.
- **Priority**:
  - **Critical**: Red background/text.
  - **Urgent**: Yellow background.
  - **Normal**: Grey.

### 3. Student Search
- **Smart Filter**: Alpine.js enabled search bar filtering by Name, Country, or current Stage.

## Suggestions for Improvement

1.  **Kanban Board**:
    - Visa processing is a linear workflow perfectly suited for a Kanban board.
    - **Suggestion**: Create a `kanban.php` view where counselors can drag students from "Submission" to "Interview" columns.

2.  **Automated Notifications**:
    - **Suggestion**: The "Overdue" logic is passive (you have to look at the page). Implement active email/system notifications when an application becomes overdue.

3.  **Checklists**:
    - Visa applications require specific documents.
    - **Suggestion**: Show a "Document Completion" progress bar (e.g., "3/5 Docs") in the list view.

4.  ** Embassy/VFS Appointment**:
    - **Suggestion**: Add a specific field to track the actual date of the embassy interview or biometrics appointment.
