<?php
/**
 * Daily Analytics Snapshot Cron Job
 * Run this daily at midnight to capture analytics snapshots
 * Schedule: 0 0 * * * (daily at midnight)
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

try {
    $analytics = new \EduCRM\Services\AnalyticsService($pdo);

    // Take daily snapshot
    $result = $analytics->takeSnapshot();

    if ($result) {
        echo date('Y-m-d H:i:s') . " - ✅ Analytics snapshot created successfully\n";

        // Update all active goals
        $goals = $pdo->query("SELECT id FROM analytics_goals WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($goals as $goalId) {
            $analytics->updateGoalProgress($goalId);
        }

        echo date('Y-m-d H:i:s') . " - ✅ Goal progress updated for " . count($goals) . " goals\n";
    } else {
        echo date('Y-m-d H:i:s') . " - ❌ Failed to create analytics snapshot\n";
    }

} catch (Exception $e) {
    echo date('Y-m-d H:i:s') . " - ❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
