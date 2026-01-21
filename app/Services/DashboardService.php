<?php

declare(strict_types=1);

namespace EduCRM\Services;

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/CacheService.php';

class DashboardService
{
    private \PDO $pdo;
    private int $userId;
    private string $role;
    private CacheService $cache;

    // Cache TTL constants (in seconds)
    private const CACHE_TTL_SHORT = 60;      // 1 minute - for frequently changing data
    private const CACHE_TTL_MEDIUM = 300;    // 5 minutes - for dashboard stats
    private const CACHE_TTL_LONG = 900;      // 15 minutes - for analytics

    public function __construct(\PDO $pdo, int $userId, string $role)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->role = $role;
        $this->cache = \EduCRM\Services\CacheService::getInstance();
    }

    /**
     * Get cache key for this user's dashboard data
     */
    private function getCacheKey(string $metric): string
    {
        return \EduCRM\Services\CacheService::dashboardKey($this->userId, $this->role) . "_{$metric}";
    }

    /**
     * Get global cache key (not user-specific)
     */
    private function getGlobalCacheKey(string $metric): string
    {
        return \EduCRM\Services\CacheService::statsKey($metric);
    }

    public function getNewInquiriesCount()
    {
        if ($this->hasRole(['admin', 'counselor'])) {
            return $this->pdo->query("SELECT COUNT(*) FROM inquiries WHERE status='new'")->fetchColumn();
        }
        return 0;
    }

    public function getActiveClassesCount()
    {
        if ($this->role === 'admin') {
            return $this->pdo->query("SELECT COUNT(*) FROM classes WHERE status='active'")->fetchColumn();
        } elseif ($this->role === 'teacher') {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM classes WHERE teacher_id = ? AND status='active'");
            $stmt->execute([$this->userId]);
            return $stmt->fetchColumn();
        }
        return 0;
    }

    public function getStudentClassesCount()
    {
        if ($this->role === 'student') {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ?");
            $stmt->execute([$this->userId]);
            return $stmt->fetchColumn();
        }
        return 0;
    }

    public function getVisaStage()
    {
        if ($this->role === 'student') {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(vs.name, vw.current_stage) as stage_name 
                FROM visa_workflows vw
                LEFT JOIN visa_stages vs ON vw.stage_id = vs.id
                WHERE vw.student_id = ?
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchColumn() ?: 'Not Started';
        }
        return null;
    }

    public function getDuesBalance()
    {
        if ($this->role === 'student') {
            $total_bill = $this->pdo->prepare("SELECT SUM(amount) FROM student_fees WHERE student_id = ?");
            $total_bill->execute([$this->userId]);
            $billed = $total_bill->fetchColumn() ?: 0;

            $total_paid = $this->pdo->prepare("SELECT SUM(p.amount) FROM payments p JOIN student_fees sf ON p.student_fee_id = sf.id WHERE sf.student_id = ?");
            $total_paid->execute([$this->userId]);
            $paid = $total_paid->fetchColumn() ?: 0;

            return $billed - $paid;
        }
        return 0;
    }

    public function getTeacherClasses()
    {
        if ($this->role === 'teacher') {
            $stmt = $this->pdo->prepare("
                SELECT c.*, co.name as course_name 
                FROM classes c 
                JOIN courses co ON c.course_id = co.id 
                WHERE c.teacher_id = ? AND c.status = 'active'
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll();
        }
        return [];
    }

    public function getStudentRecentMaterials()
    {
        if ($this->role === 'student') {
            $stmt = $this->pdo->prepare("
                SELECT cm.*, c.name as class_name, s.grade, s.submitted_at
                FROM class_materials cm
                JOIN enrollments e ON cm.class_id = e.class_id
                JOIN classes c ON cm.class_id = c.id
                LEFT JOIN submissions s ON s.material_id = cm.id AND s.student_id = e.student_id
                WHERE e.student_id = ?
                ORDER BY cm.created_at DESC
                LIMIT 3
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll();
        }
        return [];
    }

    public function getFinancialOverview(): array
    {
        if ($this->role !== 'admin') {
            return [];
        }

        $cacheKey = $this->getGlobalCacheKey('financial_overview');

        return $this->cache->remember($cacheKey, function (): array {
            // Correct Logic: Total Billed - Total Collected = Outstanding
            $total_invoiced = $this->pdo->query("SELECT SUM(amount) FROM student_fees")->fetchColumn() ?: 0;
            $total_paid = $this->pdo->query("SELECT SUM(amount) FROM payments")->fetchColumn() ?: 0;

            return [
                'revenue' => $total_paid,
                'outstanding' => $total_invoiced - $total_paid
            ];
        }, self::CACHE_TTL_MEDIUM);
    }

    public function getStudentAttendanceStats()
    {
        if ($this->role === 'student') {
            $month_start = date('Y-m-01');
            $month_end = date('Y-m-t');

            $att_stmt = $this->pdo->prepare("
                    SELECT attendance, COUNT(*) as count 
                    FROM daily_performance dp
                    JOIN daily_rosters dr ON dp.roster_id = dr.id
                    WHERE dp.student_id = ? 
                    AND dr.roster_date BETWEEN ? AND ?
                    GROUP BY attendance
                ");
            $att_stmt->execute([$this->userId, $month_start, $month_end]);
            return $att_stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        }
        return [];
    }

    public function getStudentPerformanceStats()
    {
        if ($this->role === 'student') {
            $stmt = $this->pdo->prepare("
                SELECT AVG(class_task_mark) as avg_class, AVG(home_task_mark) as avg_home
                FROM daily_performance
                WHERE student_id = ?
            ");
            $stmt->execute([$this->userId]);
            $res = $stmt->fetch();
            return [
                'avg_class' => round($res['avg_class'] ?: 0, 1),
                'avg_home' => round($res['avg_home'] ?: 0, 1)
            ];
        }
        return ['avg_class' => 0, 'avg_home' => 0];
    }

    public function getInquiryStats(): array
    {
        if (!$this->hasRole(['admin', 'counselor'])) {
            return [];
        }

        $cacheKey = $this->getGlobalCacheKey('inquiry_stats');

        return $this->cache->remember($cacheKey, function (): array {
            $stats = $this->pdo->query("
                SELECT COALESCE(ist.name, i.status) as status_name, COUNT(*) as count 
                FROM inquiries i
                LEFT JOIN inquiry_statuses ist ON i.status_id = ist.id
                GROUP BY status_name
            ")->fetchAll();
            $labels = [];
            $data = [];
            foreach ($stats as $s) {
                $labels[] = ucfirst($s['status_name']);
                $data[] = $s['count'];
            }
            return ['labels' => $labels, 'data' => $data];
        }, self::CACHE_TTL_MEDIUM);
    }

    public function getVisaPipelineStats(): array
    {
        if (!$this->hasRole(['admin', 'counselor'])) {
            return [];
        }

        $cacheKey = $this->getGlobalCacheKey('visa_pipeline');

        return $this->cache->remember($cacheKey, function (): array {
            $v_stats = $this->pdo->query("
                SELECT COALESCE(vs.name, vw.current_stage) as stage_name, COUNT(*) as count 
                FROM visa_workflows vw
                LEFT JOIN visa_stages vs ON vw.stage_id = vs.id
                GROUP BY stage_name
            ")->fetchAll();
            $v_labels = [];
            $v_data = [];
            foreach ($v_stats as $vs) {
                $v_labels[] = $vs['stage_name'];
                $v_data[] = $vs['count'];
            }
            return ['labels' => $v_labels, 'data' => $v_data];
        }, self::CACHE_TTL_MEDIUM);
    }

    public function getFunnelStats(): array
    {
        if (!$this->hasRole(['admin', 'counselor'])) {
            return [];
        }

        $cacheKey = $this->getGlobalCacheKey('funnel_stats');

        return $this->cache->remember($cacheKey, function (): array {
            $total_inq = $this->pdo->query("SELECT COUNT(*) FROM inquiries")->fetchColumn();
            $converted = $this->pdo->query("SELECT COUNT(*) FROM inquiries WHERE status='converted'")->fetchColumn();
            return ['total' => $total_inq, 'converted' => $converted];
        }, self::CACHE_TTL_MEDIUM);
    }

    // Helper to check multiple roles against current user role
    // Note: The logic in index.php used hasRole() which checks session. 
    // Here we check the primary role passed to constructor, 
    // but ideally we should support multi-role if the user has multiple.
    // For this refactor, we'll assume the primary role or use a helper if we passed the user object.
    // However, the original code used global hasRole(). 
    // We can reproduce that logic or just check the role passed.
    // Let's assume $role passed is the primary unique role for simplicity or we might need to query user_roles if strict.
    // Given the simple construct, let's keep it simple.

    private function hasRole($roles)
    {
        if (is_array($roles)) {
            return in_array($this->role, $roles);
        }
        return $this->role === $roles;
    }

    // =========================================================================
    // PHASE 1 ENHANCEMENTS - New Metrics
    // =========================================================================

    /**
     * Get pending tasks count for current user
     */
    public function getPendingTasksCount()
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM tasks 
            WHERE assigned_to = ? 
            AND status IN ('pending', 'in_progress')
        ");
        $stmt->execute([$this->userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get upcoming appointments count (next 7 days)
     */
    public function getUpcomingAppointmentsCount()
    {
        if ($this->hasRole(['admin', 'counselor'])) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM appointments 
                WHERE counselor_id = ? 
                AND status = 'scheduled'
                AND appointment_date >= NOW()
            ");
            $stmt->execute([$this->userId]);
            return (int) $stmt->fetchColumn();
        }
        return 0;
    }

    /**
     * Get overdue fees summary
     */
    public function getOverdueFeesSummary(): array
    {
        if ($this->role !== 'admin') {
            return ['count' => 0, 'amount' => 0];
        }

        $cacheKey = $this->getGlobalCacheKey('overdue_fees');

        return $this->cache->remember($cacheKey, function (): array {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(DISTINCT student_id) as student_count,
                    SUM(amount) as total_amount
                FROM student_fees 
                WHERE status != 'paid' 
                AND due_date < CURDATE()
            ");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return [
                'count' => (int) $result['student_count'],
                'amount' => (float) $result['total_amount']
            ];
        }, self::CACHE_TTL_MEDIUM);
    }

    /**
     * Get recent applications count (last 30 days)
     */
    public function getRecentApplicationsCount(): int
    {
        if (!$this->hasRole(['admin', 'counselor'])) {
            return 0;
        }

        $cacheKey = $this->getGlobalCacheKey('recent_applications');

        return $this->cache->remember($cacheKey, function (): int {
            $stmt = $this->pdo->query("
                SELECT COUNT(*) 
                FROM university_applications 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            return (int) $stmt->fetchColumn();
        }, self::CACHE_TTL_MEDIUM);
    }

    /**
     * Get active visa processes count
     */
    public function getActiveVisaProcessesCount(): int
    {
        if (!$this->hasRole(['admin', 'counselor'])) {
            return 0;
        }

        $cacheKey = $this->getGlobalCacheKey('active_visa');

        return $this->cache->remember($cacheKey, function (): int {
            $stmt = $this->pdo->query("
                SELECT COUNT(*) 
                FROM visa_workflows vw
                LEFT JOIN visa_stages vs ON vw.stage_id = vs.id
                WHERE COALESCE(vs.name, vw.current_stage) NOT IN ('Approved', 'Rejected')
            ");
            return (int) $stmt->fetchColumn();
        }, self::CACHE_TTL_MEDIUM);
    }

    /**
     * Get lead priority statistics
     */
    public function getLeadPriorityStats(): array
    {
        if (!$this->hasRole(['admin', 'counselor'])) {
            return ['hot' => 0, 'warm' => 0, 'cold' => 0];
        }

        $cacheKey = $this->getGlobalCacheKey('lead_priority');

        return $this->cache->remember($cacheKey, function (): array {
            $stmt = $this->pdo->query("
                SELECT 
                    COALESCE(pl.name, i.priority) as priority_name,
                    COUNT(*) as count
                FROM inquiries i
                LEFT JOIN priority_levels pl ON i.priority_id = pl.id
                LEFT JOIN inquiry_statuses ist ON i.status_id = ist.id
                WHERE COALESCE(ist.name, i.status) NOT IN ('closed', 'converted')
                GROUP BY priority_name
            ");

            $stats = [
                'hot' => 0,
                'warm' => 0,
                'cold' => 0
            ];

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if (isset($stats[$row['priority_name']])) {
                    $stats[$row['priority_name']] = (int) $row['count'];
                }
            }

            return $stats;
        }, self::CACHE_TTL_MEDIUM);
    }

    /**
     * Get recent tasks for dashboard widget
     */
    public function getRecentTasks($limit = 5)
    {
        $limit = (int) $limit;
        $stmt = $this->pdo->prepare("
            SELECT 
                t.*,
                u.name as created_by_name
            FROM tasks t
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.assigned_to = ?
            AND t.status IN ('pending', 'in_progress')
            ORDER BY 
                CASE t.priority 
                    WHEN 'urgent' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                END,
                t.due_date ASC
            LIMIT " . $limit . "
        ");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get upcoming appointments for dashboard widget
     */
    public function getUpcomingAppointments($limit = 5)
    {
        if ($this->hasRole(['admin', 'counselor'])) {
            $limit = (int) $limit;
            $stmt = $this->pdo->prepare("
                SELECT 
                    a.*,
                    COALESCE(u.name, i.name) as client_name
                FROM appointments a
                LEFT JOIN users u ON a.student_id = u.id
                LEFT JOIN inquiries i ON a.inquiry_id = i.id
                WHERE a.counselor_id = ?
                AND a.status = 'scheduled'
                AND a.appointment_date >= NOW()
                ORDER BY a.appointment_date ASC
                LIMIT " . $limit . "
            ");
            $stmt->execute([$this->userId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        return [];
    }

    // =========================================================================
    // VISA WORKFLOW ANALYTICS (Gap #6 Enhancement)
    // =========================================================================

    /**
     * Get comprehensive visa analytics for dashboard
     */
    public function getVisaAnalytics(): ?array
    {
        if (!$this->hasRole(['admin', 'counselor'])) {
            return null;
        }

        $cacheKey = $this->getGlobalCacheKey('visa_analytics');

        return $this->cache->remember($cacheKey, function (): array {
            // Pipeline by stage with normalized FK
            $pipelineStmt = $this->pdo->query("
                SELECT vs.name as stage, vs.stage_order, COUNT(vw.id) as count
                FROM visa_stages vs
                LEFT JOIN visa_workflows vw ON vw.stage_id = vs.id
                GROUP BY vs.id, vs.name, vs.stage_order
                ORDER BY vs.stage_order
            ");
            $pipeline = $pipelineStmt->fetchAll(\PDO::FETCH_ASSOC);

            // Processing time by country (average days from creation to approval)
            $processingTimeStmt = $this->pdo->query("
                SELECT c.name as country, 
                       COUNT(*) as total,
                       AVG(DATEDIFF(h_approved.changed_at, vw.updated_at)) as avg_days
                FROM visa_workflows vw
                JOIN countries c ON vw.country_id = c.id
                JOIN visa_stages vs ON vw.stage_id = vs.id
                LEFT JOIN visa_workflow_history h_approved 
                    ON h_approved.workflow_id = vw.id 
                    AND h_approved.to_stage_id = (SELECT id FROM visa_stages WHERE name = 'Approved')
                WHERE vs.name = 'Approved'
                GROUP BY c.id, c.name
                HAVING total > 0
                ORDER BY total DESC
                LIMIT 5
            ");
            $processingByCountry = $processingTimeStmt->fetchAll(\PDO::FETCH_ASSOC);

            // Success rate (Approved vs Rejected)
            $outcomeStmt = $this->pdo->query("
                SELECT vs.name as outcome, COUNT(*) as count
                FROM visa_workflows vw
                JOIN visa_stages vs ON vw.stage_id = vs.id
                WHERE vs.name IN ('Approved', 'Rejected')
                GROUP BY vs.name
            ");
            $outcomes = $outcomeStmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            $approved = $outcomes['Approved'] ?? 0;
            $rejected = $outcomes['Rejected'] ?? 0;
            $totalOutcomes = $approved + $rejected;
            $successRate = $totalOutcomes > 0 ? round(($approved / $totalOutcomes) * 100, 1) : 0;

            // Overdue count
            $overdueStmt = $this->pdo->query("
                SELECT COUNT(*) FROM visa_workflows vw
                JOIN visa_stages vs ON vw.stage_id = vs.id
                WHERE vw.expected_completion_date < CURDATE()
                AND vs.name NOT IN ('Approved', 'Rejected')
            ");
            $overdueCount = (int) $overdueStmt->fetchColumn();

            // Priority breakdown
            $priorityStmt = $this->pdo->query("
                SELECT priority, COUNT(*) as count
                FROM visa_workflows vw
                JOIN visa_stages vs ON vw.stage_id = vs.id
                WHERE vs.name NOT IN ('Approved', 'Rejected')
                GROUP BY priority
            ");
            $priorityBreakdown = $priorityStmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            // Recent activity (last 7 days)
            $recentActivityStmt = $this->pdo->query("
                SELECT DATE(changed_at) as date, COUNT(*) as changes
                FROM visa_workflow_history
                WHERE changed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(changed_at)
                ORDER BY date
            ");
            $recentActivity = $recentActivityStmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            return [
                'pipeline' => $pipeline,
                'processing_by_country' => $processingByCountry,
                'success_rate' => $successRate,
                'approved' => $approved,
                'rejected' => $rejected,
                'overdue_count' => $overdueCount,
                'priority_breakdown' => $priorityBreakdown,
                'recent_activity' => $recentActivity
            ];
        }, self::CACHE_TTL_LONG);
    }

    /**
     * Invalidate dashboard cache for a specific metric
     */
    public function invalidateCache(string $metric): bool
    {
        return $this->cache->delete($this->getGlobalCacheKey($metric));
    }

    /**
     * Invalidate all dashboard caches
     */
    public function invalidateAllCaches(): bool
    {
        $metrics = [
            'inquiry_stats',
            'visa_pipeline',
            'funnel_stats',
            'overdue_fees',
            'recent_applications',
            'active_visa',
            'lead_priority',
            'financial_overview',
            'visa_analytics'
        ];

        foreach ($metrics as $metric) {
            $this->cache->delete($this->getGlobalCacheKey($metric));
        }

        return true;
    }

    /**
     * Get cache statistics for monitoring
     */
    public function getCacheStats(): array
    {
        return $this->cache->getStats();
    }
}
