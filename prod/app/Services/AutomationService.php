<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
 * Automation Service
 * Manages dynamic email/SMS templates and automation workflows
 */
class AutomationService
{
    private \PDO $pdo;

    // Available trigger events with their descriptions and variables
    public const TRIGGER_EVENTS = [
        'user_created' => [
            'name' => 'User Created',
            'description' => 'When a new staff user is created',
            'variables' => ['name', 'email', 'password', 'login_url', 'role']
        ],
        'student_created' => [
            'name' => 'Student Created',
            'description' => 'When a new student account is created',
            'variables' => ['name', 'email', 'password', 'login_url', 'phone']
        ],
        'inquiry_created' => [
            'name' => 'Inquiry Created',
            'description' => 'When a new inquiry is submitted',
            'variables' => ['name', 'email', 'phone', 'country', 'course']
        ],
        'workflow_stage_changed' => [
            'name' => 'Visa Stage Changed',
            'description' => 'When visa workflow stage is updated',
            'variables' => ['name', 'email', 'old_stage', 'new_stage', 'country', 'workflow_url']
        ],
        'document_status_changed' => [
            'name' => 'Document Status Changed',
            'description' => 'When document status is updated',
            'variables' => ['name', 'email', 'document_name', 'status', 'remarks']
        ],
        'enrollment_created' => [
            'name' => 'Class Enrollment',
            'description' => 'When a student is enrolled in a class',
            'variables' => ['name', 'email', 'course_name', 'class_name', 'start_date', 'instructor', 'schedule']
        ],
        'task_assigned' => [
            'name' => 'Task Assigned',
            'description' => 'When a task is assigned to a user',
            'variables' => ['name', 'email', 'task_title', 'task_description', 'due_date', 'priority', 'task_url']
        ],
        'task_overdue' => [
            'name' => 'Task Overdue',
            'description' => 'When a task becomes overdue',
            'variables' => ['name', 'email', 'task_title', 'days_overdue', 'due_date', 'priority', 'task_url']
        ],
        'appointment_reminder' => [
            'name' => 'Appointment Reminder',
            'description' => 'Reminder before appointment',
            'variables' => ['name', 'email', 'appointment_title', 'client_name', 'appointment_date', 'location', 'meeting_link']
        ],
        'payment_received' => [
            'name' => 'Payment Received',
            'description' => 'When a payment is recorded',
            'variables' => ['name', 'email', 'amount', 'payment_method', 'receipt_url']
        ],
        'profile_updated' => [
            'name' => 'Profile Updated',
            'description' => 'When user profile is modified',
            'variables' => ['name', 'email', 'changes', 'profile_url']
        ]
    ];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =========================================================================
    // TEMPLATE METHODS
    // =========================================================================

