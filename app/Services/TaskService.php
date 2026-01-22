<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
 * Task Service
 * Handles task management operations
 */

class TaskService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new task
     */
    public function createTask($data)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO tasks (
                title, description, assigned_to, created_by,
                related_entity_type, related_entity_id,
                priority, status, due_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['assigned_to'],
            $data['created_by'],
            $data['related_entity_type'] ?? 'general',
            $data['related_entity_id'] ?? null,
            $data['priority'] ?? 'medium',
            'pending',
            $data['due_date'] ?? null
        ]);

        // Phase 5: Unified Notification System
        if ($result) {
            $taskId = $this->pdo->lastInsertId();
            try {
                // Prepare variable data for templates
                $notificationData = [
                    'task_id' => $taskId,
                    'task_title' => $data['title'],
                    'priority' => $data['priority'] ?? 'medium',
                    'due_date' => $data['due_date'] ? date('M d, Y', strtotime($data['due_date'])) : 'No due date',
                    'created_by_name' => $_SESSION['user_name'] ?? 'System'
                ];

                // Load Services
                require_once dirname(__DIR__) . '/services/EmailNotificationService.php';
                require_once dirname(__DIR__) . '/services/UnifiedNotificationService.php';

                $emailService = new \EduCRM\Services\EmailNotificationService($this->pdo);
                $unifiedService = new \EduCRM\Services\UnifiedNotificationService($this->pdo, $emailService);

                // Dispatch Event
                $unifiedService->dispatch('task_assigned', (int) $data['assigned_to'], $notificationData);

            } catch (\Exception $e) {
                // Log error but don't fail task creation
                error_log("Failed to send unified task notification: " . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Get task details
     */
    public function getTask($taskId)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                t.*,
                u1.name as assigned_to_name,
                u2.name as created_by_name
            FROM tasks t
            LEFT JOIN users u1 ON t.assigned_to = u1.id
            LEFT JOIN users u2 ON t.created_by = u2.id
            WHERE t.id = ?
        ");
        $stmt->execute([$taskId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Send task SMS notification
     * Phase 3B Integration
     */
    private function sendTaskSMS($taskId, $type = 'assignment')
    {
        try {
            require_once __DIR__ . '/MessagingFactory.php';

            // Get task details
            $stmt = $this->pdo->prepare("
                SELECT t.*, 
                       u1.name as assigned_to_name, u1.phone as assigned_to_phone,
                       u2.name as created_by_name
                FROM tasks t
                LEFT JOIN users u1 ON t.assigned_to = u1.id
                LEFT JOIN users u2 ON t.created_by = u2.id
                WHERE t.id = ?
            ");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$task || !$task['assigned_to_phone'])
                return; // No phone number

            // Get template
            $templateStmt = $this->pdo->prepare("
                SELECT content, variables FROM messaging_templates 
                WHERE category = 'task' AND is_active = TRUE 
                LIMIT 1
            ");
            $templateStmt->execute();
            $template = $templateStmt->fetch(\PDO::FETCH_ASSOC);

            if ($template) {
                // Use template
                \EduCRM\Services\MessagingFactory::init($this->pdo);
                $gateway = \EduCRM\Services\MessagingFactory::create();

                $message = $gateway->replaceVariables($template['content'], [
                    'name' => $task['assigned_to_name'],
                    'task_title' => $task['title'],
                    'due_date' => $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : 'No due date'
                ]);
            } else {
                // Fallback message
                if ($type === 'assignment') {
                    $message = "Hi {$task['assigned_to_name']}, you have been assigned a new task: \"{$task['title']}\".";
                    if ($task['due_date']) {
                        $message .= " Due: " . date('M d, Y', strtotime($task['due_date']));
                    }
                } else {
                    $message = "Hi {$task['assigned_to_name']}, reminder: Task \"{$task['title']}\" is due on " .
                        date('M d, Y', strtotime($task['due_date'])) . ".";
                }
            }

            // Queue SMS
            \EduCRM\Services\MessagingFactory::init($this->pdo);
            $gateway = \EduCRM\Services\MessagingFactory::create();
            $gateway->queue($task['assigned_to_phone'], $message, [
                'entity_type' => 'task',
                'entity_id' => $taskId
            ]);

        } catch (\Exception $e) {
            // Log error but don't fail task creation
            error_log('SMS notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Get tasks for a user
     */
    public function getUserTasks($userId, $filters = [])
    {
        $sql = "
            SELECT 
                t.*,
                u1.name as assigned_to_name,
                u2.name as created_by_name
            FROM tasks t
            LEFT JOIN users u1 ON t.assigned_to = u1.id
            LEFT JOIN users u2 ON t.created_by = u2.id
            WHERE t.assigned_to = ?
        ";

        $params = [$userId];

        // Apply filters
        if (isset($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['priority'])) {
            $sql .= " AND t.priority = ?";
            $params[] = $filters['priority'];
        }

        if (isset($filters['entity_type'])) {
            $sql .= " AND t.related_entity_type = ?";
            $params[] = $filters['entity_type'];
        }

        $sql .= " ORDER BY 
            CASE t.priority 
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
            END,
            t.due_date ASC,
            t.created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all tasks (for admin)
     */
    public function getAllTasks($filters = [])
    {
        $sql = "
            SELECT 
                t.*,
                u1.name as assigned_to_name,
                u2.name as created_by_name
            FROM tasks t
            LEFT JOIN users u1 ON t.assigned_to = u1.id
            LEFT JOIN users u2 ON t.created_by = u2.id
            WHERE 1=1
        ";

        $params = [];

        // Apply filters
        if (isset($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['priority'])) {
            $sql .= " AND t.priority = ?";
            $params[] = $filters['priority'];
        }

        if (isset($filters['assigned_to'])) {
            $sql .= " AND t.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }

        $sql .= " ORDER BY 
            CASE t.priority 
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
            END,
            t.due_date ASC,
            t.created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Update task
     */
    public function updateTask($taskId, $data)
    {
        $fields = [];
        $params = [];

        if (isset($data['title'])) {
            $fields[] = "title = ?";
            $params[] = $data['title'];
        }

        if (isset($data['description'])) {
            $fields[] = "description = ?";
            $params[] = $data['description'];
        }

        if (isset($data['assigned_to'])) {
            $fields[] = "assigned_to = ?";
            $params[] = $data['assigned_to'];
        }

        if (isset($data['priority'])) {
            $fields[] = "priority = ?";
            $params[] = $data['priority'];
        }

        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];

            // If marking as completed, set completed_at
            if ($data['status'] === 'completed') {
                $fields[] = "completed_at = NOW()";
            }
        }

        if (isset($data['due_date'])) {
            $fields[] = "due_date = ?";
            $params[] = $data['due_date'];
        }

        if (empty($fields))
            return false;

        $params[] = $taskId;

        $sql = "UPDATE tasks SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Delete task
     */
    public function deleteTask($taskId)
    {
        $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE id = ?");
        return $stmt->execute([$taskId]);
    }

    /**
     * Get pending tasks count for user
     */
    public function getPendingTasksCount($userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM tasks
            WHERE assigned_to = ? 
            AND status IN ('pending', 'in_progress')
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) $result['count'];
    }

    /**
     * Get overdue tasks for user
     */
    public function getOverdueTasks($userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                t.*,
                u1.name as assigned_to_name,
                u2.name as created_by_name
            FROM tasks t
            LEFT JOIN users u1 ON t.assigned_to = u1.id
            LEFT JOIN users u2 ON t.created_by = u2.id
            WHERE t.assigned_to = ?
            AND t.status IN ('pending', 'in_progress')
            AND t.due_date < NOW()
            ORDER BY t.due_date ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get tasks related to an entity
     */
    public function getEntityTasks($entityType, $entityId)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                t.*,
                u1.name as assigned_to_name,
                u2.name as created_by_name
            FROM tasks t
            LEFT JOIN users u1 ON t.assigned_to = u1.id
            LEFT JOIN users u2 ON t.created_by = u2.id
            WHERE t.related_entity_type = ?
            AND t.related_entity_id = ?
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$entityType, $entityId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get task statistics
     */
    public function getTaskStats($userId = null)
    {
        $sql = "
            SELECT 
                status,
                priority,
                COUNT(*) as count
            FROM tasks
        ";

        $params = [];
        if ($userId) {
            $sql .= " WHERE assigned_to = ?";
            $params[] = $userId;
        }

        $sql .= " GROUP BY status, priority";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
