<?php
/**
 * Secure File Upload Handler
 * Validates and securely handles file uploads
 */

class SecureFileUpload
{
    private $allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];

    private $maxSize = 5242880; // 5MB
    private $uploadDir = __DIR__ . '/../../uploads/';

    /**
     * Validate uploaded file
     */
    public function validateUpload($file)
    {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('No file uploaded or invalid upload');
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadErrorMessage($file['error']));
        }

        // Check file size
        if ($file['size'] > $this->maxSize) {
            throw new Exception('File size exceeds maximum allowed size (5MB)');
        }

        if ($file['size'] === 0) {
            throw new Exception('File is empty');
        }

        // Verify MIME type using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $this->allowedTypes)) {
            throw new Exception('File type not allowed. Allowed types: images, PDF, Word, Excel');
        }

        // Additional security: check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];

        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception('File extension not allowed');
        }

        // Check for double extensions (e.g., file.php.jpg)
        if (substr_count($file['name'], '.') > 1) {
            throw new Exception('Multiple file extensions not allowed');
        }

        return true;
    }

    /**
     * Upload file securely
     */
    public function upload($file, $subfolder = '')
    {
        // Validate first
        $this->validateUpload($file);

        // Create subfolder if needed
        $targetDir = $this->uploadDir . $subfolder;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // Generate secure filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $targetPath = $targetDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Failed to move uploaded file');
        }

        // Set proper permissions
        chmod($targetPath, 0644);

        return [
            'filename' => $filename,
            'path' => $targetPath,
            'size' => $file['size'],
            'type' => $file['type'],
            'original_name' => basename($file['name'])
        ];
    }

    /**
     * Delete file securely
     */
    public function delete($filename, $subfolder = '')
    {
        $filepath = $this->uploadDir . $subfolder . '/' . $filename;

        // Prevent directory traversal
        $realpath = realpath($filepath);
        $uploadRealpath = realpath($this->uploadDir);

        if ($realpath === false || strpos($realpath, $uploadRealpath) !== 0) {
            throw new Exception('Invalid file path');
        }

        if (file_exists($filepath)) {
            return unlink($filepath);
        }

        return false;
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds MAX_FILE_SIZE directive in HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }

    /**
     * Set allowed file types
     */
    public function setAllowedTypes($types)
    {
        $this->allowedTypes = $types;
    }

    /**
     * Set maximum file size
     */
    public function setMaxSize($size)
    {
        $this->maxSize = $size;
    }

    /**
     * Scan uploaded file for malware (basic check)
     */
    public function scanFile($filepath)
    {
        // Check for PHP code in uploaded files
        $content = file_get_contents($filepath);

        $suspiciousPatterns = [
            '/<\?php/i',
            '/eval\(/i',
            '/base64_decode/i',
            '/system\(/i',
            '/exec\(/i',
            '/shell_exec/i',
            '/passthru/i'
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                unlink($filepath);
                throw new Exception('Suspicious content detected in file');
            }
        }

        return true;
    }
}
