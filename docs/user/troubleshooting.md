# eduCRM Troubleshooting Guide

## Quick Diagnostics

### System Health Check
1. Visit `/health_check.php`
2. Check all status indicators
3. Review any warnings or errors

### Common Issues Checklist
- [ ] Clear browser cache
- [ ] Check internet connection
- [ ] Verify login credentials
- [ ] Check file permissions
- [ ] Review error logs

---

## Login Issues

### Cannot Login

**Symptoms:** Login fails, error message displayed

**Possible Causes:**
1. Incorrect username/password
2. Account locked (too many failed attempts)
3. Session issues
4. Database connection problem

**Solutions:**
```
1. Verify credentials (check Caps Lock)
2. Wait 1 hour if account locked
3. Clear browser cookies
4. Contact administrator to reset account
```

**Admin Fix:**
```sql
-- Unlock user account
UPDATE users SET account_locked_until = NULL, failed_login_attempts = 0 
WHERE email = 'user@example.com';
```

---

### Forgot Password

**Solution:**
1. Click "Forgot Password" on login page
2. Enter email address
3. Check email for reset link
4. Follow link and set new password

**If email not received:**
- Check spam folder
- Verify email address is correct
- Contact administrator

**Admin Reset:**
```sql
-- Reset password to 'NewPassword123!'
UPDATE users SET password = '$2y$10$...' WHERE email = 'user@example.com';
```

---

## Performance Issues

### Slow Page Load

**Symptoms:** Pages take > 5 seconds to load

**Diagnostics:**
```
1. Check server resources (CPU, RAM, disk)
2. Review slow query log
3. Check network speed
4. Review performance logs
```

**Solutions:**
```
1. Clear query cache:
   TRUNCATE TABLE query_cache;

2. Optimize database:
   OPTIMIZE TABLE inquiries, students, applications;

3. Clear file cache:
   rm -rf cache/*

4. Restart web server:
   service apache2 restart
```

---

### Database Slow

**Symptoms:** Queries taking > 1 second

**Diagnostics:**
```sql
-- Check slow queries
SELECT * FROM performance_logs 
WHERE execution_time > 1.0 
ORDER BY execution_time DESC 
LIMIT 10;

-- Check table sizes
SELECT 
    table_name,
    round(((data_length + index_length) / 1024 / 1024), 2) as size_mb
FROM information_schema.TABLES 
WHERE table_schema = 'edu_crm'
ORDER BY size_mb DESC;
```

**Solutions:**
```sql
-- Add missing indexes
CREATE INDEX idx_inquiry_created ON inquiries(created_at);
CREATE INDEX idx_student_status ON students(status);

-- Analyze tables
ANALYZE TABLE inquiries, students, applications;
```

---

## File Upload Issues

### Upload Fails

**Symptoms:** "File upload failed" error

**Possible Causes:**
1. File too large (> 5MB)
2. Invalid file type
3. Directory permissions
4. PHP upload limits

**Solutions:**
```
1. Check file size (max 5MB)
2. Verify file type (JPG, PNG, PDF, DOC, XLS)
3. Check permissions:
   chmod 777 uploads/

4. Increase PHP limits (php.ini):
   upload_max_filesize = 10M
   post_max_size = 10M
```

---

### File Not Found

**Symptoms:** Uploaded file shows as missing

**Diagnostics:**
```bash
# Check if file exists
ls -la uploads/

# Check database record
SELECT * FROM documents WHERE id = X;
```

**Solutions:**
```
1. Verify file path in database matches actual file
2. Check file permissions
3. Restore from backup if deleted
```

---

## Messaging Issues

### SMS Not Sending

**Symptoms:** SMS stuck in "pending" status

**Diagnostics:**
```
1. Check gateway status: Messaging → Gateways
2. Review message queue
3. Check gateway credentials
4. Review error logs
```

**Solutions:**
```
1. Verify gateway is active
2. Check daily limit not exceeded
3. Verify credentials (API key, auth token)
4. Test with single message first
5. Check phone number format (+1234567890)
```

**Admin Check:**
```sql
-- Check failed messages
SELECT * FROM message_queue 
WHERE status = 'failed' 
ORDER BY created_at DESC 
LIMIT 10;

-- Reset stuck messages
UPDATE message_queue 
SET status = 'pending', attempts = 0 
WHERE status = 'processing' 
AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

---

### WhatsApp Not Connecting

**Symptoms:** WhatsApp messages fail

**Possible Causes:**
1. Invalid credentials
2. Phone number not verified
3. API quota exceeded
4. Network issues

**Solutions:**
```
1. Verify WhatsApp Business API credentials
2. Check phone number is verified
3. Review API usage limits
4. Test with Meta/Twilio dashboard
```

---

## Report Issues

### Report Not Generating

**Symptoms:** "No data found" or timeout

**Diagnostics:**
```
1. Check date range (not too large)
2. Verify data exists for period
3. Check database connection
4. Review error logs
```

**Solutions:**
```
1. Reduce date range (try 1 month)
2. Clear browser cache
3. Try different report type
4. Export smaller dataset
```

---

### Export Fails

**Symptoms:** Export button doesn't work

**Solutions:**
```
1. Check exports directory exists and writable:
   mkdir exports
   chmod 777 exports

