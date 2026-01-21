# eduCRM Administrator Guide

## Table of Contents
1. [System Overview](#system-overview)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [User Management](#user-management)
5. [Security](#security)
6. [Backup & Recovery](#backup--recovery)
7. [Maintenance](#maintenance)
8. [Troubleshooting](#troubleshooting)

---

## System Overview

eduCRM is a comprehensive Customer Relationship Management system designed for educational institutions. It manages inquiries, students, applications, messaging, analytics, and more.

**Key Features:**
- Lead management with scoring
- Student lifecycle tracking
- Multi-channel messaging (SMS, WhatsApp, Viber, Email)
- Advanced analytics and reporting
- Document management
- Task and appointment scheduling

---

## Installation

### Prerequisites
- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache/Nginx web server
- Composer (optional, for dependencies)

### Installation Steps

1. **Upload Files**
   ```bash
   # Upload all files to your web server
   # Recommended: /var/www/html/crm or C:\xampp\htdocs\CRM
   ```

2. **Create Database**
   ```sql
   CREATE DATABASE edu_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Configure Database**
   - Copy `config.sample.php` to `config.php`
   - Edit database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'edu_crm');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   ```

4. **Run Migrations**
   ```bash
   php run_migration.php
   ```

5. **Set Permissions**
   ```bash
   chmod -R 755 .
   chmod -R 777 uploads/
   chmod -R 777 exports/
   chmod -R 777 cache/
   ```

6. **Access System**
   - URL: `http://yoursite.com/crm`
   - Default login: admin / admin123
   - **Change password immediately!**

---

## Configuration

### System Settings

Access: **Settings → System Settings**

**General Settings:**
- Site name
- Time zone
- Date format
- Currency

**Email Settings:**
- SMTP host
- SMTP port
- SMTP username/password
- From email/name

**Messaging Settings:**
- Default SMS gateway
- WhatsApp enabled
- Viber enabled
- Message templates

### Gateway Configuration

Access: **Messaging → Gateways**

**SMS Gateways:**
- Twilio: Account SID, Auth Token, From Number
- SMPP: Host, Port, Username, Password
- Gammu: Path, Device, Connection

**WhatsApp Gateways:**
- Twilio WhatsApp: Account SID, Auth Token
- Meta Cloud API: Phone Number ID, Access Token
- 360Dialog: API Key, Client ID

**Viber Gateway:**
- Auth Token, Bot Name, Avatar URL

---

## User Management

### User Roles

1. **Admin** - Full system access
2. **Counselor** - Manage inquiries, students, applications
3. **Teacher** - LMS access, class management
4. **Student** - View profile, classes, tasks
5. **Accountant** - Financial management

### Adding Users

Access: **Users → Add User**

1. Enter user details
2. Select role
3. Set password (min 8 chars, uppercase, lowercase, number, special char)
4. Enable 2FA (optional)
5. Save

### Password Policy

- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character

---

## Security

### CSRF Protection

All forms include CSRF tokens automatically. No configuration needed.

### Rate Limiting

Default limits:
- Login attempts: 5 per hour
- API requests: 100 per hour
- Message sending: 1000 per day

Configure in `SecurityService.php`

### Two-Factor Authentication (2FA)

**Enable for User:**
1. Go to user profile
2. Enable 2FA
3. Scan QR code with authenticator app
4. Enter verification code

**Disable 2FA:**
- Admin can disable from user management
- User can disable from profile (requires password)

### Security Headers

Automatically set on all pages:
- X-Frame-Options: DENY
- X-Content-Type-Options: nosniff
- X-XSS-Protection: 1; mode=block
- Strict-Transport-Security
- Content-Security-Policy

### Security Logs

Access: **Admin → Security Logs**

Tracks:
- Login attempts
- Failed logins
- Rate limit violations
- Permission denied
- Data exports

---

## Backup & Recovery

### Automated Backups

**Database Backup (Daily):**
```bash
# Add to cron (Linux)
0 2 * * * /usr/bin/php /path/to/crm/cron/backup_database.php

# Add to Task Scheduler (Windows)
Program: C:\xampp\php\php.exe
Arguments: C:\xampp\htdocs\CRM\cron\backup_database.php
Schedule: Daily at 2:00 AM
```

**File Backup (Weekly):**
```bash
# Linux
0 3 * * 0 tar -czf /backups/crm_files_$(date +\%Y\%m\%d).tar.gz /var/www/html/crm

# Windows (PowerShell)
Compress-Archive -Path C:\xampp\htdocs\CRM -DestinationPath C:\backups\crm_files_$(Get-Date -Format 'yyyyMMdd').zip
```

### Manual Backup

**Database:**
```bash
mysqldump -u username -p edu_crm > backup.sql
```

**Files:**
```bash
tar -czf crm_backup.tar.gz /path/to/crm
```

### Recovery

**Restore Database:**
```bash
mysql -u username -p edu_crm < backup.sql
```

**Restore Files:**
```bash
tar -xzf crm_backup.tar.gz -C /path/to/restore
```

---

## Maintenance

### Daily Tasks

1. **Check Health Status**
   - Access: `/health_check.php`
   - Verify all checks pass

2. **Review Security Logs**
   - Check for suspicious activity
   - Review failed login attempts

3. **Monitor Queue**
   - Messaging → Queue Monitor
   - Retry failed messages

### Weekly Tasks

1. **Database Optimization**
   ```sql
   OPTIMIZE TABLE inquiries, students, applications;
   ```

2. **Clear Old Logs**
   ```sql
   DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
   DELETE FROM performance_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
   ```

3. **Review Analytics**
   - Analytics → Dashboard
   - Check conversion rates
   - Review counselor performance

### Monthly Tasks

1. **Update System**
   - Check for updates
   - Test in staging
   - Deploy to production

2. **Review Backups**
   - Verify backup integrity
   - Test restore procedure

3. **Performance Review**
   - Check slow queries
   - Optimize indexes
   - Review cache hit rates

---

## Troubleshooting

### Common Issues

**1. Can't Login**
- Check username/password
- Verify account not locked
- Check database connection
- Review security logs

**2. Messages Not Sending**
- Check gateway status (Messaging → Gateways)
- Verify gateway credentials
- Check queue (Messaging → Queue)
- Review error logs

**3. Slow Performance**
- Clear cache: `rm -rf cache/*`
- Optimize database
- Check disk space
- Review slow query log

**4. Email Not Sending**
- Verify SMTP settings
- Check email logs
- Test SMTP connection
- Review firewall rules

**5. Reports Not Generating**
- Check exports directory permissions
- Verify PHP memory limit
- Review error logs
- Test with smaller date range

### Error Logs

**PHP Errors:**
```bash
# Linux
tail -f /var/log/apache2/error.log

# Windows
C:\xampp\apache\logs\error.log
```

**Application Logs:**
```bash
tail -f logs/app.log
```

### Support

**Documentation:** `/docs`  
**Health Check:** `/health_check.php`  
**System Info:** `phpinfo.php` (remove in production!)

---

## Cron Jobs

### Required Cron Jobs

```bash
# Analytics snapshot (daily at midnight)
0 0 * * * /usr/bin/php /path/to/crm/cron/analytics_snapshot.php

# Message queue processor (every 5 minutes)
*/5 * * * * /usr/bin/php /path/to/crm/cron/messaging_queue_processor.php

# Email notifications (every 10 minutes)
*/10 * * * * /usr/bin/php /path/to/crm/cron/notification_cron.php

# Database backup (daily at 2 AM)
0 2 * * * /usr/bin/php /path/to/crm/cron/backup_database.php

# Clear old cache (daily at 3 AM)
0 3 * * * /usr/bin/php /path/to/crm/cron/clear_cache.php
```

---

**Version:** 1.0  
**Last Updated:** 2026-01-01  
**Support:** admin@yoursite.com
