#!/bin/bash
# backup.sh - produce SQL dumps using mysqldump and then run backup.php
# Place this script in the project root and make executable (chmod +x backup.sh)

clear
set -eu
DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="$DIR/.env"
BACKUPS_JSON="$DIR/backups.json"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
export TIMESTAMP

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

case "$BACKUP_TEMP_DIR" in
  /*) ;;
  *) BACKUP_TEMP_DIR="$DIR/$BACKUP_TEMP_DIR" ;;
esac
mkdir -p "$BACKUP_TEMP_DIR"

DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-}"
DB_PASSWORD="${DB_PASSWORD:-}"

if [ ! -f "$BACKUPS_JSON" ]; then
  echo "ERROR: Missing backups.json at $BACKUPS_JSON"
  exit 1
fi

# Use PHP to extract database list and app folder list (one-line space separated)
uploadFiles=()
databases=$(php -r 'echo implode(" ", array_map("strval", json_decode(file_get_contents($argv[1]), true)["databases"] ?? array()));' "$BACKUPS_JSON")
app_folders=$(php -r 'echo implode(" ", array_map("strval", json_decode(file_get_contents($argv[1]), true)["app_folders"] ?? array()));' "$BACKUPS_JSON")

if [ -z "$databases" ]; then
  echo "ERROR: No databases listed in backups.json"
else
  # Loop through each database and perform mysqldump
  for db in $databases; do
    ts=$TIMESTAMP
    outfile="$BACKUP_TEMP_DIR/${db}_$ts.sql"
    MYSQL_PWD="$DB_PASSWORD" /bin/mysqldump -h "$DB_HOST" -u "$DB_USER" "$db" > "$outfile" 2>&1

    rc=$?
    if [ "$rc" -ne 0 ]; then
      echo "ERROR: mysqldump failed for $db (exit $rc). Check $outfile for details."
    else
      if command -v zip >/dev/null 2>&1; then
        zipfile="$BACKUP_TEMP_DIR/${db}_$ts.zip"
        (cd "$BACKUP_TEMP_DIR" && zip -q "$zipfile" "$(basename "$outfile")")
        if [ -f "$zipfile" ]; then
          uploadFiles+=("$zipfile")
          rm "$outfile"
        else
          echo "ERROR: Failed to create zip backup: $zipfile"
        fi
      else
        echo "ERROR: zip command not available; skipped zip creation"
      fi
    fi
  done
fi

if [ -n "$app_folders" ]; then
  for folder in $app_folders; do
    ts=$TIMESTAMP
    zipfile="$BACKUP_TEMP_DIR/${folder}_$ts.zip"
    if command -v zip >/dev/null 2>&1; then
      (cd "$DIR" && zip -q -r "$zipfile" ~/"${APP_ROOT}/${folder}")
      if [ -f "$zipfile" ]; then
        uploadFiles+=("$zipfile")
      else
        echo "ERROR: Failed to create app zip backup: $zipfile"
      fi
    else
      echo "ERROR: zip command not available; skipped app zip creation"
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
  "timestamp" => getenv("TIMESTAMP") !== false ? getenv("TIMESTAMP") : null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
')

# Now run PHP backup orchestration and pass the generated JSON payload as one argument
php "$DIR/backup.php" "$envFile"
