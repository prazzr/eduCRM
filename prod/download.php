<?php
require_once 'app/bootstrap.php';
requireLogin();

// 1. Validate Input
$attachment_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$attachment_id) {
    die("Invalid file ID.");
}

// 2. Fetch File Metadata
$stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
$stmt->execute([$attachment_id]);
$file = $stmt->fetch();

if (!$file) {
    die("File not found or access denied.");
}

// 3. Authorization Check (RBAC Strategy)
// Admin/Counselor can view all. Students can only view their own (or public context).
// Ideally, the 'attachments' table should have a 'context' or 'visibility' column.
// For now, we allow Owner OR Admin/Counselor OR Teacher.
$is_owner = ($file['user_id'] == $_SESSION['user_id']);
$can_view = hasRole('admin') || hasRole('counselor') || hasRole('teacher') || $is_owner;

if (!$can_view) {
    logAction('unauthorized_download_attempt', "File ID: $attachment_id, User: " . $_SESSION['user_id']);
    die("Access Denied: You do not have permission to view this file.");
}

// 4. Locate File
// Check Secure Directory first, then fallback to Legacy 'uploads/' for backward compatibility
$secure_path = SECURE_UPLOAD_DIR . $file['file_name']; // Stored as just filename in v2
$legacy_path = __DIR__ . '/' . $file['file_path'];     // Stored as relative path in v1

$final_path = '';
if (file_exists($secure_path)) {
    $final_path = $secure_path;
} elseif (file_exists($legacy_path)) {
    $final_path = $legacy_path;
} else {
    die("File content missing from server.");
}

// 5. Serve File (Proxy)
$mime = $file['file_mime'] ?: 'application/octet-stream';

// Validate Internal MIME (Double Check)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$real_mime = finfo_file($finfo, $final_path);
finfo_close($finfo);

// Force download for non-browser-safe type, or inline for PDF/Images
header('Content-Description: File Transfer');
header('Content-Type: ' . $real_mime);
header('Content-Disposition: inline; filename="' . basename($file['file_name']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($final_path));

// Clear output buffer to prevent corruption
if (ob_get_level())
    ob_end_clean();
readfile($final_path);

logAction('file_download', "ID: $attachment_id");
exit;
?>