    /**
     * Get all templates with optional filtering
     */
    public function getTemplates(array $filters = []): array
    {
        $sql = "SELECT t.*, u.name as created_by_name 
                FROM automation_templates t 
                LEFT JOIN users u ON t.created_by = u.id 
                WHERE 1=1";
        $params = [];

        if (!empty($filters['channel'])) {
            $sql .= " AND t.channel = ?";
            $params[] = $filters['channel'];
        }

        if (isset($filters['is_active'])) {
            $sql .= " AND t.is_active = ?";
            $params[] = $filters['is_active'] ? 1 : 0;
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (t.name LIKE ? OR t.template_key LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY t.channel, t.name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get a single template by ID
     */
    public function getTemplate(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM automation_templates WHERE id = ?");
        $stmt->execute([$id]);
        $template = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $template ?: null;
    }

    /**
     * Get a template by key
     */
    public function getTemplateByKey(string $key): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM automation_templates WHERE template_key = ? AND is_active = 1");
        $stmt->execute([$key]);
        $template = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $template ?: null;
    }

    /**
     * Create a new template
     */
    public function createTemplate(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO automation_templates 
            (name, template_key, channel, subject, body_html, body_text, variables, is_system, is_active, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['name'],
            $data['template_key'],
            $data['channel'] ?? 'email',
            $data['subject'] ?? null,
            $data['body_html'] ?? null,
            $data['body_text'] ?? null,
            json_encode($data['variables'] ?? []),
            $data['is_system'] ?? false,
            $data['is_active'] ?? true,
            $data['created_by'] ?? null
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update an existing template
     */
    public function updateTemplate(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        $allowedFields = ['name', 'subject', 'body_html', 'body_text', 'is_active'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (!empty($data['variables'])) {
            $fields[] = "variables = ?";
            $params[] = json_encode($data['variables']);
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE automation_templates SET " . implode(', ', $fields) . " WHERE id = ?";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete a template (only non-system templates)
     */
    public function deleteTemplate(int $id): bool
    {
        // Check if it's a system template
        $stmt = $this->pdo->prepare("SELECT is_system FROM automation_templates WHERE id = ?");
        $stmt->execute([$id]);
        $template = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$template || $template['is_system']) {
            return false; // Cannot delete system templates
        }

        $stmt = $this->pdo->prepare("DELETE FROM automation_templates WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Toggle template active status
     */
    public function toggleTemplate(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE automation_templates SET is_active = NOT is_active WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // =========================================================================
    // WORKFLOW METHODS
    // =========================================================================

    /**
     * Get all workflows with optional filtering
     */
    public function getWorkflows(array $filters = []): array
    {
        $sql = "SELECT w.*, t.name as template_name, t.template_key, t.channel as template_channel,
                       g.name as gateway_name, u.name as created_by_name
                FROM automation_workflows w
                JOIN automation_templates t ON w.template_id = t.id
                LEFT JOIN messaging_gateways g ON w.gateway_id = g.id
                LEFT JOIN users u ON w.created_by = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['trigger_event'])) {
            $sql .= " AND w.trigger_event = ?";
            $params[] = $filters['trigger_event'];
        }

        if (!empty($filters['channel'])) {
            $sql .= " AND w.channel = ?";
            $params[] = $filters['channel'];
        }

        if (isset($filters['is_active'])) {
            $sql .= " AND w.is_active = ?";
            $params[] = $filters['is_active'] ? 1 : 0;
        }

        $sql .= " ORDER BY w.trigger_event, w.priority DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get a single workflow by ID
     */
    public function getWorkflow(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT w.*, t.name as template_name, t.template_key
            FROM automation_workflows w
            JOIN automation_templates t ON w.template_id = t.id
            WHERE w.id = ?
        ");
        $stmt->execute([$id]);
        $workflow = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $workflow ?: null;
    }

    /**
     * Get active workflows for a specific trigger event
     */
    public function getWorkflowsForTrigger(string $triggerEvent): array
    {
        $stmt = $this->pdo->prepare("
            SELECT w.*, t.template_key, t.subject, t.body_html, t.body_text, t.channel,
                   g.name as gateway_name
            FROM automation_workflows w
            JOIN automation_templates t ON w.template_id = t.id
            LEFT JOIN messaging_gateways g ON w.gateway_id = g.id
            WHERE w.trigger_event = ? AND w.is_active = 1 AND t.is_active = 1
            ORDER BY w.priority DESC
        ");
        $stmt->execute([$triggerEvent]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Create a new workflow
     */
    public function createWorkflow(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO automation_workflows 
            (name, description, trigger_event, channel, template_id, gateway_id, delay_minutes, 
             schedule_type, schedule_offset, schedule_unit, schedule_reference,
             conditions, is_active, priority, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['trigger_event'],
            $data['channel'] ?? 'email',
            $data['template_id'],
            $data['gateway_id'] ?? null,
            $data['delay_minutes'] ?? 0,
            $data['schedule_type'] ?? 'immediate',
            $data['schedule_offset'] ?? 0,
            $data['schedule_unit'] ?? 'minutes',
            $data['schedule_reference'] ?? null,
            json_encode($data['conditions'] ?? []),
            $data['is_active'] ?? true,
            $data['priority'] ?? 0,
            $data['created_by'] ?? null
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update an existing workflow
     */
    public function updateWorkflow(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        $allowedFields = [
            'name',
            'description',
            'trigger_event',
            'channel',
            'template_id',
            'gateway_id',
            'delay_minutes',
            'is_active',
            'priority',
            'schedule_type',
            'schedule_offset',
            'schedule_unit',
            'schedule_reference'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (!empty($data['conditions'])) {
            $fields[] = "conditions = ?";
            $params[] = json_encode($data['conditions']);
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE automation_workflows SET " . implode(', ', $fields) . " WHERE id = ?";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete a workflow
     */
    public function deleteWorkflow(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM automation_workflows WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Toggle workflow active status
     */
    public function toggleWorkflow(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE automation_workflows SET is_active = NOT is_active WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // =========================================================================
    // LOGGING METHODS
    // =========================================================================

    /**
     * Log an automation execution
     */
    public function logExecution(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO automation_logs 
            (workflow_id, workflow_name, trigger_event, recipient_id, recipient_name, recipient_contact, channel, template_key, status, error_message, metadata)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['workflow_id'] ?? null,
            $data['workflow_name'] ?? null,
            $data['trigger_event'],
            $data['recipient_id'] ?? null,
            $data['recipient_name'] ?? null,
            $data['recipient_contact'] ?? null,
            $data['channel'],
            $data['template_key'] ?? null,
            $data['status'] ?? 'queued',
            $data['error_message'] ?? null,
            json_encode($data['metadata'] ?? [])
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Get automation logs with pagination
     */
    public function getLogs(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM automation_logs WHERE 1=1";
        $params = [];

        if (!empty($filters['trigger_event'])) {
            $sql .= " AND trigger_event = ?";
            $params[] = $filters['trigger_event'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['channel'])) {
            $sql .= " AND channel = ?";
            $params[] = $filters['channel'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND executed_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND executed_at <= ?";
            $params[] = $filters['date_to'];
        }

        $sql .= " ORDER BY executed_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get log statistics
     */
    public function getLogStats(int $days = 7): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued,
                SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped
            FROM automation_logs
            WHERE executed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // TEMPLATE RENDERING
    // =========================================================================

    /**
     * Render a template with data
     */
    public function renderTemplate(string $templateKey, array $data): array
    {
        $template = $this->getTemplateByKey($templateKey);

        if (!$template) {
            return ['success' => false, 'error' => 'Template not found'];
        }

        $subject = $template['subject'] ?? '';
        $bodyHtml = $template['body_html'] ?? '';
        $bodyText = $template['body_text'] ?? '';

        // Replace placeholders
        foreach ($data as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $placeholder = '{' . $key . '}';
                $subject = str_replace($placeholder, (string) $value, $subject);
                $bodyHtml = str_replace($placeholder, htmlspecialchars((string) $value), $bodyHtml);
                $bodyText = str_replace($placeholder, (string) $value, $bodyText);
            }
        }

        return [
            'success' => true,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'channel' => $template['channel']
        ];
    }

    // =========================================================================
    // MIGRATION HELPER
    // =========================================================================

    /**
     * Check if initial templates have been migrated
     */
    public function hasTemplates(): bool
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM automation_templates");
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Get log count for pagination
     */
    public function getLogCount(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM automation_logs WHERE 1=1";
        $params = [];

        if (!empty($filters['trigger_event'])) {
            $sql .= " AND trigger_event = ?";
            $params[] = $filters['trigger_event'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['channel'])) {
            $sql .= " AND channel = ?";
            $params[] = $filters['channel'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND executed_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND executed_at <= ?";
            $params[] = $filters['date_to'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get messaging gateways for dropdown
     */
    public function getMessagingGateways(): array
    {
        $stmt = $this->pdo->query("SELECT id, name, provider, type FROM messaging_gateways WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all available channels (Email + Active Gateway Types)
     */
    public function getAvailableChannels(): array
    {
        $channels = ['email']; // Email is always available

        $stmt = $this->pdo->query("SELECT DISTINCT type FROM messaging_gateways WHERE is_active = 1 ORDER BY type");
        $types = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($types as $type) {
            if (!in_array($type, $channels)) {
                $channels[] = strtolower($type);
            }
        }

        return $channels;
    }

    // =========================================================================
    // PHASE 2: SCHEDULING & LOGIC
    // =========================================================================

    /**
     * Evaluate workflow conditions against event data
     */
    public function evaluateConditions(?array $conditions, array $data): bool
    {
        // If no conditions, always pass
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? '=';
            $expected = $condition['value'] ?? null;

            // Get actual value from data (dot notation supported e.g. 'user.country')
            // For simple implementation, we assume top-level keys
            $actual = $data[$field] ?? null;

            $matched = false;

            switch ($operator) {
                case '=':
                    $matched = ($actual == $expected);
                    break;
                case '!=':
                    $matched = ($actual != $expected);
                    break;
                case '>':
                    $matched = ($actual > $expected);
                    break;
                case '<':
                    $matched = ($actual < $expected);
                    break;
                case 'IN':
                    if (is_array($expected)) {
                        $matched = in_array($actual, $expected);
                    } else {
                        // Handle comma-separated string if passed as such
                        $list = array_map('trim', explode(',', $expected));
                        $matched = in_array($actual, $list);
                    }
                    break;
                case 'CONTAINS':
                    $matched = (strpos((string) $actual, (string) $expected) !== false);
                    break;
                default:
                    // Unknown operator, fail safe
                    return false;
            }

            if (!$matched) {
                return false; // AND logic: all must pass
            }
        }

        return true;
    }

    /**
     * Trigger an event and process matching workflows
     * 
     * @param string $event The trigger event name (e.g., 'user_created')
     * @param array $data Data associated with the event (e.g., user details)
     * @return array Result summary ['matched' => int, 'queued' => int]
     */
    public function triggerEvent(string $event, array $data): array
    {
        $workflows = $this->getWorkflowsForTrigger($event);
        $results = ['matched' => 0, 'queued' => 0];

        foreach ($workflows as $workflow) {
            $conditions = !empty($workflow['conditions']) ? json_decode($workflow['conditions'], true) : [];

            if ($this->evaluateConditions($conditions, $data)) {
                $results['matched']++;

                // Calculate schedule time
                $scheduledAt = date('Y-m-d H:i:s');
                if ($workflow['schedule_type'] === 'delay' && $workflow['delay_minutes'] > 0) {
                    $scheduledAt = date('Y-m-d H:i:s', strtotime("+{$workflow['delay_minutes']} minutes"));
                } elseif ($workflow['schedule_type'] === 'distinct_time' && !empty($workflow['schedule_reference'])) {
                    // Logic for distinct scheduled time (e.g. 2 days before Due Date)
                    $refDate = $data[$workflow['schedule_reference']] ?? null;
                    if ($refDate) {
                        $offset = (int) $workflow['schedule_offset']; // Can be negative
                        $unit = $workflow['schedule_unit']; // minutes, hours, days

                        // Calculate scheduled time
                        $scheduledAt = date('Y-m-d H:i:s', strtotime("{$offset} {$unit}", strtotime($refDate)));
                    }
                }

                $this->addToQueue(
                    (int) $workflow['id'],
                    $data['email'] ?? '',
                    $data['phone'] ?? '',
                    $data,
                    $scheduledAt
                );
                $results['queued']++;
            }
        }

        return $results;
    }

    /**
     * Add message to scheduling queue
     */
    public function addToQueue(int $workflowId, string $email, string $phone, array $data, string $scheduleTime): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO automation_queue
            (workflow_id, recipient_email, recipient_phone, serialized_data, scheduled_at, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");

        $stmt->execute([
            $workflowId,
            $email,
            $phone,
            json_encode($data),
            $scheduleTime
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Process the automation queue (to be called via Cron)
     */
    public function processQueue(): array
    {
        $results = ['processed' => 0, 'sent' => 0, 'failed' => 0];

        // Fetch pending items that are due
        $stmt = $this->pdo->prepare("
            SELECT q.*, w.channel, w.template_id, w.trigger_event, w.gateway_id
            FROM automation_queue q
            JOIN automation_workflows w ON q.workflow_id = w.id
            WHERE q.status = 'pending' AND q.scheduled_at <= NOW()
            LIMIT 50
        ");
        $stmt->execute();
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            $results['processed']++;

            // Mark as processing
            $this->pdo->prepare("UPDATE automation_queue SET status = 'processing' WHERE id = ?")
                ->execute([(int) $item['id']]);

            try {
                // Get Template
                $template = $this->getTemplate((int) $item['template_id']);
                if (!$template) {
                    throw new \Exception("Template ID {$item['template_id']} not found");
                }

                $data = json_decode($item['serialized_data'], true);

                // Render
                $rendered = $this->renderTemplate($template['template_key'], $data);

                if (!$rendered['success']) {
                    throw new \Exception("Rendering failed: " . ($rendered['error'] ?? 'Unknown error'));
                }

                $sent = false;

                // Send
                if ($item['channel'] === 'email') {
                    // Assuming EmailNotificationService is available via global or DI
                    // For this service context, we assume the caller handles dependencies, 
                    // or we use a basic mailer if not injected. 
                    // To keep it robust, we should instantiate services if possible.
                    if (class_exists('EmailNotificationService')) {
                        $emailService = new \EduCRM\Services\EmailNotificationService($this->pdo);
                        $sent = $emailService->sendEmail(
                            $item['recipient_email'],
                            $rendered['subject'],
                            $rendered['body_html']
                        );
                    }
                } elseif ($item['channel'] === 'sms' || $item['channel'] === 'whatsapp' || $item['channel'] === 'viber') {
                    if (class_exists('MessagingService')) {
                        $messagingService = new \EduCRM\Services\MessagingService($this->pdo);
                        $sent = $messagingService->send(
                            $item['recipient_phone'],
                            $rendered['body_text'], // SMS uses text body
                            $item['channel'],
                            $item['gateway_id']
                        );
                    }
                }

                if ($sent) {
                    $this->updateQueueStatus((int) $item['id'], 'sent');

                    // Also log to main automation_logs history
                    $this->logExecution([
                        'workflow_id' => (int) $item['workflow_id'],
                        'trigger_event' => $item['trigger_event'],
                        'recipient_name' => $data['name'] ?? '',
                        'channel' => $item['channel'],
                        'status' => 'sent',
                        'template_key' => $template['template_key'],
                        'metadata' => ['queue_id' => (int) $item['id']]
                    ]);

                    $results['sent']++;
                } else {
                    throw new \Exception("Sending failed via provider");
                }

            } catch (\Exception $e) {
                $this->updateQueueStatus((int) $item['id'], 'failed', $e->getMessage());
                $results['failed']++;
            }
        }

        return $results;
    }

    private function updateQueueStatus(int $id, string $status, ?string $error = null): void
    {
        $sql = "UPDATE automation_queue SET status = ?, updated_at = NOW()";
        $params = [$status];

        if ($error) {
            $sql .= ", error_message = ?";
            $params[] = $error;
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $this->pdo->prepare($sql)->execute($params);
    }
}
