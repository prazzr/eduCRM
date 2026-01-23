<?php
/**
 * Task List
 * Displays all tasks with filtering by status, priority, and assignee
 */
require_once '../../app/bootstrap.php';



requireLogin();

$pageDetails = ['title' => 'Tasks'];
require_once '../../templates/header.php';

$taskService = new \EduCRM\Services\TaskService($pdo);

// Get filter parameters
$statusFilter = $_GET['status'] ?? null;
$priorityFilter = $_GET['priority'] ?? null;
$entityFilter = $_GET['entity_type'] ?? null;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$filters = [];
if ($statusFilter)
    $filters['status'] = $statusFilter;
if ($priorityFilter)
    $filters['priority'] = $priorityFilter;
if ($entityFilter)
    $filters['entity_type'] = $entityFilter;

// Get tasks based on role (with pagination)
if (hasRole('admin')) {
    $assignedToFilter = $_GET['assigned_to'] ?? null;
    if ($assignedToFilter)
        $filters['assigned_to'] = $assignedToFilter;
    $result = $taskService->getPaginatedTasks($filters, $currentPage, $perPage);

    // Get all users for filter dropdown (using helper)
    $users = users()->getAllUsers();
} else {
    $result = $taskService->getPaginatedUserTasks($_SESSION['user_id'], $filters, $currentPage, $perPage);
}

// Extract tasks and pagination data
$tasks = $result['data'];
$pagination = $result['pagination'];
?>

<div class="page-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h1 class="page-title">My Tasks</h1>
    <div class="flex gap-3">
        <!-- View Toggle -->
        <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 p-1 bg-slate-100 dark:bg-slate-800">
            <span class="px-3 py-1.5 text-sm rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-200 shadow-sm flex items-center gap-1">
                <?php echo \EduCRM\Services\NavigationService::getIcon('list', 16); ?> List
            </span>
            <a href="kanban.php" class="px-3 py-1.5 text-sm rounded-md text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 transition-colors flex items-center gap-1">
                <?php echo \EduCRM\Services\NavigationService::getIcon('columns', 16); ?> Board
            </a>
        </div>
        <a href="add.php" class="btn btn-primary">
            <?php echo \EduCRM\Services\NavigationService::getIcon('plus', 16); ?> New Task
        </a>
    </div>
</div>

