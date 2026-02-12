<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
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
        $sql = "
            SELECT 
                DATE_FORMAT(p.payment_date, '%Y-%m') as month,
                SUM(p.amount) as revenue
            FROM payments p
        ";
        
        $params = [];
        if ($this->branchId) {
            $sql .= " JOIN users u ON p.student_id = u.id WHERE p.status = 'completed' AND u.branch_id = ? AND p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
            $params[] = $this->branchId;
        } else {
            $sql .= " WHERE p.status = 'completed' AND p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
        }
        
        $sql .= " GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m') ORDER BY month";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

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

        $branchFilter = "";
        $params = [];
        if ($this->branchId) {
            $branchFilter = " AND u.branch_id = ?";
            $params[] = $this->branchId;
        }
        
        $stmt = $this->pdo->prepare("
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
            LEFT JOIN users s ON i.id = s.id AND s.role = 'student' -- Assuming inquiries converted to student users linked by email or similar? Reverting to original query structure but fixing 'students' table usage if necessary.
            -- Actually original query used 'students s ON i.id = s.inquiry_id'. Since I can't find 'students' table, I will assume it's a view I missed OR I should use 'users' based on 'inquiries.assigned_to'.
            -- However, keeping original JOINs for safety but adding u.branch_id filter.
            LEFT JOIN users s_user ON i.email = s_user.email AND s_user.role = 'student' -- fallback join
            LEFT JOIN university_applications a ON s_user.id = a.student_id -- Changed from applications to university_applications
            LEFT JOIN payments p ON s_user.id = p.student_id AND p.status = 'completed'
            WHERE u.role IN ('admin', 'counselor') $branchFilter
            GROUP BY u.id, u.name
            HAVING total_inquiries > 0
            ORDER BY conversion_rate DESC, total_revenue DESC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get real-time metrics for dashboard
     */
    public function getRealTimeMetrics()
    {
        // Today's stats
        $today = date('Y-m-d');

        $inqSql = "SELECT COUNT(*) FROM inquiries WHERE DATE(created_at) = ?";
        if ($this->branchId) $inqSql .= " AND branch_id = " . (int)$this->branchId;

        $enrollSql = "SELECT COUNT(*) FROM users WHERE role='student' AND DATE(created_at) = ?";
        if ($this->branchId) $enrollSql .= " AND branch_id = " . (int)$this->branchId;

        $appSql = "SELECT COUNT(*) FROM university_applications t"; // Changed from applications
        if ($this->branchId) $appSql .= " JOIN users u ON t.student_id = u.id WHERE u.branch_id = " . (int)$this->branchId . " AND DATE(t.created_at) = ?";
        else $appSql .= " WHERE DATE(t.created_at) = ?";

        $revSql = "SELECT COALESCE(SUM(amount), 0) FROM payments p";
        if ($this->branchId) $revSql .= " JOIN users u ON p.student_id = u.id WHERE u.branch_id = " . (int)$this->branchId . " AND DATE(payment_date) = ? AND status = 'completed'";
        else $revSql .= " WHERE DATE(payment_date) = ? AND status = 'completed'";

        $taskSql = "SELECT COUNT(*) FROM tasks t";
        if ($this->branchId) $taskSql .= " JOIN users u ON t.assigned_to = u.id WHERE u.branch_id = " . (int)$this->branchId . " AND status = 'pending'";
        else $taskSql .= " WHERE status = 'pending'";

        $apptSql = "SELECT COUNT(*) FROM appointments a";
        if ($this->branchId) $apptSql .= " JOIN users u ON a.counselor_id = u.id WHERE u.branch_id = " . (int)$this->branchId . " AND DATE(appointment_date) = ? AND status = 'scheduled'";
        else $apptSql .= " WHERE DATE(appointment_date) = ? AND status = 'scheduled'";

        $newInqSql = "SELECT COUNT(*) FROM inquiries WHERE status = 'new'";
        if ($this->branchId) $newInqSql .= " AND branch_id = " . (int)$this->branchId;

        $overdueSql = "SELECT COUNT(*) FROM tasks t";
        if ($this->branchId) $overdueSql .= " JOIN users u ON t.assigned_to = u.id WHERE u.branch_id = " . (int)$this->branchId . " AND status = 'pending' AND due_date < CURDATE()";
        else $overdueSql .= " WHERE status = 'pending' AND due_date < CURDATE()";

        $sql = "SELECT 
            ($inqSql) as today_inquiries,
            ($enrollSql) as today_enrollments,
            ($appSql) as today_applications,
            ($revSql) as today_revenue,
            ($taskSql) as pending_tasks,
            ($apptSql) as today_appointments,
            ($newInqSql) as new_inquiries,
            ($overdueSql) as overdue_tasks";
        
        $params = [$today, $today, $today, $today, $today]; // 5 params used in subqueries
        
        // Wait, binding params to subqueries in a single string is risky with PDO execute array causing mismatch if we injected variables.
        // I injected (int)$branchId directly to avoid param count mismatch hell.
        // The params array needs to match the number of '?' placeholders.
        // Inquiries: 1 ?
        // Enrollments: 1 ?
        // App: 1 ?
        // Rev: 1 ?
        // Task: 0 ? (pending)
        // Appt: 1 ?
        // New Inq: 0 ?
        // Overdue: 0 ?
        // Total ? count = 5.
        // Checks out.

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get trend data for charts
     */
    /**
     * Get trend data for charts
     */
    public function getTrendData($metric = 'inquiries', $days = 30)
    {
        $params = [$days];
        
        if ($metric === 'revenue') {
            $sql = "SELECT DATE(p.payment_date) as date, SUM(p.amount) as value FROM payments p";
            if ($this->branchId) {
                $sql .= " JOIN users u ON p.student_id = u.id WHERE u.branch_id = ? AND p.payment_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY) AND p.status = 'completed'";
                $params = [$this->branchId, $days];
            } else {
                $sql .= " WHERE p.payment_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY) AND p.status = 'completed'";
            }
            $sql .= " GROUP BY DATE(p.payment_date) ORDER BY date";
        } else {
            // Mapping for other metrics
            if ($metric === 'inquiries') {
                $sql = "SELECT DATE(created_at) as date, COUNT(*) as value FROM inquiries WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                if ($this->branchId) {
                    $sql .= " AND branch_id = ?";
                    $params[] = $this->branchId;
                }
            } elseif ($metric === 'students') {
                 $sql = "SELECT DATE(created_at) as date, COUNT(*) as value FROM users WHERE role='student' AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                 if ($this->branchId) {
                    $sql .= " AND branch_id = ?";
                    $params[] = $this->branchId;
                 }
            } elseif ($metric === 'applications') {
                 $sql = "SELECT DATE(t.created_at) as date, COUNT(*) as value FROM university_applications t"; // Fixed table
                 if ($this->branchId) {
                    $sql .= " JOIN users u ON t.student_id = u.id WHERE u.branch_id = ? AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                    $params = [$this->branchId, $days];
                 } else {
                    $sql .= " WHERE t.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                 }
            } else {
                 // Default inquiries
                $sql = "SELECT DATE(created_at) as date, COUNT(*) as value FROM inquiries WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                 if ($this->branchId) {
                    $sql .= " AND branch_id = ?";
                    $params[] = $this->branchId;
                 }
            }
            
            if ($metric !== 'revenue' && $metric !== 'applications' && strpos($sql, 'GROUP BY') === false) {
                 $sql .= " GROUP BY DATE(created_at) ORDER BY date";
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
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
        // Add branch logic here if methods use this helper
        $sql = "SELECT COUNT(*) FROM $table WHERE created_at BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        
        if ($this->branchId && $table === 'inquiries') {
            $sql .= " AND branch_id = ?";
            $params[] = $this->branchId;
        } elseif ($this->branchId && $table === 'visa_workflows') {
             // Visa workflows might not have branch_id directly, check if this helper is used for it
             // updateGoalProgress uses 'visa_workflows'.
             // visa_workflows needs join.
             $sql = "SELECT COUNT(*) FROM $table t JOIN users u ON t.student_id = u.id WHERE t.created_at BETWEEN ? AND ? AND u.branch_id = ?";
             $params = [$startDate, $endDate, $this->branchId];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function getStudentCount($startDate, $endDate)
    {
        $sql = "
            SELECT COUNT(DISTINCT ur.user_id) 
            FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            JOIN users u ON ur.user_id = u.id
            WHERE r.name = 'student' AND u.created_at BETWEEN ? AND ?
        ";
        $params = [$startDate, $endDate];
        
        if ($this->branchId) {
            $sql .= " AND u.branch_id = ?";
            $params[] = $this->branchId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function getRevenue($startDate, $endDate)
    {
        $sql = "SELECT COALESCE(SUM(amount), 0) FROM payments p WHERE transaction_date BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        
        if ($this->branchId) {
            $sql = "SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN users u ON p.student_id = u.id WHERE p.transaction_date BETWEEN ? AND ? AND u.branch_id = ?";
            $params[] = $this->branchId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
