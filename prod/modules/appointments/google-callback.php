<?php
/**
 * Google Calendar OAuth Callback Handler
 * Receives the OAuth callback and stores tokens
 */
require_once '../../app/bootstrap.php';
requireLogin();

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';

// Handle OAuth errors
if ($error) {
    $errorMessage = match ($error) {
        'access_denied' => 'You declined the calendar permission request.',
        default => 'An error occurred during authorization: ' . htmlspecialchars($error)
    };
    redirectWithAlert('list.php', $errorMessage, 'error');
}

// Validate required parameters
if (!$code) {
    redirectWithAlert('list.php', 'Invalid callback: missing authorization code.', 'error');
}

// Decode state to get user ID (with fallback to session)
$stateData = json_decode(base64_decode($state), true);
$userId = $stateData['user_id'] ?? $_SESSION['user_id'];

// Security: Ensure user can only connect their own calendar
if ($userId != $_SESSION['user_id']) {
    redirectWithAlert('list.php', 'Security error: User mismatch.', 'error');
}

$calendarService = new \EduCRM\Services\GoogleCalendarService($pdo);

try {
    $calendarService->handleCallback($code, $userId);
    redirectWithAlert('list.php', '✅ Google Calendar connected successfully! Your appointments will now sync automatically.', 'success');
} catch (\Exception $e) {
    error_log('Google Calendar Callback Error: ' . $e->getMessage());
    redirectWithAlert('list.php', 'Failed to connect Google Calendar: ' . $e->getMessage(), 'error');
}
