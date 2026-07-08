<?php
/**
 * GoogleDriveAuthenticator.php - Google Drive OAuth 2.0 token management
 * 
 * PHP 7.2.23+ compatible
 * Handles token refresh, loading, and storage
 * Auto-refreshes tokens before each backup run (proactive approach)
 */

namespace BackupTool;

use Google_Client;
use Exception;

class GoogleDriveAuthenticator
{
    private $client;
    private $credentialsFile;
    private $tokenFile;
    private $logger;

    public function __construct($credentialsFile = './credentials.json', $tokenFile = './token.json', Logger $logger = null)
    {
        $this->credentialsFile = $credentialsFile;
        $this->tokenFile = $tokenFile;
        $this->logger = $logger ?? new Logger();

        $this->logger->error("Initializing GoogleDriveAuthenticator with credentials: {$this->credentialsFile} and token: {$this->tokenFile}");

        if (!file_exists($this->credentialsFile)) {
            $errorMessage = "Credentials file not found: {$this->credentialsFile}";
            $this->logger->error("ERROR: {$errorMessage}");
            throw new Exception($errorMessage);
        }

        $this->logger->error("Loading Google Client with credentials from: {$this->credentialsFile}");
        $this->client = new Google_Client();
        $this->client->setAuthConfig($this->credentialsFile);
        $this->client->setScopes(array('https://www.googleapis.com/auth/drive'));
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    /**
     * Load token from file and refresh if needed
     * Proactive refresh: always refresh before backup to ensure token is fresh
     * 
     * @return bool True if token loaded/refreshed successfully
     * @throws Exception
     */
    public function authenticate()
    {
        $this->logger->error("Authenticating Google Drive client using token file: {$this->tokenFile}");
        if (!file_exists($this->tokenFile)) {
            $errorMessage = "Token file not found: {$this->tokenFile}. Please run auth-setup.php first.";
            $this->logger->error("ERROR: {$errorMessage}");
            throw new Exception($errorMessage);
        }

        try {
            $tokenData = json_decode(file_get_contents($this->tokenFile), true);
            
            if (!$tokenData) {
                $this->logger->error("ERROR: Invalid token.json format");
                throw new Exception("Invalid token.json format");
            }

            $this->logger->error("Setting access token for Google Client");
            // Set the access token
            $this->client->setAccessToken($tokenData);


            $this->logger->error("Checking if access token is expired or needs refresh");
            // Proactive refresh: always refresh before each backup
            if ($this->client->isAccessTokenExpired()) {
                $this->logger->error("Access token expired, refreshing...");
                $this->refreshToken();
            } else {
                $this->logger->error("Access token is valid, but refreshing proactively to ensure freshness...");
                // Even if not expired, refresh proactively to ensure freshness
                $this->refreshToken();
            }

            $this->logger->error("Google Drive authentication successful, access token is valid and refreshed.");
            return true;
        } catch (Exception $e) {
            $this->logger->error("Authentication failed: " . $e->getMessage());
            throw new Exception("Authentication error: " . $e->getMessage());
        }
    }

    /**
     * Refresh access token using refresh token
     * Updates token.json with new tokens
     * 
     * @return bool True if refresh successful
     * @throws Exception
     */
    public function refreshToken()
    {
        try {
            if (!$this->client->getRefreshToken()) {
                throw new Exception("No refresh token available");
            }

            // Refresh the access token
            $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            
            $tokenData = $this->client->getAccessToken();
            
            // Save updated token
            if (!file_put_contents($this->tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                throw new Exception("Failed to save token file");
            }

            return true;
        } catch (Exception $e) {
            $this->logger->error("Token refresh failed: " . $e->getMessage());
            throw new Exception("Token refresh error: " . $e->getMessage());
        }
    }

    /**
     * Get authenticated Google Drive service
     * 
     * @return \Google_Service_Drive Google Drive service instance
     */
    public function getDriveService()
    {
        return new \Google_Service_Drive($this->client);
    }

    /**
     * Get current access token
     * 
     * @return string Access token
     */
    public function getAccessToken()
    {
        return $this->client->getAccessToken()['access_token'];
    }

    /**
     * Get token file path
     * 
     * @return string Token file path
     */
    public function getTokenFile()
    {
        return $this->tokenFile;
    }
}
