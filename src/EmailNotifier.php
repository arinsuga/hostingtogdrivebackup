<?php
/**
 * EmailNotifier.php - Email notifications for backup errors
 * 
 * PHP 7.2.23+ compatible
 * Sends email notifications on critical failures
 * Uses native mail() function (shared hosting compatible)
 */

namespace BackupTool;

use Exception;

class EmailNotifier
{
    private $adminEmail;
    private $logger;
    private $senderEmail;

    public function __construct($adminEmail = '', Logger $logger = null, $senderEmail = 'backup@backup-tool.local')
    {
        $this->adminEmail = $adminEmail;
        $this->logger = $logger ?? new Logger();
        $this->senderEmail = $senderEmail;
    }

    /**
     * Send error notification email
     * 
     * @param string $subject Email subject
     * @param string $errorMessage Error message body
     * @param array $logExcerpt Recent log lines
     * @return bool True if email sent successfully
     */
    public function sendErrorNotification($subject = '', $errorMessage = '', $logExcerpt = array())
    {
        try {
            if (!$this->adminEmail) {
                return false;
            }

            $body = $this->buildEmailBody($errorMessage, $logExcerpt);
            $headers = $this->buildEmailHeaders();

            $subject = $this->sanitizeSubject($subject);

            if (mail($this->adminEmail, $subject, $body, $headers)) {
                return true;
            } else {
                $this->logger->error("Failed to send email notification to: {$this->adminEmail}");
                return false;
            }
        } catch (Exception $e) {
            $this->logger->error("Email notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send critical error notification
     * 
     * @param string $errorType Type of error (e.g., 'TokenInvalid', 'BackupFailed')
     * @param string $errorMessage Detailed error message
     * @return bool True if email sent
     */
    public function sendCriticalError($errorType = '', $errorMessage = '')
    {
        $subject = "[BACKUP ERROR] {$errorType} - " . date('Y-m-d H:i:s');
        $logExcerpt = $this->logger->getLastLines(20);

        return $this->sendErrorNotification($subject, $errorMessage, $logExcerpt);
    }

    /**
     * Build email body with error details
     * 
     * @param string $errorMessage Main error message
     * @param array $logExcerpt Recent log lines
     * @return string Email body
     */
    private function buildEmailBody($errorMessage = '', $logExcerpt = array())
    {
        $timestamp = date('Y-m-d H:i:s');
        $hostname = gethostname();

        $body = "BACKUP ERROR NOTIFICATION\n";
        $body .= str_repeat("=", 50) . "\n\n";
        
        $body .= "Timestamp: {$timestamp}\n";
        $body .= "Host: {$hostname}\n\n";
        
        $body .= "ERROR MESSAGE:\n";
        $body .= str_repeat("-", 50) . "\n";
        $body .= $errorMessage . "\n\n";

        if (!empty($logExcerpt)) {
            $body .= "RECENT LOG ENTRIES:\n";
            $body .= str_repeat("-", 50) . "\n";
            foreach ($logExcerpt as $line) {
                $body .= $line . "\n";
            }
            $body .= "\n";
        }

        $body .= "ACTIONS:\n";
        $body .= "1. Check the backup logs at: logs/backup.log\n";
        $body .= "2. If token is invalid, run: php src/revoke-token.php\n";
        $body .= "3. Then authenticate: php src/auth-setup.php\n";
        $body .= "4. Re-upload token.json to hosting\n\n";

        $body .= "For support, check README.md in the backup tools directory.\n";

        return $body;
    }

    /**
     * Build email headers
     * 
     * @return string Email headers
     */
    private function buildEmailHeaders()
    {
        $headers = "From: {$this->senderEmail}\r\n";
        $headers .= "Reply-To: {$this->senderEmail}\r\n";
        $headers .= "X-Mailer: BackupTool-EmailNotifier\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        return $headers;
    }

    /**
     * Sanitize email subject (remove newlines, limit length)
     * 
     * @param string $subject Raw subject
     * @return string Sanitized subject
     */
    private function sanitizeSubject($subject = '')
    {
        // Remove newlines
        $subject = str_replace(array("\r", "\n"), '', $subject);
        
        // Limit to 78 characters
        if (strlen($subject) > 78) {
            $subject = substr($subject, 0, 75) . '...';
        }

        return $subject;
    }

    /**
     * Set admin email address
     * 
     * @param string $email Email address
     * @return void
     */
    public function setAdminEmail($email = '')
    {
        $this->adminEmail = $email;
    }

    /**
     * Check if email notifications are enabled
     * 
     * @return bool True if admin email is configured
     */
    public function isEnabled()
    {
        return !empty($this->adminEmail);
    }
}
