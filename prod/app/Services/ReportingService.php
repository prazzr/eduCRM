<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
 * Reporting Service
 * Provides analytics and reporting functionality
 */

class ReportingService
{
    private \PDO $pdo;
    private $branchId;

    public function __construct(\PDO $pdo, $branchId = null)
    {
        $this->pdo = $pdo;
        $this->branchId = $branchId;
    }

    /**
     * Get task completion rate and statistics
     */
    public function getTaskCompletionRate($startDate, $endDate)
    {
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN due_date < NOW() AND status != 'completed' THEN 1 ELSE 0 END) as overdue
            FROM tasks
            WHERE created_at BETWEEN ? AND ?
        ";

        $params = [$startDate, $endDate];
        if ($this->branchId) {
            $sql .= " AND branch_id = ?";
            $params[] = $this->branchId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

        $completionRate = $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100, 1) : 0;

        // Get daily completion trend
        $trendSql = "
            SELECT 
                DATE(completed_at) as date,
                COUNT(*) as count
            FROM tasks
            WHERE completed_at BETWEEN ? AND ?
        ";

        $trendParams = [$startDate, $endDate];
        if ($this->branchId) {
            $trendSql .= " AND branch_id = ?";
            $trendParams[] = $this->branchId;
        }

        $trendSql .= " GROUP BY DATE(completed_at) ORDER BY date ASC";

