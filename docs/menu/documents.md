# Documents Menu Documentation

## Overview
The Document Management module (`modules/documents/manage.php`) serves as the central digital filing system. It manages uploads, versioning, and organization of files related to students, applications, and general business operations.

## Features

### 1. File Repository
- **Visual Grid**: Displays files with format-specific icons (PDF, Word, Image) and metadata.
- **Filtering**:
  - **By Category**: Passport, Transcript, Offer Letter, Verified Visa, etc.
  - **Search**: Real-time text search for file names.

### 2. Version Control
- **History Tracking**: Supports maintaining multiple versions of the same document (e.g., "SOP - Draft 1", "SOP - Final").
- **Version Modal**: A dedicated modal shows the timeline of changes, uploaded by whom, and allows downloading previous versions.

### 3. Smart Uploads
- **Entity Linking**: Can link documents specifically to a Student or Application record.
- **Categorization**: Enforces tagging documents with categories for better organization.

## Suggestions for Improvement

1.  **In-Browser Preview**:
    - Currently, files must be downloaded to be viewed.
    - **Suggestion**: Integrate a PDF.js or Image Viewer modal to preview documents instantly within the browser.

2.  **Document Expiry Alerts**:
    - Passports and Visas have expiry dates.
    - **Suggestion**: Add an "Expiry Date" field during upload. The system can then auto-notify when a document is about to expire.

3.  **Drag-and-Drop Area**:
    - **Suggestion**: Add a drop zone at the top of the management page for rapid uploads without navigating to a separate form.

4.  **Bulk Download**:
    - **Suggestion**: Allow selecting multiple files (e.g., all 5 visa docs) and downloading them as a single .zip file.
