<?php
/**
 * ApplicationBackup.php - Application folder backup
 * 
 * PHP 7.2.23+ compatible
 * Recursively compresses folders to ZIP with specified compression level
 */

namespace BackupTool;

use Exception;

class ApplicationBackup
{
    private $tempDir;
    private $compressionLevel;
    private $logger;

    public function __construct($tempDir = '/tmp', $compressionLevel = 6, Logger $logger = null)
    {
        $this->tempDir = rtrim($tempDir, '/');
        $this->compressionLevel = max(1, min(9, $compressionLevel));
        $this->logger = $logger ?? new Logger();

        if (!is_dir($this->tempDir)) {
            @mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Backup application folder
     * Format: {foldername}_{YYYYMMDD}_{HHMMSS}.zip
     * 
     * @param string $folderPath Full path to folder to backup
     * @param string $folderName Display name of folder (e.g., 'autorefreshauthapi')
     * @return string Path to backup ZIP file
     * @throws Exception
     */
    public function backup($folderPath = '', $folderName = '')
    {
        try {
            if (!is_dir($folderPath)) {
                throw new Exception("Folder not found: {$folderPath}");
            }

            $timestamp = date('Ymd_His');
            $zipFilename = $folderName . '_' . $timestamp . '.zip';
            $zipPath = $this->tempDir . '/' . $zipFilename;

            // Compress folder to ZIP
            $this->compressFolder($folderPath, $zipPath);

            if (!file_exists($zipPath)) {
                throw new Exception("ZIP file not created: {$zipPath}");
            }

            return $zipPath;
        } catch (Exception $e) {
            $this->logger->error("Application backup failed for '{$folderName}': " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Recursively compress folder to ZIP
     * 
     * @param string $folderPath Path to folder
     * @param string $zipPath Path to output ZIP file
     * @return bool True if successful
     * @throws Exception
     */
    private function compressFolder($folderPath = '', $zipPath = '')
    {
        try {
            $zip = new \ZipArchive();
            
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new Exception("Cannot create ZIP file: {$zipPath}");
            }

            $zip->setCompressionLevel($this->compressionLevel);
            
            // Recursively add files
            $this->addFilesToZip($zip, $folderPath, '');

            $zip->close();
            return true;
        } catch (Exception $e) {
            throw new Exception("ZIP compression failed: " . $e->getMessage());
        }
    }

    /**
     * Recursively add files to ZIP archive
     * 
     * @param \ZipArchive $zip ZIP archive instance
     * @param string $folderPath Current folder path
     * @param string $basePath Base path for ZIP structure
     * @return void
     */
    private function addFilesToZip(\ZipArchive $zip, $folderPath = '', $basePath = '')
    {
        $files = scandir($folderPath);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $folderPath . '/' . $file;
            $zipPath = $basePath === '' ? $file : $basePath . '/' . $file;

            if (is_dir($filePath)) {
                $zip->addEmptyDir($zipPath);
                $this->addFilesToZip($zip, $filePath, $zipPath);
            } else {
                $zip->addFile($filePath, $zipPath);
            }
        }
    }
}
