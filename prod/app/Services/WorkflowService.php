<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
 * Workflow Service
 * Manages workflow templates and student progress tracking
 */

class WorkflowService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all active workflow templates
     */
    public function getActiveTemplates($category = null)
    {
        $sql = "SELECT * FROM workflow_templates WHERE is_active = TRUE";
        $params = [];

        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }

        $sql .= " ORDER BY country, name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get workflow template by ID with steps
     */
    public function getTemplateWithSteps($templateId)
    {
        // Get template
        $stmt = $this->pdo->prepare("SELECT * FROM workflow_templates WHERE id = ?");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$template) {
            return null;
        }

        // Get steps
        $stmt = $this->pdo->prepare("
            SELECT * FROM workflow_steps 
            WHERE template_id = ? 
            ORDER BY step_order
        ");
        $stmt->execute([$templateId]);
        $template['steps'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $template;
    }

    /**
     * Assign workflow to student
     * Also creates/updates the simple visa_workflows entry for unification (Gap #2)
     */
    public function assignWorkflow($studentId, $templateId, $assignedTo = null)
    {
        // Check if already assigned
        $stmt = $this->pdo->prepare("
            SELECT id FROM student_workflow_progress 
            WHERE student_id = ? AND template_id = ? 
            AND status NOT IN ('completed', 'cancelled')
        ");
        $stmt->execute([$studentId, $templateId]);

        if ($stmt->fetch()) {
            throw new \Exception("Student already has this workflow assigned");
        }

        // Create progress record
        $stmt = $this->pdo->prepare("
            INSERT INTO student_workflow_progress 
            (student_id, template_id, status, assigned_to, started_at)
            VALUES (?, ?, 'in_progress', ?, NOW())
        ");
        $stmt->execute([$studentId, $templateId, $assignedTo]);

        $progressId = $this->pdo->lastInsertId();

        // Get template info for visa_workflows sync
        $tplStmt = $this->pdo->prepare("SELECT country_id, name FROM workflow_templates WHERE id = ?");
        $tplStmt->execute([$templateId]);
        $template = $tplStmt->fetch(\PDO::FETCH_ASSOC);

        // Gap #2: Auto-create or link visa_workflows entry
        if ($template) {
            $docCollectionStageId = $this->pdo->query("SELECT id FROM visa_stages WHERE name = 'Doc Collection' LIMIT 1")->fetchColumn();

            // Check if simple workflow exists
            $vwStmt = $this->pdo->prepare("SELECT id FROM visa_workflows WHERE student_id = ?");
            $vwStmt->execute([$studentId]);
            $existingVw = $vwStmt->fetch();

            if ($existingVw) {
                // Link existing workflow
                $linkStmt = $this->pdo->prepare("UPDATE visa_workflows SET workflow_progress_id = ? WHERE id = ?");
                $linkStmt->execute([$progressId, $existingVw['id']]);
            } else {
                // Get student branch
                $branchStmt = $this->pdo->prepare("SELECT branch_id FROM users WHERE id = ?");
                $branchStmt->execute([$studentId]);
                $branchId = $branchStmt->fetchColumn();

                // Create new simple workflow entry linked to template progress
                $createStmt = $this->pdo->prepare("
                    INSERT INTO visa_workflows 
                    (student_id, country_id, stage_id, workflow_progress_id, notes, stage_started_at, expected_completion_date, priority, branch_id)
                    VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'normal', ?)
                ");
                $createStmt->execute([
                    $studentId,
                    $template['country_id'],
                    $docCollectionStageId,
                    $progressId,
                    "Template workflow assigned: " . $template['name'],
                    $branchId
                ]);
            }
        }

        // Get first step
        $stmt = $this->pdo->prepare("
            SELECT id FROM workflow_steps 
            WHERE template_id = ? 
            ORDER BY step_order 
            LIMIT 1
        ");
        $stmt->execute([$templateId]);
        $firstStep = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($firstStep) {
            // Update current step
            $stmt = $this->pdo->prepare("
                UPDATE student_workflow_progress 
                SET current_step_id = ? 
                WHERE id = ?
            ");
            $stmt->execute([$firstStep['id'], $progressId]);

            // Auto-create task if configured
            $this->autoCreateTaskForStep($progressId, $firstStep['id']);
        }

        return $progressId;
    }

    /**
     * Complete current step and move to next
     */
    public function completeStep($progressId, $stepId, $completedBy, $notes = null, $documents = null)
    {
        // Record step completion
        $stmt = $this->pdo->prepare("
            INSERT INTO workflow_step_completions 
            (progress_id, step_id, completed_at, completed_by, notes, documents_uploaded)
            VALUES (?, ?, NOW(), ?, ?, ?)
        ");
        $stmt->execute([
            $progressId,
            $stepId,
            $completedBy,
            $notes,
            $documents ? json_encode($documents) : null
        ]);

        // Get next step
        $stmt = $this->pdo->prepare("
            SELECT ws.* 
            FROM workflow_steps ws
            JOIN workflow_steps current ON ws.template_id = current.template_id
            WHERE current.id = ? 
            AND ws.step_order > current.step_order
            ORDER BY ws.step_order
            LIMIT 1
        ");
        $stmt->execute([$stepId]);
        $nextStep = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($nextStep) {
            // Move to next step
            $stmt = $this->pdo->prepare("
                UPDATE student_workflow_progress 
                SET current_step_id = ? 
                WHERE id = ?
            ");
            $stmt->execute([$nextStep['id'], $progressId]);

            // Auto-create task for next step
            $this->autoCreateTaskForStep($progressId, $nextStep['id']);

            return ['completed' => false, 'next_step' => $nextStep];
        } else {
            // Workflow complete
            $stmt = $this->pdo->prepare("
                UPDATE student_workflow_progress 
                SET status = 'completed', completed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$progressId]);

            return ['completed' => true, 'next_step' => null];
        }
    }

    /**
     * Get student workflow progress
     */
    public function getStudentProgress($studentId, $templateId = null)
    {
        $sql = "
            SELECT 
                swp.*,
                wt.name as template_name,
                wt.category,
                wt.country,
                ws.step_name as current_step_name,
                ws.step_order as current_step_order,
                u.name as assigned_to_name
            FROM student_workflow_progress swp
            JOIN workflow_templates wt ON swp.template_id = wt.id
            LEFT JOIN workflow_steps ws ON swp.current_step_id = ws.id
            LEFT JOIN users u ON swp.assigned_to = u.id
            WHERE swp.student_id = ?
        ";
        $params = [$studentId];

        if ($templateId) {
            $sql .= " AND swp.template_id = ?";
            $params[] = $templateId;
        }

        $sql .= " ORDER BY swp.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get workflow progress details with all steps
     */
    public function getProgressDetails($progressId)
    {
        // Get progress record
        $stmt = $this->pdo->prepare("
            SELECT 
                swp.*,
                wt.name as template_name,
                wt.description as template_description,
                u.name as student_name,
                c.name as assigned_to_name
            FROM student_workflow_progress swp
            JOIN workflow_templates wt ON swp.template_id = wt.id
            JOIN users u ON swp.student_id = u.id
            LEFT JOIN users c ON swp.assigned_to = c.id
            WHERE swp.id = ?
        ");
        $stmt->execute([$progressId]);
        $progress = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$progress) {
            return null;
        }

        // Get all steps with completion status
        $stmt = $this->pdo->prepare("
            SELECT 
                ws.*,
                wsc.completed_at,
                wsc.completed_by,
                wsc.notes as completion_notes,
                u.name as completed_by_name
            FROM workflow_steps ws
            LEFT JOIN workflow_step_completions wsc ON ws.id = wsc.step_id AND wsc.progress_id = ?
            LEFT JOIN users u ON wsc.completed_by = u.id
            WHERE ws.template_id = ?
            ORDER BY ws.step_order
        ");
        $stmt->execute([$progressId, $progress['template_id']]);
        $progress['steps'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $progress;
    }

    /**
     * Auto-create task for workflow step
     */
    private function autoCreateTaskForStep($progressId, $stepId)
    {
        // Get step details
        $stmt = $this->pdo->prepare("SELECT * FROM workflow_steps WHERE id = ?");
        $stmt->execute([$stepId]);
        $step = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$step || !$step['auto_create_task']) {
            return false;
        }

        // Get progress details
        $stmt = $this->pdo->prepare("
            SELECT student_id, assigned_to 
            FROM student_workflow_progress 
            WHERE id = ?
        ");
        $stmt->execute([$progressId]);
        $progress = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$progress) {
            return false;
        }

        // Create task
        $stmt = $this->pdo->prepare("
            INSERT INTO tasks 
            (title, description, assigned_to, created_by, related_entity_type, related_entity_id, priority, status, due_date)
            VALUES (?, ?, ?, ?, 'workflow', ?, 'high', 'pending', DATE_ADD(NOW(), INTERVAL ? DAY))
        ");

        $title = $step['task_title'] ?: $step['step_name'];
        $description = $step['task_description'] ?: $step['description'];
        $assignedTo = $progress['assigned_to'] ?: 1; // Default to admin

        $stmt->execute([
            $title,
            $description,
            $assignedTo,
            $assignedTo,
            $progressId,
            $step['estimated_days']
        ]);

        return true;
    }

    /**
     * Get workflow analytics
     */
    public function getAnalytics()
    {
        // Total workflows
        $total = $this->pdo->query("SELECT COUNT(*) FROM student_workflow_progress")->fetchColumn();

        // By status
        $stmt = $this->pdo->query("
            SELECT status, COUNT(*) as count 
            FROM student_workflow_progress 
            GROUP BY status
        ");
        $byStatus = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        // By template
        $stmt = $this->pdo->query("
            SELECT wt.name, COUNT(*) as count
            FROM student_workflow_progress swp
            JOIN workflow_templates wt ON swp.template_id = wt.id
            GROUP BY wt.id, wt.name
            ORDER BY count DESC
        ");
        $byTemplate = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Average completion time
        $stmt = $this->pdo->query("
            SELECT AVG(DATEDIFF(completed_at, started_at)) as avg_days
            FROM student_workflow_progress
            WHERE status = 'completed'
        ");
        $avgDays = $stmt->fetchColumn();

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'by_template' => $byTemplate,
            'avg_completion_days' => round($avgDays ?? 0, 1)
        ];
    }
}
