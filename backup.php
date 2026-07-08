<?php
/**
 * backup.php - Main backup orchestration script
 *
 * PHP 7.2.23+ compatible
 * Usage: php backup.php <zip-file> [<zip-file> ...]
 */

require_once __DIR__ . '/vendor/autoload.php';

use BackupTool\Logger;
use BackupTool\EmailNotifier;
use BackupTool\GoogleDriveAuthenticator;
use BackupTool\GoogleDriveUploader;
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

function resolvePath($value, $default, $baseDir = null)
{
    if ($value === null || $value === '') {
        return $default;
    }

    if (substr($value, 0, 1) === '/' || preg_match('/^[A-Za-z]:[\\/]/', $value)) {
        return $value;
    }

    $base = $baseDir !== null ? rtrim($baseDir, '/\\') : __DIR__;
    return $base . DIRECTORY_SEPARATOR . ltrim($value, '/\\');
}

$logger = new Logger(
    resolvePath(getEnvValue('LOG_DIR', './logs'), __DIR__ . DIRECTORY_SEPARATOR . 'logs', __DIR__),
    getEnvValue('LOG_FILE', 'backup.log')
);

$logger->debugTerminal("===== Start backup.php =====");
$adminEmail = getenv('ADMIN_EMAIL') ?: '';
$emailNotifier = new EmailNotifier($adminEmail, $logger);

try {

    $googleDriveFolderId = getEnvValue('GOOGLE_DRIVE_FOLDER_ID');
    $backupTempDir = resolvePath(getEnvValue('BACKUP_TEMP_DIR', 'temp'), __DIR__ . DIRECTORY_SEPARATOR . 'temp', __DIR__);
    $appRoot = resolvePath(getEnvValue('APP_ROOT', __DIR__ . '/public_html'), __DIR__ . DIRECTORY_SEPARATOR . 'public_html', __DIR__);
    $compressionLevel = intval(getEnvValue('COMPRESSION_LEVEL', 6));
    $retentionDays = intval(getEnvValue('RETENTION_DAYS', 30));

    $logger->debugTerminal("backup.php - GOOGLE_DRIVE_FOLDER_ID={$googleDriveFolderId}");
    $logger->debugTerminal("backup.php - BACKUP_TEMP_DIR={$backupTempDir}");
    $logger->debugTerminal("backup.php - APP_ROOT={$appRoot}");
    $logger->debugTerminal("backup.php - COMPRESSION_LEVEL={$compressionLevel}");
    $logger->debugTerminal("backup.php - RETENTION_DAYS={$retentionDays}");

    


    if (!$googleDriveFolderId) {
        $errorMessage = "Missing required environment variable: GOOGLE_DRIVE_FOLDER_ID";
        $logger->errorTerminal("{$errorMessage}");
        throw new Exception($errorMessage);
    }

    $auth = new GoogleDriveAuthenticator(__DIR__ . '/credentials.json', __DIR__ . '/token.json', $logger);
    $authResult = $auth->authenticate();
    $logger->debugTerminal("backup.php - Google Drive authentication successful, Drive service initialized.");


    $driveService = $auth->getDriveService();
    $logger->debugTerminal("backup.php - Google Drive service initialized successfully.");

    $uploader = new GoogleDriveUploader($driveService, $googleDriveFolderId, $logger);
    $logger->debugTerminal("backup.php - Google Drive uploader initialized successfully.");

    // Database and application dumps are produced by external script (backup.sh).
    // This script will upload the ZIP files that `backup.sh` created in the temp directory.
    $logger->debugTerminal("backup.php - Ready to upload ZIPs from: {$backupTempDir}");

    $retention = new RetentionPolicy($driveService, $googleDriveFolderId, $retentionDays, $logger);
    $logger->debugTerminal("backup.php - Retention policy initialized successfully.");

    $uploadedFiles = array_slice($argv, 1);
    if (empty($uploadedFiles)) {
        $logger->errorTerminal("No backup ZIP files were provided to backup.php.");
        throw new Exception('No backup ZIP files were provided to backup.php.');
    }

    $logger->debugTerminal('backup.php - Files passed for upload: ' . implode(', ', $uploadedFiles));

    foreach ($uploadedFiles as $filePath) {
        if (!is_string($filePath) || trim($filePath) === '') {
            continue;
        }

        if (!file_exists($filePath)) {
            $logger->error("Provided backup file does not exist: {$filePath}");
            continue;
        }

        $logger->debugTerminal("backup.php - Uploading provided ZIP: {$filePath}");
        $uploader->uploadBackup($filePath, basename($filePath));
        @unlink($filePath);
    }

    $logger->debugTerminal("backup.php - All provided backup ZIP files processed successfully.");

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
