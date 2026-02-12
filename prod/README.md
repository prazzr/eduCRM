# EduCRM (Refactored)

A professional CRM for educational institutions, refactored to modern industry standards.

## Features
- **Student Management**: Enrollments, Profiles, Documents.
- **Lead Management**: Inquiry tracking, Kanban board.
- **Financials**: Invoices, Payments, Ledgers.
- **Communication**: Email/SMS automation (PHPMailer, Gammu).
- **Architecture**: PSR-4 Autoloading, Service-Repository Pattern, Secure Environment Configuration.

## Requirements
- PHP 8.0 or higher
- MySQL / MariaDB
- Web Server (Apache/Nginx)
- Composer (Optional, for dependency management)

## Installation

1. **Setup Database**
   - Import `database/schema.sql` into your database (fresh install)
   - Or restore from `backups/` directory (existing data)

2. **Configure Environment**
   - Copy `.env.example` to `.env`.
   - Edit `.env` with your credentials:
     ```ini
     DB_HOST=localhost
     DB_NAME=edu_crm
     DB_USER=root
     DB_PASS=your_password
     APP_URL=http://localhost/CRM/
     ```

3. **Deploy**
   - Place the project in your web server's root.
   - Ensure `storage/` and `uploads/` are writable.
   - Access via browser.

## Directory Structure
- `app/`: Core application logic (Services, Helpers, Contracts).
- `config/`: Configuration files.
- `public/`: Public assets (CSS, JS).
- `storage/`: Logs, Cache, Exports (Protected).
- `templates/`: View partials (Header, Footer).
- `modules/`: Functional modules (legacy structure, refactoring in progress).

## Development
- **Namespaces**: All classes are namespaced under `EduCRM\`.
- **Autoloading**: Handled by `app/bootstrap.php` (PSR-4 compliant).
- **Public Assets**: Use `public/assets/`.

## Contributing
See `CHANGELOG.md` for recent changes.

## License
Proprietary / Private.
