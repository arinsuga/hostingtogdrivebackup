<?php
/**
 * GoogleDriveUploader.php - Upload backups to Google Drive
 * 
 * PHP 7.2.23+ compatible
 * Uploads backup files to Google Drive with proper folder structure
 */

namespace BackupTool;

use BackupTool\Logger;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Exception;

class GoogleDriveUploader
{
    private $service;
    private $parentFolderId;
    private $logger;

    public function __construct(Google_Service_Drive $service, $parentFolderId = '', Logger $logger = null)
    {
        $this->service = $service;
        $this->parentFolderId = $parentFolderId;
        $this->logger = $logger ?? new Logger();
    }

    /**
     * Upload backup file to Google Drive
     * Creates dated folder structure: /backups/YYYYMMDD/
     * 
     * @param string $filePath Local file path to upload
     * @param string $fileName Display name in Google Drive
     * @return string File ID of uploaded file
     * @throws Exception
     */
    public function uploadBackup($backupDir = '', $filePath = '', $fileName = '')
    {
        try {
            if (!file_exists($filePath)) {
                throw new Exception("File not found: {$filePath}");
            }

            // Get or create dated folder
            $dateFolderName = $backupDir;
            $dateFolderId = $this->getOrCreateFolder($dateFolderName, $this->parentFolderId);

            if (!$dateFolderId) {
                throw new Exception("Failed to create/get date folder: {$dateFolderName}");
            }

            // Upload file to dated folder
            $fileId = $this->uploadFile($filePath, $fileName, $dateFolderId);

            return $fileId;
        } catch (Exception $e) {
            $errorMessage = "GoogleDriveUploader.php - Google Drive upload failed for '{$fileName}': " . $e->getMessage();
            $this->logger->errorTerminal($errorMessage);
            $this->logger->error($errorMessage);
            throw $e;
        }
    }

    /**
     * Get or create folder in Google Drive
     * 
     * @param string $folderName Folder name
     * @param string $parentId Parent folder ID
     * @return string|null Folder ID or null if failed
     */
    public function getOrCreateFolder($folderName = '', $parentId = '')
    {
        try {
            // If parentId is actually a path, resolve or create the folder path first.
            if ($parentId && preg_match('#[\\/]+#', $parentId)) {
                $parentId = $this->ensureFolderPath($parentId, 'root');
            }

            // Check if folder exists
            $folderId = $this->findFolder($folderName, $parentId);
            
            if ($folderId) {
                return $folderId;
            }

            // Create folder
            $folder = new Google_Service_Drive_DriveFile();
            $folder->setName($folderName);
            $folder->setMimeType('application/vnd.google-apps.folder');
            
            if ($parentId) {
                $folder->setParents(array($parentId));
            }

            $createdFolder = $this->service->files->create($folder, array(
                'fields' => 'id'
            ));

            return $createdFolder->getId();
        } catch (Exception $e) {

            $errorMessage = "GoogleDriveUploader.php - Failed to create folder '{$folderName}': " . $e->getMessage();
            $this->logger->errorTerminal($errorMessage);
            $this->logger->error($errorMessage);
            return null;
            
        }
    }

    /**
     * Find folder by name in parent folder
     * 
     * @param string $folderName Folder name to search
     * @param string $parentId Parent folder ID
     * @return string|null Folder ID or null if not found
     */
    private function findFolder($folderName = '', $parentId = '')
    {
        try {
            $query = "name='{$folderName}' and mimeType='application/vnd.google-apps.folder' and trashed=false";
            
            if ($parentId) {
                $query .= " and '{$parentId}' in parents";
            }

            $results = $this->service->files->listFiles(array(
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'files(id)',
                'pageSize' => 1
            ));

            $files = $results->getFiles();
            
            if (count($files) > 0) {
                return $files[0]->getId();
            }

            return null;
        } catch (Exception $e) {
            $errorMessage = "GoogleDriveUploader.php - Folder search failed: " . $e->getMessage();
            $this->logger->errorTerminal($errorMessage);
            $this->logger->error($errorMessage);
            return null;
        }
    }

    /**
     * Upload file to Google Drive
     * 
     * @param string $filePath Local file path
     * @param string $fileName File name in Google Drive
     * @param string $parentId Parent folder ID
     * @return string File ID
     * @throws Exception
     */
    private function uploadFile($filePath = '', $fileName = '', $parentId = '')
    {
        try {
            $file = new Google_Service_Drive_DriveFile();
            $file->setName($fileName);
            
            if ($parentId) {
                $file->setParents(array($parentId));
            }

            $content = file_get_contents($filePath);

            $createdFile = $this->service->files->create($file, array(
                'data' => $content,
                'uploadType' => 'multipart',
                'fields' => 'id'
            ));

            return $createdFile->getId();
        } catch (Exception $e) {
            $errorMessage = "GoogleDriveUploader.php - File upload failed: " . $e->getMessage();
            $this->logger->errorTerminal($errorMessage);
            $this->logger->error($errorMessage);
            throw new Exception($errorMessage);
        }
    }

    /**
     * Find folder by ID hierarchy path
     * Searches for: /DOKUMEN/BUSINESS/BSCSteam/backups/
     * 
     * @param array $folderPath Array of folder names in hierarchy
     * @param string $startFromId Starting folder ID (usually root 'root')
     * @return string|null Final folder ID or null if not found
     */
    public function findFolderByPath($folderPath = array(), $startFromId = 'root')
    {
        $currentId = $startFromId;

        foreach ($folderPath as $folderName) {
            $currentId = $this->findFolder($folderName, $currentId);
            
            if (!$currentId) {
                return null;
            }
        }

        return $currentId;
    }

    private function ensureFolderPath($folderPath, $startFromId = 'root')
    {
        $segments = array_values(array_filter(preg_split('#[\\/]+#', $folderPath)));
        $currentId = $startFromId;

        foreach ($segments as $segment) {
            $existing = $this->findFolder($segment, $currentId);
            if ($existing) {
                $currentId = $existing;
                continue;
            }

            $folder = new Google_Service_Drive_DriveFile();
            $folder->setName($segment);
            $folder->setMimeType('application/vnd.google-apps.folder');

            if ($currentId) {
                $folder->setParents(array($currentId));
            }

            $createdFolder = $this->service->files->create($folder, array('fields' => 'id'));
            $currentId = $createdFolder->getId();
        }

        return $currentId;
    }
}
