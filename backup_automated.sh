#!/bin/bash
# Automated Backup Script for eduCRM
# Runs daily via cron

# Configuration
BACKUP_DIR="/backups/educrm"
DB_NAME="edu_crm"
DB_USER="root"
DB_PASS=""
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

# Create backup directory if not exists
mkdir -p $BACKUP_DIR

echo "========================================="
echo "eduCRM Automated Backup"
echo "Date: $(date)"
echo "========================================="

# 1. Database Backup
echo "📦 Backing up database..."
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > "$BACKUP_DIR/db_$DATE.sql"

if [ $? -eq 0 ]; then
    echo "✅ Database backup successful"
    gzip "$BACKUP_DIR/db_$DATE.sql"
else
    echo "❌ Database backup failed"
    exit 1
fi

# 2. Files Backup
echo "📦 Backing up files..."
tar -czf "$BACKUP_DIR/files_$DATE.tar.gz" \
    --exclude='cache' \
    --exclude='exports' \
    --exclude='backups' \
    --exclude='.git' \
    --exclude='vendor' \
    --exclude='node_modules' \
    .

if [ $? -eq 0 ]; then
    echo "✅ Files backup successful"
else
    echo "❌ Files backup failed"
    exit 1
fi

# 3. Verify Backups
echo "🔍 Verifying backups..."
if [ -f "$BACKUP_DIR/db_$DATE.sql.gz" ] && [ -f "$BACKUP_DIR/files_$DATE.tar.gz" ]; then
    DB_SIZE=$(du -h "$BACKUP_DIR/db_$DATE.sql.gz" | cut -f1)
    FILES_SIZE=$(du -h "$BACKUP_DIR/files_$DATE.tar.gz" | cut -f1)
    echo "✅ Backup verification passed"
    echo "   Database: $DB_SIZE"
    echo "   Files: $FILES_SIZE"
else
    echo "❌ Backup verification failed"
    exit 1
fi

# 4. Clean Old Backups
echo "🧹 Cleaning old backups (older than $RETENTION_DAYS days)..."
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +$RETENTION_DAYS -delete
find $BACKUP_DIR -name "files_*.tar.gz" -mtime +$RETENTION_DAYS -delete
echo "✅ Cleanup complete"

# 5. Log Backup
echo "📝 Logging backup..."
echo "$(date): Backup completed successfully - db_$DATE.sql.gz, files_$DATE.tar.gz" >> "$BACKUP_DIR/backup.log"

# 6. Send Notification (optional)
# Uncomment to enable email notifications
# echo "Backup completed: $DATE" | mail -s "eduCRM Backup Success" admin@example.com

echo "========================================="
echo "✅ Backup completed successfully!"
echo "========================================="
echo ""
echo "Backup files:"
echo "  Database: $BACKUP_DIR/db_$DATE.sql.gz ($DB_SIZE)"
echo "  Files: $BACKUP_DIR/files_$DATE.tar.gz ($FILES_SIZE)"
echo ""

exit 0
