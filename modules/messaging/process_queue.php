<?php
require_once '../../app/bootstrap.php';


requireLogin();
requireAdminCounselorOrBranchManager();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    \EduCRM\Services\MessagingFactory::init($pdo);

    // Get first active gateway
    $gateway = \EduCRM\Services\MessagingFactory::create();

    // Process queue
    $result = $gateway->processQueue(50);

    echo json_encode([
        'success' => true,
        'sent' => $result['sent'],
        'failed' => $result['failed']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
