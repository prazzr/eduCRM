<?php
/**
 * Student List
 * Displays all students with search and filtering capabilities
 */
require_once '../../app/bootstrap.php';
requireLogin();

// Staff access only (students cannot view student list)
requireStaffMember();

$is_teacher_only = hasRole('teacher') && !hasRole('admin') && !hasRole('counselor') && !hasRole('branch_manager');


$branchService = new \EduCRM\Services\BranchService($pdo);
$branchFilter = $branchService->getBranchFilter($_SESSION['user_id'], 'u');

// Pagination parameters
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

// Build Query with FK columns
$sql = "
    SELECT DISTINCT u.id, u.name, u.email, u.phone, 
           c.name as country_name, 
           el.name as education_level_name,
           u.country as legacy_country, 
           u.education_level as legacy_education,
           u.created_at
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    LEFT JOIN countries c ON u.country_id = c.id
    LEFT JOIN education_levels el ON u.education_level_id = el.id
";

// If Teacher, join enrollments to filter
if ($is_teacher_only) {
    $sql .= "
        JOIN enrollments e ON u.id = e.student_id
        JOIN classes cls ON e.class_id = cls.id
        WHERE r.name = 'student' AND cls.teacher_id = ? $branchFilter
    ";
    $params = [$_SESSION['user_id']];
} else {
    $sql .= " WHERE r.name = 'student' $branchFilter ";
    $params = [];
}

$sql .= " ORDER BY u.id DESC";

// Use PaginationService for server-side pagination
$paginationService = new \EduCRM\Services\PaginationService($pdo, $perPage);
$paginationService->setPage($currentPage);
$students = $paginationService->paginate($sql, $params);
$pagination = $paginationService->getMetadata();

$pageDetails = ['title' => 'Student Management'];
require_once '../../templates/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Student Records</h1>
    <a href="add.php" class="btn btn-primary">
        <?php echo \EduCRM\Services\NavigationService::getIcon('plus', 16); ?> Add New Student
    </a>
</div>

<!-- Quick Search with Alpine.js -->
<div class="bg-white px-4 py-3 rounded-xl border border-slate-200 shadow-sm mb-4">
    <div x-data='searchFilter({
        data: <?php echo json_encode(array_map(function ($s) {
            return [
                'id' => $s['id'],
                'name' => $s['name'],
                'email' => $s['email'],
                'phone' => $s['phone'] ?? '',
                'country' => $s['country_name'] ?? $s['legacy_country'] ?? '',
                'education' => $s['education_level_name'] ?? $s['legacy_education'] ?? ''
            ];
        }, $students)); ?>,
        searchFields: ["name", "email", "phone"],
        minLength: 2,
        maxResults: 8
    })' class="relative">
        <div class="flex items-center gap-3">
            <span class="text-slate-400">🔍</span>
            <input type="text" x-model="query" @input="search()" @focus="if(query.length >= 2) showResults = true"
                @keydown="handleKeydown($event)" @keydown.escape="showResults = false"
                class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Quick search by name, email, or phone..." autocomplete="off">

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
                <a :href="'profile.php?id=' + item.id" :data-index="index" @mouseenter="setSelectedIndex(index)"
                    class="flex items-center gap-3 px-4 py-3 border-b border-slate-100 transition-colors"
                    :class="{ 'bg-primary-50 border-l-4 border-l-teal-600': isSelected(index), 'hover:bg-slate-50': !isSelected(index) }">
                    <div class="w-9 h-9 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-full flex items-center justify-center text-white font-bold text-sm flex-shrink-0"
                        x-text="item.name.charAt(0).toUpperCase()"></div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-slate-800" x-text="item.name"></div>
                        <div class="text-xs text-slate-500 truncate">
                            <span x-text="item.email"></span> • <span x-text="item.phone || 'No phone'"></span>
                        </div>
                    </div>
                    <div class="text-right text-xs text-slate-400">
                        <div x-text="item.country || ''"></div>
                        <div x-text="item.education || ''"></div>
                    </div>
                </a>
            </template>

            <div x-show="results.length === 0 && query.length >= 2 && !loading"
                class="px-4 py-3 text-center text-slate-500 text-sm">
                No students found
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(id) {
        Modal.show({
            type: 'error',
            title: 'Delete Student?',
            message: 'Are you sure you want to delete this student? This action cannot be undone.',
            confirmText: 'Yes, Delete It',
            onConfirm: function () {
                window.location.href = 'delete.php?id=' + id;
            }
        });
    }
