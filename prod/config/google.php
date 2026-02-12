<?php
/**
 * Google API Configuration
 * 
 * To set up:
 * 1. Go to Google Cloud Console: https://console.cloud.google.com/
 * 2. Create a new project or select existing
 * 3. Enable "Google Calendar API"
 * 4. Go to "Credentials" → Create OAuth 2.0 Client ID
 * 5. Set Application Type: "Web Application"
 * 6. Add Authorized redirect URI: https://your-domain.com/modules/appointments/google-callback.php
 * 7. Copy Client ID and Client Secret to your .env file
 */

return [
    'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
    'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
    'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
        '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') .
        '/modules/appointments/google-callback.php'
    ),
    'scopes' => [
        'https://www.googleapis.com/auth/calendar.events',
    ],
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Kathmandu',
];
