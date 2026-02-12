<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
 * Activity Service
 * Tracks and reports user activity throughout the system
 */
class ActivityService
{
    private \PDO $pdo;
    private ?int $branchId;

    // Action categories for reporting
    const ACTIONS = [
        'login' => 'User Login',
        'logout' => 'User Logout',
        'inquiry_add' => 'Added Inquiry',
        'inquiry_edit' => 'Edited Inquiry',
        'inquiry_convert' => 'Converted Inquiry',
        'inquiry_delete' => 'Deleted Inquiry',
        'student_add' => 'Added Student',
        'student_edit' => 'Edited Student',
        'task_add' => 'Created Task',
        'task_complete' => 'Completed Task',
        'task_edit' => 'Edited Task',
        'appointment_add' => 'Created Appointment',
        'appointment_edit' => 'Edited Appointment',
        'class_add' => 'Created Class',
        'enrollment_add' => 'Enrolled Student',
    ];

    public function __construct(\PDO $pdo, ?int $branchId = null)
    {
        $this->pdo = $pdo;
        $this->branchId = $branchId;
    }

    public function getActionLabel(string $action): string
    {
        return self::ACTIONS[$action] ?? ucwords(str_replace('_', ' ', $action));
    }

    /**
     * Store pre-computed daily summary (for cron job)
     */
    public function storeDailySummary(int $userId, string $date): bool
    {
        $summary = $this->calculateDailySummary($userId, $date);

        $stmt = $this->pdo->prepare("
            INSERT INTO activity_daily_summary 
            (user_id, summary_date, total_actions, inquiries_added, inquiries_converted, 
             students_added, tasks_completed, appointments_created, logins)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            total_actions = VALUES(total_actions),
            inquiries_added = VALUES(inquiries_added),
            inquiries_converted = VALUES(inquiries_converted),
            students_added = VALUES(students_added),
            tasks_completed = VALUES(tasks_completed),
            appointments_created = VALUES(appointments_created),
            logins = VALUES(logins)
        ");

        return $stmt->execute([
            $summary['user_id'],
            $summary['summary_date'],
            $summary['total_actions'],
            $summary['inquiries_added'],
            $summary['inquiries_converted'],
            $summary['students_added'],
            $summary['tasks_completed'],
            $summary['appointments_created'],
            $summary['logins']
        ]);
    }

    private function calculateDailySummary(int $userId, string $date): array
    {
        // Calculate stats from system_logs for a specific user and date
        $start = "$date 00:00:00";
        $end = "$date 23:59:59";

        $sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN action = 'inquiry_add' THEN 1 ELSE 0 END) as inquiries_added,
            SUM(CASE WHEN action = 'inquiry_convert' THEN 1 ELSE 0 END) as inquiries_converted,
            SUM(CASE WHEN action = 'student_add' THEN 1 ELSE 0 END) as students_added,
            SUM(CASE WHEN action = 'task_complete' THEN 1 ELSE 0 END) as tasks_completed,
            SUM(CASE WHEN action = 'appointment_add' THEN 1 ELSE 0 END) as appointments_created,
            SUM(CASE WHEN action = 'login' THEN 1 ELSE 0 END) as logins
            FROM system_logs 
            WHERE user_id = ? AND created_at BETWEEN ? AND ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $start, $end]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'user_id' => $userId,
            'summary_date' => $date,
            'total_actions' => (int) ($stats['total'] ?? 0),
            'inquiries_added' => (int) ($stats['inquiries_added'] ?? 0),
            'inquiries_converted' => (int) ($stats['inquiries_converted'] ?? 0),
            'students_added' => (int) ($stats['students_added'] ?? 0),
            'tasks_completed' => (int) ($stats['tasks_completed'] ?? 0),
            'appointments_created' => (int) ($stats['appointments_created'] ?? 0),
            'logins' => (int) ($stats['logins'] ?? 0)
        ];
    }

    public function getAllUsersSummary(string $date): array
    {
        $sql = "
            SELECT u.id, u.name, ads.* 
            FROM activity_daily_summary ads 
            JOIN users u ON ads.user_id = u.id 
            WHERE ads.summary_date = ?
        ";
        $params = [$date];

        if ($this->branchId) {
            $sql .= " AND u.branch_id = ?";
            $params[] = $this->branchId;
        }

        $sql .= " ORDER BY ads.total_actions DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getActivityStats(string $startDate, string $endDate): array
    {
        $sql = "
            SELECT 
                SUM(total_actions) as total_actions,
                SUM(inquiries_added) as inquiries_added,
                SUM(inquiries_converted) as inquiries_converted,
                SUM(students_added) as students_added,
                SUM(tasks_completed) as tasks_completed,
                SUM(appointments_created) as appointments_created,
                SUM(logins) as logins
            FROM activity_daily_summary ads
        ";
        $params = [];
        $where = " WHERE ads.summary_date BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;

        if ($this->branchId) {
            $sql .= " JOIN users u ON ads.user_id = u.id";
            $where .= " AND u.branch_id = ?";
            $params[] = $this->branchId;
        }

        $sql .= $where; // Append WHERE clause after potential JOIN

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'total_actions' => 0,
            'inquiries_added' => 0,
            'inquiries_converted' => 0,
            'students_added' => 0,
            'tasks_completed' => 0,
            'appointments_created' => 0,
            'logins' => 0
        ];
    }

    public function getMostActiveUsers(string $startDate, string $endDate, int $limit = 5): array
    {
        $sql = "
            SELECT u.id, u.name, SUM(ads.total_actions) as total 
            FROM activity_daily_summary ads 
            JOIN users u ON ads.user_id = u.id 
            WHERE ads.summary_date BETWEEN ? AND ?
        ";
        $params = [$startDate, $endDate];

        if ($this->branchId) {
            $sql .= " AND u.branch_id = ?";
            $params[] = $this->branchId;
        }

        $sql .= " GROUP BY u.id ORDER BY total DESC LIMIT " . (int) $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getAllActivity(string $startDate, string $endDate, int $limit = 100): array
    {
        $sql = "
            SELECT sl.*, u.name as user_name 
            FROM system_logs sl 
            JOIN users u ON sl.user_id = u.id 
            WHERE sl.created_at BETWEEN ? AND ?
        ";
        // Timestamps in DB, but inputs are dates. Adjust end date.
        $start = "$startDate 00:00:00";
        $end = "$endDate 23:59:59";
        $params = [$start, $end];

        if ($this->branchId) {
            $sql .= " AND u.branch_id = ?";
            $params[] = $this->branchId;
        }

        $sql .= " ORDER BY sl.created_at DESC LIMIT " . (int) $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getUserActivity(int $userId, string $startDate, string $endDate, int $limit = 100): array
    {
        $sql = "
            SELECT sl.*, u.name as user_name 
            FROM system_logs sl 
            JOIN users u ON sl.user_id = u.id 
            WHERE sl.user_id = ? AND sl.created_at BETWEEN ? AND ?
        ";
        $start = "$startDate 00:00:00";
        $end = "$endDate 23:59:59";
        $params = [$userId, $start, $end];

        if ($this->branchId) {
            // Ensure the viewed user belongs to the branch (or at least filter logs by branch user - effectively same)
            $sql .= " AND u.branch_id = ?";
            $params[] = $this->branchId;
        }

        $sql .= " ORDER BY sl.created_at DESC LIMIT " . (int) $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
