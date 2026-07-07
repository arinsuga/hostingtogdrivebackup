#!/bin/sh
# backup.sh - produce SQL dumps using mysqldump and then run backup.php
# Place this script in the project root and make executable (chmod +x backup.sh)

sed -i 's/\r$//' .env
sed -i 's/\r$//' backups.json

set -eu
DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="$DIR/.env"
BACKUPS_JSON="$DIR/backups.json"

echo "===== start Initial Base Variables ====="
echo "Current working directory: $DIR"
echo "Environment file: $ENV_FILE"
echo "Backups JSON file: $BACKUPS_JSON"
echo "===== end Initial Base Variables ====="
echo ""

# Load .env if present (simple key=value parser)
if [ -f "$ENV_FILE" ]; then
  while IFS= read -r line || [ -n "$line" ]; do
    # skip comments and empty lines
    case "$line" in
      ''|[[:space:]]*'#'*)
        continue
        ;;
    esac

    # skip lines without an assignment
    case "$line" in
      *=*) ;;
      *) continue ;;
    esac

    # split on first =
    key=${line%%=*}
    value=${line#*=}

    # trim whitespace
    key=$(printf '%s' "$key" | tr -d '[:space:]')
    # remove surrounding quotes from value
    value=$(printf '%s' "$value" | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//")
    export "$key=$value"
  done < "$ENV_FILE"
fi

BACKUP_TEMP_DIR="${BACKUP_TEMP_DIR:-temp}"
echo "===== start Initial Backup Variables ====="
echo "Backup temporary directory: $BACKUP_TEMP_DIR"
echo "===== end Initial Backup Variables ====="
echo ""

case "$BACKUP_TEMP_DIR" in
  /*) ;;
  *) BACKUP_TEMP_DIR="$DIR/$BACKUP_TEMP_DIR" ;;
esac
mkdir -p "$BACKUP_TEMP_DIR"

DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-}"
DB_PASSWORD="${DB_PASSWORD:-}"

echo "===== start Initial Database Variables ====="
echo "DB_HOST: $DB_HOST"
echo "DB_USER: $DB_USER"
echo "DB_PASSWORD: $DB_PASSWORD"
echo "===== end Initial Database Variables ====="
echo ""

if [ ! -f "$BACKUPS_JSON" ]; then
  echo "Missing backups.json at $BACKUPS_JSON"
  exit 1
fi

# Use PHP to extract database list (one-line space separated)
databases=$(php -r 'echo implode(" ", array_map("strval", json_decode(file_get_contents($argv[1]), true)["databases"] ?? array()));' "$BACKUPS_JSON")
echo "===== start Initial Databases List ====="
echo "Databases to backup: $databases"
echo "===== end Initial Databases List ====="
echo ""

if [ -z "$databases" ]; then
  echo "No databases listed in backups.json"
else

  echo "===== start mysqldump for each database ====="
  for db in $databases; do
    ts=$(date +%Y%m%d_%H%M%S)
    outfile="$BACKUP_TEMP_DIR/${db}_$ts.sql"
    echo "Running mysqldump for: $db -> $outfile"
    MYSQL_PWD="$DB_PASSWORD"
    echo "MYSQL_PWD: $MYSQL_PWD"
    #MYSQL_PWD="$DB_PASSWORD" /bin/mysqldump -h "$DB_HOST" -u "$DB_USER" "$db" > "$outfile" 2>&1
    rc=$?
    if [ "$rc" -ne 0 ]; then
      echo "mysqldump failed for $db (exit $rc). Check $outfile for details."
    else
      echo "mysqldump completed for $db"
    fi
  done
  echo "===== end mysqldump for each database ====="

fi

# Now run PHP backup orchestration (will compress uploaded SQL files and upload to Drive)
#php "$DIR/backup.php"
