---
name: hostingtogdrivebackup
description: A skill for develope Tools for Backup App and DB to GDrive on Shared Hosting using PHP 7.4
---

# Skill: PHP Backup to Google Drive

## Purpose
Membuat tools backup aplikasi dan database MySQL di shared hosting menggunakan PHP 7.4, dengan upload otomatis ke Google Drive via API.

## Environment
- **Hosting:** Shared hosting dengan cron job support
- **Language:** PHP 7.4 (CLI mode)
- **Database:** MySQL/MariaDB
- **IDE:** VS Code + GitHub Copilot coding agent
- **Version Control:** GitHub

## Requirements
- Composer untuk install `google/apiclient`
- OAuth 2.0 credentials (`credentials.json`)
- Token refresh otomatis (`token.json`)
- Access ke `mysqldump` via exec()

## Workflow
1. **Dump Database**
   - Gunakan `mysqldump` untuk export `.sql`

2. **Compress Application**
   - Gunakan `zip` untuk compress

3. **Authenticate Google Drive API**
   - Load `credentials.json` dan `token.json`
   - Refresh token otomatis jika expired

4. **Upload to Google Drive**
   - Buat folder `/Backups/{YYYYMMDD}`
   - Upload ke folder tersebut

5. **Logging**
   - Simpan status gagal ke `backup.log`
   - Format: `[timestamp] ERROR: message`

6. **Error Handling**
   - Tangkap exception dari API
   - Tulis pesan error ke log
