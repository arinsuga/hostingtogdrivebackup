<?php
/**
 * auth-setup.php - Local OAuth 2.0 authentication setup
 * 
 * PHP 7.2.23+ compatible
 * Run this script on local PC to authenticate and generate token.json
 * Usage: php src/auth-setup.php
 * 
 * Output: token.json (upload this to hosting)
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Check if credentials.json exists
$credentialsFile = __DIR__ . '/../credentials.json';
if (!file_exists($credentialsFile)) {
    die("ERROR: credentials.json not found at: {$credentialsFile}\n");
}

// Initialize Google Client
$client = new Google_Client();
$client->setAuthConfig($credentialsFile);
$client->setScopes(array('https://www.googleapis.com/auth/drive'));
$client->setAccessType('offline');
$client->setPrompt('consent');

// Use the redirect URI that is already configured in credentials.json
// Avoid hard-coding a URI that may not match the registered OAuth client.

echo "\n";
echo "========================================\n";
echo "Google Drive Backup - OAuth 2.0 Setup\n";
echo "========================================\n\n";

// Generate authorization URL
try {
    $authUrl = $client->createAuthUrl();
} catch (\InvalidArgumentException $e) {
    echo "ERROR: " . $e->getMessage() . "\n\n";
    echo "This error means your OAuth credentials are missing a valid redirect URI.\n";
    echo "Use a Desktop app OAuth client, or add an authorized redirect URI to your\n";
    echo "Google Cloud OAuth client settings. For local CLI auth, the recommended\n";
    echo "redirect URI is: urn:ietf:wg:oauth:2.0:oob\n\n";
    echo "If you are using a Web application client, open the Google Cloud console,\n";
    echo "edit the OAuth client, and add the redirect URI shown above.\n";
    exit(1);
}

echo "1. Please visit the following URL in your browser:\n\n";
echo "   " . $authUrl . "\n\n";

echo "2. After authenticating, you will receive an authorization code.\n";
echo "3. Copy the authorization code and paste it below.\n\n";

// Get authorization code from user
echo "Enter authorization code: ";
$authCode = trim(fgets(STDIN));

if (empty($authCode)) {
    die("ERROR: No authorization code provided.\n");
}

try {
    // Exchange authorization code for tokens
    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

    if (!isset($accessToken['access_token'])) {
        die("ERROR: Failed to fetch access token. Please check your authorization code.\n");
    }

    // Save token to file
    $tokenFile = __DIR__ . '/../token.json';
    $tokenJson = json_encode($accessToken, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (!file_put_contents($tokenFile, $tokenJson)) {
        die("ERROR: Failed to write token.json file.\n");
    }

    echo "\n✓ SUCCESS! Token saved to: {$tokenFile}\n\n";

    // Display token info
    echo "Token Information:\n";
    echo "  Access Token: " . substr($accessToken['access_token'], 0, 20) . "...\n";
    echo "  Token Type: " . $accessToken['token_type'] . "\n";
    echo "  Expires In: " . $accessToken['expires_in'] . " seconds\n";
    
    if (isset($accessToken['refresh_token'])) {
        echo "  Refresh Token: " . substr($accessToken['refresh_token'], 0, 20) . "...\n";
    }

    echo "\n========================================\n";
    echo "NEXT STEPS:\n";
    echo "========================================\n\n";
    echo "1. Upload token.json to your hosting server\n";
    echo "   Command: scp token.json user@host:/path/to/backup/\n\n";
    echo "2. Set environment variables on hosting:\n";
    echo "   - DB_HOST\n";
    echo "   - DB_USER\n";
    echo "   - DB_PASSWORD\n";
    echo "   - GOOGLE_DRIVE_FOLDER_ID\n";
    echo "   - ADMIN_EMAIL (optional)\n\n";
    echo "3. Test the backup script:\n";
    echo "   Command: php backup.php\n\n";
    echo "4. Add to cron job (recommended: 2 AM daily)\n";
    echo "   0 2 * * * /usr/bin/php7.2 /path/to/backup.php\n\n";

} catch (\Exception $e) {
    die("ERROR: " . $e->getMessage() . "\n");
}
