<?php
/**
 * Toggle Gateway Status
 */

require_once '../../app/bootstrap.php';

requireLogin();
requireBranchManager();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$gatewayId = $_POST['id'] ?? null;
$active = $_POST['active'] === 'true' ? 1 : 0;

if (!$gatewayId) {
    echo json_encode(['success' => false, 'message' => 'Gateway ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE messaging_gateways SET is_active = ? WHERE id = ?");
    $result = $stmt->execute([$active, $gatewayId]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Gateway updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update gateway']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
