# Plan: PHP Backup Script to Google Drive for Shared Hosting

## TL;DR
Create a single PHP 7.2.23+ CLI script that:
1. Backs up two MySQL databases (ariq2418_autorefreshauthapidb, ariq2418_autorefreshappapidb) using mysqldump
2. Backs up two application folders (public_html/autorefreshauthapi, public_html/autorefreshappapi)
3. Uploads all backups to Google Drive with OAuth 2.0 token refresh
4. Auto-deletes backups older than 30 days from Google Drive
5. Logs only failures to log.txt with timestamps

**Recommended approach:** Monolithic script structure with helper functions for DRY code, using environment variables for MySQL credentials, balanced compression (level 6), and efficient error handling.

---

## Steps

### Phase 1: Project Setup & Configuration
1. Create directory structure:
   - `src/` — main backup script and helpers
   - `config/` — configuration file template
   - `logs/` — backup logs
   - `temp/` — temporary files during backup

2. Create `config/config.example.php` with:
   - MySQL credentials placeholders (to be populated from env vars)
   - Google Drive folder IDs
   - Backup paths (databases, app folders)
   - Compression settings
   - Logging configuration

3. Create `.env.example` documenting required environment variables:
   - `DB_HOST`, `DB_USER`, `DB_PASSWORD`
   - `GOOGLE_DRIVE_FOLDER_ID`
   - `BACKUP_TEMP_DIR`

### Phase 2: OAuth 2.0 Authentication Setup (Two-Step Process)
4. **Step 1 - Local Authentication (src/auth-setup.php):**
   - Load credentials.json
   - Initiate OAuth 2.0 authorization flow in browser
   - Display authorization URL for user to authenticate on local PC
   - Accept authorization code from user input (or callback redirect)
   - Exchange code for access_token and refresh_token
   - Save tokens to token.json with metadata (created_at, expires_in)
   - Output success message + instructions to upload token.json to hosting

