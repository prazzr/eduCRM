<?php
require_once '../../config.php';
require_once '../../includes/services/AppointmentService.php';

requireLogin();

// Only admin and counselor can access
if (!hasRole('admin') && !hasRole('counselor')) {
    header('Location: ../../index.php');
    exit;
}

$appointmentService = new AppointmentService($pdo);

// Get appointment ID
$appointmentId = $_GET['id'] ?? 0;
$appointment = $appointmentService->getAppointment($appointmentId);

if (!$appointment) {
    header('Location: list.php');
    exit;
}

// Check permission
if (!hasRole('admin') && $appointment['counselor_id'] != $_SESSION['user_id']) {
    header('Location: list.php');
    exit;
}

// Delete the appointment
if ($appointmentService->deleteAppointment($appointmentId)) {
    header('Location: list.php?deleted=1');
} else {
    header('Location: list.php?error=delete_failed');
}
exit;
