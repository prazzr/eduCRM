<?php
/**
 * Bulk Action Handler for Inquiries
 * Processes bulk operations on selected inquiries
 */

require_once '../../config.php';
require_once '../../includes/services/BulkActionService.php';
require_once '../../includes/services/LeadScoringService.php';
require_once '../../includes/services/EmailNotificationService.php';

requireLogin();
requireAdminOrCounselor();

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$bulkService = new BulkActionService($pdo);
$leadScoringService = new LeadScoringService($pdo);
$emailService = new EmailNotificationService($pdo);

$action = $_POST['action'] ?? '';
$inquiryIds = $_POST['inquiry_ids'] ?? [];

// Validate input
if (empty($inquiryIds) || !is_array($inquiryIds)) {
    echo json_encode(['success' => false, 'message' => 'No inquiries selected']);
    exit;
}

// Convert to integers
$inquiryIds = array_map('intval', $inquiryIds);

// Verify permissions - counselors can only edit assigned inquiries unless admin
if (!hasRole('admin')) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inquiries WHERE id IN (" . implode(',', array_fill(0, count($inquiryIds), '?')) . ") AND assigned_to != ?");
    $stmt->execute(array_merge($inquiryIds, [$_SESSION['user_id']]));

    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'You can only perform bulk actions on your assigned inquiries']);
        exit;
    }
}

try {
    $result = false;
    $message = '';

    switch ($action) {
        case 'assign':
            $assignTo = intval($_POST['assign_to'] ?? 0);
            if ($assignTo <= 0) {
                throw new Exception('Invalid counselor selected');
            }

            $result = $bulkService->bulkUpdateInquiries($inquiryIds, ['assigned_to' => $assignTo]);
            $message = $result ? 'Inquiries assigned successfully' : 'Failed to assign inquiries';
            break;

        case 'priority':
            $priority = $_POST['priority'] ?? '';
            if (!in_array($priority, ['hot', 'warm', 'cold'])) {
                throw new Exception('Invalid priority');
            }

            $result = $bulkService->bulkUpdateInquiries($inquiryIds, ['priority' => $priority]);

            // Rescore inquiries after priority change
            foreach ($inquiryIds as $id) {
                $leadScoringService->updateInquiryScore($id);
            }

            $message = $result ? 'Inquiry priority updated successfully' : 'Failed to update priority';
            break;

        case 'status':
            $status = $_POST['status'] ?? '';
            if (!in_array($status, ['new', 'contacted', 'converted', 'closed'])) {
                throw new Exception('Invalid status');
            }

            $result = $bulkService->bulkUpdateInquiries($inquiryIds, ['status' => $status]);
            $message = $result ? 'Inquiry status updated successfully' : 'Failed to update status';
            break;

        case 'email':
            $subject = trim($_POST['email_subject'] ?? '');
            $body = trim($_POST['email_body'] ?? '');

            if (empty($subject) || empty($body)) {
                throw new Exception('Email subject and body are required');
            }

            // Get recipients
            $recipients = $bulkService->getBulkEmailRecipients($inquiryIds);

            if (empty($recipients)) {
                throw new Exception('No valid email addresses found for selected inquiries');
            }

            // Queue emails
            $queued = 0;
            foreach ($recipients as $recipient) {
                if (
                    $emailService->queueEmail(
                        $recipient['email'],
                        $recipient['name'],
                        $subject,
                        $body,
                        'bulk_inquiry_email'
                    )
                ) {
                    $queued++;
                }
            }

            $result = $queued > 0;
            $message = $result ? "$queued email(s) queued successfully" : 'Failed to queue emails';
            break;

        case 'delete':
            // Admin only for bulk delete
            if (!hasRole('admin')) {
                throw new Exception('Only administrators can bulk delete inquiries');
            }

            $result = $bulkService->bulkDelete('inquiries', $inquiryIds);
            $message = $result ? count($inquiryIds) . ' inquiries deleted successfully' : 'Failed to delete inquiries';
            break;

        default:
            throw new Exception('Invalid action');
    }

    echo json_encode([
        'success' => $result,
        'message' => $message,
        'count' => count($inquiryIds)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
