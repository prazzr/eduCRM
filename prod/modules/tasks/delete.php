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
    redirectWithAlert("list.php", "Task not found.", "error");
}

// Check permission (admin or assigned user can delete)
if (!hasRole('admin') && !hasRole('branch_manager') && $task['assigned_to'] != $_SESSION['user_id']) {
    redirectWithAlert("list.php", "Access denied. You cannot delete this task.", "error");
}

// Delete the task
if ($taskService->deleteTask($taskId)) {
    logAction('task_delete', "Deleted task ID: {$taskId}");
    redirectWithAlert("list.php", "Task deleted successfully!", "success");
} else {
    redirectWithAlert("list.php", "Unable to delete task. Please try again.", "error");
}