2. Verify PHP memory limit:
   memory_limit = 256M

3. Try CSV instead of Excel/PDF
4. Reduce number of records
```

---

## Security Issues

### CSRF Token Error

**Symptoms:** "Invalid CSRF token" message

**Solutions:**
```
1. Refresh the page
2. Clear browser cookies
3. Log out and log back in
4. Check session timeout settings
```

---

### Account Locked

**Symptoms:** "Account locked" message

**Cause:** Too many failed login attempts

**Solution:**
```
Wait 1 hour for automatic unlock
OR contact administrator for immediate unlock
```

**Admin Unlock:**
```sql
UPDATE users 
SET account_locked_until = NULL, failed_login_attempts = 0 
WHERE email = 'user@example.com';
```

---

## Database Issues

### Connection Failed

**Symptoms:** "Database connection error"

**Diagnostics:**
```bash
# Test MySQL connection
mysql -u root -p edu_crm

# Check MySQL is running
service mysql status
```

**Solutions:**
```
1. Start MySQL:
   service mysql start

2. Verify credentials in config.php
3. Check database exists
4. Verify user permissions
```

---

### Table Not Found

**Symptoms:** "Table doesn't exist" error

**Solution:**
```bash
# Run migrations
php run_migration.php

# Or manually create tables
mysql -u root -p edu_crm < schema.sql
```

---

## Email Issues

### Emails Not Sending

**Symptoms:** Email notifications not received

**Diagnostics:**
```
1. Check SMTP settings
2. Test email configuration
3. Review email logs
4. Check spam folder
```

**Solutions:**
```
1. Verify SMTP credentials in config.php
2. Test SMTP connection:
   telnet smtp.example.com 587

3. Check firewall allows SMTP (port 587/465)
4. Try different SMTP provider
```

---

## System Errors

### 500 Internal Server Error

**Diagnostics:**
```bash
# Check Apache error log
tail -f /var/log/apache2/error.log

# Check PHP error log
tail -f /var/log/php/error.log
```

**Common Causes:**
1. PHP syntax error
2. Missing file
3. Permission issues
4. .htaccess error

**Solutions:**
```
1. Check error logs for specific error
2. Verify file permissions (755 for dirs, 644 for files)
3. Check .htaccess syntax
4. Increase PHP memory limit
```

---

### 404 Not Found

**Cause:** File or page doesn't exist

**Solutions:**
```
1. Verify URL is correct
2. Check file exists on server
3. Review .htaccess rewrite rules
4. Clear browser cache
```

---

## Backup & Recovery

### Backup Failed

**Symptoms:** Backup script errors

**Diagnostics:**
```bash
# Check disk space
df -h

# Check backup directory permissions
ls -la backups/

# Test backup manually
./backup.sh
```

**Solutions:**
```
1. Free up disk space
2. Check directory permissions:
   chmod 777 backups/

3. Verify mysqldump is installed
4. Check database credentials
```

---

### Restore Failed

**Symptoms:** Database restore errors

**Solution:**
```bash
# Restore database
mysql -u root -p edu_crm < backup.sql

# If errors, check:
1. Backup file not corrupted
2. Database exists
3. User has permissions
4. MySQL version compatible
```

---

## Browser-Specific Issues

### Works in Chrome, not Firefox

**Solution:**
```
1. Clear Firefox cache
2. Disable Firefox extensions
3. Try private/incognito mode
4. Update Firefox to latest version
```

---

### JavaScript Errors

**Symptoms:** Features not working, console errors

**Solutions:**
```
1. Clear browser cache (Ctrl+Shift+Delete)
2. Disable browser extensions
3. Try different browser
4. Check browser console for errors (F12)
```

---

## Emergency Procedures

### System Down

**Immediate Actions:**
```
1. Check server status
2. Review error logs
3. Restart services:
   service apache2 restart
   service mysql restart

4. Contact hosting provider if needed
```

---

### Data Loss

**Recovery Steps:**
```
1. Stop all operations immediately
2. Locate latest backup
3. Restore from backup:
   mysql -u root -p edu_crm < backup.sql

4. Verify data integrity
5. Document what was lost
```

---

## Getting Help

### Before Contacting Support

**Gather Information:**
1. What were you trying to do?
2. What happened instead?
3. Error messages (screenshots)
4. Browser and version
5. Steps to reproduce

### Log Files to Check

```bash
# Application logs
tail -f logs/app.log

# Apache error log
tail -f /var/log/apache2/error.log

# PHP error log
tail -f /var/log/php/error.log

# MySQL error log
tail -f /var/log/mysql/error.log
```

### Health Check Report

```bash
# Generate health check
php health_check.php > health_report.txt

# Include in support request
```

---

## Preventive Maintenance

### Daily Tasks
- [ ] Check dashboard for errors
- [ ] Review failed messages
- [ ] Monitor disk space

### Weekly Tasks
- [ ] Review performance logs
- [ ] Check backup status
- [ ] Update system if needed

### Monthly Tasks
- [ ] Database optimization
- [ ] Clear old logs
- [ ] Security audit
- [ ] Review user accounts

---

**Still having issues?**

Contact your system administrator with:
- Detailed problem description
- Screenshots of errors
- Steps to reproduce
- Health check report

**Version:** 1.0  
**Last Updated:** 2026-01-01
