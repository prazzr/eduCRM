<?php
/**
 * Google Calendar Background Sync
 * 
 * This script syncs future appointments for all users with active Google Calendar connections.
 * Recommend running via cron every 5-15 minutes or daily depending on rate limits.
 * 
 * Usage: php cron/sync_calendar.php
 */

// Define root path
define('APP_ROOT', dirname(__DIR__));

// Bootstrap application
require_once APP_ROOT . '/app/bootstrap.php';

use EduCRM\Services\GoogleCalendarService;

echo "Starting Calendar Sync Job: " . date('Y-m-d H:i:s') . "\n";

try {
    // 1. Initialize Service
    $calendarService = new GoogleCalendarService($pdo);

    if (!$calendarService->isConfigured()) {
        die("Error: Google Calendar is not configured in .env\n");
    }

    // 2. Get users with active tokens
    $stmt = $pdo->query("SELECT DISTINCT user_id FROM user_calendar_tokens WHERE is_active = 1");
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($userIds)) {
        die("No users with active calendar connections found.\n");
    }

    echo "Found " . count($userIds) . " connected users.\n";
    $totalSynced = 0;
    $totalFailed = 0;

    foreach ($userIds as $userId) {
        echo "Processing User ID: {$userId}...\n";

        // 3. Fetch future appointments for this counselor that need syncing
        // We sync appointments that are:
        // - In the future (or recent past, e.g. today)
        // - Assigned to this user
        // - NOT Cancelled (or handle cancellation separately? Service handles delete/update if exists)
        // 
        // We select ALL active appointments to ensure updates are propagated. 
        // Optimization: Could check updated_at vs last_synced_at, but for now we simple-sync.

        $apptStmt = $pdo->prepare("
            SELECT a.*, 
                   s.name as client_name, s.email as client_email, s.phone as client_phone
            FROM appointments a
            LEFT JOIN users s ON a.student_id = s.id
            WHERE a.counselor_id = ? 
            AND a.appointment_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            AND a.status != 'cancelled'
        ");
        $apptStmt->execute([$userId]);
        $appointments = $apptStmt->fetchAll(PDO::FETCH_ASSOC);

        echo "  Found " . count($appointments) . " appointments.\n";

        foreach ($appointments as $appt) {
            // Rate limiting sleep (avoid hitting Google API limits)
            usleep(200000); // 200ms

            $result = $calendarService->syncAppointment($appt);

            if ($result['success']) {
                $totalSynced++;
                // echo "    Synced Appt #{$appt['id']} -> Event: {$result['event_id']}\n";
            } else {
                $totalFailed++;
                echo "    Failed Appt #{$appt['id']}: " . ($result['error'] ?? 'Unknown error') . "\n";
            }
        }
    }

    echo "\nSync Complete.\n";
    echo "Total Synced: $totalSynced\n";
    echo "Total Failed: $totalFailed\n";

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