        $trendStmt = $this->pdo->prepare($trendSql);
        $trendStmt->execute($trendParams);
        $trend = $trendStmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'total' => (int) $stats['total'],
            'completed' => (int) $stats['completed'],
            'pending' => (int) $stats['pending'],
            'in_progress' => (int) $stats['in_progress'],
            'overdue' => (int) $stats['overdue'],
            'completion_rate' => $completionRate,
            'trend_data' => $trend
        ];
    }

    /**
     * Get appointment metrics
     */
    public function getAppointmentMetrics($startDate, $endDate)
    {
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show
            FROM appointments
            WHERE appointment_date BETWEEN ? AND ?
        ";

        $params = [$startDate, $endDate];
        if ($this->branchId) {
            $sql .= " AND branch_id = ?";
            $params[] = $this->branchId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

        $attendanceRate = $stats['scheduled'] > 0 ? round(($stats['completed'] / ($stats['scheduled'] + $stats['completed'])) * 100, 1) : 0;
        $noShowRate = $stats['total'] > 0 ? round(($stats['no_show'] / $stats['total']) * 100, 1) : 0;

        // Get daily appointment trend
        $trendSql = "
            SELECT 
                DATE(appointment_date) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM appointments
            WHERE appointment_date BETWEEN ? AND ?
        ";

        $trendParams = [$startDate, $endDate];
        if ($this->branchId) {
            $trendSql .= " AND branch_id = ?";
            $trendParams[] = $this->branchId;
        }

        $trendSql .= " GROUP BY DATE(appointment_date) ORDER BY date ASC";

        $trendStmt = $this->pdo->prepare($trendSql);
        $trendStmt->execute($trendParams);
        $trend = $trendStmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'total' => (int) $stats['total'],
            'scheduled' => (int) $stats['scheduled'],
            'completed' => (int) $stats['completed'],
            'cancelled' => (int) $stats['cancelled'],
            'no_show' => (int) $stats['no_show'],
            'attendance_rate' => $attendanceRate,
            'no_show_rate' => $noShowRate,
            'trend_data' => $trend
        ];
    }

    /**
     * Get lead conversion funnel
     */
    public function getLeadConversionFunnel($startDate, $endDate)
    {
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new,
                SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) as contacted,
                SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
            FROM inquiries
            WHERE created_at BETWEEN ? AND ?
        ";

        $params = [$startDate, $endDate];
        if ($this->branchId) {
            $sql .= " AND branch_id = ?";
            $params[] = $this->branchId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

        $conversionRate = $stats['total'] > 0 ? round(($stats['converted'] / $stats['total']) * 100, 1) : 0;
        $contactRate = $stats['total'] > 0 ? round((($stats['contacted'] + $stats['converted']) / $stats['total']) * 100, 1) : 0;

        return [
            'total' => (int) $stats['total'],
            'new' => (int) $stats['new'],
            'contacted' => (int) $stats['contacted'],
            'converted' => (int) $stats['converted'],
            'closed' => (int) $stats['closed'],
            'conversion_rate' => $conversionRate,
            'contact_rate' => $contactRate
        ];
    }

    /**
     * Get priority distribution
     */
    public function getPriorityDistribution($startDate, $endDate)
    {
        $sql = "
            SELECT 
                priority,
                COUNT(*) as count,
                AVG(score) as avg_score
            FROM inquiries
            WHERE created_at BETWEEN ? AND ?
        ";

        $params = [$startDate, $endDate];
        if ($this->branchId) {
            $sql .= " AND branch_id = ?";
            $params[] = $this->branchId;
        }

        $sql .= " GROUP BY priority";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get counselor performance metrics
     */
    /**
     * Get counselor performance metrics
     */
    public function getCounselorPerformance($startDate, $endDate)
    {
        $sql = "
            SELECT 
                u.id,
                u.name,
                COUNT(DISTINCT t.id) as total_tasks,
                SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                COUNT(DISTINCT a.id) as total_appointments,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
                COUNT(DISTINCT i.id) as total_inquiries,
                SUM(CASE WHEN i.status = 'converted' THEN 1 ELSE 0 END) as converted_inquiries,
                AVG(i.score) as avg_lead_score
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            LEFT JOIN tasks t ON u.id = t.assigned_to AND t.created_at BETWEEN ? AND ?
            LEFT JOIN appointments a ON u.id = a.counselor_id AND a.appointment_date BETWEEN ? AND ?
            LEFT JOIN inquiries i ON u.id = i.assigned_to AND i.created_at BETWEEN ? AND ?
            WHERE r.name IN ('admin', 'counselor')
        ";

        $params = [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate];

        if ($this->branchId) {
            $sql .= " AND u.branch_id = ?";
            $params[] = $this->branchId;
        }

        $sql .= " GROUP BY u.id, u.name ORDER BY converted_inquiries DESC, completed_tasks DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $counselors = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Calculate rates
        foreach ($counselors as &$counselor) {
            $counselor['task_completion_rate'] = $counselor['total_tasks'] > 0
                ? round(($counselor['completed_tasks'] / $counselor['total_tasks']) * 100, 1)
                : 0;

            $counselor['appointment_completion_rate'] = $counselor['total_appointments'] > 0
                ? round(($counselor['completed_appointments'] / $counselor['total_appointments']) * 100, 1)
                : 0;

            $counselor['conversion_rate'] = $counselor['total_inquiries'] > 0
                ? round(($counselor['converted_inquiries'] / $counselor['total_inquiries']) * 100, 1)
                : 0;

            $counselor['avg_lead_score'] = round((float) ($counselor['avg_lead_score'] ?? 0), 1);
        }

        return $counselors;
    }

    /**
     * Get overdue task trends
     */
    public function getOverdueTaskTrends($startDate, $endDate)
    {
        $sql = "
            SELECT 
                DATE(due_date) as date,
                COUNT(*) as count
            FROM tasks
            WHERE due_date BETWEEN ? AND ?
            AND due_date < NOW()
            AND status != 'completed'
        ";

        $params = [$startDate, $endDate];
        if ($this->branchId) {
            $sql .= " AND branch_id = ?";
            $params[] = $this->branchId;
        }

        $sql .= " GROUP BY DATE(due_date) ORDER BY date ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get status distribution for inquiries
     */
    public function getStatusDistribution($startDate, $endDate)
    {
        $sql = "
            SELECT 
                status,
                COUNT(*) as count
            FROM inquiries
            WHERE created_at BETWEEN ? AND ?
        ";

        $params = [$startDate, $endDate];
        if ($this->branchId) {
            $sql .= " AND branch_id = ?";
            $params[] = $this->branchId;
        }

        $sql .= " GROUP BY status";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get average task completion time
     */
    public function getAverageCompletionTime($startDate, $endDate)
    {
        $sql = "
            SELECT 
                AVG(DATEDIFF(completed_at, created_at)) as avg_days
            FROM tasks
            WHERE completed_at BETWEEN ? AND ?
            AND status = 'completed'
        ";

        $params = [$startDate, $endDate];
        if ($this->branchId) {
            $sql .= " AND branch_id = ?";
            $params[] = $this->branchId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return round($result['avg_days'] ?? 0, 1);
    }

    /**
     * Get top performing counselors
     */
    public function getTopPerformers($startDate, $endDate, $limit = 5)
    {
        $counselors = $this->getCounselorPerformance($startDate, $endDate);

        // Sort by conversion rate and task completion
        usort($counselors, function ($a, $b) {
            if ($a['conversion_rate'] == $b['conversion_rate']) {
                return $b['task_completion_rate'] - $a['task_completion_rate'];
            }
            return $b['conversion_rate'] - $a['conversion_rate'];
        });

        return array_slice($counselors, 0, $limit);
    }
    /**
     * Get student enrollment statistics
     */
    public function getStudentEnrollmentStats($startDate, $endDate)
    {
        $sql = "
            SELECT 
                COUNT(*) as total_students
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE r.name = 'student'
            AND u.created_at BETWEEN ? AND ?
        ";

        $params = [$startDate, $endDate];
        if ($this->branchId) {
            $sql .= " AND u.branch_id = ?";
            $params[] = $this->branchId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'new_students' => (int) $stats['total_students']
        ];
    }
}
