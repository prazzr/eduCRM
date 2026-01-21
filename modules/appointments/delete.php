<?php
require_once '../../app/bootstrap.php';


requireLogin();

// Only admin and counselor can access
if (!hasRole('admin') && !hasRole('counselor')) {
    header('Location: ../../index.php');
    exit;
}

$appointmentService = new \EduCRM\Services\AppointmentService($pdo);

// Get appointment ID
$appointmentId = $_GET['id'] ?? 0;
$appointment = $appointmentService->getAppointment($appointmentId);

if (!$appointment) {
    header('Location: list.php');
    exit;
}

// Check permission
if (!hasRole('admin') && $appointment['counselor_id'] != $_SESSION['user_id']) {
    redirectWithAlert("list.php", "Unauthorized access.", "danger");
}

// Delete the appointment
if ($appointmentService->deleteAppointment($appointmentId)) {
    redirectWithAlert("list.php", "Appointment deleted successfully!", "danger");
} else {
    redirectWithAlert("list.php", "Failed to delete appointment.", "danger");
}
exit;
