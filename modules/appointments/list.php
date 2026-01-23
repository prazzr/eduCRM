<?php
require_once '../../app/bootstrap.php';


requireLogin();

// Only admin and counselor can access
if (!hasRole('admin') && !hasRole('counselor')) {
    header('Location: ../../index.php');
    exit;
}

$pageDetails = ['title' => 'Appointments'];
require_once '../../templates/header.php';

$appointmentService = new \EduCRM\Services\AppointmentService($pdo);

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

// Initialize Google Calendar service for UI status
$calendarService = new \EduCRM\Services\GoogleCalendarService($pdo);
$isCalendarConnected = $calendarService->isConnected($_SESSION['user_id']);
$isCalendarConfigured = $calendarService->isConfigured();

// Get sync status for appointments (if calendar is connected)
$syncStatuses = [];
if ($isCalendarConnected && count($appointments) > 0) {
    $appointmentIds = array_column($appointments, 'id');
    $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
    $syncStmt = $pdo->prepare("
        SELECT appointment_id, sync_status, last_synced_at 
        FROM calendar_sync_events 
        WHERE appointment_id IN ($placeholders) AND user_id = ?
    ");
    $syncStmt->execute([...$appointmentIds, $_SESSION['user_id']]);
    while ($row = $syncStmt->fetch(PDO::FETCH_ASSOC)) {
        $syncStatuses[$row['appointment_id']] = $row;
    }
}
?>

<div class="page-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h1 class="page-title">Appointments</h1>
    <div class="flex items-center gap-3 flex-wrap">
        <!-- Google Calendar Status -->
        <?php if ($isCalendarConfigured): ?>
            <?php if ($isCalendarConnected): ?>
                <span class="flex items-center gap-1 text-sm text-green-600 bg-green-50 px-3 py-1.5 rounded-lg">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" />
                    </svg>
                    Calendar Synced
                </span>
                <a href="google-connect.php?action=disconnect" class="text-sm text-slate-500 hover:text-red-500">Disconnect</a>
            <?php else: ?>
                <a href="google-connect.php"
                    class="flex items-center gap-2 text-sm bg-white border border-slate-200 px-3 py-1.5 rounded-lg hover:bg-slate-50 transition-colors">
                    <svg class="w-4 h-4" viewBox="0 0 24 24">
                        <path fill="#4285F4"
                            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                        <path fill="#34A853"
                            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                        <path fill="#FBBC05"
                            d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                        <path fill="#EA4335"
                            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                    </svg>
                    Connect Google Calendar
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <a href="calendar.php" class="btn btn-secondary">
            <?php echo \EduCRM\Services\NavigationService::getIcon('calendar', 16); ?> Calendar View
        </a>
        <a href="add.php" class="btn btn-primary">
            <?php echo \EduCRM\Services\NavigationService::getIcon('plus', 16); ?> New Appointment
        </a>
    </div>
</div>

<?php renderFlashMessage(); ?>

<!-- Quick Search with Alpine.js -->
<div class="bg-white px-4 py-3 rounded-xl border border-slate-200 shadow-sm mb-4">
    <div x-data='searchFilter({
        data: <?php echo json_encode(array_map(function ($a) {
            return [
                'id' => $a['id'],
                'title' => $a['title'],
                'client' => $a['student_name'] ?? $a['inquiry_name'] ?? 'N/A',
                'date' => date('M d, Y H:i', strtotime($a['appointment_date'])),
                'status' => $a['status']
            ];
        }, $appointments)); ?>,
        searchFields: ["title", "client"],
        minLength: 1,
        maxResults: 8
        })' class="relative">
        <div class="flex items-center gap-3">
            <span class="text-slate-400">🔍</span>
            <input type="text" x-model="query" @input="search()" @focus="if(query.length >= 1) showResults = true"
                @keydown="handleKeydown($event)" @keydown.escape="showResults = false"
                class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500"
                placeholder="Quick search by title or client name..." autocomplete="off">

            <span x-show="loading" class="spinner text-slate-400"></span>
        </div>

        <!-- Search Results Dropdown -->
        <div x-show="showResults && results.length > 0" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 transform -translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" @click.outside="showResults = false"
            class="search-results-container absolute top-full left-0 right-0 mt-2 bg-white border border-slate-200 rounded-xl shadow-lg max-h-80 overflow-y-auto z-50">

            <template x-for="(item, index) in results" :key="item.id">
                <a :href="'edit.php?id=' + item.id" :data-index="index" @mouseenter="setSelectedIndex(index)"
                    class="flex items-center gap-3 px-4 py-3 border-b border-slate-100 transition-colors"
                    :class="{ 'bg-primary-50 border-l-4 border-l-teal-600': isSelected(index), 'hover:bg-slate-50': !isSelected(index) }">
                    <div class="w-1.5 h-9 rounded-full" :class="{
                             'bg-blue-500': item.status === 'scheduled',
                             'bg-emerald-500': item.status === 'completed',
                             'bg-red-500': item.status === 'cancelled',
                             'bg-orange-500': item.status === 'no_show'
                         }"></div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-slate-800" x-text="item.title"></div>
                        <div class="text-xs text-slate-500" x-text="item.client"></div>
                    </div>
                    <div class="text-right text-xs text-slate-400">
                        <div x-text="item.date"></div>
                        <div class="uppercase text-[9px] font-semibold" x-text="item.status"></div>
                    </div>
                </a>
            </template>

            <div x-show="results.length === 0 && query.length >= 1 && !loading"
                class="px-4 py-3 text-center text-slate-500 text-sm">
                No appointments found
            </div>
        </div>
    </div>
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
                                <div class="flex gap-2 justify-end items-center">
                                    <?php 
                                    // Show sync status indicator if calendar is connected
                                    if ($isCalendarConnected) {
                                        $syncInfo = $syncStatuses[$apt['id']] ?? null;
                                        if ($syncInfo && $syncInfo['sync_status'] === 'synced') {
                                            echo '<span class="text-green-500" title="Synced to Google Calendar"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.6 5.84C16.24 4.47 14.28 3.5 12 3.5c-3.73 0-6.92 2.38-8.11 5.71L7 10.55c.67-2.08 2.46-3.55 4.67-3.55 1.52 0 2.88.64 3.87 1.66L12 12h8V4l-2.4 1.84z"/><path d="M17.6 5.84C16.24 4.47 14.28 3.5 12 3.5c-3.73 0-6.92 2.38-8.11 5.71L7 10.55c.67-2.08 2.46-3.55 4.67-3.55 1.52 0 2.88.64 3.87 1.66L12 12h8V4l-2.4 1.84M12 20.5c2.28 0 4.24-.97 5.6-2.34L20 20V12h-8l3.54 3.54c-.99 1.02-2.35 1.66-3.87 1.66-2.21 0-4-1.47-4.67-3.55L3.89 14.79c1.19 3.33 4.38 5.71 8.11 5.71"/></svg></span>';
                                        } elseif ($syncInfo && $syncInfo['sync_status'] === 'failed') {
                                            echo '<span class="text-red-500" title="Sync failed - click to retry"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg></span>';
                                        } else {
                                            echo '<span class="text-slate-300" title="Not synced"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg></span>';
                                        }
                                    }
                                    ?>
                                    <a href="edit.php?id=<?php echo $apt['id']; ?>" class="action-btn default" title="Edit">
                                        <?php echo \EduCRM\Services\NavigationService::getIcon('edit', 16); ?>
                                    </a>
                                    <?php if ($apt['meeting_link']): ?>
                                        <a href="<?php echo htmlspecialchars($apt['meeting_link']); ?>" target="_blank"
                                            class="action-btn blue" title="Join Meeting">
                                            <?php echo \EduCRM\Services\NavigationService::getIcon('users', 16); ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (hasRole('admin')): ?>
                                        <a href="#" onclick="confirmDelete(<?php echo $apt['id']; ?>)" class="action-btn red"
                                            title="Delete">
                                            <?php echo \EduCRM\Services\NavigationService::getIcon('trash', 16); ?>
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

