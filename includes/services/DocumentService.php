<?php
/**
 * Document Service
 * Handles document upload, download, versioning, and management
 */

class DocumentService
{
    private $pdo;
    private $uploadPath;
    private $maxFileSize;
    private $allowedTypes;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->loadSettings();
    }

    /**
     * Load document settings from system_settings
     */
    private function loadSettings()
    {
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'document_%'");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->uploadPath = $settings['document_storage_path'] ?? 'uploads/documents/';
        $this->maxFileSize = (int) ($settings['document_max_size'] ?? 10485760); // 10MB default
        $this->allowedTypes = explode(',', $settings['document_allowed_types'] ?? 'pdf,doc,docx,jpg,jpeg,png');
    }

    /**
     * Upload a document
     */
    public function uploadDocument($file, $entityType, $entityId, $category = null, $description = null)
    {
        // Validate file
        $validation = $this->validateFile($file);
        if (!$validation['valid']) {
            throw new Exception($validation['error']);
        }

        // Create directory structure
        $directory = $this->uploadPath . $entityType . 's/' . $entityId . '/';
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        // Generate secure filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = $directory . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to upload file');
        }

        // Insert into database
        $stmt = $this->pdo->prepare("
            INSERT INTO documents (
                entity_type, entity_id, file_name, file_path, 
                file_type, file_size, uploaded_by, category, description
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $entityType,
            $entityId,
            $file['name'],
            $filepath,
            $extension,
            $file['size'],
            $_SESSION['user_id'],
            $category,
            $description
        ]);

        if ($result) {
            return $this->pdo->lastInsertId();
        }

        return false;
    }

    /**
     * Validate uploaded file
     */
    private function validateFile($file)
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'File upload error: ' . $file['error']];
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            $maxMB = round($this->maxFileSize / 1048576, 1);
            return ['valid' => false, 'error' => "File size exceeds maximum of {$maxMB}MB"];
        }

        // Check file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes)) {
            return ['valid' => false, 'error' => 'File type not allowed. Allowed: ' . implode(', ', $this->allowedTypes)];
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        if (!in_array($mimeType, $allowedMimes)) {
            return ['valid' => false, 'error' => 'Invalid file type'];
        }

        return ['valid' => true];
    }

    /**
     * Get document by ID
     */
    public function getDocument($documentId)
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, u.name as uploaded_by_name
            FROM documents d
            LEFT JOIN users u ON d.uploaded_by = u.id
            WHERE d.id = ?
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get documents for an entity
     */
    public function getEntityDocuments($entityType, $entityId, $category = null)
    {
        $sql = "
            SELECT d.*, u.name as uploaded_by_name
            FROM documents d
            LEFT JOIN users u ON d.uploaded_by = u.id
            WHERE d.entity_type = ? AND d.entity_id = ?
        ";
        $params = [$entityType, $entityId];

        if ($category) {
            $sql .= " AND d.category = ?";
            $params[] = $category;
        }

        $sql .= " ORDER BY d.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Download document (increments download count)
     */
    public function downloadDocument($documentId)
    {
        $document = $this->getDocument($documentId);

        if (!$document) {
            throw new Exception('Document not found');
        }

        // Check if file exists
        if (!file_exists($document['file_path'])) {
            throw new Exception('File not found on server');
        }

        // Increment download count
        $this->pdo->prepare("UPDATE documents SET download_count = download_count + 1 WHERE id = ?")
            ->execute([$documentId]);

        // Return file info for download
        return [
            'path' => $document['file_path'],
            'name' => $document['file_name'],
            'type' => $document['file_type'],
            'size' => $document['file_size']
        ];
    }

    /**
     * Delete document
     */
    public function deleteDocument($documentId)
    {
        $document = $this->getDocument($documentId);

        if (!$document) {
            return false;
        }

        // Delete file from filesystem
        if (file_exists($document['file_path'])) {
            unlink($document['file_path']);
        }

        // Delete all versions
        $versions = $this->getVersionHistory($documentId);
        foreach ($versions as $version) {
            if (file_exists($version['file_path'])) {
                unlink($version['file_path']);
            }
        }

        // Delete from database (cascade will delete versions)
        $stmt = $this->pdo->prepare("DELETE FROM documents WHERE id = ?");
        return $stmt->execute([$documentId]);
    }

    /**
     * Create a new version of a document
     */
    public function createVersion($documentId, $file, $changeNotes = null)
    {
        $document = $this->getDocument($documentId);

        if (!$document) {
            throw new Exception('Document not found');
        }

        // Validate file
        $validation = $this->validateFile($file);
        if (!$validation['valid']) {
            throw new Exception($validation['error']);
        }

        // Get next version number
        $stmt = $this->pdo->prepare("SELECT MAX(version_number) as max_version FROM document_versions WHERE document_id = ?");
        $stmt->execute([$documentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextVersion = ($result['max_version'] ?? 0) + 1;

        // Create version directory
        $directory = dirname($document['file_path']) . '/versions/';
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        // Generate filename for version
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'v' . $nextVersion . '_' . uniqid() . '.' . $extension;
        $filepath = $directory . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to upload version');
        }

        // Insert version record
        $stmt = $this->pdo->prepare("
            INSERT INTO document_versions (
                document_id, version_number, file_path, 
                file_size, uploaded_by, change_notes
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $documentId,
            $nextVersion,
            $filepath,
            $file['size'],
            $_SESSION['user_id'],
            $changeNotes
        ]);

        // Update main document to point to new version
        if ($result) {
            $this->pdo->prepare("UPDATE documents SET file_path = ?, file_size = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$filepath, $file['size'], $documentId]);
        }

        return $result;
    }

    /**
     * Get version history for a document
     */
    public function getVersionHistory($documentId)
    {
        $stmt = $this->pdo->prepare("
            SELECT v.*, u.name as uploaded_by_name
            FROM document_versions v
            LEFT JOIN users u ON v.uploaded_by = u.id
            WHERE v.document_id = ?
            ORDER BY v.version_number DESC
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get document categories
     */
    public function getCategories()
    {
        return [
            'passport' => 'Passport',
            'visa' => 'Visa Documents',
            'certificates' => 'Certificates',
            'transcripts' => 'Academic Transcripts',
            'offer_letters' => 'Offer Letters',
            'contracts' => 'Contracts',
            'applications' => 'Applications',
            'financial' => 'Financial Documents',
            'other' => 'Other'
        ];
    }

    /**
     * Get document statistics
     */
    public function getStatistics($entityType = null, $entityId = null)
    {
        $sql = "SELECT 
            COUNT(*) as total_documents,
            SUM(file_size) as total_size,
            SUM(download_count) as total_downloads,
            category,
            COUNT(*) as count_by_category
        FROM documents WHERE 1=1";

        $params = [];

        if ($entityType) {
            $sql .= " AND entity_type = ?";
            $params[] = $entityType;
        }

        if ($entityId) {
            $sql .= " AND entity_id = ?";
            $params[] = $entityId;
        }

        $sql .= " GROUP BY category";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
