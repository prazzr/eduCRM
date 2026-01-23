<?php
/**
 * Kanban API Endpoints
 * GET /api/v1/kanban/{entity} - Get board data
 * PUT /api/v1/kanban/{entity}/{id}/move - Move card to new column
 */

require_once __DIR__ . '/../../../app/bootstrap.php';

header('Content-Type: application/json');

// Require login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = $_GET['path'] ?? $_SERVER['PATH_INFO'] ?? '';
$pathParts = array_values(array_filter(explode('/', trim($pathInfo, '/'))));

$entity = $pathParts[0] ?? '';

// Validate entity
$validEntities = ['tasks', 'inquiries', 'visa'];
if (!$entity || !in_array($entity, $validEntities)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid entity. Valid: ' . implode(', ', $validEntities)]);
    exit;
}

$kanbanService = new \EduCRM\Services\KanbanService($pdo);
$entityColumns = \EduCRM\Services\KanbanService::getEntityColumns($entity);

try {
    if ($method === 'GET') {
        // Get Kanban board data
        $filters = [
            'assigned_to' => $_GET['assigned_to'] ?? null,
            'priority' => $_GET['priority'] ?? null,
            'branch_id' => $_GET['branch_id'] ?? null,
        ];

        // Non-admins see only their items for tasks and inquiries
        if (!hasRole('admin') && in_array($entity, ['tasks', 'inquiries'])) {
            $filters['assigned_to'] = $_SESSION['user_id'];
        }

        // Branch managers see only their branch
        if (hasRole('branch_manager') && !hasRole('admin')) {
            $filters['branch_id'] = $_SESSION['branch_id'] ?? null;
        }

        $data = $kanbanService->getKanbanData($entity, $entityColumns, $filters);
        echo json_encode(['success' => true, 'data' => $data]);

    } elseif ($method === 'PUT' || $method === 'POST') {
        // Move card to new status
        $itemId = isset($pathParts[1]) ? (int) $pathParts[1] : 0;
        $action = $pathParts[2] ?? '';

        if ($action !== 'move') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $newStatus = $input['status'] ?? '';

        if (!$itemId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Item ID required']);
            exit;
        }

        if (!$newStatus) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'New status required']);
            exit;
        }

        // Validate status exists in columns
        if (!$kanbanService->isValidStatus($entity, $newStatus)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid status: ' . $newStatus]);
            exit;
        }

        $userId = $_SESSION['user_id'] ?? null;
        $result = $kanbanService->moveItem($entity, $itemId, $newStatus, $userId);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Status updated']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update status']);
        }

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (\Exception $e) {
    error_log("Kanban API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
