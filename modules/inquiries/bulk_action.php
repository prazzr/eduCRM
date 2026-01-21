<?php
/**
 * Bulk Action Handler for Inquiries
 * Processes bulk operations on selected inquiries
 */

require_once '../../app/bootstrap.php';




requireLogin();
requireAdminCounselorOrBranchManager();

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$bulkService = new \EduCRM\Services\BulkActionService($pdo);
$leadScoringService = new \EduCRM\Services\LeadScoringService($pdo);
$emailService = new \EduCRM\Services\EmailNotificationService($pdo);

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

            // Get priority_id from lookup table
            $priorityStmt = $pdo->prepare("SELECT id FROM priority_levels WHERE name = ?");
            $priorityStmt->execute([$priority]);
            $priority_id = $priorityStmt->fetchColumn();

            if (!$priority_id) {
                throw new Exception('Priority not found');
            }

            $result = $bulkService->bulkUpdateInquiries($inquiryIds, ['priority_id' => $priority_id]);

            // Note: Do NOT call updateInquiryScore here - it would overwrite the manual priority
            // The score is calculated automatically and doesn't need to be updated when only priority changes

            $message = $result ? 'Inquiry priority updated successfully' : 'Failed to update priority';
            break;

        case 'status':
            $status = $_POST['status'] ?? '';
            if (!in_array($status, ['new', 'contacted', 'converted', 'closed'])) {
                throw new Exception('Invalid status');
            }

            // Get status_id from lookup table
            $statusStmt = $pdo->prepare("SELECT id FROM inquiry_statuses WHERE name = ?");
            $statusStmt->execute([$status]);
            $status_id = $statusStmt->fetchColumn();

            if (!$status_id) {
                throw new Exception('Status not found');
            }

            // Auto-convert to students if status is 'converted'
            $studentsCreated = 0;
            if ($status === 'converted') {
                // Get inquiries that are not already converted
                $convertedStatusCheck = $pdo->prepare("SELECT id FROM inquiry_statuses WHERE name = 'converted'");
                $convertedStatusCheck->execute();
                $convertedStatusId = $convertedStatusCheck->fetchColumn();

                // Get inquiries to convert
                $placeholders = implode(',', array_fill(0, count($inquiryIds), '?'));
                $inqStmt = $pdo->prepare("SELECT id, name, email, phone, country_id, education_level_id, status_id FROM inquiries WHERE id IN ($placeholders)");
                $inqStmt->execute($inquiryIds);
                $inquiriesToConvert = $inqStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($inquiriesToConvert as $inq) {
                    // Skip if already converted or no email
                    if ($inq['status_id'] == $convertedStatusId || empty($inq['email'])) {
                        continue;
                    }

                    // Check if user exists
                    $checkUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $checkUser->execute([$inq['email']]);

                    if ($checkUser->rowCount() == 0) {
                        try {
                            $raw_password = generateSecurePassword();
                            $password_hash = password_hash($raw_password, PASSWORD_DEFAULT);

                            $userStmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, phone, country_id, education_level_id) VALUES (?, ?, ?, 'student', ?, ?, ?)");
                            $userStmt->execute([$inq['name'], $inq['email'], $password_hash, $inq['phone'], $inq['country_id'], $inq['education_level_id']]);
                            $user_id = $pdo->lastInsertId();

                            $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'student'");
                            $roleStmt->execute();
                            $role_id = $roleStmt->fetchColumn();

                            $linkStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                            $linkStmt->execute([$user_id, $role_id]);

                            $studentsCreated++;
                        } catch (PDOException $e) {
                            // Continue with next inquiry on error
                        }
                    }
                }
            }

            $result = $bulkService->bulkUpdateInquiries($inquiryIds, ['status_id' => $status_id]);
            $message = $result ? 'Inquiry status updated successfully' : 'Failed to update status';
            if ($studentsCreated > 0) {
                $message .= " ($studentsCreated student(s) created)";
            }
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
