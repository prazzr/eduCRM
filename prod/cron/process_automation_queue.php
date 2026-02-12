<?php
/**
 * Automation Queue Processor
 * Should be run every 1-5 minutes via Cron
 */

require_once __DIR__ . '/../app/bootstrap.php';

// Set proper headers for CLI/Browser output compatibility
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain');
}

echo "Starting Automation Queue Processor at " . date('Y-m-d H:i:s') . "\n";

try {
    $automationService = new \EduCRM\Services\AutomationService($pdo);

    // Process queue
    $results = $automationService->processQueue();

    echo "Processing complete:\n";
    echo "- Processed: " . $results['processed'] . "\n";
    echo "- Sent: " . $results['sent'] . "\n";
    echo "- Failed: " . $results['failed'] . "\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Automation Queue Error: " . $e->getMessage());
}

echo "Done.\n";
