#!/bin/sh
rm backuptools.zip
7z a backuptools.zip src logs temp vendor .env backup.sh backup.php backups.json composer.json composer.lock credentials.json token.json tes.sh
# 7z a backuptools.zip src backup.sh backup.php