5. **Step 2 - Hosted Token Management (src/GoogleDriveAuthenticator.php):**
   - Load credentials.json and token.json from hosting
   - Auto-refresh tokens before each backup run (proactive, not reactive)
   - Handle token refresh failures gracefully
   - Update token.json with new tokens after refresh
   - Token storage: `token.json` (gitignore'd, plain JSON format)

### Phase 3: Database Backup Function
7. Create `src/DatabaseBackup.php` class:
   - Method to dump each database via mysqldump CLI
   - Format: `{database_name}_{YYYYMMDD}_{HHMMSS}.sql`
   - Compress SQL file to ZIP (compression level 6)
   - Delete temp .sql file after zipping
   - Return path to final .zip file

### Phase 4: Application Backup Function
8. Create `src/ApplicationBackup.php` class:
   - Method to compress folders recursively
   - Folders: public_html/autorefreshauthapi, public_html/autorefreshappapi
   - Format: `{foldername}_{YYYYMMDD}_{HHMMSS}.zip`
   - Compression level 6
   - Return array of backup file paths

### Phase 5: Google Drive Upload & Organization
9. Create `src/GoogleDriveUploader.php` class:
   - Authenticate using GoogleDriveAuthenticator
   - Find or create folder structure: `/DOKUMEN/BUSINESS/BSCSteam/backups/{YYYYMMDD}/`
   - Upload backup files to dated folder
   - Handle API rate limiting / timeouts gracefully
   - Return array of uploaded file IDs for retention policy

### Phase 6: Retention Policy (Auto-Delete Old Backups)
10. Create `src/RetentionPolicy.php` class:
    - List all backups in Google Drive dated older than 30 days
    - Delete old backup files and empty dated folders
    - Track deletions in log (success or failure)

### Phase 6.5: Token Revocation Mechanism
10b. Create `src/TokenRevocation.php` class:
     - Method to revoke access token via Google OAuth2 API
     - Method to delete local token.json file
     - Log revocation events to backup.log
     - Handle revocation failures gracefully

11. Create `src/revoke-token.php` (CLI script):
    - Load token.json
    - Revoke token via Google API (optional, best-effort)
    - Delete token.json file
    - Output success message with instructions to re-authenticate

### Phase 7: Logging & Error Notification System
12. Create `src/Logger.php` class:
    - Log failures only (not successes) to `logs/backup.log`
    - Format: `[YYYY-MM-DD HH:MM:SS] ERROR: {message}`
    - Create log file if doesn't exist
    - Handle file write permissions gracefully

13. Create `src/EmailNotifier.php` class:
    - Send email notification on critical failures (token invalid, backup failed)
    - Use PHPMailer or native mail() function (shared hosting compatible)
    - Include backup log excerpt in email
    - Email template for error notifications

### Phase 8: Main Orchestration & Error Handling
14. Create `backup.php` (main entry point):
    - Load config from environment variables
    - Initialize all helpers (Auth, DB, App, Upload, Retention, Logger)
    - Orchestrate: Database Backup → App Backup → Upload → Retention
    - Catch and log exceptions
    - Exit with proper status code (0 = success, 1 = failure)
    - Cleanup temp files in `temp/` directory

### Phase 9: Cron Job Integration
15. Create setup documentation:
    - Add cron job example: `0 2 * * * /usr/bin/php7.2 /path/to/backup.php >> /dev/null 2>&1`
    - Document environment variable setup for cron (via .env or crontab)
    - Note PHP 7.2.23+ compatibility requirements
    - Document Composer install for PHP 7.2.23 compatible packages

### Phase 10: Testing & Documentation
16. Create/update README with:
    - Installation steps (local + hosting)
    - **OAuth Setup Workflow:**
      - Step 1: Run src/auth-setup.php on local PC to get tokens
      - Step 2: Upload token.json to hosting
      - Step 3: Set environment variables
      - Step 4: Test backup.php manually
      - Step 5: Add to cron job
    - Configuration guide
    - Token revocation instructions
    - Troubleshooting common errors
    - Google API setup walkthrough

---

## Relevant Files

**Files to create:**
- `src/auth-setup.php` — Local OAuth 2.0 authentication (run on local PC)
- `src/revoke-token.php` — Token revocation CLI script (revoke + delete token.json)
- `src/GoogleDriveAuthenticator.php` — Hosted token management with auto-refresh
- `src/DatabaseBackup.php` — mysqldump + compression
- `src/ApplicationBackup.php` — folder compression
- `src/GoogleDriveUploader.php` — upload orchestration
- `src/RetentionPolicy.php` — 30-day auto-delete logic
- `src/TokenRevocation.php` — Token revocation helper
- `src/Logger.php` — failure logging only
- `src/EmailNotifier.php` — email notifications on critical errors
- `backup.php` — main orchestration script (cron job)
- `config/config.example.php` — configuration template
- `.env.example` — environment variable template
- `logs/.gitkeep` — ensure logs directory exists
- `temp/.gitkeep` — ensure temp directory exists
- `.gitignore` updates — ignore token.json, logs/*, temp/*, .env
- Updated `README.md` — installation & usage guide

**Existing files to reference:**
- `credentials.json` — Google OAuth credentials (already set up)
- `composer.json` — add `google/apiclient` dependency (if not exists)

---

## Verification

1. **Unit-level verification:**
   - Test DatabaseBackup: manually run `mysqldump`, verify output format matches spec
   - Test ApplicationBackup: verify ZIP files are created with correct names and compression level
   - Test GoogleDriveAuthenticator: verify token.json is created after first auth
   - Test Logger: verify only failures are logged, format matches spec

2. **Integration verification:**
   - Run `php backup.php` from CLI locally, verify all steps execute
   - Check Google Drive for /DOKUMEN/BUSINESS/BSCSteam/backups/{YYYYMMDD}/ structure
   - Verify backup files are uploaded with correct naming
   - Run backup.php twice, verify retention policy deletes nothing (files < 30 days)

3. **Cron job verification:**
   - Add to cron, let it run once via schedule
   - Check `logs/backup.log` for failures (should be empty if successful)
   - Check Google Drive for new backups at scheduled time
   - Verify backup.log growth is minimal (only errors logged)

4. **30-day retention test:**
   - Manually rename backup folders in Drive to simulate old backups
   - Run backup.php, verify old folders are deleted
   - Verify retention.log entries confirm deletions

---

## Decisions

- **Single vs. separate scripts:** One monolithic script for simplicity & cron scheduling (user confirmed)
- **MySQL credentials:** Via environment variables (more secure than config file on shared hosting)
- **Compression:** Level 6 (balanced speed/size, user confirmed)
- **Databases:** ariq2418_autorefreshauthapidb, ariq2418_autorefreshappapidb (user specified)
- **Applications:** public_html/autorefreshauthapi, public_html/autorefreshappapi (user specified)
- **Retention:** 30 days auto-delete (user confirmed)
- **Logging:** Failures only (per spec), format: `[timestamp] ERROR: message`
- **Token storage:** token.json in repo root (gitignore'd), auto-refresh on each run
- **Error handling:** Exceptions caught & logged, script exits with status code for cron visibility
- **Temp files:** Cleaned up after backup completes (don't clutter shared hosting)
- **PHP version:** 7.2.23 or higher (user specified), all code patterns compatible with this version
- **OAuth 2.0 workflow:** Two-step process (local auth on PC → upload token.json to hosting) — no automatic re-auth
- **Token refresh:** Proactive auto-refresh before each backup (ensures token is always fresh, not reactive)
- **Token revocation:** Both API revocation (Google side) + local file deletion (hosting side)
- **Error notification:** Email notifications on critical failures (token invalid, backup failed)
- **Token security:** Plain JSON format (assumes HTTPS transfer from PC to hosting)

---

## PHP 7.2.23+ Compatibility & Package Versions

**Critical Code Constraints (for PHP 7.2.23):**
- **Scalar type hints:** Use compatible syntax (PHP 7.0+) — no union types or strict types
- **Nullable types:** Use `?Type` syntax (PHP 7.1+) — supported in 7.2.23
- **Anonymous classes:** Avoid (support varies)
- **void return type:** Supported in PHP 7.1+
- **AVOID:** Arrow functions (PHP 7.4+), match expressions (PHP 8.0+), named arguments (PHP 8.0+), property type declarations (PHP 7.4+)

**Verified Package Compatibility:**
- `google/apiclient` ^2.10 — Full OAuth 2.0 support for PHP 7.2+
- `guzzlehttp/guzzle` ^6.5 — HTTP client dependency, compatible with PHP 7.2+
- All transitive dependencies verified compatible with PHP 7.2.23

**Recommended composer.json:**
```json
{
    "require": {
        "php": ">=7.2.23",
        "google/apiclient": "^2.10"
    }
}