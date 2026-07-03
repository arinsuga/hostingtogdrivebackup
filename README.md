# Hosting to Google Drive Backup Tools

Backup tools for application folders and MySQL databases on shared hosting, with upload to Google Drive.

## Requirements

- PHP 7.2.23 or higher
- Composer installed
- Google API credentials (`credentials.json`)
- `mysqldump` available on hosting
- `zip`/`ZipArchive` enabled in PHP
- `curl` enabled for token revocation and Google API calls

## Installation

1. Copy the project to your hosting directory.
2. Install dependencies:
   ```bash
   composer install
   ```
3. Create `.env` from `.env.example` and fill in values.
4. Ensure `credentials.json` is present in the project root.
5. On your local PC, run:
   ```bash
   php src/auth-setup.php
   ```
6. Upload the generated `token.json` to hosting.

## Usage

### Manual backup

```bash
php backup.php
```

### Schedule via cron

Example cron entry:

```cron
0 2 * * * /usr/bin/php7.2 /path/to/backup.php >> /dev/null 2>&1
```

### Revoke token

```bash
php src/revoke-token.php
```

Then re-authenticate using `php src/auth-setup.php` and upload the new `token.json`.

## Folder backup targets

The script backs up:
- `public_html/autorefreshauthapi`
- `public_html/autorefreshappapi`

Update `APP_ROOT` in `.env` if your public_html is located elsewhere.

## Databases

The script backs up these databases by default:
- `ariq2418_autorefreshauthapidb`
- `ariq2418_autorefreshappapidb`

Change database names in `backup.php` if needed.

## Logging

Failure logs are written to `logs/backup.log`.
Only errors are logged.

## Notes

- `token.json` is not committed; it is ignored by `.gitignore`.
- Upload `token.json` manually to hosting after running `src/auth-setup.php` on your local machine.
- The script refreshes tokens proactively before each backup run.
