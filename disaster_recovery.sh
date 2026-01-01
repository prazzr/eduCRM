#!/bin/bash
# Disaster Recovery Plan for eduCRM
# Use this script to restore from backup

echo "========================================="
echo "eduCRM Disaster Recovery"
echo "========================================="
echo ""

# Check if backup file provided
if [ -z "$1" ]; then
    echo "Usage: ./disaster_recovery.sh <backup_date>"
    echo "Example: ./disaster_recovery.sh 20260101_120000"
    echo ""
    echo "Available backups:"
    ls -lh /backups/educrm/db_*.sql.gz | tail -10
    exit 1
fi

BACKUP_DATE=$1
BACKUP_DIR="/backups/educrm"
DB_NAME="edu_crm"
DB_USER="root"

# Verify backup files exist
DB_BACKUP="$BACKUP_DIR/db_$BACKUP_DATE.sql.gz"
FILES_BACKUP="$BACKUP_DIR/files_$BACKUP_DATE.tar.gz"

if [ ! -f "$DB_BACKUP" ]; then
    echo "❌ Database backup not found: $DB_BACKUP"
    exit 1
fi

if [ ! -f "$FILES_BACKUP" ]; then
    echo "❌ Files backup not found: $FILES_BACKUP"
    exit 1
fi

echo "Found backups:"
echo "  Database: $DB_BACKUP"
echo "  Files: $FILES_BACKUP"
echo ""

# Confirmation
read -p "⚠️  This will REPLACE all current data. Continue? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "Recovery cancelled"
    exit 0
fi

# Create pre-recovery backup
echo "📦 Creating pre-recovery backup..."
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
mysqldump -u $DB_USER -p $DB_NAME > "/backups/educrm/pre_recovery_$TIMESTAMP.sql"

# Restore Database
echo "🗄️  Restoring database..."
gunzip < "$DB_BACKUP" | mysql -u $DB_USER -p $DB_NAME

if [ $? -eq 0 ]; then
    echo "✅ Database restored successfully"
else
    echo "❌ Database restore failed"
    exit 1
fi

# Restore Files
echo "📁 Restoring files..."
tar -xzf "$FILES_BACKUP" -C .

if [ $? -eq 0 ]; then
    echo "✅ Files restored successfully"
else
    echo "❌ Files restore failed"
    exit 1
fi

# Set Permissions
echo "🔒 Setting permissions..."
chmod -R 755 .
chmod -R 777 uploads/ exports/ cache/

# Clear Cache
echo "🧹 Clearing cache..."
rm -rf cache/*

# Verify Restoration
echo "🔍 Verifying restoration..."
php health_check.php

if [ $? -eq 0 ]; then
    echo "✅ Health check passed"
else
    echo "⚠️  Health check warnings - review manually"
fi

echo ""
echo "========================================="
echo "✅ Recovery completed!"
echo "========================================="
echo ""
echo "Restored from: $BACKUP_DATE"
echo "Pre-recovery backup: pre_recovery_$TIMESTAMP.sql"
echo ""
echo "Next steps:"
echo "1. Test critical functionality"
echo "2. Verify data integrity"
echo "3. Check user access"
echo "4. Monitor error logs"
echo ""

exit 0
