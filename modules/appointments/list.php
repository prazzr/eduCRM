<?php
require_once '../../config.php';
require_once '../../includes/services/AppointmentService.php';

requireLogin();

// Only admin and counselor can access
if (!hasRole('admin') && !hasRole('counselor')) {
    header('Location: ../../index.php');
    exit;
}

$pageDetails = ['title' => 'Appointments'];
require_once '../../includes/header.php';

$appointmentService = new AppointmentService($pdo);

// Get filter parameters
$statusFilter = $_GET['status'] ?? null;
$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;

$filters = [];
if ($statusFilter)
    $filters['status'] = $statusFilter;
if ($dateFrom)
    $filters['date_from'] = $dateFrom . ' 00:00:00';
if ($dateTo)
    $filters['date_to'] = $dateTo . ' 23:59:59';

// Get appointments based on role
if (hasRole('admin')) {
    $counselorFilter = $_GET['counselor_id'] ?? null;
    if ($counselorFilter)
        $filters['counselor_id'] = $counselorFilter;
    $appointments = $appointmentService->getAllAppointments($filters);

    // Get all counselors for filter
    $counselorsStmt = $pdo->query("
        SELECT DISTINCT u.id, u.name 
        FROM users u 
        JOIN user_roles ur ON u.id = ur.user_id 
        JOIN roles r ON ur.role_id = r.id 
        WHERE r.name IN ('admin', 'counselor')
        ORDER BY u.name
    ");
    $counselors = $counselorsStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $appointments = $appointmentService->getCounselorAppointments($_SESSION['user_id'], $filters);
}
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold text-slate-800">Appointments</h1>
    <div class="flex gap-3">
        <a href="calendar.php" class="btn-secondary px-4 py-2 rounded-lg font-medium">📅 Calendar View</a>
        <a href="add.php" class="btn">+ New Appointment</a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6">
    <form method="GET" class="flex flex-wrap gap-3">
        <select name="status" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">All Statuses</option>
            <option value="scheduled" <?php echo $statusFilter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            <option value="no_show" <?php echo $statusFilter === 'no_show' ? 'selected' : ''; ?>>No Show</option>
        </select>

        <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom ?? ''); ?>"
            class="px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="From Date">

        <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo ?? ''); ?>"
            class="px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="To Date">

        <?php if (hasRole('admin')): ?>
            <select name="counselor_id" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                <option value="">All Counselors</option>
                <?php foreach ($counselors as $counselor): ?>
                    <option value="<?php echo $counselor['id']; ?>" <?php echo ($counselorFilter == $counselor['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($counselor['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <button type="submit" class="btn-secondary px-4 py-2 rounded-lg text-sm">Apply Filters</button>
        <a href="list.php" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Clear</a>
    </form>
</div>

<!-- Phase 2C: Bulk Action Toolbar -->
<div id="bulkToolbar" class="hidden bg-primary-50 border border-primary-200 p-4 rounded-xl mb-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <span class="text-primary-700 font-medium">
                <span id="selectedCount">0</span> appointment(s) selected
            </span>

            <select id="bulkAction" class="px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white">
                <option value="">Choose Action...</option>
                <option value="status">Change Status...</option>
                <option value="reschedule">Reschedule...</option>
                <?php if (hasRole('admin')): ?>
                    <option value="delete">Delete Selected</option>
                <?php endif; ?>
            </select>

            <select id="statusSelect" class="hidden px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white">
                <option value="">Select Status...</option>
                <option value="scheduled">Scheduled</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
                <option value="no_show">No Show</option>
            </select>

            <input type="number" id="daysOffset"
                class="hidden px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white w-32"
                placeholder="Days (+/-)">

            <button id="applyBulkAction" class="btn px-4 py-2 text-sm">Apply</button>
        </div>

        <button id="clearSelection" class="text-sm text-primary-600 hover:text-primary-800">Clear Selection</button>
    </div>
</div>

<!-- Appointments Table -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-sm">
                    <th class="p-3 w-12">
                        <input type="checkbox" id="selectAll"
                            class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                    </th>
                    <th class="p-3 font-semibold">Date & Time</th>
                    <th class="p-3 font-semibold">Title</th>
                    <th class="p-3 font-semibold">Client</th>
                    <?php if (hasRole('admin')): ?>
                        <th class="p-3 font-semibold">Counselor</th>
                    <?php endif; ?>
                    <th class="p-3 font-semibold">Location</th>
                    <th class="p-3 font-semibold">Status</th>
                    <th class="p-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (count($appointments) > 0): ?>
                    <?php foreach ($appointments as $apt): ?>
                        <?php
                        $statusColors = [
                            'scheduled' => 'bg-blue-100 text-blue-700',
                            'completed' => 'bg-emerald-100 text-emerald-700',
                            'cancelled' => 'bg-red-100 text-red-700',
                            'no_show' => 'bg-orange-100 text-orange-700'
                        ];

                        $isPast = strtotime($apt['appointment_date']) < time();
                        $isToday = date('Y-m-d', strtotime($apt['appointment_date'])) === date('Y-m-d');
                        ?>
                        <tr
                            class="hover:bg-slate-50 transition-colors <?php echo $isToday && $apt['status'] === 'scheduled' ? 'bg-blue-50' : ''; ?>">
                            <td class="p-3">
                                <input type="checkbox"
                                    class="appointment-checkbox rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                                    value="<?php echo $apt['id']; ?>">
                            </td>
                            <td class="p-3">
                                <div class="text-sm">
                                    <div class="font-semibold text-slate-800">
                                        <?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?>
                                    </div>
                                    <div class="text-slate-600">
                                        <?php echo date('h:i A', strtotime($apt['appointment_date'])); ?>
                                        <?php if ($isToday): ?>
                                            <span class="ml-2 text-xs bg-blue-600 text-white px-2 py-0.5 rounded">Today</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3">
                                <strong class="text-slate-800">
                                    <?php echo htmlspecialchars($apt['title']); ?>
                                </strong>
                                <?php if ($apt['description']): ?>
                                    <p class="text-xs text-slate-500 mt-1">
                                        <?php echo htmlspecialchars(substr($apt['description'], 0, 50)) . (strlen($apt['description']) > 50 ? '...' : ''); ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-sm text-slate-600">
                                <?php
                                $clientName = $apt['student_name'] ?? $apt['inquiry_name'] ?? 'N/A';
                                echo htmlspecialchars($clientName);
                                ?>
                            </td>
                            <?php if (hasRole('admin')): ?>
                                <td class="p-3 text-sm text-slate-600">
                                    <?php echo htmlspecialchars($apt['counselor_name']); ?>
                                </td>
                            <?php endif; ?>
                            <td class="p-3 text-sm text-slate-600">
                                <?php if ($apt['meeting_link']): ?>
                                    <a href="<?php echo htmlspecialchars($apt['meeting_link']); ?>" target="_blank"
                                        class="text-primary-600 hover:underline">
                                        Online Meeting
                                    </a>
                                <?php elseif ($apt['location']): ?>
                                    <?php echo htmlspecialchars($apt['location']); ?>
                                <?php else: ?>
                                    <span class="text-slate-400">Not specified</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3">
                                <span
                                    class="inline-block px-2 py-0.5 rounded text-xs font-bold uppercase <?php echo $statusColors[$apt['status']]; ?>">
                                    <?php echo str_replace('_', ' ', $apt['status']); ?>
                                </span>
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex gap-2 justify-end">
                                    <a href="edit.php?id=<?php echo $apt['id']; ?>"
                                        class="btn-secondary px-3 py-1.5 text-xs rounded">Edit</a>
                                    <?php if ($apt['meeting_link']): ?>
                                        <a href="<?php echo htmlspecialchars($apt['meeting_link']); ?>" target="_blank"
                                            class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 text-xs rounded font-medium">
                                            Join
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo hasRole('admin') ? '8' : '7'; ?>" class="p-6 text-center text-slate-500">
                            No appointments found. <a href="add.php" class="text-primary-600 hover:underline">Schedule your
                                first appointment</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Phase 2C: Bulk Action JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('selectAll');
        const appointmentCheckboxes = document.querySelectorAll('.appointment-checkbox');
        const bulkToolbar = document.getElementById('bulkToolbar');
        const selectedCount = document.getElementById('selectedCount');
        const bulkAction = document.getElementById('bulkAction');
        const statusSelect = document.getElementById('statusSelect');
        const daysOffset = document.getElementById('daysOffset');
        const applyBulkAction = document.getElementById('applyBulkAction');
        const clearSelection = document.getElementById('clearSelection');

        function updateBulkToolbar() {
            const checked = document.querySelectorAll('.appointment-checkbox:checked');
            selectedCount.textContent = checked.length;
            bulkToolbar.classList.toggle('hidden', checked.length === 0);
        }

        selectAll.addEventListener('change', function () {
            appointmentCheckboxes.forEach(cb => cb.checked = this.checked);
            updateBulkToolbar();
        });

        appointmentCheckboxes.forEach(cb => {
            cb.addEventListener('change', function () {
                selectAll.checked = document.querySelectorAll('.appointment-checkbox:checked').length === appointmentCheckboxes.length;
                updateBulkToolbar();
            });
        });

        bulkAction.addEventListener('change', function () {
            statusSelect.classList.add('hidden');
            daysOffset.classList.add('hidden');

            if (this.value === 'status') statusSelect.classList.remove('hidden');
            if (this.value === 'reschedule') daysOffset.classList.remove('hidden');
        });

        clearSelection.addEventListener('click', function () {
            appointmentCheckboxes.forEach(cb => cb.checked = false);
            selectAll.checked = false;
            updateBulkToolbar();
        });

        applyBulkAction.addEventListener('click', function () {
            const action = bulkAction.value;
            if (!action) {
                alert('Please select an action');
                return;
            }

            const selectedIds = Array.from(document.querySelectorAll('.appointment-checkbox:checked')).map(cb => cb.value);
            if (selectedIds.length === 0) {
                alert('Please select at least one appointment');
                return;
            }

            let formData = new FormData();
            formData.append('action', action);
            selectedIds.forEach(id => formData.append('appointment_ids[]', id));

            if (action === 'status') {
                const status = statusSelect.value;
                if (!status) {
                    alert('Please select a status');
                    return;
                }
                formData.append('status', status);
            } else if (action === 'reschedule') {
                const days = daysOffset.value;
                if (!days || days == 0) {
                    alert('Please enter number of days to reschedule (+/-)');
                    return;
                }
                formData.append('days_offset', days);
            }

            if (!confirm(`Are you sure you want to ${action} ${selectedIds.length} appointment(s)?`)) return;

            fetch('bulk_action.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('An error occurred: ' + error);
                });
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>