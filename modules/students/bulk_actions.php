<?php
/**
 * Student Bulk Actions API Endpoint
 * Handles bulk operations for students
 */
require_once '../../app/bootstrap.php';
requireLogin();
requireStaffMember();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$studentIds = $_POST['student_ids'] ?? [];

// Sanitize student IDs
$studentIds = array_filter(array_map('intval', $studentIds));

if (empty($studentIds)) {
    echo json_encode(['success' => false, 'message' => 'No students selected']);
    exit;
}

$bulkService = new \EduCRM\Services\StudentBulkService($pdo);

try {
    switch ($action) {
        case 'email':
            requireAdminCounselorOrBranchManager();
            $subject = trim($_POST['subject'] ?? '');
            $body = trim($_POST['body'] ?? '');

            if (empty($subject) || empty($body)) {
                echo json_encode(['success' => false, 'message' => 'Subject and body are required']);
                exit;
            }

            $result = $bulkService->bulkEmail($studentIds, $subject, $body);
            $message = "Email queued for {$result['success']} student(s).";
            if ($result['failed'] > 0) {
                $message .= " {$result['failed']} failed.";
            }
            echo json_encode(['success' => true, 'message' => $message, 'details' => $result]);
            break;

        case 'sms':
            requireAdminCounselorOrBranchManager();
            $message = trim($_POST['message'] ?? '');

            if (empty($message)) {
                echo json_encode(['success' => false, 'message' => 'Message is required']);
                exit;
            }

            $result = $bulkService->bulkSms($studentIds, $message);
            $responseMsg = "SMS queued for {$result['success']} student(s).";
            if ($result['no_phone'] > 0) {
                $responseMsg .= " {$result['no_phone']} had no phone number.";
            }
            echo json_encode(['success' => true, 'message' => $responseMsg, 'details' => $result]);
            break;

        case 'enroll':
            requireAdminCounselorOrBranchManager();
            $classId = (int) ($_POST['class_id'] ?? 0);

            if ($classId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Please select a class']);
                exit;
            }

            $result = $bulkService->bulkEnroll($studentIds, $classId);
            $message = "{$result['success']} student(s) enrolled successfully.";
            if ($result['already_enrolled'] > 0) {
                $message .= " {$result['already_enrolled']} were already enrolled.";
            }
            echo json_encode(['success' => true, 'message' => $message, 'details' => $result]);
            break;

        case 'status':
            requireAdmin();
            $status = trim($_POST['status'] ?? '');

            if (empty($status)) {
                echo json_encode(['success' => false, 'message' => 'Please select a status']);
                exit;
            }

            $count = $bulkService->bulkUpdateStatus($studentIds, $status);
            echo json_encode(['success' => true, 'message' => "{$count} student(s) updated to '{$status}'"]);
            break;

        case 'export':
            $filename = $bulkService->exportToCsv($studentIds);
            echo json_encode([
                'success' => true,
                'message' => 'Export ready for download',
                'filename' => $filename,
                'download_url' => '/CRM/storage/exports/' . $filename
            ]);
            break;

        case 'delete':
            requireAdmin();
            $result = $bulkService->bulkDelete($studentIds);
            echo json_encode([
                'success' => $result['success'] > 0,
                'message' => "{$result['success']} student(s) deleted. {$result['failed']} failed."
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
    }
} catch (\InvalidArgumentException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (\Exception $e) {
    error_log("Bulk action error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request']);
}
