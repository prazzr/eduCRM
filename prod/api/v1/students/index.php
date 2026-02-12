<?php
/**
 * Students API Endpoint
 * 
 * GET /api/v1/students/index.php         - List students (paginated)
 * GET /api/v1/students/index.php?id=123  - Get student profile with details
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../ApiController.php';

$api = new ApiController($pdo);

// Require authentication
$user = $api->requireAuth();

$method = $api->getMethod();
$id = $api->getParam('id');

// Only GET methods supported
if ($method !== 'GET') {
    $api->error('Method not allowed', 405);
}

try {
    if ($id) {
        // Get single student profile
        // Students can only view their own profile, admin/counselor can view all
        if (in_array('student', $user['roles']) && 
            !in_array('admin', $user['roles']) && 
            !in_array('counselor', $user['roles']) &&
            $user['id'] != $id) {
            $api->error('You can only view your own profile', 403);
        }
        
        // Get student info (with FK JOINs)
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.phone, 
                   COALESCE(c.name, u.country) as country,
                   COALESCE(el.name, u.education_level) as education_level,
                   u.created_at
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            LEFT JOIN countries c ON u.country_id = c.id
            LEFT JOIN education_levels el ON u.education_level_id = el.id
            WHERE u.id = ? AND r.name = 'student'
        ");
        $stmt->execute([$id]);
        $student = $stmt->fetch();
        
        if (!$student) {
            $api->error('Student not found', 404);
        }
        
        // Get enrollments
        $enrollStmt = $pdo->prepare("
            SELECT c.id as class_id, c.name as class_name, co.name as course_name, e.enrolled_at
            FROM enrollments e
            JOIN classes c ON e.class_id = c.id
            JOIN courses co ON c.course_id = co.id
            WHERE e.student_id = ?
            ORDER BY e.enrolled_at DESC
        ");
        $enrollStmt->execute([$id]);
        $student['enrollments'] = $enrollStmt->fetchAll();
        
        // Get visa workflow (with FK JOINs)
        $visaStmt = $pdo->prepare("
            SELECT COALESCE(c.name, vw.country) as country, 
                   COALESCE(vs.name, vw.current_stage) as current_stage, 
                   vw.notes, vw.updated_at
            FROM visa_workflows vw
            LEFT JOIN countries c ON vw.country_id = c.id
            LEFT JOIN visa_stages vs ON vw.stage_id = vs.id
            WHERE vw.student_id = ?
        ");
        $visaStmt->execute([$id]);
        $student['visa_status'] = $visaStmt->fetch() ?: null;
        
        // Get test scores
        $scoresStmt = $pdo->prepare("
            SELECT test_type, overall_score, listening, reading, writing, speaking, test_date
            FROM test_scores
            WHERE student_id = ?
            ORDER BY created_at DESC
        ");
        $scoresStmt->execute([$id]);
        $student['test_scores'] = $scoresStmt->fetchAll();
        
        // Get fee summary
        $feeStmt = $pdo->prepare("
            SELECT 
                SUM(amount) as total_fees,
                (SELECT COALESCE(SUM(p.amount), 0) FROM payments p 
                 JOIN student_fees sf2 ON p.student_fee_id = sf2.id 
                 WHERE sf2.student_id = ?) as total_paid
            FROM student_fees
            WHERE student_id = ?
        ");
        $feeStmt->execute([$id, $id]);
        $feeSummary = $feeStmt->fetch();
        
        $student['financial'] = [
            'total_fees' => floatval($feeSummary['total_fees'] ?? 0),
            'total_paid' => floatval($feeSummary['total_paid'] ?? 0),
            'balance' => floatval(($feeSummary['total_fees'] ?? 0) - ($feeSummary['total_paid'] ?? 0))
        ];
        
        $api->success($student);
        
    } else {
        // List students (admin/counselor only)
        $api->requireRole(['admin', 'counselor']);
        
        $page = max(1, intval($api->getParam('page', 1)));
        $perPage = min(100, max(1, intval($api->getParam('per_page', 20))));
        $offset = ($page - 1) * $perPage;
        
        // Filters
        $search = $api->getParam('search');
        $country = $api->getParam('country');
        
        $where = ["r.name = 'student'"];
        $params = [];
        
        if ($search) {
            $where[] = "(u.name LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($country) {
            // Filter by country name (supports both FK and legacy)
            $where[] = "(c.name = ? OR u.country = ?)";
            $params[] = $country;
            $params[] = $country;
        }
        
        $whereClause = "WHERE " . implode(" AND ", $where);
        
        // Get total count
        $countStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT u.id) 
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            LEFT JOIN countries c ON u.country_id = c.id
            $whereClause
        ");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();
        
        // Get paginated data (with FK JOINs)
        $params[] = $perPage;
        $params[] = $offset;
        
        $dataStmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.name, u.email, u.phone, 
                   COALESCE(c.name, u.country) as country, 
                   COALESCE(el.name, u.education_level) as education_level, 
                   u.created_at
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            LEFT JOIN countries c ON u.country_id = c.id
            LEFT JOIN education_levels el ON u.education_level_id = el.id
            $whereClause
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $dataStmt->execute($params);
        $students = $dataStmt->fetchAll();
        
        $api->paginate($students, $total, $page, $perPage);
    }
    
} catch (PDOException $e) {
    $api->error('Database error', 500);
}
