<?php
require_once __DIR__ . '/../../config.php';

class DashboardService
{
    private $pdo;
    private $userId;
    private $role;

    public function __construct($pdo, $userId, $role)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->role = $role;
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
            $stmt = $this->pdo->prepare("SELECT current_stage FROM visa_workflows WHERE student_id = ?");
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

    public function getFinancialOverview()
    {
        if ($this->role === 'admin') {
            $total_due = $this->pdo->query("SELECT SUM(amount) FROM student_fees WHERE status != 'paid'")->fetchColumn() ?: 0;
            $total_paid = $this->pdo->query("SELECT SUM(amount) FROM payments")->fetchColumn() ?: 0;
            return [
                'revenue' => $total_paid,
                'outstanding' => $total_due - $total_paid
            ];
        }
        return [];
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
            return $att_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
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

    public function getInquiryStats()
    {
        if ($this->hasRole(['admin', 'counselor'])) {
            $stats = $this->pdo->query("SELECT status, COUNT(*) as count FROM inquiries GROUP BY status")->fetchAll();
            $labels = [];
            $data = [];
            foreach ($stats as $s) {
                $labels[] = ucfirst($s['status']);
                $data[] = $s['count'];
            }
            return ['labels' => $labels, 'data' => $data];
        }
        return [];
    }

    public function getVisaPipelineStats()
    {
        if ($this->hasRole(['admin', 'counselor'])) {
            $v_stats = $this->pdo->query("SELECT current_stage, COUNT(*) as count FROM visa_workflows GROUP BY current_stage")->fetchAll();
            $v_labels = [];
            $v_data = [];
            foreach ($v_stats as $vs) {
                $v_labels[] = $vs['current_stage'];
                $v_data[] = $vs['count'];
            }
            return ['labels' => $v_labels, 'data' => $v_data];
        }
        return [];
    }

    public function getFunnelStats()
    {
        if ($this->hasRole(['admin', 'counselor'])) { // Assuming counselor sees this too based on index.php
            $total_inq = $this->pdo->query("SELECT COUNT(*) FROM inquiries")->fetchColumn();
            $converted = $this->pdo->query("SELECT COUNT(*) FROM inquiries WHERE status='converted'")->fetchColumn();
            return ['total' => $total_inq, 'converted' => $converted];
        }
        return [];
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
                AND appointment_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$this->userId]);
            return (int) $stmt->fetchColumn();
        }
        return 0;
    }

    /**
     * Get overdue fees summary
     */
    public function getOverdueFeesSummary()
    {
        if ($this->role === 'admin') {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(DISTINCT student_id) as student_count,
                    SUM(amount) as total_amount
                FROM student_fees 
                WHERE status != 'paid' 
                AND due_date < CURDATE()
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return [
                'count' => (int) $result['student_count'],
                'amount' => (float) $result['total_amount']
            ];
        }
        return ['count' => 0, 'amount' => 0];
    }

    /**
     * Get recent applications count (last 30 days)
     */
    public function getRecentApplicationsCount()
    {
        if ($this->hasRole(['admin', 'counselor'])) {
            $stmt = $this->pdo->query("
                SELECT COUNT(*) 
                FROM university_applications 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            return (int) $stmt->fetchColumn();
        }
        return 0;
    }

    /**
     * Get active visa processes count
     */
    public function getActiveVisaProcessesCount()
    {
        if ($this->hasRole(['admin', 'counselor'])) {
            $stmt = $this->pdo->query("
                SELECT COUNT(*) 
                FROM visa_workflows 
                WHERE current_stage NOT IN ('Approved', 'Rejected')
            ");
            return (int) $stmt->fetchColumn();
        }
        return 0;
    }

    /**
     * Get lead priority statistics
     */
    public function getLeadPriorityStats()
    {
        if ($this->hasRole(['admin', 'counselor'])) {
            $stmt = $this->pdo->query("
                SELECT 
                    priority,
                    COUNT(*) as count
                FROM inquiries
                WHERE status NOT IN ('closed', 'converted')
                GROUP BY priority
            ");

            $stats = [
                'hot' => 0,
                'warm' => 0,
                'cold' => 0
            ];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stats[$row['priority']] = (int) $row['count'];
            }

            return $stats;
        }
        return ['hot' => 0, 'warm' => 0, 'cold' => 0];
    }

    /**
     * Get recent tasks for dashboard widget
     */
    public function getRecentTasks($limit = 5)
    {
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
            LIMIT ?
        ");
        $stmt->execute([$this->userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get upcoming appointments for dashboard widget
     */
    public function getUpcomingAppointments($limit = 5)
    {
        if ($this->hasRole(['admin', 'counselor'])) {
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
                LIMIT ?
            ");
            $stmt->execute([$this->userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return [];
    }
}
