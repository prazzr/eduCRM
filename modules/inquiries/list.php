<?php
require_once '../../config.php';
require_once '../../includes/services/LeadScoringService.php';
requireLogin();

requireAdminOrCounselor();

$leadScoringService = new LeadScoringService($pdo);

// Get filter parameter
$priorityFilter = $_GET['priority'] ?? null;
$statusFilter = $_GET['status'] ?? null;

// Build query with filters
$sql = "SELECT i.*, u.name as counselor_name FROM inquiries i LEFT JOIN users u ON i.assigned_to = u.id WHERE 1=1";
$params = [];

if ($priorityFilter) {
    $sql .= " AND i.priority = ?";
    $params[] = $priorityFilter;
}

if ($statusFilter) {
    $sql .= " AND i.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY i.score DESC, i.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inquiries = $stmt->fetchAll();

// Get priority stats for filter chips
$priorityStats = $leadScoringService->getPriorityStats();

// Get all counselors for bulk assign
$counselorsStmt = $pdo->query("
    SELECT DISTINCT u.id, u.name 
    FROM users u 
    JOIN user_roles ur ON u.id = ur.user_id 
    JOIN roles r ON ur.role_id = r.id 
    WHERE r.name IN ('admin', 'counselor')
    ORDER BY u.name
");
$counselors = $counselorsStmt->fetchAll(PDO::FETCH_ASSOC);

$pageDetails = ['title' => 'Inquiry List'];
require_once '../../includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold text-slate-800">Inquiries</h1>
    <a href="add.php" class="btn">+ Add New Inquiry</a>
</div>

<?php renderFlashMessage(); ?>

<!-- Phase 1: Priority Filter Chips -->
<div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6">
    <div class="flex items-center gap-3 flex-wrap">
        <span class="text-sm font-medium text-slate-600">Filter by Priority:</span>
        <a href="list.php"
            class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?php echo !$priorityFilter ? 'bg-slate-200 text-slate-800' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'; ?>">
            All (<?php echo array_sum($priorityStats); ?>)
        </a>
        <a href="?priority=hot"
            class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?php echo $priorityFilter === 'hot' ? 'bg-red-100 text-red-700 border border-red-200' : 'bg-red-50 text-red-600 hover:bg-red-100'; ?>">
            🔥 Hot (<?php echo $priorityStats['hot']; ?>)
        </a>
        <a href="?priority=warm"
            class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?php echo $priorityFilter === 'warm' ? 'bg-orange-100 text-orange-700 border border-orange-200' : 'bg-orange-50 text-orange-600 hover:bg-orange-100'; ?>">
            ☀️ Warm (<?php echo $priorityStats['warm']; ?>)
        </a>
        <a href="?priority=cold"
            class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?php echo $priorityFilter === 'cold' ? 'bg-blue-100 text-blue-700 border border-blue-200' : 'bg-blue-50 text-blue-600 hover:bg-blue-100'; ?>">
            ❄️ Cold (<?php echo $priorityStats['cold']; ?>)
        </a>

        <span class="text-slate-300 mx-2">|</span>

        <select name="status"
            onchange="window.location.href='?status=' + this.value + (<?php echo $priorityFilter ? "'&priority=<?php echo $priorityFilter; ?>'" : "''"; ?>)"
            class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm">
            <option value="">All Statuses</option>
            <option value="new" <?php echo $statusFilter === 'new' ? 'selected' : ''; ?>>New</option>
            <option value="contacted" <?php echo $statusFilter === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
            <option value="converted" <?php echo $statusFilter === 'converted' ? 'selected' : ''; ?>>Converted</option>
            <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>Closed</option>
        </select>
    </div>
</div>

<!-- Phase 2C: Bulk Action Toolbar -->
<div id="bulkToolbar" class="hidden bg-primary-50 border border-primary-200 p-4 rounded-xl mb-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <span class="text-primary-700 font-medium">
                <span id="selectedCount">0</span> inquiry(ies) selected
            </span>

            <select id="bulkAction" class="px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white">
                <option value="">Choose Action...</option>
                <option value="assign">Assign Counselor...</option>
                <option value="priority">Change Priority...</option>
                <option value="status">Change Status...</option>
                <option value="email">Send Email...</option>
                <?php if (hasRole('admin')): ?>
                    <option value="delete">Delete Selected</option>
                <?php endif; ?>
            </select>

            <select id="assignSelect" class="hidden px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white">
                <option value="">Select Counselor...</option>
                <?php foreach ($counselors as $counselor): ?>
                    <option value="<?php echo $counselor['id']; ?>"><?php echo htmlspecialchars($counselor['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select id="prioritySelect" class="hidden px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white">
                <option value="">Select Priority...</option>
                <option value="hot">🔥 Hot</option>
                <option value="warm">☀️ Warm</option>
                <option value="cold">❄️ Cold</option>
            </select>

            <select id="statusSelect" class="hidden px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white">
                <option value="">Select Status...</option>
                <option value="new">New</option>
                <option value="contacted">Contacted</option>
                <option value="converted">Converted</option>
                <option value="closed">Closed</option>
            </select>

            <button id="applyBulkAction" class="btn px-4 py-2 text-sm">Apply</button>
        </div>

        <button id="clearSelection" class="text-sm text-primary-600 hover:text-primary-800">Clear Selection</button>
    </div>
</div>

<!-- Inquiries Table -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-sm">
                    <th class="p-3 w-12">
                        <input type="checkbox" id="selectAll"
                            class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                    </th>
                    <th class="p-3 font-semibold">Name</th>
                    <th class="p-3 font-semibold">Contact</th>
                    <th class="p-3 font-semibold">Interest</th>
                    <th class="p-3 font-semibold">Priority</th>
                    <th class="p-3 font-semibold">Score</th>
                    <th class="p-3 font-semibold">Assigned To</th>
                    <th class="p-3 font-semibold">Status</th>
                    <th class="p-3 font-semibold text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (count($inquiries) > 0):
                    foreach ($inquiries as $inq): ?>
                        <?php
                        $priorityColors = [
                            'hot' => 'bg-red-100 text-red-700 border-red-200',
                            'warm' => 'bg-orange-100 text-orange-700 border-orange-200',
                            'cold' => 'bg-blue-100 text-blue-700 border-blue-200'
                        ];
                        $priorityIcons = ['hot' => '🔥', 'warm' => '☀️', 'cold' => '❄️'];

                        $statusColors = [
                            'new' => 'bg-blue-100 text-blue-700',
                            'contacted' => 'bg-yellow-100 text-yellow-700',
                            'converted' => 'bg-emerald-100 text-emerald-700',
                            'closed' => 'bg-slate-100 text-slate-700'
                        ];
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="p-3">
                                <input type="checkbox"
                                    class="inquiry-checkbox rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                                    value="<?php echo $inq['id']; ?>">
                            </td>
                            <td class="p-3">
                                <strong class="text-slate-800"><?php echo htmlspecialchars($inq['name']); ?></strong>
                            </td>
                            <td class="p-3 text-sm text-slate-600">
                                <div><?php echo htmlspecialchars($inq['email']); ?></div>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($inq['phone']); ?></div>
                            </td>
                            <td class="p-3 text-sm text-slate-600">
                                <div><?php echo htmlspecialchars($inq['intended_country']); ?></div>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($inq['intended_course']); ?>
                                </div>
                            </td>
                            <td class="p-3">
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded border text-xs font-bold uppercase <?php echo $priorityColors[$inq['priority']]; ?>">
                                    <?php echo $priorityIcons[$inq['priority']]; ?>
                                    <?php echo $inq['priority']; ?>
                                </span>
                            </td>
                            <td class="p-3">
                                <span class="text-lg font-bold text-slate-800"><?php echo $inq['score']; ?></span>
                                <span class="text-xs text-slate-500">/100</span>
                            </td>
                            <td class="p-3 text-sm text-slate-600">
                                <?php echo htmlspecialchars($inq['counselor_name'] ?? 'Unassigned'); ?>
                            </td>
                            <td class="p-3">
                                <span
                                    class="inline-block px-2 py-0.5 rounded text-xs font-bold uppercase <?php echo $statusColors[$inq['status']]; ?>">
                                    <?php echo $inq['status']; ?>
                                </span>
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex gap-2 justify-end">
                                    <a href="edit.php?id=<?php echo $inq['id']; ?>"
                                        class="btn-secondary px-3 py-1.5 text-xs rounded">Edit</a>
                                    <a href="convert.php?id=<?php echo $inq['id']; ?>"
                                        class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 text-xs rounded font-medium">Convert</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="9" class="p-6 text-center text-slate-500">
                            No inquiries found. <a href="add.php" class="text-primary-600 hover:underline">Add your first
                                inquiry</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Phase 2C: Bulk Email Composer Modal -->
<div id="emailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4">
        <div class="p-6">
            <div class="flex justify-between items-start mb-4">
                <h2 class="text-xl font-bold text-slate-800">Compose Bulk Email</h2>
                <button onclick="closeEmailModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-700">
                    <strong>Recipients:</strong> <span id="recipientCount">0</span> selected inquiries with valid email
                    addresses
                </p>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Subject *</label>
                    <input type="text" id="emailSubject"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                        placeholder="Email subject...">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Message *</label>
                    <textarea id="emailBody" rows="8"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                        placeholder="Email message..."></textarea>
                </div>
            </div>

            <div class="flex gap-3 mt-6 pt-4 border-t border-slate-200">
                <button onclick="sendBulkEmail()" class="btn">Send Email</button>
                <button onclick="closeEmailModal()"
                    class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Phase 2C: Bulk Action JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('selectAll');
        const inquiryCheckboxes = document.querySelectorAll('.inquiry-checkbox');
        const bulkToolbar = document.getElementById('bulkToolbar');
        const selectedCount = document.getElementById('selectedCount');
        const bulkAction = document.getElementById('bulkAction');
        const assignSelect = document.getElementById('assignSelect');
        const prioritySelect = document.getElementById('prioritySelect');
        const statusSelect = document.getElementById('statusSelect');
        const applyBulkAction = document.getElementById('applyBulkAction');
        const clearSelection = document.getElementById('clearSelection');
        const emailModal = document.getElementById('emailModal');

        function updateBulkToolbar() {
            const checked = document.querySelectorAll('.inquiry-checkbox:checked');
            selectedCount.textContent = checked.length;
            bulkToolbar.classList.toggle('hidden', checked.length === 0);
        }

        selectAll.addEventListener('change', function () {
            inquiryCheckboxes.forEach(cb => cb.checked = this.checked);
            updateBulkToolbar();
        });

        inquiryCheckboxes.forEach(cb => {
            cb.addEventListener('change', function () {
                selectAll.checked = document.querySelectorAll('.inquiry-checkbox:checked').length === inquiryCheckboxes.length;
                updateBulkToolbar();
            });
        });

        bulkAction.addEventListener('change', function () {
            assignSelect.classList.add('hidden');
            prioritySelect.classList.add('hidden');
            statusSelect.classList.add('hidden');

            if (this.value === 'assign') assignSelect.classList.remove('hidden');
            if (this.value === 'priority') prioritySelect.classList.remove('hidden');
            if (this.value === 'status') statusSelect.classList.remove('hidden');
        });

        clearSelection.addEventListener('click', function () {
            inquiryCheckboxes.forEach(cb => cb.checked = false);
            selectAll.checked = false;
            updateBulkToolbar();
        });

        applyBulkAction.addEventListener('click', function () {
            const action = bulkAction.value;
            if (!action) {
                alert('Please select an action');
                return;
            }

            const selectedIds = Array.from(document.querySelectorAll('.inquiry-checkbox:checked')).map(cb => cb.value);
            if (selectedIds.length === 0) {
                alert('Please select at least one inquiry');
                return;
            }

            if (action === 'email') {
                document.getElementById('recipientCount').textContent = selectedIds.length;
                emailModal.classList.remove('hidden');
                return;
            }

            let formData = new FormData();
            formData.append('action', action);
            selectedIds.forEach(id => formData.append('inquiry_ids[]', id));

            if (action === 'assign') {
                const assign = assignSelect.value;
                if (!assign) {
                    alert('Please select a counselor');
                    return;
                }
                formData.append('assign_to', assign);
            } else if (action === 'priority') {
                const priority = prioritySelect.value;
                if (!priority) {
                    alert('Please select a priority');
                    return;
                }
                formData.append('priority', priority);
            } else if (action === 'status') {
                const status = statusSelect.value;
                if (!status) {
                    alert('Please select a status');
                    return;
                }
                formData.append('status', status);
            }

            if (!confirm(`Are you sure you want to ${action} ${selectedIds.length} inquiry(ies)?`)) return;

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

        window.closeEmailModal = function () {
            emailModal.classList.add('hidden');
            document.getElementById('emailSubject').value = '';
            document.getElementById('emailBody').value = '';
        };

        window.sendBulkEmail = function () {
            const subject = document.getElementById('emailSubject').value.trim();
            const body = document.getElementById('emailBody').value.trim();

            if (!subject || !body) {
                alert('Please fill in both subject and message');
                return;
            }

            const selectedIds = Array.from(document.querySelectorAll('.inquiry-checkbox:checked')).map(cb => cb.value);

            let formData = new FormData();
            formData.append('action', 'email');
            selectedIds.forEach(id => formData.append('inquiry_ids[]', id));
            formData.append('email_subject', subject);
            formData.append('email_body', body);

            if (!confirm(`Send email to ${selectedIds.length} recipient(s)?`)) return;

            fetch('bulk_action.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        closeEmailModal();
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('An error occurred: ' + error);
                });
        };
    });
</script>

<?php require_once '../../includes/footer.php'; ?>