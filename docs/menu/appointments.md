# Appointments Menu Documentation

## Overview
The Appointments module (`modules/appointments/list.php` & `calendar.php`) facilitates scheduling and tracking meetings with students or inquiries. It supports both physical locations and online meetings.

## Features

### 1. View Modes
- **List View**: A chronological table of appointments.
- **Calendar View**: A visual calendar interface (referenced via button) to see monthly/weekly schedules.

### 2. Appointment Management
- **Status Tracking**:
  - **Scheduled**: Default state (Blue).
  - **Completed**: Meeting finished (Green).
  - **Cancelled**: Meeting off (Red).
  - **No Show**: Client didn't appear (Orange).
- **Date Filtering**: Users can filter appointments by a specific date range.
- **Meeting Links**:
  - If a meeting link is provided, a "Join Meeting" button appears directly in the list for one-click access.

### 3. Role-Based Access
- **Admins**: Can view appointments for ALL counselors and filter by specific counselor.
- **Counselors**: Can only view and manage their own assigned appointments.

### 4. Bulk Actions
- **Mass Status Update**: Mark multiple meetings as Completed/Cancelled at once.
- **Bulk Reschedule**: Shift multiple appointments forward or backward by a set number of days (e.g., "Move all today's meetings to tomorrow" by entering `1`).
- **Bulk Delete**: Admin only.

### 5. Quick Search
- **Client-Side Search**: Instantly filter the list by Title or Client Name using Alpine.js.

## Suggestions for Improvement

1.  **Calendar Sync Integration**:
    - Currently, appointments rely on the internal system.
    - **Suggestion**: Integrate with Google Calendar or Outlook API so appointments added here appear on the counselor's phone/external calendar.

2.  **Conflict Detection**:
    - The documentation/list doesn't explicitly show conflict warnings.
    - **Suggestion**: Ensure the "Add Appointment" flow checks for double-booking of counselors or rooms.

3.  **Automated Reminders**:
    - **Suggestion**: Implement automated Email or WhatsApp reminders (via the Messaging module) sent 24 hours and 1 hour before the meeting to reduce "No Show" rates.

4.  **Client Hyperlinks**:
    - The "Client" column displays the name as text.
    - **Suggestion**: Link the client name to their respective Profile or Inquiry Detail page for quick context access before the meeting.

5.  **Meeting Notes**:
    - **Suggestion**: Add a "Quick Note" feature in the list view to log the outcome of a meeting without opening the full edit page.
