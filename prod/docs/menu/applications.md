# Applications Menu Documentation

## Overview
The Applications module (`modules/applications/tracker.php`) is a specialized tracker for external university applications. It allows counselors to monitor the status of student applications to various institutions.

## Features

### 1. Application Management
- **Add Application**: A modal form allows quick entry of a new application. Includes a **smart student search** (Alpine.js) to link applications to existing students.
- **Fields**: University Name, Course Name, Country (USA, UK, Australia, etc.).

### 2. Status Tracking
- **Lifecycle Stages**:
  - **Applied**: Initial stage.
  - **Offer Received**: Conditional/Unconditional offer.
  - **Offer Accepted**: Student accepted the offer.
  - **Visa Lodged**: Transition to Visa stage.
  - **Visa Granted**: Successful outcome.
  - **Rejected**: Unsuccessful application.
- **Visuals**: Color-coded status badges for quick scanning (Green for success, Red for rejection).

### 3. Quick Updates
- **Update Modal**: Clicking "Update" opens an overlay to change status and append notes without leaving the page.
- **Logging**: All status changes are logged to the `student_logs` table for audit trails.

## Suggestions for Improvement

1.  **Document Attachment**:
    - Currently, the "Docs" button links to the generic student document folder.
    - **Suggestion**: Allow uploading specific files (e.g., "Offer Letter.pdf") *directly* to the application record.

2.  **Deadline Tracking**:
    - There is no field for "Application Deadline" or "Offer Expiry Date".
    - **Suggestion**: Add date fields to track critical deadlines.

3.  **Offer Conditions**:
    - Offers often have conditions (e.g., "IELTS 6.5 required").
    - **Suggestion**: Add a text field for "Conditions" to the "Offer Received" status.

4.  **University Database**:
    - "University Name" is a free text field.
    - **Suggestion**: Maintain a database of Partner Universities for a dropdown selection to ensure data consistency.
