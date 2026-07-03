<?php
/**
 * RetentionPolicy.php - Auto-delete old backups from Google Drive
 * 
 * PHP 7.2.23+ compatible
 * Deletes backups older than specified days from Google Drive
 */

namespace BackupTool;

use Google_Service_Drive;
use Exception;

class RetentionPolicy
{
    private $service;
    private $parentFolderId;
    private $retentionDays;
    private $logger;

    public function __construct(Google_Service_Drive $service, $parentFolderId = '', $retentionDays = 30, Logger $logger = null)
    {
        $this->service = $service;
        $this->parentFolderId = $parentFolderId;
        $this->retentionDays = $retentionDays;
        $this->logger = $logger ?? new Logger();
    }

    /**
     * Delete backups older than retention days
     * 
     * @return bool True if successful
     */
    public function deleteOldBackups()
    {
        try {
            $cutoffDate = date('Ymd', strtotime("-{$this->retentionDays} days"));
            
            // Get all dated folders under backups/
            $folders = $this->getBackupFolders();

            $deletedCount = 0;
            foreach ($folders as $folder) {
                $folderName = $folder['name'];
                
                // Check if folder is older than cutoff date
                if ($folderName < $cutoffDate) {
                    if ($this->deleteFolder($folder['id'])) {
                        $this->logger->error("Deleted old backup folder: {$folderName}");
                        $deletedCount++;
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            $this->logger->error("Retention policy failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all dated backup folders under /backups/
     * 
     * @return array Array of folders with 'id' and 'name'
     */
    private function getBackupFolders()
    {
        try {
            $query = "mimeType='application/vnd.google-apps.folder' and trashed=false";
            
            if ($this->parentFolderId) {
                $query .= " and '{$this->parentFolderId}' in parents";
            }

            $results = $this->service->files->listFiles(array(
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'files(id, name)',
                'pageSize' => 100
            ));

            $folders = array();
            foreach ($results->getFiles() as $folder) {
                $folders[] = array(
                    'id' => $folder->getId(),
                    'name' => $folder->getName()
                );
            }

            return $folders;
        } catch (Exception $e) {
            $this->logger->error("Failed to get backup folders: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Recursively delete folder and all its contents
     * 
     * @param string $folderId Folder ID to delete
     * @return bool True if successful
     */
    private function deleteFolder($folderId = '')
    {
        try {
            // Get all files in folder
            $files = $this->getFilesInFolder($folderId);

            // Delete all files first
            foreach ($files as $file) {
                $this->service->files->delete($file['id']);
            }

            // Delete the folder itself
            $this->service->files->delete($folderId);

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to delete folder {$folderId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all files in a folder
     * 
     * @param string $folderId Folder ID
     * @return array Array of files with 'id' and 'name'
     */
    private function getFilesInFolder($folderId = '')
    {
        try {
            $query = "'{$folderId}' in parents and trashed=false";

            $results = $this->service->files->listFiles(array(
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'files(id, name)',
                'pageSize' => 1000
            ));

            $files = array();
            foreach ($results->getFiles() as $file) {
                $files[] = array(
                    'id' => $file->getId(),
                    'name' => $file->getName()
                );
            }

            return $files;
        } catch (Exception $e) {
            $this->logger->error("Failed to get files in folder: " . $e->getMessage());
            return array();
        }
    }
}
