<?php
/**
 * Document Expiry Alert Cron Job
 * 
 * Checks for expiring documents and sends notifications.
 * Run daily: 0 8 * * * php /path/to/cron/document_expiry_check.php
 * 
 * @package EduCRM\Cron
 */

require_once __DIR__ . '/../app/bootstrap.php';

use EduCRM\Services\DocumentExpiryService;

echo "=== Document Expiry Check ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $expiryService = new DocumentExpiryService($pdo);

    // Get summary before processing
    $summary = $expiryService->getExpirySummary();
    echo "Current Status:\n";
    echo "  - Expired documents: {$summary['expired']}\n";
    echo "  - Expiring in 7 days: {$summary['expiring_7_days']}\n";
    echo "  - Expiring in 30 days: {$summary['expiring_30_days']}\n\n";

    // Process alerts
    $results = $expiryService->processExpiryAlerts();

    echo "Processing Results:\n";
    echo "  - Alerts Sent: {$results['alerts_sent']}\n";

    if (!empty($results['errors'])) {
        echo "  - Errors:\n";
        foreach ($results['errors'] as $error) {
            echo "    - {$error}\n";
        }
    }

    echo "\nCompleted: " . date('Y-m-d H:i:s') . "\n";

    // Log to file
    $logMessage = date('Y-m-d H:i:s') . " - Alerts sent: {$results['alerts_sent']}, Errors: " . count($results['errors']) . "\n";
    file_put_contents(__DIR__ . '/../storage/logs/document_expiry.log', $logMessage, FILE_APPEND);

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Document expiry cron failed: " . $e->getMessage());
    exit(1);
}

exit(0);
