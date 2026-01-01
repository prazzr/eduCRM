<?php
/**
 * Bulk Action Handler for Appointments
 * Processes bulk operations on selected appointments
 */

require_once '../../config.php';
require_once '../../includes/services/BulkActionService.php';
require_once '../../includes/services/AppointmentService.php';

requireLogin();
requireAdminOrCounselor();

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$bulkService = new BulkActionService($pdo);
$appointmentService = new AppointmentService($pdo);

$action = $_POST['action'] ?? '';
$appointmentIds = $_POST['appointment_ids'] ?? [];

// Validate input
if (empty($appointmentIds) || !is_array($appointmentIds)) {
    echo json_encode(['success' => false, 'message' => 'No appointments selected']);
    exit;
}

// Convert to integers
$appointmentIds = array_map('intval', $appointmentIds);

// Verify permissions - counselors can only edit their own appointments unless admin
if (!hasRole('admin')) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE id IN (" . implode(',', array_fill(0, count($appointmentIds), '?')) . ") AND counselor_id != ?");
    $stmt->execute(array_merge($appointmentIds, [$_SESSION['user_id']]));

    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'You can only perform bulk actions on your own appointments']);
        exit;
    }
}

try {
    $result = false;
    $message = '';

    switch ($action) {
        case 'status':
            $status = $_POST['status'] ?? '';
            if (!in_array($status, ['scheduled', 'completed', 'cancelled', 'no_show'])) {
                throw new Exception('Invalid status');
            }

            $result = $bulkService->bulkUpdateAppointments($appointmentIds, ['status' => $status]);
            $message = $result ? 'Appointment status updated successfully' : 'Failed to update status';
            break;

        case 'reschedule':
            $daysOffset = intval($_POST['days_offset'] ?? 0);
            if ($daysOffset == 0) {
                throw new Exception('Invalid reschedule offset');
            }

            $result = $bulkService->bulkRescheduleAppointments($appointmentIds, $daysOffset);
            $message = $result ? "Appointments rescheduled by $daysOffset day(s)" : 'Failed to reschedule appointments';
            break;

        case 'delete':
            // Admin only for bulk delete
            if (!hasRole('admin')) {
                throw new Exception('Only administrators can bulk delete appointments');
            }

            $result = $bulkService->bulkDelete('appointments', $appointmentIds);
            $message = $result ? count($appointmentIds) . ' appointments deleted successfully' : 'Failed to delete appointments';
            break;

        default:
            throw new Exception('Invalid action');
    }

    echo json_encode([
        'success' => $result,
        'message' => $message,
        'count' => count($appointmentIds)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
