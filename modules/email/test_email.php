<?php
/**
 * Send Test Email (AJAX endpoint)
 */

require_once '../../app/bootstrap.php';


header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('admin')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$testEmail = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$testEmail) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

try {
    $emailService = new \EduCRM\Services\EmailNotificationService($pdo);
    
    // Check if SMTP is configured
    if (!$emailService->isSmtpConfigured()) {
        echo json_encode(['success' => false, 'error' => 'SMTP is not configured. Please fill in the SMTP settings above and save before testing.']);
        exit;
    }
    
    // Send test email directly (not queued)
    $result = $emailService->sendTestEmail($testEmail);
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
