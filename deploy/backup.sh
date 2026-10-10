#!/usr/bin/env bash
# Dump database harian, simpan 7 hari. Dipasang di /usr/local/bin/ammar-backup oleh server-setup.sh.
set -euo pipefail

BACKUP_DIR="/var/backups/ammar"
KEEP_DAYS=7
ENV_FILE="/var/www/ammar-be/.env"

mkdir -p "$BACKUP_DIR"

get() { grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '"'; }

DB_NAME="$(get DB_DATABASE)"
DB_USER="$(get DB_USERNAME)"
DB_PASS="$(get DB_PASSWORD)"

FILE="$BACKUP_DIR/${DB_NAME}-$(date +%Y%m%d-%H%M%S).sql.gz"

MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --no-tablespaces -u "$DB_USER" "$DB_NAME" | gzip > "$FILE"
chmod 600 "$FILE"

find "$BACKUP_DIR" -name '*.sql.gz' -mtime +"$KEEP_DAYS" -delete

echo "Backup tersimpan: $FILE ($(du -h "$FILE" | cut -f1))"
