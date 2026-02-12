<?php
require_once '../../app/bootstrap.php';


requireLogin();

// Only admin and counselor can access
if (!hasRole('admin') && !hasRole('counselor')) {
    header('Location: ../../index.php');
    exit;
}

$appointmentService = new \EduCRM\Services\AppointmentService($pdo);

// Handle AJAX requests for calendar data
if (isset($_GET['action']) && $_GET['action'] === 'get_events') {
    header('Content-Type: application/json');

    $start = $_GET['start'] ?? date('Y-m-01');
    $end = $_GET['end'] ?? date('Y-m-t');
    $counselorId = hasRole('admin') && isset($_GET['counselor_id']) ? $_GET['counselor_id'] : $_SESSION['user_id'];

    $events = $appointmentService->getCalendarEvents($start, $end, hasRole('admin') ? ($counselorId ?: null) : $counselorId);
    echo json_encode($events);
    exit;
}

// Handle drag-drop reschedule
if (isset($_POST['action']) && $_POST['action'] === 'reschedule') {
    header('Content-Type: application/json');

    $id = $_POST['id'] ?? 0;
    $newDate = $_POST['new_date'] ?? '';

    $result = $appointmentService->rescheduleAppointment($id, $newDate);
    echo json_encode(['success' => $result]);
    exit;
}

// Get all counselors for filter (admin only)
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

$pageDetails = ['title' => 'Appointment Calendar'];
require_once '../../templates/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Appointment Calendar</h1>
        <p class="text-slate-600 mt-1">Manage your appointments visually</p>
    </div>
    <div class="flex gap-3">
        <a href="list.php" class="btn-secondary px-4 py-2 rounded-lg font-medium">📋 List View</a>
        <a href="add.php" class="btn">+ New Appointment</a>
    </div>
</div>

<!-- Calendar Controls -->
<div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            <?php if (hasRole('admin')): ?>
                <!-- Counselor Filter -->
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium text-slate-700">Counselor:</label>
                    <select id="counselorFilter" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        <option value="">All Counselors</option>
                        <?php foreach ($counselors as $counselor): ?>
                            <option value="<?php echo $counselor['id']; ?>">
                                <?php echo htmlspecialchars($counselor['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <!-- Legend -->
        <div class="flex items-center gap-4 text-xs">
            <div class="flex items-center gap-1">
                <div class="w-3 h-3 rounded" style="background-color: #3b82f6;"></div>
                <span class="text-slate-600">Scheduled</span>
            </div>
            <div class="flex items-center gap-1">
                <div class="w-3 h-3 rounded" style="background-color: #10b981;"></div>
                <span class="text-slate-600">Completed</span>
            </div>
            <div class="flex items-center gap-1">
                <div class="w-3 h-3 rounded" style="background-color: #ef4444;"></div>
                <span class="text-slate-600">Cancelled</span>
            </div>
            <div class="flex items-center gap-1">
                <div class="w-3 h-3 rounded" style="background-color: #f59e0b;"></div>
                <span class="text-slate-600">No Show</span>
            </div>
        </div>
    </div>
</div>

<!-- Calendar Container -->
<div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <div id="calendar"></div>
</div>

<!-- Appointment Detail Modal -->
<div id="appointmentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-start mb-4">
                <h2 class="text-xl font-bold text-slate-800" id="modalTitle"></h2>
                <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <div id="modalContent" class="space-y-3 text-sm">
                <!-- Content will be populated by JavaScript -->
            </div>

            <div class="flex gap-3 mt-6 pt-4 border-t border-slate-200">
                <a id="modalEditBtn" href="#" class="btn">Edit Appointment</a>
                <button onclick="closeModal()" class="btn-secondary px-4 py-2 rounded-lg font-medium">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- FullCalendar CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const calendarEl = document.getElementById('calendar');
        const counselorFilter = document.getElementById('counselorFilter');

        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            editable: true,
            droppable: false,
            eventDrop: function (info) {
                // Handle drag-drop reschedule
                const newDate = info.event.start.toISOString().slice(0, 19).replace('T', ' ');

                fetch('calendar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=reschedule&id=' + info.event.id + '&new_date=' + encodeURIComponent(newDate)
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            alert('Cannot reschedule: Time slot conflict!');
                            info.revert();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        info.revert();
                    });
            },
            eventClick: function (info) {
                showAppointmentDetails(info.event);
            },
            events: function (fetchInfo, successCallback, failureCallback) {
                const counselorId = counselorFilter ? counselorFilter.value : '';
                const url = 'calendar.php?action=get_events&start=' + fetchInfo.startStr + '&end=' + fetchInfo.endStr + '&counselor_id=' + counselorId;

                fetch(url)
                    .then(response => response.json())
                    .then(data => successCallback(data))
                    .catch(error => {
                        console.error('Error fetching events:', error);
                        failureCallback(error);
                    });
            }
        });

        calendar.render();

        // Refresh calendar when counselor filter changes
        if (counselorFilter) {
            counselorFilter.addEventListener('change', function () {
                calendar.refetchEvents();
            });
        }

        // Show appointment details in modal
        window.showAppointmentDetails = function (event) {
            const props = event.extendedProps;

            document.getElementById('modalTitle').textContent = event.title;

            const statusColors = {
                'scheduled': 'bg-blue-100 text-blue-700',
                'completed': 'bg-emerald-100 text-emerald-700',
                'cancelled': 'bg-red-100 text-red-700',
                'no_show': 'bg-orange-100 text-orange-700'
            };

            document.getElementById('modalContent').innerHTML = `
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="text-slate-500 font-medium">Date & Time:</span>
                    <p class="text-slate-800">${new Date(event.start).toLocaleString()}</p>
                </div>
                <div>
                    <span class="text-slate-500 font-medium">Status:</span>
                    <p><span class="inline-block px-2 py-0.5 rounded text-xs font-bold uppercase ${statusColors[props.status]}">${props.status}</span></p>
                </div>
                <div>
                    <span class="text-slate-500 font-medium">Client:</span>
                    <p class="text-slate-800">${props.client}</p>
                </div>
                <div>
                    <span class="text-slate-500 font-medium">Counselor:</span>
                    <p class="text-slate-800">${props.counselor}</p>
                </div>
                ${props.location ? `
                <div>
                    <span class="text-slate-500 font-medium">Location:</span>
                    <p class="text-slate-800">${props.location}</p>
                </div>
                ` : ''}
                ${props.meeting_link ? `
                <div>
                    <span class="text-slate-500 font-medium">Meeting Link:</span>
                    <p><a href="${props.meeting_link}" target="_blank" class="text-primary-600 hover:underline">Join Meeting</a></p>
                </div>
                ` : ''}
            </div>
            ${props.description ? `
            <div class="mt-4">
                <span class="text-slate-500 font-medium">Description:</span>
                <p class="text-slate-800 mt-1">${props.description}</p>
            </div>
            ` : ''}
        `;

            document.getElementById('modalEditBtn').href = 'edit.php?id=' + event.id;
            document.getElementById('appointmentModal').classList.remove('hidden');
        };

        window.closeModal = function () {
            document.getElementById('appointmentModal').classList.add('hidden');
        };
    });
</script>

<?php require_once '../../templates/footer.php'; ?>