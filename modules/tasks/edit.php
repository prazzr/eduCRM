<?php
require_once '../../config.php';
require_once '../../includes/services/TaskService.php';

requireLogin();

$taskService = new TaskService($pdo);
$error = '';
$success = '';

// Handle quick complete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete') {
    $taskId = $_POST['task_id'] ?? 0;
    if ($taskService->updateTask($taskId, ['status' => 'completed'])) {
        header('Location: list.php');
        exit;
    }
}

// Get task ID
$taskId = $_GET['id'] ?? 0;
$task = $taskService->getTask($taskId);

if (!$task) {
    header('Location: list.php');
    exit;
}

// Check permission (admin or assigned user)
if (!hasRole('admin') && $task['assigned_to'] != $_SESSION['user_id']) {
    header('Location: list.php');
    exit;
}

$pageDetails = ['title' => 'Edit Task'];
require_once '../../includes/header.php';

// Get all users for assignment dropdown
$usersStmt = $pdo->query("SELECT id, name FROM users ORDER BY name");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $assigned_to = $_POST['assigned_to'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';
    $status = $_POST['status'] ?? 'pending';
    $due_date = $_POST['due_date'] ?? null;

    if (empty($title)) {
        $error = 'Task title is required.';
    } elseif (empty($assigned_to)) {
        $error = 'Please assign the task to a user.';
    } else {
        $updateData = [
            'title' => $title,
            'description' => $description,
            'assigned_to' => $assigned_to,
            'priority' => $priority,
            'status' => $status,
            'due_date' => $due_date ?: null
        ];

        if ($taskService->updateTask($taskId, $updateData)) {
            $success = 'Task updated successfully!';
            // Refresh task data
            $task = $taskService->getTask($taskId);
        } else {
            $error = 'Failed to update task. Please try again.';
        }
    }
}
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Edit Task</h1>
        <p class="text-slate-600 mt-1">Update task details</p>
    </div>
    <a href="list.php" class="btn-secondary px-4 py-2 rounded-lg font-medium">← Back to Tasks</a>
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
                        Task Title <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="title" name="title" required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        value="<?php echo htmlspecialchars($task['title']); ?>">
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700 mb-1">
                        Description
                    </label>
                    <textarea id="description" name="description" rows="4"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"><?php echo htmlspecialchars($task['description'] ?? ''); ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Assigned To -->
                    <div>
                        <label for="assigned_to" class="block text-sm font-medium text-slate-700 mb-1">
                            Assign To <span class="text-red-500">*</span>
                        </label>
                        <select id="assigned_to" name="assigned_to" required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo ($task['assigned_to'] == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Priority -->
                    <div>
                        <label for="priority" class="block text-sm font-medium text-slate-700 mb-1">
                            Priority
                        </label>
                        <select id="priority" name="priority"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="low" <?php echo ($task['priority'] === 'low') ? 'selected' : ''; ?>>Low
                            </option>
                            <option value="medium" <?php echo ($task['priority'] === 'medium') ? 'selected' : ''; ?>
                                >Medium</option>
                            <option value="high" <?php echo ($task['priority'] === 'high') ? 'selected' : ''; ?>>High
                            </option>
                            <option value="urgent" <?php echo ($task['priority'] === 'urgent') ? 'selected' : ''; ?>
                                >Urgent</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-slate-700 mb-1">
                            Status
                        </label>
                        <select id="status" name="status"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="pending" <?php echo ($task['status'] === 'pending') ? 'selected' : ''; ?>
                                >Pending</option>
                            <option value="in_progress" <?php echo ($task['status'] === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo ($task['status'] === 'completed') ? 'selected' : ''; ?>
                                >Completed</option>
                            <option value="cancelled" <?php echo ($task['status'] === 'cancelled') ? 'selected' : ''; ?>
                                >Cancelled</option>
                        </select>
                    </div>

                    <!-- Due Date -->
                    <div>
                        <label for="due_date" class="block text-sm font-medium text-slate-700 mb-1">
                            Due Date
                        </label>
                        <input type="datetime-local" id="due_date" name="due_date"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                            value="<?php echo $task['due_date'] ? date('Y-m-d\TH:i', strtotime($task['due_date'])) : ''; ?>">
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex gap-3 pt-4">
                    <button type="submit" class="btn">
                        Update Task
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
        <!-- Task Info -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Task Information</h3>

            <div class="space-y-3 text-sm">
                <div>
                    <span class="text-slate-500">Created By:</span>
                    <p class="font-medium text-slate-800">
                        <?php echo htmlspecialchars($task['created_by_name']); ?>
                    </p>
                </div>

                <div>
                    <span class="text-slate-500">Created At:</span>
                    <p class="font-medium text-slate-800">
                        <?php echo date('M d, Y H:i', strtotime($task['created_at'])); ?>
                    </p>
                </div>

                <?php if ($task['completed_at']): ?>
                    <div>
                        <span class="text-slate-500">Completed At:</span>
                        <p class="font-medium text-emerald-600">
                            <?php echo date('M d, Y H:i', strtotime($task['completed_at'])); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($task['related_entity_type'] !== 'general'): ?>
                    <div>
                        <span class="text-slate-500">Related To:</span>
                        <p class="font-medium text-slate-800 capitalize">
                            <?php echo $task['related_entity_type']; ?> #
                            <?php echo $task['related_entity_id']; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Quick Actions</h3>

            <div class="space-y-2">
                <?php if ($task['status'] !== 'completed'): ?>
                    <form method="POST">
                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                        <input type="hidden" name="action" value="complete">
                        <button type="submit"
                            class="w-full bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-medium text-sm">
                            ✓ Mark as Complete
                        </button>
                    </form>
                <?php endif; ?>

                <a href="delete.php?id=<?php echo $task['id']; ?>"
                    onclick="return confirm('Are you sure you want to delete this task?')"
                    class="block w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium text-sm text-center">
                    Delete Task
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>