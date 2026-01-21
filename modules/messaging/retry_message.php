<?php
require_once '../../app/bootstrap.php';

requireLogin();
requireAdminCounselorOrBranchManager();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$messageId = $_POST['id'] ?? null;

if (!$messageId) {
    echo json_encode(['success' => false, 'message' => 'Message ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE messaging_queue SET status = 'pending', retry_count = 0 WHERE id = ?");
    $result = $stmt->execute([$messageId]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Message queued for retry']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to retry message']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
