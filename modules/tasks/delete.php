<?php
/**
 * Task Delete
 * Handles task deletion with permission checks
 */
require_once '../../app/bootstrap.php';


requireLogin();

$taskService = new \EduCRM\Services\TaskService($pdo);

// Validate task ID parameter
$taskId = requireIdParam();
$task = $taskService->getTask($taskId);

if (!$task) {
    redirectWithAlert("list.php", "Task not found", "danger");
}

// Check permission (admin or assigned user can delete)
if (!hasRole('admin') && $task['assigned_to'] != $_SESSION['user_id']) {
    redirectWithAlert("list.php", "You don't have permission to delete this task", "danger");
}

// Delete the task
if ($taskService->deleteTask($taskId)) {
    logAction('task_delete', "Deleted task ID: {$taskId}");
    redirectWithAlert("list.php", "Task deleted successfully!", "danger");
} else {
    redirectWithAlert("list.php", "Failed to delete task", "danger");
}
