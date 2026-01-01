<?php
/**
 * Priority Counts API
 * Returns live priority counts for inquiries
 */

require_once '../config.php';
require_once '../includes/services/LeadScoringService.php';

header('Content-Type: application/json');

try {
    $scoringService = new LeadScoringService($pdo);
    $counts = $scoringService->getPriorityStats();

    echo json_encode([
        'success' => true,
        'hot' => $counts['hot'],
        'warm' => $counts['warm'],
        'cold' => $counts['cold'],
        'total' => $counts['hot'] + $counts['warm'] + $counts['cold']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
