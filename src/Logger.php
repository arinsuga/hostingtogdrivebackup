<?php
/**
 * Logger.php - Failure logging for backup operations
 * 
 * PHP 7.2.23+ compatible
 * Logs only failures to logs/backup.log in format: [YYYY-MM-DD HH:MM:SS] ERROR: {message}
 */

namespace BackupTool;

class Logger
{
    private $logFile;
    private $logDir;

    public function __construct($logDir = './logs', $logFile = 'backup.log')
    {
        $this->logDir = $logDir;
        $this->logFile = $logDir . '/' . $logFile;

        // Ensure log directory exists
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
    }

    public function success($message = '')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] SUCCESS: {$message}\n";

        return $this->write($logEntry);
    }

    public function successTerminal($message = '')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] SUCCESS: {$message}\n";

        echo $logEntry;
    }

    public function error($message = '')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] ERROR: {$message}\n";

        return $this->write($logEntry);
    }

    public function errorTerminal($message = '')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] ERROR: {$message}\n";

        echo $logEntry;
    }

    public function debug($message = '')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] DEBUG: {$message}\n";

        return $this->write($logEntry);
    }

    public function debugTerminal($message = '')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] DEBUG: {$message}\n";

        echo $logEntry;
    }

    /**
     * Write to log file
     * 
     * @param string $entry Log entry to write
     * @return bool True if successful, false otherwise
     */
    private function write($entry = '')
    {
        try {
            if (!is_writable($this->logDir)) {
                return false;
            }

            $result = file_put_contents(
                $this->logFile,
                $entry,
                FILE_APPEND | LOCK_EX
            );

            return $result !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get last N lines from log file
     * 
     * @param int $lines Number of lines to retrieve
     * @return array Array of log lines
     */
    public function getLastLines($lines = 10)
    {
        if (!file_exists($this->logFile)) {
            return array();
        }

        $content = file_get_contents($this->logFile);
        $allLines = explode("\n", trim($content));
        $lastLines = array_slice($allLines, -$lines);

        return array_filter($lastLines);
    }

    /**
     * Get log file path
     * 
     * @return string Log file path
     */
    public function getLogFile()
    {
        return $this->logFile;
    }

    /**
     * Clear log file
     * 
     * @return bool True if successful, false otherwise
     */
    public function clear()
    {
        try {
            if (file_exists($this->logFile)) {
                return unlink($this->logFile);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
