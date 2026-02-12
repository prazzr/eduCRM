<?php
/**
 * Get Version History API
 * Returns version history for a document
 */

require_once '../../app/bootstrap.php';


requireLogin();

header('Content-Type: application/json');

$documentService = new \EduCRM\Services\DocumentService($pdo);

$documentId = $_GET['id'] ?? null;

if (!$documentId) {
    echo json_encode(['success' => false, 'message' => 'Document ID required']);
    exit;
}

try {
    $versions = $documentService->getVersionHistory($documentId);

    echo json_encode([
        'success' => true,
        'versions' => $versions
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
