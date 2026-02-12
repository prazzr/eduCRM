<?php
require_once '../../app/bootstrap.php';

requireLogin();
requireBranchManager();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$campaignId = $_POST['id'] ?? null;

if (!$campaignId) {
    echo json_encode(['success' => false, 'message' => 'Campaign ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE messaging_campaigns SET status = 'cancelled' WHERE id = ?");
    $result = $stmt->execute([$campaignId]);

    if ($result) {
        // Cancel pending messages
        $pdo->prepare("
            UPDATE messaging_queue 
            SET status = 'cancelled' 
            WHERE metadata->>'$.campaign_id' = ? AND status = 'pending'
        ")->execute([$campaignId]);

        echo json_encode(['success' => true, 'message' => 'Campaign cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel campaign']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
