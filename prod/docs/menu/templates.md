# Templates Menu Documentation

## Overview
The Templates module (`modules/templates/index.php`) allows administrators to create and manage standardized message templates for Emails, SMS, and WhatsApp notifications. This ensures consistent communication across the organization.

## Features

### 1. Template Management
- **Unified Grid View**: Displays all templates in a visual card layout.
- **Metadata**: Shows Template Name, Key (system identifier), Subject line, and Status (Active/Inactive).
- **Variable Preview**: Automatically parses and displays available variables (e.g., `{student_name}`, `{due_date}`) for easy reference.

### 2. Live Preview
- **Interactive Modal**: Users can click "Preview" to see the rendered template.
- **Sample Data Injection**: The system actively replaces variables with real-world sample data (e.g., replacing `{name}` with "John Doe") to verify formatting before saving.

### 3. Channel Agnostic
- Designed to handle various channel types, though primarily focused on Email content structure (Subject/Body).

## Suggestions for Improvement

1.  **Rich Text Editor Integration**:
    - **Suggestion**: Ensure the "Add/Edit" page uses a WYSIWYG editor (like TinyMCE) for creating beautiful HTML emails without coding.

2.  **Category Filtering**:
    - As the number of templates grows, finding "Visa Approval" vs "Payment Reminder" can get hard.
    - **Suggestion**: Add tabs or tags to filter by Category (Marketing, Transactional, Academic).

3.  **Clone Functionality**:
    - **Suggestion**: Add a "Duplicate" button. Often you want to create a slight variation of an existing template (e.g., "Welcome Email - Fall 2024" vs "Welcome Email - Spring 2025").

4.  **Test Send**:
    - The preview is visual only.
    - **Suggestion**: Add a "Send Test Email" button to send the rendered template to the admin's inbox.
