<?php
/**
 * revoke-token.php - Token revocation utility
 *
 * PHP 7.2.23+ compatible
 * Usage: php src/revoke-token.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use BackupTool\Logger;
use BackupTool\TokenRevocation;

$tokenFile = __DIR__ . '/../token.json';
$logger = new Logger();
$revocation = new TokenRevocation($tokenFile, $logger);

if (!$revocation->tokenExists()) {
    echo "No token.json found. Nothing to revoke.\n";
    exit(0);
}

$result = $revocation->revoke();

if ($result) {
    echo "Token revoked and local token.json removed successfully.\n";
    exit(0);
}

echo "Token revocation failed. Check logs for details.\n";
exit(1);
