#!/bin/bash
# backup.sh - produce SQL dumps using mysqldump and then run backup.php
# Place this script in the project root and make executable (chmod +x backup.sh)

set -euo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="$DIR/.env"
BACKUPS_JSON="$DIR/backups.json"

# Load .env if present (simple key=value parser)
if [ -f "$ENV_FILE" ]; then
  while IFS='=' read -r key value; do
    # skip comments and empty lines
    [[ "$key" =~ ^[[:space:]]*# ]] && continue
    [ -z "$key" ] && continue
    # trim whitespace
    key="$(echo "$key" | tr -d '[:space:]')"
    # remove surrounding quotes from value
    value="$(echo "$value" | sed -e 's/^\"//' -e 's/\"$//' -e "s/^'//" -e "s/'$//")"
    export "$key=$value"
  done < <(grep -v '^[[:space:]]*#' "$ENV_FILE" | grep '=') || true
fi

BACKUP_TEMP_DIR="${BACKUP_TEMP_DIR:-/tmp}"
mkdir -p "$BACKUP_TEMP_DIR"

DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-}"
DB_PASSWORD="${DB_PASSWORD:-}"

if [ ! -f "$BACKUPS_JSON" ]; then
  echo "Missing backups.json at $BACKUPS_JSON"
  exit 1
fi

# Use PHP to extract database list (one-line space separated)
databases=$(php -r 'echo implode(" ", array_map("strval", json_decode(file_get_contents($argv[1]), true)["databases"] ?? array()));' "$BACKUPS_JSON")

if [ -z "$databases" ]; then
  echo "No databases listed in backups.json"
else
  for db in $databases; do
    ts=$(date +%Y%m%d_%H%M%S)
    outfile="$BACKUP_TEMP_DIR/${db}_$ts.sql"
    echo "Running mysqldump for: $db -> $outfile"
    /bin/mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$db" > "$outfile" 2>&1
    rc=$?
    if [ $rc -ne 0 ]; then
      echo "mysqldump failed for $db (exit $rc). Check $outfile for details."
    else
      echo "mysqldump completed for $db"
    fi
  done
fi

# Now run PHP backup orchestration (will compress uploaded SQL files and upload to Drive)
php "$DIR/backup.php"
