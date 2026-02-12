# Messaging Menu Documentation

## Overview
The Messaging module (`modules/messaging/gateways.php`) handles the technical configuration of communication channels. It serves as the bridge between EduCRM and external providers like Twilio, WhatsApp, and SMS Gateways.

## Features

### 1. Gateway Configuration
- **Multi-Provider Support**:
  - **SMS**: Twilio, SMPP (Enterprise), Gammu (Physical Modem).
  - **WhatsApp**: Twilio API, Meta Business API, 360Dialog.
  - **Push**: ntfy (Self-hosted).
- **Dynamic UI**: The configuration form adapts fields based on the selected provider (e.g., hiding SMPP Host when Twilio is selected).

### 2. Operational Control
- **Usage Limits**: Set "Daily Limits" and "Cost Per Message" to control budget.
- **Status Toggles**: Instantly Enable/Disable specific gateways.
- **Default Routing**: Flag a gateway as the "Default" for its type.

### 3. Diagnostics
- **Live Testing**: A built-in "Test Gateway" modal allows sending real messages to a test number to verify configuration.
- **Log Access**: Direct link to transaction logs (`gateway_logs.php`) for debugging failures.

## Suggestions for Improvement

1.  **Failover Logic Visualization**:
    - While "Priority" sort order exists, an explicit "Failover Chain" visual would be better (e.g., "Try WhatsApp -> If Fail, Try SMS").
    - **Suggestion**: A drag-and-drop ordering UI for failover strategies.

2.  **Live Balance Checking**:
    - **Suggestion**: For providers like Twilio, integrate the API to show the current account credit balance on the dashboard card.

3.  **Campaign Management**:
    - The menu links to Gateways (Technical), but users often want to send "Campaigns" (Marketing).
    - **Suggestion**: Ensure the "Campaigns" submenu is clearly exposed to standard users, separating technical configuration from marketing execution.

4.  **Two-Way Chat**:
    - **Suggestion**: If using WhatsApp/SMS, an "Inbox" feature to read and reply to incoming student messages directly in the CRM is a high-value addition.
