<?php
/**
 * DatabaseBackup.php - MySQL database backup using mysqldump
 * 
 * PHP 7.2.23+ compatible
 * Dumps database using mysqldump CLI, compresses to ZIP, and cleans up
 */

namespace BackupTool;

use Exception;

class DatabaseBackup
{
    private $host;
    private $user;
    private $password;
    private $tempDir;
    private $compressionLevel;
    private $logger;

    public function __construct($host, $user, $password, $tempDir = '/tmp', $compressionLevel = 6, Logger $logger = null)
    {
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->tempDir = rtrim($tempDir, '/');
        $this->compressionLevel = max(1, min(9, $compressionLevel));
        $this->logger = $logger ?? new Logger();

        // Ensure temp directory exists
        if (!is_dir($this->tempDir)) {
            @mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Backup a single database
     * Format: {database_name}_{YYYYMMDD}_{HHMMSS}.zip
     * 
     * @param string $database Database name to backup
     * @return string Path to backup ZIP file
     * @throws Exception
     */
    public function backup($database = '')
    {
        try {
            $timestamp = date('Ymd_His');
            $sqlFilename = $database . '_' . $timestamp . '.sql';
            $sqlPath = $this->tempDir . '/' . $sqlFilename;
            $zipFilename = $database . '_' . $timestamp . '.zip';
            $zipPath = $this->tempDir . '/' . $zipFilename;
            // Expect an existing SQL dump file produced by external tool (backup.sh)
            // Find the most recent SQL file for this database in temp dir
            $pattern = $this->tempDir . '/' . $database . '_*.sql';
            $matches = glob($pattern);
            if (empty($matches)) {
                throw new Exception("SQL dump file not found for database {$database} in {$this->tempDir}");
            }
            // pick the latest by modification time
            usort($matches, function ($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            $sqlPath = $matches[0];
            $zipFilename = $database . '_' . date('Ymd_His', filemtime($sqlPath)) . '.zip';
            $zipPath = $this->tempDir . '/' . $zipFilename;

            // Compress SQL to ZIP
            $this->compressToZip($sqlPath, $zipPath);

            // Delete temp SQL file
            @unlink($sqlPath);

            if (!file_exists($zipPath)) {
                throw new Exception("ZIP file not created: {$zipPath}");
            }

            return $zipPath;
        } catch (Exception $e) {
            $this->logger->error("Database backup failed for '{$database}': " . $e->getMessage());
            throw $e;
        }
    }

    // Mysqldump and shell execution removed. Compression-only class now.

    /**
     * Compress SQL file to ZIP with specified compression level
     * 
     * @param string $sqlFile Path to SQL file
     * @param string $zipFile Path to output ZIP file
     * @return bool True if successful
     * @throws Exception
     */
    private function compressToZip($sqlFile = '', $zipFile = '')
    {
        try {
            $zip = new \ZipArchive();
            
            if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new Exception("Cannot create ZIP file: {$zipFile}");
            }

            $zip->setCompressionLevel($this->compressionLevel);
            
            if (!$zip->addFile($sqlFile, basename($sqlFile))) {
                $zip->close();
                throw new Exception("Cannot add file to ZIP: {$sqlFile}");
            }

            $zip->close();
            return true;
        } catch (Exception $e) {
            throw new Exception("ZIP compression failed: " . $e->getMessage());
        }
    }
}
