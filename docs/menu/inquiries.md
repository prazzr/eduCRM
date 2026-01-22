# Inquiries Menu Documentation

## Overview
The Inquiries module (`modules/inquiries/list.php`) is the central hub for Lead Management. It features advanced lead scoring, prioritization, and workflow tools to convert prospects into students.

## Features

### 1. Lead Prioritization & Scoring
- **Lead Scoring**: Displays a calculated score (0-100) for each inquiry, helping counselors focus on high-potential leads.
- **Priority Pills**:
  - **Hot (🔥)**: Urgent/High score.
  - **Warm (☀️)**: Medium potential.
  - **Cold (❄️)**: Low potential/Just started.
- **Visual Filtering**: The top bar features colorful interactive "pills" that filter the list by priority and show live counts (e.g., "Hot: 5").

### 2. Status Management
- **Workflow Stages**: New -> Contacted -> Converted -> Closed.
- **Conversion**: A dedicated "Convert to Student" action button moves the inquiry to the Student module.

### 3. Bulk Communication & Actions
- **Bulk Email**: Select multiple inquiries -> Compose Email -> Send immediately to all selected recipients.
- **Mass Assignment**: Admins can reassign a batch of inquiries to a specific counselor.
- **Bulk Updates**: fast updates for Priority and Status.

### 4. Advanced Search
- **Smart Search**: Filters by Name, Email, or Phone number.
- **Results Dropdown**: Shows a rich preview of matches with their priority status and assigned counselor before submitting.

## Suggestions for Improvement

1.  **Kanban Board View**:
    - Inquiries move through stages (New -> Contacted -> etc.).
    - **Suggestion**: Implement a Drag-and-Drop Kanban Board. This is industry standard for CRMs and significantly improves UX for moving leads through the pipeline.

2.  **Import/Export Capabilities**:
    - **Suggestion**: Add a "Import CSV" button to the list view to allow bulk uploading of leads from excel sheets. Also, an "Export" function for reporting.

3.  **Activity Log / Last Contacted**:
    - It's hard to tell *when* a lead was last spoken to from the list.
    - **Suggestion**: Add a "Last Contacted" column (e.g., "2 days ago") to highlight neglected leads.

4.  **Source Tracking**:
    - **Suggestion**: Display the "Source" (e.g., Website, Walk-in, Referral) in the list view to help analyze which channels brings the best leads.