</script>

<?php
// Get available classes for enrollment modal
$classesStmt = $pdo->query("
    SELECT c.id, c.name, COALESCE(co.name, 'No Course') as course_name
    FROM classes c
    LEFT JOIN courses co ON c.course_id = co.id
    WHERE c.status = 'active'
    ORDER BY c.name
");
$availableClasses = $classesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Bulk Actions Alpine.js Component -->
<script>
    function studentBulkActions() {
        return {
            selected: [],
            action: '',
            classId: '',
            status: '',
            loading: false,
            allIds: <?php echo json_encode(array_column($students, 'id')); ?>,

            get count() { return this.selected.length; },
            get hasSelection() { return this.selected.length > 0; },
            get allSelected() { return this.selected.length === this.allIds.length && this.allIds.length > 0; },

            toggleAll() {
                this.selected = this.allSelected ? [] : [...this.allIds];
            },
            toggle(id) {
                const idx = this.selected.indexOf(id);
                if (idx === -1) this.selected.push(id);
                else this.selected.splice(idx, 1);
            },
            isSelected(id) { return this.selected.includes(id); },
            clear() { this.selected = []; this.action = ''; },

            showEmailModal() {
                document.getElementById('emailRecipientCount').textContent = this.selected.length;
                document.getElementById('studentEmailModal').classList.remove('hidden');
            },

            showSmsModal() {
                document.getElementById('smsRecipientCount').textContent = this.selected.length;
                document.getElementById('studentSmsModal').classList.remove('hidden');
            },

            async apply() {
                if (!this.action) { Modal.show({ type: 'warning', title: 'Select Action', message: 'Please select an action' }); return; }
                if (this.selected.length === 0) { Modal.show({ type: 'warning', title: 'No Selection', message: 'Please select at least one student' }); return; }

                if (this.action === 'email') { this.showEmailModal(); return; }
                if (this.action === 'sms') { this.showSmsModal(); return; }

                if (this.action === 'enroll' && !this.classId) { Modal.show({ type: 'warning', title: 'Select Class', message: 'Please select a class' }); return; }
                if (this.action === 'status' && !this.status) { Modal.show({ type: 'warning', title: 'Select Status', message: 'Please select a status' }); return; }

                if (this.action === 'export') {
                    this.exportStudents();
                    return;
                }

                const actionNames = { enroll: 'enroll', status: 'update status for', delete: 'delete' };
                const self = this;

                Modal.show({
                    type: this.action === 'delete' ? 'error' : 'warning',
                    title: 'Confirm Bulk Action',
                    message: `Are you sure you want to ${actionNames[this.action] || this.action} ${this.selected.length} student(s)?`,
                    confirmText: 'Yes, Proceed',
                    onConfirm: async function () {
                        self.loading = true;
                        const formData = new FormData();
                        formData.append('action', self.action);
                        self.selected.forEach(id => formData.append('student_ids[]', id));
                        if (self.action === 'enroll') formData.append('class_id', self.classId);
                        if (self.action === 'status') formData.append('status', self.status);

                        try {
                            const response = await fetch('bulk_actions.php', { method: 'POST', body: formData });
                            const data = await response.json();
                            if (data.success) {
                                Modal.show({ type: 'success', title: 'Success', message: data.message });
                                setTimeout(() => window.location.reload(), 1500);
                            } else {
                                Modal.show({ type: 'error', title: 'Error', message: data.message });
                            }
                        } catch (e) {
                            Modal.show({ type: 'error', title: 'Error', message: 'An error occurred' });
                        }
                        self.loading = false;
                    }
                });
            },

            async exportStudents() {
                this.loading = true;
                const formData = new FormData();
                formData.append('action', 'export');
                this.selected.forEach(id => formData.append('student_ids[]', id));

                try {
                    const response = await fetch('bulk_actions.php', { method: 'POST', body: formData });
                    const data = await response.json();
                    if (data.success) {
                        Modal.show({ type: 'success', title: 'Export Ready', message: 'Your export is ready for download.' });
                        window.open(data.download_url, '_blank');
                    } else {
                        Modal.show({ type: 'error', title: 'Error', message: data.message });
                    }
                } catch (e) {
                    Modal.show({ type: 'error', title: 'Error', message: 'Export failed' });
                }
                this.loading = false;
            }
        };
    }
</script>

<?php renderFlashMessage(); ?>

<div x-data="studentBulkActions()" x-ref="bulkContainer">
    <!-- Bulk Action Toolbar (shows when items selected) -->
    <div x-show="hasSelection" x-cloak x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 transform -translate-y-2"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        class="bg-primary-50 border border-primary-200 p-4 rounded-xl mb-4">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <span class="text-primary-700 font-medium">
                    <span x-text="count"></span> student(s) selected
                </span>

                <select x-model="action" class="px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white">
                    <option value="">Choose Action...</option>
                    <option value="email">Send Email...</option>
                    <option value="sms">Send SMS...</option>
                    <option value="enroll">Add to Class...</option>
                    <option value="export">Export to CSV</option>
                    <?php if (hasRole('admin') || hasRole('branch_manager')): ?>
                        <option value="status">Change Status...</option>
                        <option value="delete">Delete Selected</option>
                    <?php endif; ?>
                </select>

                <select x-show="action === 'enroll'" x-model="classId" x-transition
                    class="px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white">
                    <option value="">Select Class...</option>
                    <?php foreach ($availableClasses as $cls): ?>
                        <option value="<?php echo $cls['id']; ?>">
                            <?php echo htmlspecialchars($cls['name'] . ' (' . $cls['course_name'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select x-show="action === 'status'" x-model="status" x-transition
                    class="px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white">
                    <option value="">Select Status...</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="alumni">Alumni</option>
                    <option value="suspended">Suspended</option>
                </select>

                <button @click="apply()" :disabled="loading" class="btn px-4 py-2 text-sm">
                    <span x-show="!loading">Apply</span>
                    <span x-show="loading">Processing...</span>
                </button>
            </div>

            <button @click="clear()" class="text-sm text-primary-600 hover:text-primary-800">Clear Selection</button>
        </div>
    </div>

    <!-- Students Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 w-12">
                            <input type="checkbox" @click="toggleAll()" :checked="allSelected"
                                class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                            Student
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                            Contact Info
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                            Details
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                            Joined
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-200">
                    <?php foreach ($students as $s): ?>
                        <tr class="hover:bg-slate-50 transition-colors"
                            :class="{ 'bg-primary-50': isSelected(<?php echo $s['id']; ?>) }">
                            <td class="px-4 py-4">
                                <input type="checkbox" @click="toggle(<?php echo $s['id']; ?>)"
                                    :checked="isSelected(<?php echo $s['id']; ?>)"
                                    class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div
                                        class="flex-shrink-0 h-10 w-10 bg-primary-100 rounded-full flex items-center justify-center">
                                        <span class="text-primary-600 font-semibold text-sm">
                                            <?php echo strtoupper(substr($s['name'], 0, 2)); ?>
                                        </span>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-slate-900">
                                            <?php echo htmlspecialchars($s['name']); ?>
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            ID: #<?php echo $s['id']; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-slate-900"><?php echo htmlspecialchars($s['email']); ?></div>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($s['phone']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-slate-900">
                                    <?php echo htmlspecialchars($s['country_name'] ?? $s['legacy_country'] ?? ''); ?>
                                </div>
                                <div class="text-xs text-slate-500">
                                    <?php echo htmlspecialchars($s['education_level_name'] ?? $s['legacy_education'] ?? ''); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                <?php echo date('Y-m-d', strtotime($s['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex gap-2 justify-end">
                                    <a href="profile.php?id=<?php echo $s['id']; ?>" class="action-btn blue"
                                        title="View Profile">
                                        <?php echo \EduCRM\Services\NavigationService::getIcon('eye', 16); ?>
                                    </a>
                                    <a href="edit.php?id=<?php echo $s['id']; ?>" class="action-btn default" title="Edit">
                                        <?php echo \EduCRM\Services\NavigationService::getIcon('edit', 16); ?>
                                    </a>
                                    <a href="#" onclick="confirmDelete(<?php echo $s['id']; ?>)" class="action-btn red"
                                        title="Delete">
                                        <?php echo \EduCRM\Services\NavigationService::getIcon('trash', 16); ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (empty($students)): ?>
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900">No students</h3>
                <p class="mt-1 text-sm text-slate-500">Get started by adding a new student.</p>
                <div class="mt-6">
                    <a href="add.php"
                        class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                        + Add Student
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div><!-- End of Alpine.js bulk container -->

<!-- Email Modal -->
<div id="studentEmailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4">
        <div class="p-6">
            <div class="flex justify-between items-start mb-4">
                <h2 class="text-xl font-bold text-slate-800">Compose Bulk Email</h2>
                <button onclick="closeStudentEmailModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-700">
                    <strong>Recipients:</strong> <span id="emailRecipientCount">0</span> selected students
                </p>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Subject *</label>
                    <input type="text" id="studentEmailSubject"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                        placeholder="Email subject...">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Message *</label>
                    <textarea id="studentEmailBody" rows="6"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                        placeholder="Email message... Use {name} to personalize."></textarea>
                </div>
            </div>

            <div class="flex gap-3 mt-6 pt-4 border-t border-slate-200">
                <button onclick="sendStudentBulkEmail()" class="btn">Send Email</button>
                <button onclick="closeStudentEmailModal()"
                    class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- SMS Modal -->
<div id="studentSmsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4">
        <div class="p-6">
            <div class="flex justify-between items-start mb-4">
                <h2 class="text-xl font-bold text-slate-800">Compose Bulk SMS</h2>
                <button onclick="closeStudentSmsModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg">
                <p class="text-sm text-emerald-700">
                    <strong>Recipients:</strong> <span id="smsRecipientCount">0</span> selected students with phone
                    numbers
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Message * <span class="text-slate-400">(160
                        chars max)</span></label>
                <textarea id="studentSmsMessage" rows="4" maxlength="160"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                    placeholder="SMS message... Use {name} to personalize."></textarea>
            </div>

            <div class="flex gap-3 mt-6 pt-4 border-t border-slate-200">
                <button onclick="sendStudentBulkSms()" class="btn">Send SMS</button>
                <button onclick="closeStudentSmsModal()"
                    class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Functions -->
<script>
    function closeStudentEmailModal() {
        document.getElementById('studentEmailModal').classList.add('hidden');
        document.getElementById('studentEmailSubject').value = '';
        document.getElementById('studentEmailBody').value = '';
    }

    function closeStudentSmsModal() {
        document.getElementById('studentSmsModal').classList.add('hidden');
        document.getElementById('studentSmsMessage').value = '';
    }

    function getSelectedStudentIds() {
        const bulkContainer = document.querySelector('[x-ref="bulkContainer"]');
        if (!bulkContainer || !bulkContainer.__x) return [];
        return bulkContainer.__x.$data.selected;
    }

    function sendStudentBulkEmail() {
        const subject = document.getElementById('studentEmailSubject').value.trim();
        const body = document.getElementById('studentEmailBody').value.trim();
        const selectedIds = getSelectedStudentIds();

        if (!subject || !body) {
            Modal.show({ type: 'warning', title: 'Missing Fields', message: 'Please fill in both subject and message' });
            return;
        }

        Modal.show({
            type: 'info',
            title: 'Confirm Send Email',
            message: `Send email to ${selectedIds.length} student(s)?`,
            confirmText: 'Send Email',
            onConfirm: function () {
                const formData = new FormData();
                formData.append('action', 'email');
                selectedIds.forEach(id => formData.append('student_ids[]', id));
                formData.append('subject', subject);
                formData.append('body', body);

                fetch('bulk_actions.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Modal.show({ type: 'success', title: 'Success', message: data.message });
                            closeStudentEmailModal();
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            Modal.show({ type: 'error', title: 'Error', message: data.message });
                        }
                    })
                    .catch(error => {
                        Modal.show({ type: 'error', title: 'Error', message: 'An error occurred' });
                    });
            }
        });
    }

    function sendStudentBulkSms() {
        const message = document.getElementById('studentSmsMessage').value.trim();
        const selectedIds = getSelectedStudentIds();

        if (!message) {
            Modal.show({ type: 'warning', title: 'Missing Message', message: 'Please enter a message' });
            return;
        }

        Modal.show({
            type: 'info',
            title: 'Confirm Send SMS',
            message: `Send SMS to ${selectedIds.length} student(s)?`,
            confirmText: 'Send SMS',
            onConfirm: function () {
                const formData = new FormData();
                formData.append('action', 'sms');
                selectedIds.forEach(id => formData.append('student_ids[]', id));
                formData.append('message', message);

                fetch('bulk_actions.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Modal.show({ type: 'success', title: 'Success', message: data.message });
                            closeStudentSmsModal();
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            Modal.show({ type: 'error', title: 'Error', message: data.message });
                        }
                    })
                    .catch(error => {
                        Modal.show({ type: 'error', title: 'Error', message: 'An error occurred' });
                    });
            }
        });
    }
</script>

<?php // Pagination Controls ?>
<?php include __DIR__ . '/../../templates/partials/pagination.php'; ?>

<?php require_once '../../templates/footer.php'; ?>