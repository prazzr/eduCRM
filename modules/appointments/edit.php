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

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $appointment_date = $_POST['appointment_date'] ?? '';
    $duration = $_POST['duration_minutes'] ?? 30;
    $location = trim($_POST['location'] ?? '');
    $meeting_link = trim($_POST['meeting_link'] ?? '');
    $status = $_POST['status'] ?? 'scheduled';
    $notes = trim($_POST['notes'] ?? '');

    if (empty($title)) {
        $error = 'Appointment title is required.';
    } elseif (empty($appointment_date)) {
        $error = 'Appointment date and time is required.';
    } else {
        $updateData = [
            'title' => $title,
            'description' => $description,
            'appointment_date' => $appointment_date,
            'duration_minutes' => $duration,
            'location' => $location ?: null,
            'meeting_link' => $meeting_link ?: null,
            'status' => $status,
            'notes' => $notes
        ];

        if ($appointmentService->updateAppointment($appointmentId, $updateData)) {
            $success = 'Appointment updated successfully!';
            // Refresh appointment data
            $appointment = $appointmentService->getAppointment($appointmentId);
        } else {
            $error = 'Failed to update appointment. Please try again.';
        }
    }
}

$pageDetails = ['title' => 'Edit Appointment'];
require_once '../../includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Edit Appointment</h1>
        <p class="text-slate-600 mt-1">Update appointment details</p>
    </div>
    <a href="list.php" class="btn-secondary px-4 py-2 rounded-lg font-medium">← Back to List</a>
</div>

<?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-6">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Form -->
    <div class="lg:col-span-2">
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <form method="POST" class="space-y-4">
                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-medium text-slate-700 mb-1">
                        Appointment Title <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="title" name="title" required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        value="<?php echo htmlspecialchars($appointment['title']); ?>">
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700 mb-1">
                        Description
                    </label>
                    <textarea id="description" name="description" rows="3"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"><?php echo htmlspecialchars($appointment['description'] ?? ''); ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Appointment Date & Time -->
                    <div>
                        <label for="appointment_date" class="block text-sm font-medium text-slate-700 mb-1">
                            Date & Time <span class="text-red-500">*</span>
                        </label>
                        <input type="datetime-local" id="appointment_date" name="appointment_date" required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                            value="<?php echo date('Y-m-d\TH:i', strtotime($appointment['appointment_date'])); ?>">
                    </div>

                    <!-- Duration -->
                    <div>
                        <label for="duration_minutes" class="block text-sm font-medium text-slate-700 mb-1">
                            Duration (minutes)
                        </label>
                        <select id="duration_minutes" name="duration_minutes"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="15" <?php echo $appointment['duration_minutes'] == 15 ? 'selected' : ''; ?>>15
                                minutes</option>
                            <option value="30" <?php echo $appointment['duration_minutes'] == 30 ? 'selected' : ''; ?>>30
                                minutes</option>
                            <option value="45" <?php echo $appointment['duration_minutes'] == 45 ? 'selected' : ''; ?>>45
                                minutes</option>
                            <option value="60" <?php echo $appointment['duration_minutes'] == 60 ? 'selected' : ''; ?>>1
                                hour</option>
                            <option value="90" <?php echo $appointment['duration_minutes'] == 90 ? 'selected' : ''; ?>
                                >1.5 hours</option>
                            <option value="120" <?php echo $appointment['duration_minutes'] == 120 ? 'selected' : ''; ?>
                                >2 hours</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Physical Location -->
                    <div>
                        <label for="location" class="block text-sm font-medium text-slate-700 mb-1">
                            Physical Location
                        </label>
                        <input type="text" id="location" name="location"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                            placeholder="e.g., Office Room 201"
                            value="<?php echo htmlspecialchars($appointment['location'] ?? ''); ?>">
                    </div>

                    <!-- Meeting Link -->
                    <div>
                        <label for="meeting_link" class="block text-sm font-medium text-slate-700 mb-1">
                            Online Meeting Link
                        </label>
                        <input type="url" id="meeting_link" name="meeting_link"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                            placeholder="https://zoom.us/j/..."
                            value="<?php echo htmlspecialchars($appointment['meeting_link'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700 mb-1">
                        Status
                    </label>
                    <select id="status" name="status"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="scheduled" <?php echo $appointment['status'] === 'scheduled' ? 'selected' : ''; ?>
                            >Scheduled</option>
                        <option value="completed" <?php echo $appointment['status'] === 'completed' ? 'selected' : ''; ?>
                            >Completed</option>
                        <option value="cancelled" <?php echo $appointment['status'] === 'cancelled' ? 'selected' : ''; ?>
                            >Cancelled</option>
                        <option value="no_show" <?php echo $appointment['status'] === 'no_show' ? 'selected' : ''; ?>>No
                            Show</option>
                    </select>
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-slate-700 mb-1">
                        Notes
                    </label>
                    <textarea id="notes" name="notes" rows="3"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        placeholder="Add any notes about this appointment..."><?php echo htmlspecialchars($appointment['notes'] ?? ''); ?></textarea>
                </div>

                <!-- Submit Buttons -->
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="btn">
                        Update Appointment
                    </button>
                    <a href="list.php" class="btn-secondary px-4 py-2 rounded-lg font-medium">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Sidebar Info -->
    <div class="space-y-6">
        <!-- Appointment Info -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Appointment Information</h3>

            <div class="space-y-3 text-sm">
                <div>
                    <span class="text-slate-500">Client:</span>
                    <p class="font-medium text-slate-800">
                        <?php echo htmlspecialchars($appointment['student_name'] ?? $appointment['inquiry_name'] ?? 'No Client'); ?>
                    </p>
                </div>

                <div>
                    <span class="text-slate-500">Counselor:</span>
                    <p class="font-medium text-slate-800">
                        <?php echo htmlspecialchars($appointment['counselor_name']); ?>
                    </p>
                </div>

                <div>
                    <span class="text-slate-500">Created At:</span>
                    <p class="font-medium text-slate-800">
                        <?php echo date('M d, Y H:i', strtotime($appointment['created_at'])); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Quick Actions</h3>

            <div class="space-y-2">
                <a href="calendar.php"
                    class="block w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium text-sm text-center">
                    📅 View in Calendar
                </a>

                <a href="delete.php?id=<?php echo $appointment['id']; ?>"
                    onclick="return confirm('Are you sure you want to delete this appointment?')"
                    class="block w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium text-sm text-center">
                    Delete Appointment
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>