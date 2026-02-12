<?php
require_once '../../app/bootstrap.php';

requireLogin();
requireBranchManager();

header('Content-Type: application/json');

$templateId = $_GET['id'] ?? null;

if (!$templateId) {
    echo json_encode(['success' => false, 'error' => 'Template ID required']);
    exit;
}

$stmt = $pdo->prepare("SELECT content FROM messaging_templates WHERE id = ?");
$stmt->execute([$templateId]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if ($template) {
    echo json_encode(['success' => true, 'content' => $template['content']]);
} else {
    echo json_encode(['success' => false, 'error' => 'Template not found']);
}
