<?php
/**
 * TokenRevocation.php - Revoke Google OAuth tokens
 * 
 * PHP 7.2.23+ compatible
 * Handles token revocation via Google API and local file deletion
 */

namespace BackupTool;

use Google_Client;
use Exception;

class TokenRevocation
{
    private $client;
    private $tokenFile;
    private $logger;

    public function __construct($tokenFile = './token.json', Logger $logger = null)
    {
        $this->tokenFile = $tokenFile;
        $this->logger = $logger ?? new Logger();
    }

    /**
     * Revoke token via Google OAuth2 API (best-effort)
     * Then delete local token.json file
     * 
     * @return bool True if successful
     */
    public function revoke()
    {
        try {
            // Try to revoke via Google API (best-effort)
            $this->revokeViaAPI();

            // Delete local token file
            return $this->deleteTokenFile();
        } catch (Exception $e) {
            $this->logger->error("Token revocation error: " . $e->getMessage());
            
            // Even if API revocation fails, try to delete local token
            return $this->deleteTokenFile();
        }
    }

    /**
     * Revoke token via Google API
     * 
     * @return bool True if successful
     * @throws Exception
     */
    private function revokeViaAPI()
    {
        try {
            if (!file_exists($this->tokenFile)) {
                return true;
            }

            $tokenData = json_decode(file_get_contents($this->tokenFile), true);
            
            if (!$tokenData || !isset($tokenData['access_token'])) {
                throw new Exception("Invalid token.json format");
            }

            $accessToken = $tokenData['access_token'];

            // Revoke via Google API
            $revokeUrl = 'https://oauth2.googleapis.com/revoke?token=' . $accessToken;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $revokeUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $this->logger->error("Token revoked successfully via Google API");
                return true;
            } else {
                $this->logger->error("Google API revocation returned HTTP {$httpCode}");
                return false;
            }
        } catch (Exception $e) {
            $this->logger->error("API revocation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete local token.json file
     * 
     * @return bool True if successful
     */
    private function deleteTokenFile()
    {
        try {
            if (!file_exists($this->tokenFile)) {
                return true;
            }

            if (!unlink($this->tokenFile)) {
                throw new Exception("Failed to delete token file");
            }

            $this->logger->error("Token file deleted: {$this->tokenFile}");
            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to delete token file: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if token file exists
     * 
     * @return bool True if token file exists
     */
    public function tokenExists()
    {
        return file_exists($this->tokenFile);
    }
}
