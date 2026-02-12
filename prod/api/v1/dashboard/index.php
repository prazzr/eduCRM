<?php
/**
 * Dashboard API Endpoint
 * 
 * GET /api/v1/dashboard/index.php - Get role-based dashboard data
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../ApiController.php';
require_once __DIR__ . '/../../../app/Services/LeadScoringService.php';

$api = new ApiController($pdo);

// Require authentication
$user = $api->requireAuth();

// Only GET method
if ($api->getMethod() !== 'GET') {
    $api->error('Method not allowed', 405);
}

try {
    $dashboard = [
        'user' => [
            'id' => $user['id'],
            'roles' => $user['roles']
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Admin / Counselor metrics
    if (in_array('admin', $user['roles']) || in_array('counselor', $user['roles'])) {

        // Lead priority stats
        $leadService = new \EduCRM\Services\LeadScoringService($pdo);
        $priorityStats = $leadService->getPriorityStats();

        $dashboard['leads'] = [
            'hot' => $priorityStats['hot'],
            'warm' => $priorityStats['warm'],
            'cold' => $priorityStats['cold'],
            'total' => $priorityStats['hot'] + $priorityStats['warm'] + $priorityStats['cold']
        ];

        // New inquiries (last 7 days)
        $stmt = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $dashboard['inquiries_this_week'] = intval($stmt->fetchColumn());

        // Active students
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT u.id) 
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE r.name = 'student'
        ");
        $dashboard['total_students'] = intval($stmt->fetchColumn());

        // Visa pipeline
        $stmt = $pdo->query("
            SELECT COALESCE(vs.name, vw.current_stage) as stage_name, COUNT(*) as count
            FROM visa_workflows vw
            LEFT JOIN visa_stages vs ON vw.stage_id = vs.id
            GROUP BY stage_name
        ");
        $visaStats = [];
        while ($row = $stmt->fetch()) {
            $visaStats[$row['stage_name']] = intval($row['count']);
        }
        $dashboard['visa_pipeline'] = $visaStats;

        // Pending tasks
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM tasks 
            WHERE (assigned_to = ? OR created_by = ?)
            AND status != 'completed'
        ");
        $stmt->execute([$user['id'], $user['id']]);
        $dashboard['pending_tasks'] = intval($stmt->fetchColumn());

        // Upcoming appointments (next 7 days)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM appointments 
            WHERE user_id = ?
            AND appointment_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
            AND status = 'scheduled'
        ");
        $stmt->execute([$user['id']]);
        $dashboard['upcoming_appointments'] = intval($stmt->fetchColumn());
    }

    // Teacher metrics
    if (in_array('teacher', $user['roles'])) {
        // Classes assigned
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, co.name as course_name, COUNT(e.id) as student_count
            FROM classes c
            JOIN courses co ON c.course_id = co.id
            LEFT JOIN enrollments e ON e.class_id = c.id
            WHERE c.teacher_id = ? AND c.status = 'active'
            GROUP BY c.id
        ");
        $stmt->execute([$user['id']]);
        $dashboard['my_classes'] = $stmt->fetchAll();

        // Today's rosters
        $stmt = $pdo->prepare("
            SELECT dr.id, dr.topic, c.name as class_name
            FROM daily_rosters dr
            JOIN classes c ON dr.class_id = c.id
            WHERE dr.teacher_id = ? AND dr.roster_date = CURDATE()
        ");
        $stmt->execute([$user['id']]);
        $dashboard['todays_rosters'] = $stmt->fetchAll();
    }

    // Student metrics
    if (in_array('student', $user['roles'])) {
        $studentId = $user['id'];

        // My classes
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, co.name as course_name
            FROM enrollments e
            JOIN classes c ON e.class_id = c.id
            JOIN courses co ON c.course_id = co.id
            WHERE e.student_id = ? AND c.status = 'active'
        ");
        $stmt->execute([$studentId]);
        $dashboard['my_classes'] = $stmt->fetchAll();

        // Visa status
        $stmt = $pdo->prepare("
            SELECT COALESCE(vs.name, vw.current_stage) as current_stage, COALESCE(c.name, vw.country) as country 
            FROM visa_workflows vw
            LEFT JOIN visa_stages vs ON vw.stage_id = vs.id
            LEFT JOIN countries c ON vw.country_id = c.id
            WHERE vw.student_id = ?
        ");
        $stmt->execute([$studentId]);
        $visa = $stmt->fetch();
        $dashboard['visa_status'] = $visa ?: ['current_stage' => 'Not Started', 'country' => null];

        // Fee balance
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(sf.amount), 0) as total_fees,
                COALESCE((SELECT SUM(p.amount) FROM payments p 
                          JOIN student_fees sf2 ON p.student_fee_id = sf2.id 
                          WHERE sf2.student_id = ?), 0) as total_paid
            FROM student_fees sf
            WHERE sf.student_id = ?
        ");
        $stmt->execute([$studentId, $studentId]);
        $fees = $stmt->fetch();
        $dashboard['balance_due'] = floatval($fees['total_fees']) - floatval($fees['total_paid']);

        // Attendance summary
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN attendance = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN attendance = 'late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN attendance = 'absent' THEN 1 ELSE 0 END) as absent
            FROM daily_performance
            WHERE student_id = ?
        ");
        $stmt->execute([$studentId]);
        $attendance = $stmt->fetch();
        $dashboard['attendance'] = [
            'total_days' => intval($attendance['total_days']),
            'present' => intval($attendance['present']),
            'late' => intval($attendance['late']),
            'absent' => intval($attendance['absent']),
            'percentage' => $attendance['total_days'] > 0
                ? round(($attendance['present'] + $attendance['late'] * 0.5) / $attendance['total_days'] * 100, 1)
                : 0
        ];

        // Pending tasks
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM tasks 
            WHERE assigned_to = ? AND status != 'completed'
        ");
        $stmt->execute([$studentId]);
        $dashboard['pending_tasks'] = intval($stmt->fetchColumn());
    }

    // Accountant metrics
    if (in_array('accountant', $user['roles'])) {
        // Total revenue
        $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments");
        $dashboard['total_revenue'] = floatval($stmt->fetchColumn());

        // Outstanding balance
        $stmt = $pdo->query("
            SELECT 
                COALESCE(SUM(sf.amount), 0) - COALESCE((SELECT SUM(p.amount) FROM payments p), 0)
            FROM student_fees sf
        ");
        $dashboard['outstanding_balance'] = floatval($stmt->fetchColumn());

        // Payments this month
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(amount), 0) 
            FROM payments 
            WHERE MONTH(transaction_date) = MONTH(NOW()) 
            AND YEAR(transaction_date) = YEAR(NOW())
        ");
        $dashboard['revenue_this_month'] = floatval($stmt->fetchColumn());

        // Overdue invoices
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM student_fees 
            WHERE status != 'paid' AND due_date < CURDATE()
        ");
        $dashboard['overdue_invoices'] = intval($stmt->fetchColumn());
    }

    $api->success($dashboard);

} catch (PDOException $e) {
    $api->error('Database error', 500);
}
