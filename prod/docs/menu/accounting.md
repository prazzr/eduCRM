# Accounting Menu Documentation

## Overview
The Accounting module (`modules/accounting/ledger.php`) manages the financial relationship with students. It follows a "Student Ledger" model, tracking invoices and payments for each individual.

## Features

### 1. Student Directory
- **Searchable List**: Provides a client-side filtered list of all students to access their financial records.
- **Ledger Access**: Links to `student_ledger.php`, which allows viewing transaction history, creating invoices, and recording payments.

### 2. Fee Structure
- **Manage Fee Types**: Links to `fee_types.php` to define standard charge categories (e.g., Tuition Fee, Visa Processing Fee, Book Fee).

## Suggestions for Improvement

1.  **Financial Dashboard**:
    - The entry page is just a list of names.
    - **Suggestion**: Turn this into a dashboard showing:
        - **Total Revenue (This Month)**
        - **Total Outstanding Dues**
        - **Recent Payments**

2.  **Visual Due Status**:
    - You cannot tell from the list who owes money.
    - **Suggestion**: Add a "Balance" column to the main table, highlighting negative balances in red.

3.  **Bulk Invoicing**:
    - **Suggestion**: Add a feature to generate invoices for multiple students at once (e.g., "Invoice all IELTS students for February").

4.  **Payment Methods**:
    - **Suggestion**: Ensure the ledger recording supports method tracking (Cash, Bank Transfer, Cheque) and Reference Numbers.
