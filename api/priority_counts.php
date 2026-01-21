<?php
/**
 * Priority Counts API
 * Returns live priority counts for inquiries
 */

require_once '../config/config.php';
require_once '../app/ApiMiddleware.php';
require_once '../app/services/LeadScoringService.php';

// Apply rate limiting: 60 requests per minute per user/IP
\EduCRM\ApiMiddleware::enforceRateLimit(60, 60, 'api_priority_counts');

header('Content-Type: application/json');

try {
    $scoringService = new \EduCRM\Services\LeadScoringService($pdo);
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

