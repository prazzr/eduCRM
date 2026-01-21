<?php
/**
 * Add Task
 * Creates new tasks with user assignment and priority
 */
require_once '../../app/bootstrap.php';



requireLogin();

$pageDetails = ['title' => 'Add Task'];
require_once '../../templates/header.php';

$taskService = new \EduCRM\Services\TaskService($pdo);
$error = '';
$success = '';

// Get all users for assignment dropdown (using helper)
$users = users()->getAllUsers();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $assigned_to = $_POST['assigned_to'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';
    $due_date = $_POST['due_date'] ?? null;
    $entity_type = $_POST['entity_type'] ?? 'general';
    $entity_id = $_POST['entity_id'] ?? null;

    if (empty($title)) {
        $error = 'Task title is required.';
    } elseif (empty($assigned_to)) {
        $error = 'Please assign the task to a user.';
    } else {
        $taskData = [
            'title' => $title,
            'description' => $description,
            'assigned_to' => $assigned_to,
            'created_by' => $_SESSION['user_id'],
            'priority' => $priority,
            'due_date' => $due_date ?: null,
            'related_entity_type' => $entity_type,
            'related_entity_id' => ($entity_type !== 'general' && $entity_id) ? $entity_id : null
        ];

        if ($taskService->createTask($taskData)) {
            // Use standard helper for consistent behavior
            redirectWithAlert('list.php', 'Task created successfully!', 'success');
        } else {
            $error = 'Failed to create task. Please try again.';
        }
    }
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Create New Task</h1>
    <p class="text-slate-600 mt-1">Assign a task to a team member</p>
</div>

<?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-6">
        <?php echo htmlspecialchars($success); ?> Redirecting...
    </div>
<?php endif; ?>

<div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <form method="POST" class="space-y-4">
        <!-- Title -->
        <div>
            <label for="title" class="block text-sm font-medium text-slate-700 mb-1">
                Task Title <span class="text-red-500">*</span>
            </label>
            <input type="text" id="title" name="title" required
                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="e.g., Follow up with inquiry #123"
                value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
        </div>

        <!-- Description -->
        <div>
            <label for="description" class="block text-sm font-medium text-slate-700 mb-1">
                Description
            </label>
            <textarea id="description" name="description" rows="4"
                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Provide additional details about this task..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Assigned To -->
            <div>
                <label for="assignedSearch" class="block text-sm font-medium text-slate-700 mb-1">
                    Assign To <span class="text-red-500">*</span>
                </label>
                <input type="hidden" name="assigned_to" id="assignedToValue"
                    value="<?php echo htmlspecialchars($_POST['assigned_to'] ?? ''); ?>">
                <div style="position: relative;">
                    <input type="text" id="assignedSearch"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        placeholder="🔍 Search user by name..." autocomplete="off">
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const userData = <?php echo json_encode(array_map(function ($u) {
                            return ['id' => $u['id'], 'name' => $u['name']];
                        }, $users)); ?>;

                        new SearchableDropdown({
                            inputId: 'assignedSearch',
                            hiddenInputId: 'assignedToValue',
                            data: userData,
                            displayField: 'name'
                        });
                    });
                </script>
            </div>

            <!-- Priority -->
            <div>
                <label for="priority" class="block text-sm font-medium text-slate-700 mb-1">
                    Priority
                </label>
                <select id="priority" name="priority"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'low') ? 'selected' : ''; ?>>Low</option>
                    <option value="medium" <?php echo (!isset($_POST['priority']) || $_POST['priority'] === 'medium') ? 'selected' : ''; ?>>Medium</option>
                    <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'high') ? 'selected' : ''; ?>>High</option>
                    <option value="urgent" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                </select>
            </div>
        </div>

        <!-- Due Date -->
        <div>
            <label for="due_date" class="block text-sm font-medium text-slate-700 mb-1">
                Due Date
            </label>
            <input type="datetime-local" id="due_date" name="due_date"
                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                value="<?php echo htmlspecialchars($_POST['due_date'] ?? ''); ?>">
        </div>

        <div class="border-t border-slate-200 pt-4 mt-4">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Link to Entity (Optional)</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Entity Type -->
                <div>
                    <label for="entity_type" class="block text-sm font-medium text-slate-700 mb-1">
                        Entity Type
                    </label>
                    <select id="entity_type" name="entity_type"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="general" <?php echo (!isset($_POST['entity_type']) || $_POST['entity_type'] === 'general') ? 'selected' : ''; ?>>General</option>
                        <option value="inquiry" <?php echo (isset($_POST['entity_type']) && $_POST['entity_type'] === 'inquiry') ? 'selected' : ''; ?>>Inquiry</option>
                        <option value="student" <?php echo (isset($_POST['entity_type']) && $_POST['entity_type'] === 'student') ? 'selected' : ''; ?>>Student</option>
                        <option value="application" <?php echo (isset($_POST['entity_type']) && $_POST['entity_type'] === 'application') ? 'selected' : ''; ?>>Application</option>
                        <option value="class" <?php echo (isset($_POST['entity_type']) && $_POST['entity_type'] === 'class') ? 'selected' : ''; ?>>Class</option>
                    </select>
                </div>

                <!-- Entity ID -->
                <div>
                    <label for="entity_id" class="block text-sm font-medium text-slate-700 mb-1">
                        Entity ID
                    </label>
                    <input type="number" id="entity_id" name="entity_id"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        placeholder="Enter ID" value="<?php echo htmlspecialchars($_POST['entity_id'] ?? ''); ?>">
                    <p class="text-xs text-slate-500 mt-1">Leave blank for general tasks</p>
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex gap-3 pt-4">
            <button type="submit" class="btn">
                Create Task
            </button>
            <a href="list.php" class="btn-secondary px-4 py-2 rounded-lg font-medium">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>