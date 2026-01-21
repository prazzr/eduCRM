<?php
/**
 * Delete Document Handler
 * Deletes document and all versions
 */

require_once '../../app/bootstrap.php';


requireLogin();

header('Content-Type: application/json');

$documentService = new \EduCRM\Services\DocumentService($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$documentId = $_POST['id'] ?? null;

if (!$documentId) {
    echo json_encode(['success' => false, 'message' => 'Document ID required']);
    exit;
}

try {
    // Get document to check permissions
    $document = $documentService->getDocument($documentId);

    if (!$document) {
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit;
    }

    // Check permissions
    if (!hasRole('admin') && $document['uploaded_by'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    // Delete document
    $result = $documentService->deleteDocument($documentId);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Document deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete document']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
