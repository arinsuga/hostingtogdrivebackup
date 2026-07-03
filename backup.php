<?php
/**
 * backup.php - Main backup orchestration script
 *
 * PHP 7.2.23+ compatible
 * Usage: php backup.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use BackupTool\Logger;
use BackupTool\EmailNotifier;
use BackupTool\GoogleDriveAuthenticator;
use BackupTool\GoogleDriveUploader;
use BackupTool\DatabaseBackup;
use BackupTool\ApplicationBackup;
use BackupTool\RetentionPolicy;

// Load environment variables from .env if present
if (!function_exists('loadEnvFile')) {
    function loadEnvFile($filename)
    {
        $lines = @file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        $result = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }

            $equalsPos = strpos($line, '=');
            if ($equalsPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $equalsPos));
            $value = trim(substr($line, $equalsPos + 1));

            if ($key === '') {
                continue;
            }

            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') || (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            $result[$key] = $value;
        }

        return $result;
    }
}

$loadedEnv = array();
if (file_exists(__DIR__ . '/.env')) {
    $loadedEnv = loadEnvFile(__DIR__ . '/.env');
    if ($loadedEnv === false) {
        error_log('Failed to parse .env file: ' . __DIR__ . '/.env');
        $loadedEnv = array();
    }
    if (is_array($loadedEnv)) {
        foreach ($loadedEnv as $key => $value) {
            @putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

function getEnvValue($key, $default = null)
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }
    global $loadedEnv;
    if (isset($loadedEnv[$key]) && $loadedEnv[$key] !== '') {
        return $loadedEnv[$key];
    }
    return $default;
}

$logger = new Logger(
    getenv('LOG_DIR') ?: './logs',
    getenv('LOG_FILE') ?: 'backup.log'
);

$adminEmail = getenv('ADMIN_EMAIL') ?: '';
$emailNotifier = new EmailNotifier($adminEmail, $logger);

try {

    $googleDriveFolderId = getEnvValue('GOOGLE_DRIVE_FOLDER_ID');
    $backupTempDir = getEnvValue('BACKUP_TEMP_DIR', sys_get_temp_dir());
    $appRoot = getEnvValue('APP_ROOT', __DIR__ . '/public_html');
    $compressionLevel = intval(getEnvValue('COMPRESSION_LEVEL', 6));
    $retentionDays = intval(getEnvValue('RETENTION_DAYS', 30));


    if (!$googleDriveFolderId) {
        throw new Exception('Missing required environment variable: GOOGLE_DRIVE_FOLDER_ID');
    }

    $auth = new GoogleDriveAuthenticator(__DIR__ . '/credentials.json', __DIR__ . '/token.json', $logger);
    $auth->authenticate();
    $driveService = $auth->getDriveService();

    $uploader = new GoogleDriveUploader($driveService, $googleDriveFolderId, $logger);
    // Database dumps are produced by external script (backup.sh). DatabaseBackup will only compress existing SQL files.
    $dbBackup = new DatabaseBackup('', '', '', $backupTempDir, $compressionLevel, $logger);
    $appBackup = new ApplicationBackup($backupTempDir, $compressionLevel, $logger);
    $retention = new RetentionPolicy($driveService, $googleDriveFolderId, $retentionDays, $logger);

    // Load database and application backup lists from JSON
    $backupsConfigFile = __DIR__ . '/backups.json';
    if (!file_exists($backupsConfigFile)) {
        $message = "Missing required backups config file: {$backupsConfigFile}";
        $logger->error($message);
        throw new Exception($message);
    }

    $json = @file_get_contents($backupsConfigFile);
    $cfg = $json ? json_decode($json, true) : null;
    if (!is_array($cfg)) {
        $message = "Invalid JSON in backups config file: {$backupsConfigFile}";
        $logger->error($message);
        throw new Exception($message);
    }

    if (!isset($cfg['databases']) || !is_array($cfg['databases'])) {
        $message = "Missing or invalid 'databases' section in backups config file: {$backupsConfigFile}";
        $logger->error($message);
        throw new Exception($message);
    }

    if (!isset($cfg['app_folders']) || !is_array($cfg['app_folders'])) {
        $message = "Missing or invalid 'app_folders' section in backups config file: {$backupsConfigFile}";
        $logger->error($message);
        throw new Exception($message);
    }

    $databases = $cfg['databases'];
    $appFolders = $cfg['app_folders'];

    foreach ($databases as $database) {
        $zipPath = $dbBackup->backup($database);
        $uploader->uploadBackup($zipPath, basename($zipPath));
        @unlink($zipPath);
    }

    foreach ($appFolders as $folderName) {
        $folderPath = rtrim($appRoot, '/') . '/' . $folderName;
        $zipPath = $appBackup->backup($folderPath, $folderName);
        $uploader->uploadBackup($zipPath, basename($zipPath));
        @unlink($zipPath);
    }

    // Retention policy cleanup
    $retention->deleteOldBackups();

    exit(0);
} catch (Exception $e) {
    $logger->error($e->getMessage());
    if ($emailNotifier->isEnabled()) {
        $emailNotifier->sendCriticalError('BackupFailure', $e->getMessage());
    }
    exit(1);
}
