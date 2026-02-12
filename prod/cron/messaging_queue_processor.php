<?php
/**
 * Messaging Queue Processor
 * Processes pending messages from queue
 * Run via cron every 1-5 minutes
 */

require_once __DIR__ . '/../app/bootstrap.php';

// Prevent direct browser access
if (php_sapi_name() !== 'cli' && !isset($_GET['manual_run'])) {
    die('This script must be run from command line or with manual_run parameter');
}

try {
    \EduCRM\Services\MessagingFactory::init($pdo);

    echo "[" . date('Y-m-d H:i:s') . "] Starting message queue processor...\n";

    // Get all active gateways
    $stmt = $pdo->query("SELECT * FROM messaging_gateways WHERE is_active = TRUE ORDER BY priority DESC");
    $gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($gateways) === 0) {
        echo "No active gateways found. Exiting.\n";
        exit;
    }

    $totalSent = 0;
    $totalFailed = 0;

    // Process queue for each gateway
    foreach ($gateways as $gatewayConfig) {
        try {
            echo "Processing gateway: {$gatewayConfig['name']} ({$gatewayConfig['provider']})...\n";

            $gateway = \EduCRM\Services\MessagingFactory::create($gatewayConfig['id']);

            // Process batch of messages
            $batchSize = 50; // Process 50 messages per gateway per run
            $result = $gateway->processQueue($batchSize);

            $totalSent += $result['sent'];
            $totalFailed += $result['failed'];

            echo "  Sent: {$result['sent']}, Failed: {$result['failed']}\n";

        } catch (Exception $e) {
            echo "  Error processing gateway: " . $e->getMessage() . "\n";
            continue;
        }
    }

    echo "\n[" . date('Y-m-d H:i:s') . "] Queue processing complete.\n";
    echo "Total sent: $totalSent, Total failed: $totalFailed\n";

    // Reset daily counters if it's a new day
    $lastReset = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'messaging_last_reset'")->fetchColumn();
    $today = date('Y-m-d');

    if ($lastReset !== $today) {
        \EduCRM\Services\MessagingFactory::resetDailyCounters();

        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, setting_type) 
            VALUES ('messaging_last_reset', ?, 'string')
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$today, $today]);

        echo "Daily counters reset.\n";
    }

} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
