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

        if (!file_exists($this->credentialsFile)) {
            $errorMessage = "Credentials file not found: {$this->credentialsFile}";
            $this->logger->error("ERROR: {$errorMessage}");
            throw new Exception($errorMessage);
        }

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
        if (!file_exists($this->tokenFile)) {

            $errorMessage = "Token file not found: {$this->tokenFile}. Please run auth-setup.php first.";
            $this->logger->error("ERROR: {$errorMessage}");
            $this->logger->errorTerminal("ERROR: {$errorMessage}");
            throw new Exception($errorMessage);

        }

        try {
            $tokenData = json_decode(file_get_contents($this->tokenFile), true);
            
            if (!$tokenData) {

                $errorMessage = "Invalid token.json format. Please ensure the file is valid JSON.";
                $this->logger->error("{$errorMessage}");
                $this->logger->errorTerminal("{$errorMessage}");
                throw new Exception($errorMessage);

            }

            // Set the access token
            $this->client->setAccessToken($tokenData);


            // Proactive refresh: always refresh before each backup
            if ($this->client->isAccessTokenExpired()) {
                $errorMessage = "Access token expired, refreshing...";
                $this->logger->error("{$errorMessage}");
                $this->logger->errorTerminal("{$errorMessage}");
                $this->refreshToken();

            } else {

                $errorMessage = "Access token is valid, but refreshing proactively to ensure freshness...";
                $this->logger->error("{$errorMessage}");
                $this->logger->errorTerminal("{$errorMessage}");

                // Even if not expired, refresh proactively to ensure freshness
                $this->refreshToken();
            }

            $this->logger->success("Google Drive authentication successful, access token is valid and refreshed.");
            $this->logger->successTerminal("Google Drive authentication successful, access token is valid and refreshed.");

            return true;
        } catch (Exception $e) {

            $errorMessage = "Authentication failed: " . $e->getMessage();
            $this->logger->errorTerminal("{$errorMessage}");
            $this->logger->error("{$errorMessage}");
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
                $errorMessage = "No refresh token available. Please re-run auth-setup.php to obtain a new refresh token.";
                $this->logger->error("{$errorMessage}");
                $this->logger->errorTerminal("{$errorMessage}");
                throw new Exception($errorMessage);

            }

            // Refresh the access token
            $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            
            $tokenData = $this->client->getAccessToken();
            
            // Save updated token
            if (!file_put_contents($this->tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {

                $errorMessage = "Failed to save refreshed token to file: {$this->tokenFile}";
                $this->logger->error("{$errorMessage}");
                $this->logger->errorTerminal("{$errorMessage}");
                throw new Exception($errorMessage);

            }

            return true;
        } catch (Exception $e) {

            $errorMessage = "Token refresh failed: " . $e->getMessage();
            $this->logger->errorTerminal("{$errorMessage}");
            $this->logger->error("{$errorMessage}");
            throw new Exception($errorMessage);
            
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
