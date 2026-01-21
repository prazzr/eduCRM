<?php
/**
 * Bulk Action Handler for Tasks
 * Processes bulk operations on selected tasks
 */

require_once '../../app/bootstrap.php';



requireLogin();

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$bulkService = new \EduCRM\Services\BulkActionService($pdo);
$taskService = new \EduCRM\Services\TaskService($pdo);

$action = $_POST['action'] ?? '';
$taskIds = $_POST['task_ids'] ?? [];

// Validate input
if (empty($taskIds) || !is_array($taskIds)) {
    echo json_encode(['success' => false, 'message' => 'No tasks selected']);
    exit;
}

// Convert to integers
$taskIds = array_map('intval', $taskIds);

// Verify permissions - users can only bulk edit their own tasks unless admin
if (!hasRole('admin')) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE id IN (" . implode(',', array_fill(0, count($taskIds), '?')) . ") AND assigned_to != ?");
    $stmt->execute(array_merge($taskIds, [$_SESSION['user_id']]));

    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'You can only perform bulk actions on your own tasks']);
        exit;
    }
}

try {
    $result = false;
    $message = '';

    switch ($action) {
        case 'assign':
            $assignTo = intval($_POST['assign_to'] ?? 0);
            if ($assignTo <= 0) {
                throw new Exception('Invalid user selected');
            }

            $result = $bulkService->bulkUpdateTasks($taskIds, ['assigned_to' => $assignTo]);
            $message = $result ? 'Tasks assigned successfully' : 'Failed to assign tasks';
            break;

        case 'priority':
            $priority = $_POST['priority'] ?? '';
            if (!in_array($priority, ['urgent', 'high', 'medium', 'low'])) {
                throw new Exception('Invalid priority');
            }

            $result = $bulkService->bulkUpdateTasks($taskIds, ['priority' => $priority]);
            $message = $result ? 'Task priority updated successfully' : 'Failed to update priority';
            break;

        case 'status':
            $status = $_POST['status'] ?? '';
            if (!in_array($status, ['pending', 'in_progress', 'completed'])) {
                throw new Exception('Invalid status');
            }

            $updates = ['status' => $status];
            if ($status === 'completed') {
                $updates['completed_at'] = date('Y-m-d H:i:s');
            }

            $result = $bulkService->bulkUpdateTasks($taskIds, $updates);
            $message = $result ? 'Task status updated successfully' : 'Failed to update status';
            break;

        case 'delete':
            // Admin only for bulk delete
            if (!hasRole('admin')) {
                throw new Exception('Only administrators can bulk delete tasks');
            }

            $result = $bulkService->bulkDelete('tasks', $taskIds);
            $message = $result ? count($taskIds) . ' tasks deleted successfully' : 'Failed to delete tasks';
            break;

        default:
            throw new Exception('Invalid action');
    }

    echo json_encode([
        'success' => $result,
        'message' => $message,
        'count' => count($taskIds)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
