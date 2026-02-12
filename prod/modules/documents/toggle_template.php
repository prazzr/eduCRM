<?php
/**
 * Toggle Template Active Status
 */

require_once '../../app/bootstrap.php';

requireLogin();
requireBranchManager();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$templateId = $_POST['id'] ?? null;
$active = $_POST['active'] === 'true' ? 1 : 0;

if (!$templateId) {
    echo json_encode(['success' => false, 'message' => 'Template ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE document_templates SET is_active = ? WHERE id = ?");
    $result = $stmt->execute([$active, $templateId]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Template updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update template']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
