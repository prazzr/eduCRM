# EduCRM Deployment Guide

> Production deployment checklist for EduCRM

---

## Prerequisites

- [ ] PHP 8.0 or higher
- [ ] MySQL 8.0 / MariaDB 10.5+
- [ ] Web Server (Apache with mod_rewrite OR Nginx)
- [ ] Composer installed (optional - vendor already included)

---

## Pre-Deployment Checklist

### 1. Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Edit with production values
nano .env
```

**Required `.env` settings:**
```ini
# Database (REQUIRED)
DB_HOST=localhost
DB_USER=your_db_user
DB_PASS=your_secure_password
DB_NAME=edu_crm

# Application (REQUIRED)
APP_URL=https://your-domain.com/
APP_ENV=production
APP_DEBUG=false

# Security (REQUIRED - Generate unique values!)
JWT_SECRET=generate-a-64-character-random-string-here

# Email (Optional but recommended)
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password

# Push Notifications (Optional)
NTFY_URL=http://your-ntfy-server:8090
```

---

### 2. Database Setup

**Option A: Fresh Installation**
```sql
-- Create database
CREATE DATABASE edu_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Import schema
mysql -u root -p edu_crm < database/schema.sql

-- Run migrations
mysql -u root -p edu_crm < database/migrations/create_user_devices.sql
mysql -u root -p edu_crm < database/migrations/2026_01_08_email_templates.sql
```

**Option B: Export Schema from Existing DB**
```bash
mysqldump -u root edu_crm --no-data --routines --triggers > database/schema.sql
```

---

### 3. Directory Permissions

```bash
# Linux/Mac
chmod -R 755 storage/
chmod -R 755 public/uploads/
chmod -R 755 logs/
chmod -R 755 cache/
chmod -R 755 exports/

# Ensure web server user owns these
chown -R www-data:www-data storage/ public/uploads/ logs/
```

**Windows (XAMPP):**
- Ensure `storage/`, `public/uploads/`, `logs/` are not read-only

---

### 4. Apache Configuration

Ensure `.htaccess` is enabled:
```apache
<Directory /var/www/html/CRM>
    AllowOverride All
    Require all granted
</Directory>
```

Enable mod_rewrite:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

---

### 5. Security Hardening

**Verify protected directories have .htaccess:**
- `storage/.htaccess` - Deny all
- `logs/.htaccess` - Deny all
- `config/.htaccess` - Deny all (or restrict)
- `app/.htaccess` - Deny all

**Production .env settings:**
```ini
APP_ENV=production
APP_DEBUG=false
```

---

### 6. Cron Jobs Setup

Add to crontab (`crontab -e`):

```cron
# Notification emails (every 15 minutes)
*/15 * * * * /usr/bin/php /var/www/html/CRM/cron/notification_cron.php >> /var/log/educrm-notifications.log 2>&1

# Message queue (every 5 minutes)
*/5 * * * * /usr/bin/php /var/www/html/CRM/cron/messaging_queue_processor.php >> /var/log/educrm-messaging.log 2>&1

# Analytics snapshot (daily at midnight)
0 0 * * * /usr/bin/php /var/www/html/CRM/cron/analytics_snapshot.php >> /var/log/educrm-analytics.log 2>&1

# Automation queue (every 5 minutes)
*/5 * * * * /usr/bin/php /var/www/html/CRM/cron/process_automation_queue.php >> /var/log/educrm-automation.log 2>&1
```

**Windows Task Scheduler:**
```
C:\xampp\php\php.exe C:\xampp\htdocs\CRM\cron\notification_cron.php
```

---

## Post-Deployment Verification

### 1. Health Check
```bash
curl http://your-domain.com/CRM/tools/health_check.php
```

Expected response:
```json
{
    "status": "healthy",
    "checks": {
        "database": {"status": "ok"},
        "php_version": {"status": "ok"},
        "disk_space": {"status": "ok"}
    }
}
```

### 2. Login Test
- Navigate to `http://your-domain.com/CRM/`
- Should redirect to login page
- Test admin login

### 3. Verify Cron Jobs
```bash
# Test notification cron
php cron/notification_cron.php
```

---

## Troubleshooting

### Error: "Class not found"
```bash
composer dump-autoload
```

### Error: "Permission denied on storage/"
```bash
chmod -R 755 storage/
chown -R www-data:www-data storage/
```

### Error: "Database connection failed"
- Check `.env` credentials
- Verify MySQL is running
- Check database exists

---

## Default Admin Account

| Field | Value |
|-------|-------|
| Email | `admin@edu.crm` |
| Password | Change after first login! |

---

## Support

For issues, check:
- `logs/email.log` - Email errors
- `logs/messaging.log` - Messaging errors
- Browser console - JavaScript errors
