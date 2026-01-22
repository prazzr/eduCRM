<?php
require_once '../../app/bootstrap.php';
ob_start();


requireLogin();

// Only admin and counselor can access
if (!hasRole('admin') && !hasRole('counselor')) {
    header('Location: ../../index.php');
    exit;
}

$pageDetails = ['title' => 'Schedule Appointment'];
require_once '../../templates/header.php';

$appointmentService = new \EduCRM\Services\AppointmentService($pdo);
$error = '';
$success = '';

// Get students and inquiries for dropdown
$studentsStmt = $pdo->query("
    SELECT u.id, u.name, u.email 
    FROM users u 
    JOIN user_roles ur ON u.id = ur.user_id 
    JOIN roles r ON ur.role_id = r.id 
    WHERE r.name = 'student'
    ORDER BY u.name
");
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

$inquiriesStmt = $pdo->query("
    SELECT id, name, email 
    FROM inquiries 
    WHERE status NOT IN ('converted', 'closed')
    ORDER BY created_at DESC
");
$inquiries = $inquiriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get counselors (for admin)
if (hasRole('admin')) {
    $counselorsStmt = $pdo->query("
        SELECT DISTINCT u.id, u.name 
        FROM users u 
        JOIN user_roles ur ON u.id = ur.user_id 
        JOIN roles r ON ur.role_id = r.id 
        WHERE r.name IN ('admin', 'counselor')
        ORDER BY u.name
    ");
    $counselors = $counselorsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $appointment_date = $_POST['appointment_date'] ?? '';
    $duration = $_POST['duration_minutes'] ?? 30;
    $location = trim($_POST['location'] ?? '');
    $meeting_link = trim($_POST['meeting_link'] ?? '');
    $client_type = $_POST['client_type'] ?? '';
    $client_id = $_POST['client_id'] ?? null;
    $counselor_id = hasRole('admin') ? ($_POST['counselor_id'] ?? '') : $_SESSION['user_id'];

    if (empty($title)) {
        redirectWithAlert("add.php", 'Appointment title is required.', 'error');
    } elseif (empty($appointment_date)) {
        redirectWithAlert("add.php", 'Appointment date and time is required.', 'error');
    } elseif (empty($counselor_id)) {
        redirectWithAlert("add.php", 'Please select a counselor.', 'error');
    } else {
        // Check for conflicts
        $hasConflict = $appointmentService->checkConflict($counselor_id, $appointment_date, $duration);

        if ($hasConflict) {
            redirectWithAlert("add.php", 'This time slot conflicts with another appointment. Please choose a different time.', 'error');
        } else {
            $appointmentData = [
                'title' => $title,
                'description' => $description,
                'appointment_date' => $appointment_date,
                'duration_minutes' => $duration,
                'location' => $location ?: null,
                'meeting_link' => $meeting_link ?: null,
                'counselor_id' => $counselor_id,
                'student_id' => ($client_type === 'student' && $client_id) ? $client_id : null,
                'inquiry_id' => ($client_type === 'inquiry' && $client_id) ? $client_id : null
            ];

            if ($appointmentService->createAppointment($appointmentData)) {
                redirectWithAlert("list.php", 'Appointment scheduled successfully!', 'success');
            } else {
                redirectWithAlert("add.php", 'Failed to schedule appointment. Please try again.', 'error');
            }
        }
    }
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Schedule New Appointment</h1>
    <p class="text-slate-600 mt-1">Create an appointment with a student or inquiry</p>
</div>

<?php renderFlashMessage(); ?>

<div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <form method="POST" class="space-y-4">
        <!-- Title -->
        <div>
            <label for="title" class="block text-sm font-medium text-slate-700 mb-1">
                Appointment Title <span class="text-red-500">*</span>
            </label>
            <input type="text" id="title" name="title" required
                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="e.g., Initial Consultation, Document Review"
                value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
        </div>

        <!-- Description -->
        <div>
            <label for="description" class="block text-sm font-medium text-slate-700 mb-1">
                Description
            </label>
            <textarea id="description" name="description" rows="3"
                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Additional details about this appointment..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Appointment Date & Time -->
            <div>
                <label for="appointment_date" class="block text-sm font-medium text-slate-700 mb-1">
                    Date & Time <span class="text-red-500">*</span>
                </label>
                <input type="datetime-local" id="appointment_date" name="appointment_date" required
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    value="<?php echo htmlspecialchars($_POST['appointment_date'] ?? ''); ?>">
            </div>

            <!-- Duration -->
            <div>
                <label for="duration_minutes" class="block text-sm font-medium text-slate-700 mb-1">
                    Duration (minutes)
                </label>
                <select id="duration_minutes" name="duration_minutes"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    <option value="15">15 minutes</option>
                    <option value="30" selected>30 minutes</option>
                    <option value="45">45 minutes</option>
                    <option value="60">1 hour</option>
                    <option value="90">1.5 hours</option>
                    <option value="120">2 hours</option>
                </select>
            </div>
        </div>

        <?php if (hasRole('admin')): ?>
            <!-- Counselor (Admin only) -->
            <div>
                <label for="counselor_id" class="block text-sm font-medium text-slate-700 mb-1">
                    Counselor <span class="text-red-500">*</span>
                </label>
                <select id="counselor_id" name="counselor_id" required
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    <option value="">Select Counselor</option>
                    <?php foreach ($counselors as $counselor): ?>
                        <option value="<?php echo $counselor['id']; ?>" <?php echo (isset($_POST['counselor_id']) && $_POST['counselor_id'] == $counselor['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($counselor['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="border-t border-slate-200 pt-4 mt-4">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Client Information</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Client Type -->
                <div>
                    <label for="client_type" class="block text-sm font-medium text-slate-700 mb-1">
                        Client Type
                    </label>
                    <select id="client_type" name="client_type"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        onchange="toggleClientSelect()">
                        <option value="">None</option>
                        <option value="student">Student</option>
                        <option value="inquiry">Inquiry</option>
                    </select>
                </div>

                <!-- Client Selection -->
                <div id="client_select_wrapper" style="display: none;">
                    <label for="client_id" class="block text-sm font-medium text-slate-700 mb-1">
                        Select Client
                    </label>
                    <select id="client_id" name="client_id"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Select...</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="border-t border-slate-200 pt-4 mt-4">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Location</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Physical Location -->
                <div>
                    <label for="location" class="block text-sm font-medium text-slate-700 mb-1">
                        Physical Location
                    </label>
                    <input type="text" id="location" name="location"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        placeholder="e.g., Office Room 201"
                        value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                </div>

                <!-- Meeting Link -->
                <div>
                    <label for="meeting_link" class="block text-sm font-medium text-slate-700 mb-1">
                        Online Meeting Link
                    </label>
                    <input type="url" id="meeting_link" name="meeting_link"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        placeholder="https://zoom.us/j/..."
                        value="<?php echo htmlspecialchars($_POST['meeting_link'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex gap-3 pt-4">
            <button type="submit" class="btn">
                Schedule Appointment
            </button>
            <a href="list.php" class="btn-secondary px-4 py-2 rounded-lg font-medium">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
    const students = <?php echo json_encode($students); ?>;
    const inquiries = <?php echo json_encode($inquiries); ?>;

    function toggleClientSelect() {
        const clientType = document.getElementById('client_type').value;
        const wrapper = document.getElementById('client_select_wrapper');
        const select = document.getElementById('client_id');

        if (clientType) {
            wrapper.style.display = 'block';
            select.innerHTML = '<option value="">Select...</option>';

            const data = clientType === 'student' ? students : inquiries;
            data.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = `${item.name} (${item.email})`;
                select.appendChild(option);
            });
        } else {
            wrapper.style.display = 'none';
            select.innerHTML = '<option value="">Select...</option>';
        }
    }
</script>

<?php require_once '../../templates/footer.php'; ?>