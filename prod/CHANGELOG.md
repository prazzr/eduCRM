# Changelog

All notable changes to this project will be documented in this file.

## [2.1.0] - 2026-01-21

### Added
- **DEPLOYMENT.md**: Comprehensive production deployment guide with checklist
- **Security .htaccess**: Added protection to `app/`, `config/`, `tools/` directories
- **database/schema.sql**: Clean schema export for fresh installations

### Changed
- **Cron Jobs**: All cron scripts now use `app/bootstrap.php` for proper autoloading
- **health_check.php**: Fixed to use correct bootstrap path
- **config.sample.php**: Updated to match production config with full Env support
- **README.md**: Updated database setup instructions

### Removed
- **Duplicate SQL files**: Removed `edu_crm.sql`, `edu_crm (1).sql`
- **Composer setup**: Removed `composer-setup.php` (not needed)
- **Debug scripts**: Removed `verify_ntfy.php`
- **ntfy binaries**: Removed `ntfy/bin/` (using Docker instead)
- **PowerShell scripts**: Removed 7 one-time fix scripts from `tools/`

---

## [2.0.0 Refactor] - 2026-01-07

### Added
- **PSR-4 Namespacing**: All classes under `EduCRM\` namespace.
- **Environment Configuration**: Added `.env` support via `EduCRM\Helpers\Env`.
- **Directory Structure**:
  - `app/`: Services, Helpers, Contracts.
  - `config/`: Centralized config.
  - `public/`: Static assets.
  - `storage/`: Secure logs and cache.
- **Tools**: Added `tools/` directory for maintenance scripts.

### Changed
- **Autoloading**: Replaced manual includes with PSR-4 Autoloader.
- **Database**: Configured to use `.env` credentials.
- **Frontend**: Moved assets to `public/assets`.
- **Services**: Renamed `app/services` to `app/Services` (Capitalized).

### Removed
- **Redundant Files**: `email_queue.sql`, `composer.phar` (local binary).
- **Legacy Components**: `includes/` directory deleted (contents moved).

### Security
- **Protected Directories**: Added `.htaccess` to `storage/`, `app/`, `config/`.
- **Credentials**: Moved DB credentials out of `config.php` into `.env`.
