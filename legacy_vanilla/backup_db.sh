#!/bin/bash

# Configuration
DB_NAME="khadilu_hub"
BACKUP_DIR="/opt/lampp/htdocs/khadilu/backups"
DATE=$(date +%Y-%m-%d_%H%M%S)
FILE_NAME="$BACKUP_DIR/${DB_NAME}_$DATE.sql"

# Create backup directory if it doesn't exist
mkdir -p $BACKUP_DIR

# Run mysqldump
mysqldump -u root $DB_NAME > $FILE_NAME

if [ $? -eq 0 ]; then
    echo "Successfully backed up $DB_NAME to $FILE_NAME"
    # Keep only the last 7 days of backups to save space
    find $BACKUP_DIR -type f -mtime +7 -name "*.sql" -delete
else
    echo "Error: Backup failed."
fi