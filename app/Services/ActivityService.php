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
        'payment_add' => 'Recorded Payment',
        'document_upload' => 'Uploaded Document',
        'message_sent' => 'Sent Message',
        'report_view' => 'Viewed Report'
    ];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Log an activity
     */
    public function logActivity(int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_activity (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            return $stmt->execute([
                $userId,
                $action,
                $entityType,
                $entityId,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255)
            ]);
        } catch (\PDOException $e) {
            // Fail silently - don't break the app if logging fails
            error_log("Activity logging failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user activity for a date range
     */
    public function getUserActivity(int $userId, string $startDate, string $endDate, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ua.*, u.name as user_name
            FROM user_activity ua
            LEFT JOIN users u ON ua.user_id = u.id
            WHERE ua.user_id = ?
            AND DATE(ua.created_at) BETWEEN ? AND ?
            ORDER BY ua.created_at DESC
            LIMIT " . (int)$limit . "
        ");
        $stmt->execute([$userId, $startDate, $endDate]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all activity for a date range (admin view)
     */
    public function getAllActivity(string $startDate, string $endDate, int $limit = 200): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ua.*, u.name as user_name
            FROM user_activity ua
            LEFT JOIN users u ON ua.user_id = u.id
            WHERE DATE(ua.created_at) BETWEEN ? AND ?
            ORDER BY ua.created_at DESC
            LIMIT " . (int)$limit . "
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get daily summary for a user
     */
    public function getUserDailySummary(int $userId, string $date): array
    {
        // First try to get pre-computed summary
        $stmt = $this->pdo->prepare("
            SELECT * FROM activity_daily_summary 
            WHERE user_id = ? AND summary_date = ?
        ");
        $stmt->execute([$userId, $date]);
        $summary = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($summary) {
            return $summary;
        }

        // Calculate on the fly
        return $this->calculateDailySummary($userId, $date);
    }

    /**
     * Calculate daily summary from activity logs
     */
    public function calculateDailySummary(int $userId, string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                action,
                COUNT(*) as count
            FROM user_activity
            WHERE user_id = ? AND DATE(created_at) = ?
            GROUP BY action
        ");
        $stmt->execute([$userId, $date]);
        $actions = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        return [
            'user_id' => $userId,
            'summary_date' => $date,
            'total_actions' => array_sum($actions),
            'inquiries_added' => $actions['inquiry_add'] ?? 0,
            'inquiries_converted' => $actions['inquiry_convert'] ?? 0,
            'students_added' => $actions['student_add'] ?? 0,
            'tasks_completed' => $actions['task_complete'] ?? 0,
            'appointments_created' => $actions['appointment_add'] ?? 0,
            'logins' => $actions['login'] ?? 0
        ];
    }

    /**
     * Get all users' summary for a date (admin dashboard)
     */
    public function getAllUsersSummary(string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                ua.user_id,
                u.name as user_name,
                COUNT(*) as total_actions,
                SUM(CASE WHEN ua.action = 'inquiry_add' THEN 1 ELSE 0 END) as inquiries_added,
                SUM(CASE WHEN ua.action = 'inquiry_convert' THEN 1 ELSE 0 END) as inquiries_converted,
                SUM(CASE WHEN ua.action = 'student_add' THEN 1 ELSE 0 END) as students_added,
                SUM(CASE WHEN ua.action = 'task_complete' THEN 1 ELSE 0 END) as tasks_completed,
                SUM(CASE WHEN ua.action = 'appointment_add' THEN 1 ELSE 0 END) as appointments_created,
                SUM(CASE WHEN ua.action = 'login' THEN 1 ELSE 0 END) as logins,
                MIN(ua.created_at) as first_activity,
                MAX(ua.created_at) as last_activity
            FROM user_activity ua
            LEFT JOIN users u ON ua.user_id = u.id
            WHERE DATE(ua.created_at) = ?
            GROUP BY ua.user_id, u.name
            ORDER BY total_actions DESC
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get activity statistics for date range
     */
    public function getActivityStats(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                action,
                COUNT(*) as count
            FROM user_activity
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY action
            ORDER BY count DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get most active users
     */
    public function getMostActiveUsers(string $startDate, string $endDate, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                ua.user_id,
                u.name as user_name,
                COUNT(*) as total_actions
            FROM user_activity ua
            LEFT JOIN users u ON ua.user_id = u.id
            WHERE DATE(ua.created_at) BETWEEN ? AND ?
            GROUP BY ua.user_id, u.name
            ORDER BY total_actions DESC
            LIMIT " . (int)$limit . "
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get action label
     */
    public static function getActionLabel(string $action): string
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
}
