<?php
/**
 * backup.php - Main backup orchestration script
 *
 * PHP 7.2.23+ compatible
 * Usage: php backup.php '<json-payload>'
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

function parseJsonPayload(array $argv)
{
    if (!isset($argv[1]) || trim($argv[1]) === '') {
        throw new Exception('No JSON payload provided to backup.php.');
    }

    $payload = json_decode($argv[1]);
    if ($payload === null || json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON payload passed to backup.php: ' . json_last_error_msg());
    }

    return $payload;
}

function applyPayloadEnv($payload)
{
    if (!isset($payload->env_file) || !is_object($payload->env_file)) {
        return;
    }

    foreach ($payload->env_file as $key => $value) {
        if ($value === null) {
            $value = '';
        }
        if (!is_scalar($value)) {
            continue;
        }

        $stringValue = (string)$value;
        @putenv("{$key}={$stringValue}");
        $_ENV[$key] = $stringValue;
        $_SERVER[$key] = $stringValue;
    }
}

function extractUploadFiles($payload)
{
    $uploadFiles = array();
    if (!isset($payload->upload_files) || !is_array($payload->upload_files)) {
        return $uploadFiles;
    }

    foreach ($payload->upload_files as $filePath) {
        if (is_string($filePath) && trim($filePath) !== '') {
            $uploadFiles[] = $filePath;
        }
    }

    return $uploadFiles;
}

$logger = new Logger(
    resolvePath(getEnvValue('LOG_DIR', './logs'), __DIR__ . DIRECTORY_SEPARATOR . 'logs', __DIR__),
    getEnvValue('LOG_FILE', 'backup.log')
);

$adminEmail = getenv('ADMIN_EMAIL') ?: '';
$emailNotifier = new EmailNotifier($adminEmail, $logger);

try {

    // Parse JSON payload from command line arguments
    $payload = parseJsonPayload($argv);

    // Use payload values directly to set configuration variables (object-style)
    $payloadEnv = isset($payload->env_file) && is_object($payload->env_file) ? $payload->env_file : (object)array();

    $googleDriveFolderId = (isset($payloadEnv->GOOGLE_DRIVE_FOLDER_ID) && $payloadEnv->GOOGLE_DRIVE_FOLDER_ID !== '')
        ? $payloadEnv->GOOGLE_DRIVE_FOLDER_ID
        : null;

    $backupTempDirVal = (isset($payloadEnv->BACKUP_TEMP_DIR) && $payloadEnv->BACKUP_TEMP_DIR !== '')
        ? $payloadEnv->BACKUP_TEMP_DIR
        : null;
    $backupTempDir = resolvePath($backupTempDirVal, __DIR__ . DIRECTORY_SEPARATOR . 'temp', __DIR__);

    $appRootVal = (isset($payloadEnv->APP_ROOT) && $payloadEnv->APP_ROOT !== '')
        ? $payloadEnv->APP_ROOT
        : null;
    $appRoot = resolvePath($appRootVal, __DIR__ . DIRECTORY_SEPARATOR . 'public_html', __DIR__);

    $compressionLevel = intval(isset($payloadEnv->COMPRESSION_LEVEL) ? $payloadEnv->COMPRESSION_LEVEL : getEnvValue('COMPRESSION_LEVEL', 6));
    $retentionDays = intval(isset($payloadEnv->RETENTION_DAYS) ? $payloadEnv->RETENTION_DAYS : getEnvValue('RETENTION_DAYS', 30));

    if (!$googleDriveFolderId) {
        $errorMessage = "Missing required environment variable: GOOGLE_DRIVE_FOLDER_ID";
        $logger->errorTerminal("{$errorMessage}");
        throw new Exception($errorMessage);
    }

    $auth = new GoogleDriveAuthenticator(__DIR__ . '/credentials.json', __DIR__ . '/token.json', $logger);
    $authResult = $auth->authenticate();
    $driveService = $auth->getDriveService();

    $uploader = new GoogleDriveUploader($driveService, $googleDriveFolderId, $logger);
    $retention = new RetentionPolicy($driveService, $googleDriveFolderId, $retentionDays, $logger);

    $uploadFiles = extractUploadFiles($payload);
    if (empty($uploadFiles)) {
        $errorMessage = "No upload_files were provided in the JSON payload.";
        $logger->error($errorMessage);
        $logger->errorTerminal($errorMessage);
        throw new Exception($errorMessage);
    }

    foreach ($uploadFiles as $filePath) {
        if (!file_exists($filePath)) {
            $errorMessage = "Provided backup file does not exist: {$filePath}";
            $logger->errorTerminal($errorMessage);
            $logger->error($errorMessage);
            continue;
        }

        $uploader->uploadBackup($payload->timestamp, $filePath, basename($filePath));
        @unlink($filePath);
    }

    // Retention policy cleanup
    $retention->deleteOldBackups();

    exit(0);
} catch (Exception $e) {
    $errorMessage = "Critical error in backup.php: " . $e->getMessage();
    $logger->errorTerminal($errorMessage);
    $logger->error($errorMessage);
    if ($emailNotifier->isEnabled()) {
        $emailNotifier->sendCriticalError('BackupFailure', $errorMessage);
    }
    exit(1);
}
