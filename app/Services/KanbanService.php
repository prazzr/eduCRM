<?php
namespace EduCRM\Services;

/**
 * Kanban Board Service
 * Handles data fetching and status updates for Kanban views
 */
class KanbanService
{
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get items grouped by status for Kanban display
     */
    public function getKanbanData(string $entity, array $columns, array $filters = []): array
    {
        $data = [];

        foreach ($columns as $columnKey => $columnConfig) {
            $data[$columnKey] = [
                'title' => $columnConfig['title'],
                'color' => $columnConfig['color'] ?? 'slate',
                'icon' => $columnConfig['icon'] ?? null,
                'items' => $this->getItemsForColumn($entity, $columnKey, $filters),
                'count' => 0
            ];
            $data[$columnKey]['count'] = count($data[$columnKey]['items']);
        }

        return $data;
    }

    private function getItemsForColumn(string $entity, string $status, array $filters): array
    {
        switch ($entity) {
            case 'tasks':
                return $this->getTasksForColumn($status, $filters);
            case 'inquiries':
                return $this->getInquiriesForColumn($status, $filters);
            case 'visa':
                return $this->getVisaForColumn($status, $filters);
            default:
                return [];
        }
    }

    private function getTasksForColumn(string $status, array $filters): array
    {
        $where = ['t.status = ?'];
        $params = [$status];

        if (!empty($filters['assigned_to'])) {
            $where[] = 't.assigned_to = ?';
            $params[] = $filters['assigned_to'];
        }

        if (!empty($filters['branch_id'])) {
            $where[] = 't.branch_id = ?';
            $params[] = $filters['branch_id'];
        }

        $stmt = $this->pdo->prepare("
            SELECT t.id, t.title, t.priority, t.due_date, t.status,
                   u.name as assigned_name,
                   CASE 
                       WHEN t.due_date < CURDATE() AND t.status != 'completed' THEN 1 
                       ELSE 0 
                   END as is_overdue
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY 
                FIELD(t.priority, 'urgent', 'high', 'medium', 'low'),
                t.due_date ASC
            LIMIT 100
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getInquiriesForColumn(string $status, array $filters): array
    {
        $where = ['i.status = ?'];
        $params = [$status];

        if (!empty($filters['assigned_to'])) {
            $where[] = 'i.assigned_to = ?';
            $params[] = $filters['assigned_to'];
        }
        if (!empty($filters['priority'])) {
            $where[] = 'i.priority = ?';
            $params[] = $filters['priority'];
        }
        if (!empty($filters['branch_id'])) {
            $where[] = 'i.branch_id = ?';
            $params[] = $filters['branch_id'];
        }

        $stmt = $this->pdo->prepare("
            SELECT i.id, i.name, i.email, i.phone, i.priority, i.status,
                   i.score as lead_score, i.source, i.created_at,
                   c.name as country_name,
                   u.name as assigned_name
            FROM inquiries i
            LEFT JOIN countries c ON i.country_id = c.id
            LEFT JOIN users u ON i.assigned_to = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.score DESC, i.created_at DESC
            LIMIT 100
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getVisaForColumn(string $stage, array $filters): array
    {
        $where = ['v.current_stage = ?'];
        $params = [$stage];

        if (!empty($filters['branch_id'])) {
            $where[] = 's.branch_id = ?';
            $params[] = $filters['branch_id'];
        }

        $stmt = $this->pdo->prepare("
            SELECT v.id, v.student_id, v.current_stage, v.expected_date,
                   v.priority, v.created_at,
                   s.name as student_name, s.email as student_email,
                   c.name as destination_country,
                   CASE 
                       WHEN v.expected_date < CURDATE() AND v.current_stage NOT IN ('approved', 'rejected') THEN 1 
                       ELSE 0 
                   END as is_overdue
            FROM visa_applications v
            JOIN students s ON v.student_id = s.id
            LEFT JOIN countries c ON v.country_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY 
                FIELD(v.priority, 'urgent', 'high', 'medium', 'low'),
                v.expected_date ASC
            LIMIT 100
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Move item to new column (status update)
     */
    public function moveItem(string $entity, int $itemId, string $newStatus, ?int $userId = null): bool
    {
        $tableConfig = $this->getTableConfig($entity);
        if (!$tableConfig) {
            throw new \InvalidArgumentException("Unknown entity: {$entity}");
        }

        $table = $tableConfig['table'];
        $column = $tableConfig['column'];

        // Get old status for logging
        $stmt = $this->pdo->prepare("SELECT {$column} FROM {$table} WHERE id = ?");
        $stmt->execute([$itemId]);
        $oldStatus = $stmt->fetchColumn();

        // Update status
        $stmt = $this->pdo->prepare("UPDATE {$table} SET {$column} = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$newStatus, $itemId]);

        // Log the status change
        if ($result && $oldStatus !== $newStatus) {
            $this->logStatusChange($entity, $itemId, $oldStatus, $newStatus, $userId);
        }

        return $result;
    }

    /**
     * Get table configuration for entity
     */
    private function getTableConfig(string $entity): ?array
    {
        $configs = [
            'tasks' => ['table' => 'tasks', 'column' => 'status'],
            'inquiries' => ['table' => 'inquiries', 'column' => 'status'],
            'visa' => ['table' => 'visa_applications', 'column' => 'current_stage'],
        ];

        return $configs[$entity] ?? null;
    }

    /**
     * Log status changes for activity tracking
     */
    private function logStatusChange(string $entity, int $itemId, ?string $oldStatus, string $newStatus, ?int $userId): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (entity_type, entity_id, action, details, user_id, created_at)
                VALUES (?, ?, 'status_change', ?, ?, NOW())
            ");
            $stmt->execute([
                $entity,
                $itemId,
                json_encode([
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'source' => 'kanban'
                ]),
                $userId
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the status update
            error_log("Failed to log Kanban status change: " . $e->getMessage());
        }
    }

    /**
     * Get column definitions for each entity type
     */
    public static function getEntityColumns(string $entity): array
    {
        $columns = [
            'tasks' => [
                'pending' => ['title' => 'To Do', 'color' => 'slate', 'icon' => 'circle'],
                'in_progress' => ['title' => 'In Progress', 'color' => 'blue', 'icon' => 'loader'],
                'review' => ['title' => 'Review', 'color' => 'yellow', 'icon' => 'eye'],
                'completed' => ['title' => 'Done', 'color' => 'green', 'icon' => 'check-circle'],
            ],
            'inquiries' => [
                'new' => ['title' => 'New', 'color' => 'slate', 'icon' => 'inbox'],
                'contacted' => ['title' => 'Contacted', 'color' => 'blue', 'icon' => 'phone'],
                'qualified' => ['title' => 'Qualified', 'color' => 'indigo', 'icon' => 'star'],
                'proposal' => ['title' => 'Proposal', 'color' => 'purple', 'icon' => 'file-text'],
                'converted' => ['title' => 'Converted', 'color' => 'green', 'icon' => 'user-check'],
                'lost' => ['title' => 'Lost', 'color' => 'red', 'icon' => 'user-x'],
            ],
            'visa' => [
                'doc_collection' => ['title' => 'Doc Collection', 'color' => 'slate', 'icon' => 'folder'],
                'submitted' => ['title' => 'Submitted', 'color' => 'blue', 'icon' => 'send'],
                'interview' => ['title' => 'Interview', 'color' => 'yellow', 'icon' => 'users'],
                'decision' => ['title' => 'Decision Pending', 'color' => 'orange', 'icon' => 'clock'],
                'approved' => ['title' => 'Approved', 'color' => 'green', 'icon' => 'check-circle'],
                'rejected' => ['title' => 'Rejected', 'color' => 'red', 'icon' => 'x-circle'],
            ],
        ];

        return $columns[$entity] ?? [];
    }

    /**
     * Check if a status is valid for the given entity
     */
    public function isValidStatus(string $entity, string $status): bool
    {
        $columns = self::getEntityColumns($entity);
        return isset($columns[$status]);
    }
}