<!-- Bulk Action JavaScript with Modal System -->
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
                Modal.show({ type: 'warning', title: 'Select Action', message: 'Please select an action' });
                return;
            }

            const selectedIds = Array.from(document.querySelectorAll('.appointment-checkbox:checked')).map(cb => cb.value);
            if (selectedIds.length === 0) {
                Modal.show({ type: 'warning', title: 'No Selection', message: 'Please select at least one appointment' });
                return;
            }

            let formData = new FormData();
            formData.append('action', action);
            selectedIds.forEach(id => formData.append('appointment_ids[]', id));

            if (action === 'status') {
                const status = statusSelect.value;
                if (!status) {
                    Modal.show({ type: 'warning', title: 'Select Status', message: 'Please select a status' });
                    return;
                }
                formData.append('status', status);
            } else if (action === 'reschedule') {
                const days = daysOffset.value;
                if (!days || days == 0) {
                    Modal.show({ type: 'warning', title: 'Enter Days', message: 'Please enter number of days to reschedule (+/-)' });
                    return;
                }
                formData.append('days_offset', days);
            }

            const actionNames = { status: 'update status for', reschedule: 'reschedule', delete: 'delete' };

            Modal.show({
                type: action === 'delete' ? 'error' : 'warning',
                title: 'Confirm Bulk Action',
                message: `Are you sure you want to ${actionNames[action] || action} ${selectedIds.length} appointment(s)?`,
                confirmText: 'Yes, Proceed',
                onConfirm: function () {
                    fetch('bulk_action.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Modal.show({ type: 'success', title: 'Success', message: data.message });
                                setTimeout(() => window.location.reload(), 1500);
                            } else {
                                Modal.show({ type: 'error', title: 'Error', message: data.message });
                            }
                        })
                        .catch(error => {
                            Modal.show({ type: 'error', title: 'Error', message: 'An error occurred: ' + error });
                        });
                }
            });
        });
    });

    function confirmDelete(id) {
        Modal.show({
            type: 'error',
            title: 'Delete Appointment?',
            message: 'Are you sure you want to delete this appointment? This action cannot be undone.',
            confirmText: 'Yes, Delete It',
            onConfirm: function () {
                window.location.href = 'delete.php?id=' + id;
            }
        });
    }
</script>

<?php require_once '../../templates/footer.php'; ?>