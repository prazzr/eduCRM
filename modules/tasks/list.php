<?php
require_once '../../config.php';
require_once '../../includes/services/TaskService.php';

requireLogin();

$pageDetails = ['title' => 'Tasks'];
require_once '../../includes/header.php';

$taskService = new TaskService($pdo);

// Get filter parameters
$statusFilter = $_GET['status'] ?? null;
$priorityFilter = $_GET['priority'] ?? null;
$entityFilter = $_GET['entity_type'] ?? null;

$filters = [];
if ($statusFilter) $filters['status'] = $statusFilter;
if ($priorityFilter) $filters['priority'] = $priorityFilter;
if ($entityFilter) $filters['entity_type'] = $entityFilter;

// Get tasks based on role
if (hasRole('admin')) {
    $assignedToFilter = $_GET['assigned_to'] ?? null;
    if ($assignedToFilter) $filters['assigned_to'] = $assignedToFilter;
    $tasks = $taskService->getAllTasks($filters);
    
    // Get all users for filter dropdown and bulk assign
    $usersStmt = $pdo->query("SELECT id, name FROM users ORDER BY name");
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $tasks = $taskService->getUserTasks($_SESSION['user_id'], $filters);
}
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold text-slate-800">My Tasks</h1>
    <a href="add.php" class="btn">+ New Task</a>
</div>

<!-- Filters -->
<div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6">
    <form method="GET" class="flex flex-wrap gap-3">
        <!-- Status Filter -->
        <select name="status" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">All Statuses</option>
            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
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
            <option value="application" <?php echo $entityFilter === 'application' ? 'selected' : ''; ?>>Application</option>
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
                <?php if (hasRole('admin')): foreach ($users as $user): ?>
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
                        <input type="checkbox" id="selectAll" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
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
                                <input type="checkbox" class="task-checkbox rounded border-slate-300 text-primary-600 focus:ring-primary-500" value="<?php echo $task['id']; ?>">
                            </td>
                            <td class="p-3">
                                <div>
                                    <strong class="text-slate-800"><?php echo htmlspecialchars($task['title']); ?></strong>
                                    <?php if ($task['description']): ?>
                                        <p class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars(substr($task['description'], 0, 60)) . (strlen($task['description']) > 60 ? '...' : ''); ?></p>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-3">
                                <span class="inline-block px-2 py-0.5 rounded text-xs font-bold uppercase <?php echo $priorityColors[$task['priority']]; ?>">
                                    <?php echo $task['priority']; ?>
                                </span>
                            </td>
                            <td class="p-3">
                                <span class="inline-block px-2 py-0.5 rounded text-xs font-bold uppercase <?php echo $statusColors[$task['status']]; ?>">
                                    <?php echo str_replace('_', ' ', $task['status']); ?>
                                </span>
                            </td>
                            <td class="p-3 text-sm text-slate-600">
                                <?php if ($task['related_entity_type'] !== 'general'): ?>
                                    <span class="capitalize"><?php echo $task['related_entity_type']; ?></span> #<?php echo $task['related_entity_id']; ?>
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
                                    <a href="edit.php?id=<?php echo $task['id']; ?>" class="btn-secondary px-3 py-1.5 text-xs rounded">Edit</a>
                                    <?php if ($task['status'] !== 'completed'): ?>
                                        <form method="POST" action="edit.php" class="inline">
                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                            <input type="hidden" name="action" value="complete">
                                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 text-xs rounded font-medium">
                                                Complete
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
                            No tasks found. <a href="add.php" class="text-primary-600 hover:underline">Create your first task</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Phase 2C: Bulk Action JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
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
    selectAll.addEventListener('change', function() {
        taskCheckboxes.forEach(cb => cb.checked = this.checked);
        updateBulkToolbar();
    });
    
    // Individual checkboxes
    taskCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            selectAll.checked = document.querySelectorAll('.task-checkbox:checked').length === taskCheckboxes.length;
            updateBulkToolbar();
        });
    });
    
    // Show/hide sub-options based on action
    bulkAction.addEventListener('change', function() {
        assignToSelect.classList.add('hidden');
        prioritySelect.classList.add('hidden');
        statusSelect.classList.add('hidden');
        
        if (this.value === 'assign') assignToSelect.classList.remove('hidden');
        if (this.value === 'priority') prioritySelect.classList.remove('hidden');
        if (this.value === 'status') statusSelect.classList.remove('hidden');
    });
    
    // Clear selection
    clearSelection.addEventListener('click', function() {
        taskCheckboxes.forEach(cb => cb.checked = false);
        selectAll.checked = false;
        updateBulkToolbar();
    });
    
    // Apply bulk action
    applyBulkAction.addEventListener('click', function() {
        const action = bulkAction.value;
        if (!action) {
            alert('Please select an action');
            return;
        }
        
        const selectedIds = Array.from(document.querySelectorAll('.task-checkbox:checked')).map(cb => cb.value);
        if (selectedIds.length === 0) {
            alert('Please select at least one task');
            return;
        }
        
        let confirmMsg = `Are you sure you want to ${action} ${selectedIds.length} task(s)?`;
        let formData = new FormData();
        formData.append('action', action);
        selectedIds.forEach(id => formData.append('task_ids[]', id));
        
        // Add sub-option value
        if (action === 'assign') {
            const assignTo = assignToSelect.value;
            if (!assignTo) {
                alert('Please select a user to assign to');
                return;
            }
            formData.append('assign_to', assignTo);
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
        
        if (!confirm(confirmMsg)) return;
        
        // Send AJAX request
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
