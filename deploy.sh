#!/bin/bash
# eduCRM Deployment Script
# Version: 1.0
# Date: 2026-01-01

echo "========================================="
echo "eduCRM Deployment Script"
echo "========================================="
echo ""

# Configuration
BACKUP_DIR="backups"
DB_NAME="edu_crm"
DB_USER="root"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Step 1: Create backup directory
echo -e "${YELLOW}Step 1: Creating backup directory...${NC}"
mkdir -p $BACKUP_DIR
echo -e "${GREEN}✓ Backup directory ready${NC}"
echo ""

# Step 2: Backup database
echo -e "${YELLOW}Step 2: Backing up database...${NC}"
mysqldump -u $DB_USER -p $DB_NAME > "$BACKUP_DIR/db_backup_$TIMESTAMP.sql"
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Database backup created: db_backup_$TIMESTAMP.sql${NC}"
else
    echo -e "${RED}✗ Database backup failed${NC}"
    exit 1
fi
echo ""

# Step 3: Backup files
echo -e "${YELLOW}Step 3: Backing up files...${NC}"
tar -czf "$BACKUP_DIR/files_backup_$TIMESTAMP.tar.gz" \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='backups' \
    --exclude='exports' \
    --exclude='.git' \
    .
echo -e "${GREEN}✓ Files backup created: files_backup_$TIMESTAMP.tar.gz${NC}"
echo ""

# Step 4: Pull latest code (if using git)
echo -e "${YELLOW}Step 4: Pulling latest code...${NC}"
if [ -d ".git" ]; then
    git pull origin main
    echo -e "${GREEN}✓ Code updated${NC}"
else
    echo -e "${YELLOW}⚠ Not a git repository, skipping...${NC}"
fi
echo ""

# Step 5: Install dependencies
echo -e "${YELLOW}Step 5: Installing dependencies...${NC}"
if [ -f "composer.json" ]; then
    composer install --no-dev --optimize-autoloader
    echo -e "${GREEN}✓ Composer dependencies installed${NC}"
else
    echo -e "${YELLOW}⚠ No composer.json found, skipping...${NC}"
fi
echo ""

# Step 6: Run migrations
echo -e "${YELLOW}Step 6: Running database migrations...${NC}"
php run_migration.php
echo -e "${GREEN}✓ Migrations completed${NC}"
echo ""

# Step 7: Clear cache
echo -e "${YELLOW}Step 7: Clearing cache...${NC}"
rm -rf cache/*
mysql -u $DB_USER -p -e "TRUNCATE TABLE $DB_NAME.query_cache;"
echo -e "${GREEN}✓ Cache cleared${NC}"
echo ""

# Step 8: Set permissions
echo -e "${YELLOW}Step 8: Setting permissions...${NC}"
chmod -R 755 .
chmod -R 777 uploads/
chmod -R 777 exports/
chmod -R 777 cache/
echo -e "${GREEN}✓ Permissions set${NC}"
echo ""

# Step 9: Health check
echo -e "${YELLOW}Step 9: Running health check...${NC}"
php health_check.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Health check passed${NC}"
else
    echo -e "${RED}✗ Health check failed${NC}"
    echo -e "${YELLOW}Rolling back...${NC}"
    mysql -u $DB_USER -p $DB_NAME < "$BACKUP_DIR/db_backup_$TIMESTAMP.sql"
    echo -e "${RED}Deployment failed and rolled back${NC}"
    exit 1
fi
echo ""

echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}Deployment completed successfully!${NC}"
echo -e "${GREEN}=========================================${NC}"
echo ""
echo "Backup files:"
echo "  - Database: $BACKUP_DIR/db_backup_$TIMESTAMP.sql"
echo "  - Files: $BACKUP_DIR/files_backup_$TIMESTAMP.tar.gz"
echo ""
