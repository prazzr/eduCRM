<?php
require_once '../../config.php';
require_once '../../includes/services/TaskService.php';

requireLogin();

$taskService = new TaskService($pdo);

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

// Delete the task
if ($taskService->deleteTask($taskId)) {
    header('Location: list.php?deleted=1');
} else {
    header('Location: list.php?error=delete_failed');
}
exit;
