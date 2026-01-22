# Branches Menu Documentation

## Overview
The Branch Management module (`modules/branches/list.php`) supports the multi-location nature of educational consultancies. It allows the HQ to monitor and manage satellite offices.

## Features

### 1. Branch Network
- **List View**: Displays all offices with their specific code (e.g., "SYD-01") and Manager.
- **Headquarters Indicator**: Visual star badge for the main office.
- **Status toggling**: Active/Inactive switch for opening/closing branches.

### 2. Data Segmentation
- **Global Filter**: The "View Data" action activates a session-wide filter. When active, **ALL** other modules (Students, Inquiries, Reports) show data *only* for that selected branch.
- **Quick Stats**: Shows "User Count" and "Inquiry Volume" per branch directly in the list.

## Suggestions for Improvement

1.  **Branch Dashboard**:
    - **Suggestion**: Clicking a branch name could open a "Branch Dashboard" showing localized revenue and performance stats, rather than just filtering the list.

2.  **Map Integration**:
    - **Suggestion**: Visualize branch locations on a Google Map widget.

3.  **Transfer Requests**:
    - **Suggestion**: A feature to manage "Student Transfers" between branches (e.g., student moves from Nepal office to Australia office).