<!-- Quick Search with Alpine.js -->
<div class="bg-white px-4 py-3 rounded-xl border border-slate-200 shadow-sm mb-4">
    <div x-data='searchFilter({
        data: <?php echo json_encode(array_map(function ($t) {
            return [
                'id' => $t['id'],
                'title' => $t['title'],
                'priority' => $t['priority'],
                'status' => $t['status'],
                'due' => $t['due_date'] ?? ''
            ];
        }, $tasks)); ?>,
        searchFields: ["title", "priority", "status"],
        minLength: 1,
        maxResults: 8
    })' class="relative">
        <div class="flex items-center gap-3">
            <span class="text-slate-400">🔍</span>
            <input type="text" x-model="query" @input="search()" @focus="if(query.length >= 1) showResults = true"
                @keydown="handleKeydown($event)" @keydown.escape="showResults = false"
                class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500"
                placeholder="Quick search by task title..." autocomplete="off">

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
                             'bg-red-500': item.priority === 'urgent',
                             'bg-orange-500': item.priority === 'high',
                             'bg-yellow-500': item.priority === 'medium',
                             'bg-blue-500': item.priority === 'low'
                         }"></div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-slate-800" x-text="item.title"></div>
                        <div class="text-xs text-slate-500">
                            <span class="uppercase" x-text="item.priority"></span> •
                            <span x-text="item.status.replace('_', ' ')"></span>
                        </div>
                    </div>
                    <div x-show="item.due" class="text-xs text-slate-400" x-text="item.due"></div>
                </a>
            </template>

            <div x-show="results.length === 0 && query.length >= 1 && !loading"
                class="px-4 py-3 text-center text-slate-500 text-sm">
                No tasks found
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6">
    <form method="GET" class="flex flex-wrap gap-3">
        <!-- Status Filter -->
        <select name="status" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">All Statuses</option>
            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress
            </option>
            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
        </select>

        <!-- Priority Filter -->
        <select name="priority" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">All Priorities</option>
            <option value="urgent" <?php echo $priorityFilter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
            <option value="high" <?php echo $priorityFilter === 'high' ? 'selected' : ''; ?>>High</option>
            <option value="medium" <?php echo $priorityFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
            <option value="low" <?php echo $priorityFilter === 'low' ? 'selected' : ''; ?>>Low</option>
        </select>

        <!-- Entity Type Filter -->
        <select name="entity_type" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">All Types</option>
            <option value="inquiry" <?php echo $entityFilter === 'inquiry' ? 'selected' : ''; ?>>Inquiry</option>
            <option value="student" <?php echo $entityFilter === 'student' ? 'selected' : ''; ?>>Student</option>
            <option value="application" <?php echo $entityFilter === 'application' ? 'selected' : ''; ?>>Application
            </option>
            <option value="class" <?php echo $entityFilter === 'class' ? 'selected' : ''; ?>>Class</option>
            <option value="general" <?php echo $entityFilter === 'general' ? 'selected' : ''; ?>>General</option>
        </select>

        <?php if (hasRole('admin')): ?>
            <!-- Assigned To Filter (Admin only) -->
            <select name="assigned_to" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                <option value="">All Users</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo ($assignedToFilter == $user['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <button type="submit" class="btn-secondary px-4 py-2 rounded-lg text-sm">Apply Filters</button>
        <a href="list.php" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Clear</a>
    </form>
</div>

<!-- Phase 2C: Bulk Action Toolbar (Hidden by default) -->
<div id="bulkToolbar" class="hidden bg-primary-50 border border-primary-200 p-4 rounded-xl mb-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <span class="text-primary-700 font-medium">
                <span id="selectedCount">0</span> task(s) selected
            </span>

            <select id="bulkAction" class="px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white">
                <option value="">Choose Action...</option>
                <?php if (hasRole('admin')): ?>
                    <option value="assign">Assign To...</option>
                <?php endif; ?>
                <option value="priority">Change Priority...</option>
                <option value="status">Change Status...</option>
                <?php if (hasRole('admin')): ?>
                    <option value="delete">Delete Selected</option>
                <?php endif; ?>
            </select>

            <!-- Sub-options (shown based on action) -->
            <select id="assignToSelect" class="hidden px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white">
                <option value="">Select User...</option>
                <?php if (hasRole('admin')):
                    foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                    <?php endforeach; endif; ?>
            </select>

            <select id="prioritySelect" class="hidden px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white">
                <option value="">Select Priority...</option>
                <option value="urgent">Urgent</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
            </select>

            <select id="statusSelect" class="hidden px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white">
                <option value="">Select Status...</option>
                <option value="pending">Pending</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
            </select>

            <button id="applyBulkAction" class="btn px-4 py-2 text-sm">Apply</button>
        </div>

        <button id="clearSelection" class="text-sm text-primary-600 hover:text-primary-800">Clear Selection</button>
    </div>
</div>

<!-- Tasks Table -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-sm">
                    <th class="p-3 w-12">
                        <input type="checkbox" id="selectAll"
                            class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                    </th>
                    <th class="p-3 font-semibold">Title</th>
                    <th class="p-3 font-semibold">Priority</th>
                    <th class="p-3 font-semibold">Status</th>
                    <th class="p-3 font-semibold">Related To</th>
                    <th class="p-3 font-semibold">Due Date</th>
                    <?php if (hasRole('admin')): ?>
                        <th class="p-3 font-semibold">Assigned To</th>
                    <?php endif; ?>
                    <th class="p-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (count($tasks) > 0): ?>
                    <?php foreach ($tasks as $task): ?>
                        <?php
                        // Priority colors
                        $priorityColors = [
                            'urgent' => 'bg-red-100 text-red-700',
                            'high' => 'bg-orange-100 text-orange-700',
                            'medium' => 'bg-yellow-100 text-yellow-700',
                            'low' => 'bg-blue-100 text-blue-700'
                        ];

                        // Status colors
                        $statusColors = [
                            'pending' => 'bg-slate-100 text-slate-700',
                            'in_progress' => 'bg-sky-100 text-sky-700',
                            'completed' => 'bg-emerald-100 text-emerald-700',
                            'cancelled' => 'bg-red-100 text-red-700'
                        ];

                        // Check if overdue
                        $isOverdue = $task['due_date'] && strtotime($task['due_date']) < time() && $task['status'] !== 'completed';
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors <?php echo $isOverdue ? 'bg-red-50' : ''; ?>">
                            <td class="p-3">
                                <input type="checkbox"
                                    class="task-checkbox rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                                    value="<?php echo $task['id']; ?>">
                            </td>
                            <td class="p-3">
                                <div>
                                    <strong class="text-slate-800"><?php echo htmlspecialchars($task['title']); ?></strong>
                                    <?php if ($task['description']): ?>
                                        <p class="text-xs text-slate-500 mt-1">
                                            <?php echo htmlspecialchars(substr($task['description'], 0, 60)) . (strlen($task['description']) > 60 ? '...' : ''); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-3">
                                <span
                                    class="inline-block px-2 py-0.5 rounded text-xs font-bold uppercase <?php echo $priorityColors[$task['priority']]; ?>">
                                    <?php echo $task['priority']; ?>
                                </span>
                            </td>
                            <td class="p-3">
                                <span
                                    class="inline-block px-2 py-0.5 rounded text-xs font-bold uppercase <?php echo $statusColors[$task['status']]; ?>">
                                    <?php echo str_replace('_', ' ', $task['status']); ?>
                                </span>
                            </td>
                            <td class="p-3 text-sm text-slate-600">
                                <?php if ($task['related_entity_type'] !== 'general'): ?>
                                    <span class="capitalize"><?php echo $task['related_entity_type']; ?></span>
                                    #<?php echo $task['related_entity_id']; ?>
                                <?php else: ?>
                                    <span class="text-slate-400">General</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-sm">
                                <?php if ($task['due_date']): ?>
                                    <span class="<?php echo $isOverdue ? 'text-red-600 font-bold' : 'text-slate-600'; ?>">
                                        <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                    </span>
                                    <?php if ($isOverdue): ?>
                                        <span class="text-xs text-red-500 block">Overdue!</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-slate-400">No due date</span>
                                <?php endif; ?>
                            </td>
                            <?php if (hasRole('admin')): ?>
                                <td class="p-3 text-sm text-slate-600">
                                    <?php echo htmlspecialchars($task['assigned_to_name']); ?>
                                </td>
                            <?php endif; ?>
                            <td class="p-3 text-right">
                                <div class="flex gap-2 justify-end">
                                    <a href="edit.php?id=<?php echo $task['id']; ?>" class="action-btn default" title="Edit">
                                        <?php echo \EduCRM\Services\NavigationService::getIcon('edit', 16); ?>
                                    </a>
                                    <?php if ($task['status'] !== 'completed'): ?>
                                        <form method="POST" action="edit.php" class="inline">
                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                            <input type="hidden" name="action" value="complete">
                                            <button type="submit" class="action-btn green" title="Mark Complete">
                                                <?php echo \EduCRM\Services\NavigationService::getIcon('check-square', 16); ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo hasRole('admin') ? '8' : '7'; ?>" class="p-6 text-center text-slate-500">
                            No tasks found. <a href="add.php" class="text-primary-600 hover:underline">Create your first
                                task</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php // Pagination Controls ?>
<?php include __DIR__ . '/../../templates/partials/pagination.php'; ?>

<!-- Bulk Action JavaScript with Modal System -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('selectAll');
        const taskCheckboxes = document.querySelectorAll('.task-checkbox');
        const bulkToolbar = document.getElementById('bulkToolbar');
        const selectedCount = document.getElementById('selectedCount');
        const bulkAction = document.getElementById('bulkAction');
        const assignToSelect = document.getElementById('assignToSelect');
        const prioritySelect = document.getElementById('prioritySelect');
        const statusSelect = document.getElementById('statusSelect');
        const applyBulkAction = document.getElementById('applyBulkAction');
        const clearSelection = document.getElementById('clearSelection');

        // Update toolbar visibility and count
        function updateBulkToolbar() {
            const checked = document.querySelectorAll('.task-checkbox:checked');
            selectedCount.textContent = checked.length;
            bulkToolbar.classList.toggle('hidden', checked.length === 0);
        }

        // Select all checkbox
        selectAll.addEventListener('change', function () {
            taskCheckboxes.forEach(cb => cb.checked = this.checked);
            updateBulkToolbar();
        });

        // Individual checkboxes
        taskCheckboxes.forEach(cb => {
            cb.addEventListener('change', function () {
                selectAll.checked = document.querySelectorAll('.task-checkbox:checked').length === taskCheckboxes.length;
                updateBulkToolbar();
            });
        });

        // Show/hide sub-options based on action
        bulkAction.addEventListener('change', function () {
            assignToSelect.classList.add('hidden');
            prioritySelect.classList.add('hidden');
            statusSelect.classList.add('hidden');

            if (this.value === 'assign') assignToSelect.classList.remove('hidden');
            if (this.value === 'priority') prioritySelect.classList.remove('hidden');
            if (this.value === 'status') statusSelect.classList.remove('hidden');
        });

        // Clear selection
        clearSelection.addEventListener('click', function () {
            taskCheckboxes.forEach(cb => cb.checked = false);
            selectAll.checked = false;
            updateBulkToolbar();
        });

        // Apply bulk action
        applyBulkAction.addEventListener('click', function () {
            const action = bulkAction.value;
            if (!action) {
                Modal.show({ type: 'warning', title: 'Select Action', message: 'Please select an action' });
                return;
            }

            const selectedIds = Array.from(document.querySelectorAll('.task-checkbox:checked')).map(cb => cb.value);
            if (selectedIds.length === 0) {
                Modal.show({ type: 'warning', title: 'No Selection', message: 'Please select at least one task' });
                return;
            }

            let formData = new FormData();
            formData.append('action', action);
            selectedIds.forEach(id => formData.append('task_ids[]', id));

            // Add sub-option value
            if (action === 'assign') {
                const assignTo = assignToSelect.value;
                if (!assignTo) {
                    Modal.show({ type: 'warning', title: 'Select User', message: 'Please select a user to assign to' });
                    return;
                }
                formData.append('assign_to', assignTo);
            } else if (action === 'priority') {
                const priority = prioritySelect.value;
                if (!priority) {
                    Modal.show({ type: 'warning', title: 'Select Priority', message: 'Please select a priority' });
                    return;
                }
                formData.append('priority', priority);
            } else if (action === 'status') {
                const status = statusSelect.value;
                if (!status) {
                    Modal.show({ type: 'warning', title: 'Select Status', message: 'Please select a status' });
                    return;
                }
                formData.append('status', status);
            }

            const actionNames = { assign: 'reassign', priority: 'update priority for', status: 'update status for', delete: 'delete' };

            Modal.show({
                type: action === 'delete' ? 'error' : 'warning',
                title: 'Confirm Bulk Action',
                message: `Are you sure you want to ${actionNames[action] || action} ${selectedIds.length} task(s)?`,
                confirmText: 'Yes, Proceed',
                onConfirm: function () {
                    // Send AJAX request
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
</script>

<?php require_once '../../templates/footer.php'; ?>