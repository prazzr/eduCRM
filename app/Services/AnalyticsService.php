<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
 * Analytics Service
 * Handles advanced analytics, metrics, and business intelligence
 */

class AnalyticsService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get conversion funnel data
     * Tracks: Inquiries → Follow-ups → Applications → Enrollments
     */
    public function getConversionFunnel($startDate = null, $endDate = null)
    {
        $startDate = $startDate ?? date('Y-m-01'); // First day of month
        $endDate = $endDate ?? date('Y-m-d'); // Today

        // Get inquiry counts
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM inquiries WHERE created_at BETWEEN ? AND ?");
        $stmt->execute([$startDate, $endDate]);
        $totalInquiries = (int) $stmt->fetchColumn();

        // Get follow-ups (contacted, qualified, converted inquiries)
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM inquiries WHERE status IN ('contacted', 'qualified', 'converted') AND created_at BETWEEN ? AND ?");
        $stmt->execute([$startDate, $endDate]);
        $followups = (int) $stmt->fetchColumn();

        // Get visa applications (workflows created)
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM visa_workflows WHERE created_at BETWEEN ? AND ?");
        $stmt->execute([$startDate, $endDate]);
        $totalApplications = (int) $stmt->fetchColumn();

        // Get enrollments
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE enrolled_at BETWEEN ? AND ?");
        $stmt->execute([$startDate, $endDate]);
        $totalEnrollments = (int) $stmt->fetchColumn();

        $data = [
            'total_inquiries' => $totalInquiries,
            'followups' => $followups,
            'total_applications' => $totalApplications,
            'total_enrollments' => $totalEnrollments,
            'followup_rate' => $totalInquiries > 0 ? round($followups * 100.0 / $totalInquiries, 2) : 0,
            'application_rate' => $totalInquiries > 0 ? round($totalApplications * 100.0 / $totalInquiries, 2) : 0,
            'conversion_rate' => $totalInquiries > 0 ? round($totalEnrollments * 100.0 / $totalInquiries, 2) : 0
        ];

        return [
            'stages' => [
                ['name' => 'Inquiries', 'count' => (int) $data['total_inquiries'], 'rate' => 100],
                ['name' => 'Follow-ups', 'count' => (int) $data['followups'], 'rate' => (float) $data['followup_rate']],
                ['name' => 'Applications', 'count' => (int) $data['total_applications'], 'rate' => (float) $data['application_rate']],
                ['name' => 'Enrollments', 'count' => (int) $data['total_enrollments'], 'rate' => (float) $data['conversion_rate']]
            ],
            'period' => ['start' => $startDate, 'end' => $endDate]
        ];
    }

    /**
     * Get revenue forecast based on historical data
     */
    public function getRevenueForecast($months = 3)
    {
        // Get historical revenue data (last 12 months)
        $stmt = $this->pdo->query("
            SELECT 
                DATE_FORMAT(payment_date, '%Y-%m') as month,
                SUM(amount) as revenue
            FROM payments
            WHERE status = 'completed'
            AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
            ORDER BY month
        ");

        $historical = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($historical) < 3) {
            return ['forecast' => [], 'confidence' => 'low'];
        }

        // Simple moving average forecast
        $revenues = array_column($historical, 'revenue');
        $avgGrowth = $this->calculateGrowthRate($revenues);
        $lastRevenue = end($revenues);

        $forecast = [];
        for ($i = 1; $i <= $months; $i++) {
            $forecastDate = date('Y-m', strtotime("+$i month"));
            $forecastValue = $lastRevenue * pow(1 + $avgGrowth, $i);
            $forecast[] = [
                'month' => $forecastDate,
                'forecast' => round($forecastValue, 2),
                'confidence' => $this->getConfidenceLevel(count($historical))
            ];
        }

        return [
            'historical' => $historical,
            'forecast' => $forecast,
            'growth_rate' => round($avgGrowth * 100, 2) . '%'
        ];
    }

    /**
     * Get counselor performance metrics
     */
    public function getCounselorPerformance($period = 'month')
    {
        $dateCondition = match ($period) {
            'week' => 'DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
            'month' => 'DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
            'quarter' => 'DATE_SUB(CURDATE(), INTERVAL 90 DAY)',
            'year' => 'DATE_SUB(CURDATE(), INTERVAL 365 DAY)',
            default => 'DATE_SUB(CURDATE(), INTERVAL 30 DAY)'
        };

        $stmt = $this->pdo->query("
            SELECT 
                u.id,
                u.name as counselor_name,
                COUNT(DISTINCT i.id) as total_inquiries,
                COUNT(DISTINCT s.id) as total_conversions,
                COUNT(DISTINCT a.id) as total_applications,
                ROUND(COUNT(DISTINCT s.id) * 100.0 / NULLIF(COUNT(DISTINCT i.id), 0), 2) as conversion_rate,
                COALESCE(SUM(p.amount), 0) as total_revenue,
                AVG(TIMESTAMPDIFF(HOUR, i.created_at, i.updated_at)) as avg_response_time
            FROM users u
            LEFT JOIN inquiries i ON u.id = i.counselor_id AND i.created_at >= $dateCondition
            LEFT JOIN students s ON i.id = s.inquiry_id
            LEFT JOIN applications a ON s.id = a.student_id
            LEFT JOIN payments p ON s.id = p.student_id AND p.status = 'completed'
            WHERE u.role IN ('admin', 'counselor')
            GROUP BY u.id, u.name
            HAVING total_inquiries > 0
            ORDER BY conversion_rate DESC, total_revenue DESC
        ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get real-time metrics for dashboard
     */
    public function getRealTimeMetrics()
    {
        // Today's stats
        $today = date('Y-m-d');

        $stmt = $this->pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM inquiries WHERE DATE(created_at) = ?) as today_inquiries,
                (SELECT COUNT(*) FROM students WHERE DATE(created_at) = ?) as today_enrollments,
                (SELECT COUNT(*) FROM applications WHERE DATE(created_at) = ?) as today_applications,
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(payment_date) = ? AND status = 'completed') as today_revenue,
                (SELECT COUNT(*) FROM tasks WHERE status = 'pending') as pending_tasks,
                (SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = ? AND status = 'scheduled') as today_appointments,
                (SELECT COUNT(*) FROM inquiries WHERE status = 'new') as new_inquiries,
                (SELECT COUNT(*) FROM tasks WHERE status = 'pending' AND due_date < CURDATE()) as overdue_tasks
        ");

        $stmt->execute([$today, $today, $today, $today, $today]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get trend data for charts
     */
    public function getTrendData($metric = 'inquiries', $days = 30)
    {
        $table = match ($metric) {
            'inquiries' => 'inquiries',
            'students' => 'students',
            'applications' => 'applications',
            'revenue' => 'payments',
            default => 'inquiries'
        };

        if ($metric === 'revenue') {
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(payment_date) as date,
                    SUM(amount) as value
                FROM payments
                WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                AND status = 'completed'
                GROUP BY DATE(payment_date)
                ORDER BY date
            ");
        } else {
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as value
                FROM $table
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date
            ");
        }

        $stmt->execute([$days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Take daily snapshot for historical analysis
     * Stores metrics in JSON format for flexibility
     */
    public function takeSnapshot()
    {
        // Gather all metrics
        $totalInquiries = $this->pdo->query("SELECT COUNT(*) FROM inquiries")->fetchColumn();
        $totalRevenue = $this->pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments")->fetchColumn();
        
        $totalStudents = $this->pdo->query("
            SELECT COUNT(DISTINCT ur.user_id) FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id WHERE r.name = 'student'
        ")->fetchColumn();
        
        $totalApplications = $this->pdo->query("SELECT COUNT(*) FROM visa_workflows")->fetchColumn();
        $pendingTasks = $this->pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'pending'")->fetchColumn();
        $upcomingAppointments = $this->pdo->query("
            SELECT COUNT(*) FROM appointments WHERE appointment_date >= CURDATE() AND status = 'scheduled'
        ")->fetchColumn();
        $activeCounselors = $this->pdo->query("
            SELECT COUNT(DISTINCT assigned_to) FROM inquiries WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ")->fetchColumn();
        
        $conversionRate = $totalInquiries > 0 ? round($totalStudents * 100.0 / $totalInquiries, 2) : 0;
        
        // Build metrics JSON
        $metricsJson = json_encode([
            'total_students' => (int) $totalStudents,
            'total_applications' => (int) $totalApplications,
            'conversion_rate' => $conversionRate,
            'active_counselors' => (int) $activeCounselors,
            'pending_tasks' => (int) $pendingTasks,
            'upcoming_appointments' => (int) $upcomingAppointments
        ]);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO analytics_snapshots (
                snapshot_date,
                total_inquiries,
                total_revenue,
                metrics_json
            )
            VALUES (CURDATE(), ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_inquiries = VALUES(total_inquiries),
                total_revenue = VALUES(total_revenue),
                metrics_json = VALUES(metrics_json)
        ");

        return $stmt->execute([$totalInquiries, $totalRevenue, $metricsJson]);
    }

    /**
     * Get goal progress
     */
    public function getGoalProgress()
    {
        $stmt = $this->pdo->query("
            SELECT 
                g.*,
                ROUND((current_value * 100.0 / target_value), 2) as progress_percentage,
                DATEDIFF(period_end, CURDATE()) as days_remaining
            FROM analytics_goals g
            WHERE status = 'active'
            AND period_end >= CURDATE()
            ORDER BY period_end
        ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Update goal current value
     */
    public function updateGoalProgress($goalId)
    {
        $stmt = $this->pdo->prepare("SELECT goal_type, period_start, period_end FROM analytics_goals WHERE id = ?");
        $stmt->execute([$goalId]);
        $goal = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$goal)
            return false;

        $value = match ($goal['goal_type']) {
            'inquiries' => $this->getCount('inquiries', $goal['period_start'], $goal['period_end']),
            'conversions' => $this->getStudentCount($goal['period_start'], $goal['period_end']),
            'applications' => $this->getCount('visa_workflows', $goal['period_start'], $goal['period_end']),
            'revenue' => $this->getRevenue($goal['period_start'], $goal['period_end']),
            default => 0
        };

        $stmt = $this->pdo->prepare("UPDATE analytics_goals SET current_value = ? WHERE id = ?");
        return $stmt->execute([$value, $goalId]);
    }

    // Helper methods

    private function calculateGrowthRate($values)
    {
        if (count($values) < 2)
            return 0;

        $growthRates = [];
        for ($i = 1; $i < count($values); $i++) {
            if ($values[$i - 1] > 0) {
                $growthRates[] = ($values[$i] - $values[$i - 1]) / $values[$i - 1];
            }
        }

        return count($growthRates) > 0 ? array_sum($growthRates) / count($growthRates) : 0;
    }

    private function getConfidenceLevel($dataPoints)
    {
        if ($dataPoints >= 12)
            return 'high';
        if ($dataPoints >= 6)
            return 'medium';
        return 'low';
    }

    private function getCount($table, $startDate, $endDate)
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM $table WHERE created_at BETWEEN ? AND ?");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchColumn();
    }

    private function getStudentCount($startDate, $endDate)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT ur.user_id) 
            FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            JOIN users u ON ur.user_id = u.id
            WHERE r.name = 'student' AND u.created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchColumn();
    }

    private function getRevenue($startDate, $endDate)
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE transaction_date BETWEEN ? AND ?");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchColumn();
    }
}
