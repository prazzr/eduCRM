# Automation Menu Documentation

## Overview
The Automation Workflow module (`modules/automate/workflows.php`) is the brain of the CRM's proactive features. It allows admins to define "If This Then That" rules for automated communications.

## Features

### 1. Workflow Builder
- **Triggers**: Define events that start the flow (e.g., "New Inquiry Created", "Task Overdue", "Birthday").
- **Conditions**: Logic builder to filter execution (e.g., "Only if Country equals 'USA'" AND "Priority equals 'Hot'").
- **Channels**: Supports Email, SMS, and WhatsApp output.
- **Timing**:
  - **Immediate**: Fires instantly.
  - **Delayed**: Fires X minutes/days after trigger.
  - **Scheduled**: Fires before/after a specific date field (e.g., "3 days before Appointment Date").

### 2. Template Integration
- **Dynamic Selection**: Links triggers to Templates created in the Templates module.
- **Variable Mapping**: Suggests variables available for the selected trigger (e.g., a "Task" trigger offers `{task_title}`).

## Suggestions for Improvement

1.  **Visual Flow Builder**:
    - **Suggestion**: Replace the form-based builder with a node-based visual canvas (like Zapier's editor) for complex multi-step workflows.

2.  **Test Execution**:
    - **Suggestion**: Add a "Simulate" button to test a workflow against a dummy record to ensure conditions and templates work as expected.

3.  **Execution Logs**:
    - **Suggestion**: A "History" tab to see a log of every time a workflow fired, who it was sent to, and if it succeeded or failed.

4.  **Pre-built Recipes**:
    - **Suggestion**: Library of common workflows (e.g., "Webinar Reminder", "Lead Nurture Sequence") that can be installed with one click.
