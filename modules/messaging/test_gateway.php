<?php
/**
 * Test Gateway Handler
 * Sends test message to verify gateway configuration
 */

require_once '../../config.php';
require_once '../../includes/services/MessagingFactory.php';

requireLogin();
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$gatewayId = $_POST['gateway_id'] ?? null;
$phone = $_POST['phone'] ?? null;

if (!$gatewayId || !$phone) {
    echo json_encode(['success' => false, 'error' => 'Gateway ID and phone number required']);
    exit;
}

try {
    MessagingFactory::init($pdo);
    $gateway = MessagingFactory::create($gatewayId);

    $testMessage = "This is a test message from eduCRM. Gateway is working correctly!";

    $result = $gateway->send($phone, $testMessage);

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
