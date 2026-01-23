<?php
/**
 * Google Calendar OAuth Connection Handler
 * Initiates the OAuth flow to connect user's Google Calendar
 */
require_once '../../app/bootstrap.php';
requireLogin();

$calendarService = new \EduCRM\Services\GoogleCalendarService($pdo);

// Handle disconnect action
if (isset($_GET['action']) && $_GET['action'] === 'disconnect') {
    $calendarService->disconnect($_SESSION['user_id']);
    redirectWithAlert('list.php', 'Google Calendar disconnected successfully.', 'success');
}

// Check if already connected
if ($calendarService->isConnected($_SESSION['user_id'])) {
    redirectWithAlert('list.php', 'Your Google Calendar is already connected.', 'info');
}

// Check if Google Calendar is configured
if (!$calendarService->isConfigured()) {
    redirectWithAlert('list.php', 'Google Calendar integration is not configured. Please contact your administrator.', 'error');
}

try {
    // Get OAuth URL and redirect
    $authUrl = $calendarService->getAuthUrl($_SESSION['user_id']);
    header('Location: ' . $authUrl);
    exit;
} catch (\Exception $e) {
    error_log('Google Calendar OAuth Error: ' . $e->getMessage());
    redirectWithAlert('list.php', 'Failed to connect to Google Calendar. Please try again.', 'error');
}
