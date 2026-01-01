<?php
/**
 * Document Download Handler
 * Secure download with authentication and tracking
 */

require_once '../../config.php';
require_once '../../includes/services/DocumentService.php';

requireLogin();

$documentService = new DocumentService($pdo);

$documentId = $_GET['id'] ?? null;
$versionNumber = $_GET['version'] ?? null;

if (!$documentId) {
    die('Document ID required');
}

try {
    // Get document info
    $document = $documentService->getDocument($documentId);

    if (!$document) {
        die('Document not found');
    }

    // Check permissions
    $canAccess = false;

    if (hasRole('admin')) {
        $canAccess = true;
    } elseif ($document['uploaded_by'] == $_SESSION['user_id']) {
        $canAccess = true;
    } elseif ($document['entity_type'] === 'inquiry' || $document['entity_type'] === 'student') {
        // Check if user is assigned to this entity
        $stmt = $pdo->prepare("SELECT assigned_to FROM {$document['entity_type']}ies WHERE id = ?");
        $stmt->execute([$document['entity_id']]);
        $entity = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($entity && $entity['assigned_to'] == $_SESSION['user_id']) {
            $canAccess = true;
        }
    }

    if (!$canAccess) {
        die('Access denied');
    }

    // Get file path
    $filePath = $document['file_path'];

    // If specific version requested
    if ($versionNumber) {
        $versions = $documentService->getVersionHistory($documentId);
        foreach ($versions as $version) {
            if ($version['version_number'] == $versionNumber) {
                $filePath = $version['file_path'];
                break;
            }
        }
    }

    // Check if file exists
    if (!file_exists($filePath)) {
        die('File not found on server');
    }

    // Increment download count (only for main document, not versions)
    if (!$versionNumber) {
        $pdo->prepare("UPDATE documents SET download_count = download_count + 1 WHERE id = ?")
            ->execute([$documentId]);
    }

    // Set headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($document['file_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');

    // Output file
    readfile($filePath);
    exit;

} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
