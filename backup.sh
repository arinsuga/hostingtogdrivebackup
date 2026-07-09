#!/bin/bash
# backup.sh - produce SQL dumps using mysqldump and then run backup.php
# Place this script in the project root and make executable (chmod +x backup.sh)

clear
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

BACKUP_TEMP_DIR="${BACKUP_TEMP_DIR:-./temp}"
APP_ROOT="${APP_ROOT:-~/public_htmlXXX}"

echo "===== start Initial Backup Variables ====="
echo "Backup temporary directory: $BACKUP_TEMP_DIR"
echo "Application root directory: $APP_ROOT"
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

if [ ! -f "$BACKUPS_JSON" ]; then
  echo "Missing backups.json at $BACKUPS_JSON"
  exit 1
fi

# Use PHP to extract database list and app folder list (one-line space separated)
uploadFiles=()
databases=$(php -r 'echo implode(" ", array_map("strval", json_decode(file_get_contents($argv[1]), true)["databases"] ?? array()));' "$BACKUPS_JSON")
app_folders=$(php -r 'echo implode(" ", array_map("strval", json_decode(file_get_contents($argv[1]), true)["app_folders"] ?? array()));' "$BACKUPS_JSON")

echo "Databases to backup: $databases"
echo "Application folders to backup: $app_folders"

if [ -z "$databases" ]; then
  echo "No databases listed in backups.json"
else
  # Loop through each database and perform mysqldump
  for db in $databases; do
    ts=$(date +%Y%m%d_%H%M%S)
    outfile="$BACKUP_TEMP_DIR/${db}_$ts.sql"
    echo "Running mysqldump for: $db -> $outfile"
    MYSQL_PWD="$DB_PASSWORD" /bin/mysqldump -h "$DB_HOST" -u "$DB_USER" "$db" > "$outfile" 2>&1

    rc=$?
    if [ "$rc" -ne 0 ]; then
      echo "mysqldump failed for $db (exit $rc). Check $outfile for details."
    else
      echo "mysqldump completed for $db"
      if command -v zip >/dev/null 2>&1; then
        zipfile="$BACKUP_TEMP_DIR/${db}_$ts.zip"
        (cd "$BACKUP_TEMP_DIR" && zip -q "$zipfile" "$(basename "$outfile")")
        if [ -f "$zipfile" ]; then
          echo "Created zip backup: $zipfile"
          uploadFiles+=("$zipfile")
          rm "$outfile"
        else
          echo "Failed to create zip backup: $zipfile"
        fi
      else
        echo "zip command not available; skipped zip creation"
      fi
    fi
  done
fi

if [ -n "$app_folders" ]; then
  for folder in $app_folders; do
    ts=$(date +%Y%m%d_%H%M%S)
    zipfile="$BACKUP_TEMP_DIR/${folder}_$ts.zip"
    echo "Creating app backup zip for: $folder -> $zipfile"
    if command -v zip >/dev/null 2>&1; then
      (cd "$DIR" && zip -q -r "$zipfile" ~/"${APP_ROOT}/${folder}")
      if [ -f "$zipfile" ]; then
        echo "Created app zip backup: $zipfile"
        uploadFiles+=("$zipfile")
      else
        echo "Failed to create app zip backup: $zipfile"
      fi
    else
      echo "zip command not available; skipped app zip creation"
    fi
  done
fi

# Build JSON including upload_files from the generated zip paths
envFile=$(printf '%s\n' "${uploadFiles[@]}" | php -r '
$uploadFiles = [];
while (($line = fgets(STDIN)) !== false) {
    $line = trim($line);
    if ($line !== "") {
        $uploadFiles[] = $line;
    }
}
echo json_encode([
  "env_file" => [
    "DB_HOST" => getenv("DB_HOST") !== false ? getenv("DB_HOST") : null,
    "DB_USER" => getenv("DB_USER") !== false ? getenv("DB_USER") : null,
    "DB_PASSWORD" => getenv("DB_PASSWORD") !== false ? getenv("DB_PASSWORD") : null,
    "GOOGLE_DRIVE_FOLDER_ID" => getenv("GOOGLE_DRIVE_FOLDER_ID") !== false ? getenv("GOOGLE_DRIVE_FOLDER_ID") : null,
    "BACKUP_TEMP_DIR" => getenv("BACKUP_TEMP_DIR") !== false ? getenv("BACKUP_TEMP_DIR") : null,
    "APP_ROOT" => getenv("APP_ROOT") !== false ? getenv("APP_ROOT") : null,
    "ADMIN_EMAIL" => getenv("ADMIN_EMAIL") !== false ? getenv("ADMIN_EMAIL") : null,
    "LOG_DIR" => getenv("LOG_DIR") !== false ? getenv("LOG_DIR") : null,
    "LOG_FILE" => getenv("LOG_FILE") !== false ? getenv("LOG_FILE") : null,
    "RETENTION_DAYS" => getenv("RETENTION_DAYS") !== false ? getenv("RETENTION_DAYS") : null,
    "COMPRESSION_LEVEL" => getenv("COMPRESSION_LEVEL") !== false ? getenv("COMPRESSION_LEVEL") : null,
  ],
  "upload_files" => $uploadFiles,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
')

echo "===== start Environment Variables JSON ====="
echo "$envFile"
echo "===== end Environment Variables JSON ====="

# Now run PHP backup orchestration and pass the generated JSON payload as one argument
php "$DIR/backup.php" "$envFile"
