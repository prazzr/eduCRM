<?php
/**
 * Inquiries API Endpoint
 * 
 * GET    /api/v1/inquiries/index.php         - List inquiries (paginated)
 * GET    /api/v1/inquiries/index.php?id=123  - Get single inquiry
 * POST   /api/v1/inquiries/index.php         - Create new inquiry
 * PUT    /api/v1/inquiries/index.php?id=123  - Update inquiry
 * DELETE /api/v1/inquiries/index.php?id=123  - Delete inquiry
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../ApiController.php';
require_once __DIR__ . '/../../../app/services/LeadScoringService.php';

$api = new ApiController($pdo);

// Require authentication for all endpoints
$user = $api->requireAuth();

// Only admin and counselor can access inquiries
$api->requireRole(['admin', 'counselor']);

$method = $api->getMethod();
$id = $api->getParam('id');

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                // Get single inquiry (with FK JOINs)
                $stmt = $pdo->prepare("
                    SELECT i.*, u.name as assigned_name,
                           COALESCE(c.name, i.intended_country) as intended_country,
                           COALESCE(el.name, i.education_level) as education_level,
                           COALESCE(ist.name, i.status) as status,
                           COALESCE(pl.name, i.priority) as priority
                    FROM inquiries i
                    LEFT JOIN users u ON i.assigned_to = u.id
                    LEFT JOIN countries c ON i.country_id = c.id
                    LEFT JOIN education_levels el ON i.education_level_id = el.id
                    LEFT JOIN inquiry_statuses ist ON i.status_id = ist.id
                    LEFT JOIN priority_levels pl ON i.priority_id = pl.id
                    WHERE i.id = ?
                ");
                $stmt->execute([$id]);
                $inquiry = $stmt->fetch();
                
                if (!$inquiry) {
                    $api->error('Inquiry not found', 404);
                }
                
                $api->success($inquiry);
            } else {
                // List inquiries with pagination
                $page = max(1, intval($api->getParam('page', 1)));
                $perPage = min(100, max(1, intval($api->getParam('per_page', 20))));
                $offset = ($page - 1) * $perPage;
                
                // Filters
                $status = $api->getParam('status');
                $priority = $api->getParam('priority');
                $search = $api->getParam('search');
                
                $where = [];
                $params = [];
                
                if ($status) {
                    // Filter by status name (supports FK and legacy)
                    $where[] = "(ist.name = ? OR i.status = ?)";
                    $params[] = $status;
                    $params[] = $status;
                }
                
                if ($priority) {
                    // Filter by priority name (supports FK and legacy)
                    $where[] = "(pl.name = ? OR i.priority = ?)";
                    $params[] = $priority;
                    $params[] = $priority;
                }
                
                if ($search) {
                    $where[] = "(i.name LIKE ? OR i.email LIKE ? OR i.phone LIKE ?)";
                    $searchTerm = "%$search%";
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
                
                $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
                
                // Get total count
                $countStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM inquiries i 
                    LEFT JOIN inquiry_statuses ist ON i.status_id = ist.id
                    LEFT JOIN priority_levels pl ON i.priority_id = pl.id
                    $whereClause
                ");
                $countStmt->execute($params);
                $total = $countStmt->fetchColumn();
                
                // Get paginated data (with FK JOINs)
                $params[] = $perPage;
                $params[] = $offset;
                
                $dataStmt = $pdo->prepare("
                    SELECT i.*, u.name as assigned_name,
                           COALESCE(c.name, i.intended_country) as intended_country,
                           COALESCE(el.name, i.education_level) as education_level,
                           COALESCE(ist.name, i.status) as status,
                           COALESCE(pl.name, i.priority) as priority
                    FROM inquiries i
                    LEFT JOIN users u ON i.assigned_to = u.id
                    LEFT JOIN countries c ON i.country_id = c.id
                    LEFT JOIN education_levels el ON i.education_level_id = el.id
                    LEFT JOIN inquiry_statuses ist ON i.status_id = ist.id
                    LEFT JOIN priority_levels pl ON i.priority_id = pl.id
                    $whereClause
                    ORDER BY i.created_at DESC
                    LIMIT ? OFFSET ?
                ");
                $dataStmt->execute($params);
                $inquiries = $dataStmt->fetchAll();
                
                $api->paginate($inquiries, $total, $page, $perPage);
            }
            break;
            
        case 'POST':
            // Create new inquiry
            $body = $api->getJsonBody();
            
            $required = ['name'];
            foreach ($required as $field) {
                if (empty($body[$field])) {
                    $api->error("Field '$field' is required", 400);
                }
            }
            
            // Prepare data
            $name = htmlspecialchars($body['name']);
            $email = $body['email'] ?? null;
            $phone = $body['phone'] ?? null;
            $intendedCourse = $body['intended_course'] ?? null;
            $assignedTo = $body['assigned_to'] ?? null;
            
            // Lookup FK IDs from names if provided
            $countryId = null;
            if (!empty($body['intended_country'])) {
                $countryStmt = $pdo->prepare("SELECT id FROM countries WHERE name = ?");
                $countryStmt->execute([$body['intended_country']]);
                $countryId = $countryStmt->fetchColumn() ?: null;
            }
            
            $educationLevelId = null;
            if (!empty($body['education_level'])) {
                $eduStmt = $pdo->prepare("SELECT id FROM education_levels WHERE name = ?");
                $eduStmt->execute([$body['education_level']]);
                $educationLevelId = $eduStmt->fetchColumn() ?: null;
            }
            
            // Get default status ID (new)
            $statusStmt = $pdo->prepare("SELECT id FROM inquiry_statuses WHERE name = 'new'");
            $statusStmt->execute();
            $statusId = $statusStmt->fetchColumn() ?: 1;
            
            $stmt = $pdo->prepare("
                INSERT INTO inquiries 
                (name, email, phone, country_id, intended_course, education_level_id, assigned_to, status_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $email, $phone, $countryId, $intendedCourse, $educationLevelId, $assignedTo, $statusId]);
            
            $newId = $pdo->lastInsertId();
            
            // Calculate lead score
            $leadService = new \EduCRM\Services\LeadScoringService($pdo);
            $leadService->updateInquiryScore($newId);
            
            // Fetch created inquiry (with FK JOINs)
            $stmt = $pdo->prepare("
                SELECT i.*, 
                       COALESCE(c.name, i.intended_country) as intended_country,
                       COALESCE(el.name, i.education_level) as education_level,
                       COALESCE(ist.name, i.status) as status,
                       COALESCE(pl.name, i.priority) as priority
                FROM inquiries i
                LEFT JOIN countries c ON i.country_id = c.id
                LEFT JOIN education_levels el ON i.education_level_id = el.id
                LEFT JOIN inquiry_statuses ist ON i.status_id = ist.id
                LEFT JOIN priority_levels pl ON i.priority_id = pl.id
                WHERE i.id = ?
            ");
            $stmt->execute([$newId]);
            $inquiry = $stmt->fetch();
            
            $api->success($inquiry, 201);
            break;
            
        case 'PUT':
            if (!$id) {
                $api->error('Inquiry ID required', 400);
            }
            
            // Check inquiry exists
            $checkStmt = $pdo->prepare("SELECT id FROM inquiries WHERE id = ?");
            $checkStmt->execute([$id]);
            if (!$checkStmt->fetch()) {
                $api->error('Inquiry not found', 404);
            }
            
            $body = $api->getJsonBody();
            
            // Build update query dynamically with FK support
            $updates = [];
            $params = [];
            
            // Handle basic fields
            $basicFields = ['name', 'email', 'phone', 'intended_course', 'assigned_to', 'notes'];
            foreach ($basicFields as $field) {
                if (isset($body[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $body[$field];
                }
            }
            
            // Handle FK fields - lookup IDs from names
            if (isset($body['intended_country'])) {
                $countryStmt = $pdo->prepare("SELECT id FROM countries WHERE name = ?");
                $countryStmt->execute([$body['intended_country']]);
                $countryId = $countryStmt->fetchColumn();
                if ($countryId) {
                    $updates[] = "country_id = ?";
                    $params[] = $countryId;
                }
            }
            
            if (isset($body['education_level'])) {
                $eduStmt = $pdo->prepare("SELECT id FROM education_levels WHERE name = ?");
                $eduStmt->execute([$body['education_level']]);
                $eduId = $eduStmt->fetchColumn();
                if ($eduId) {
                    $updates[] = "education_level_id = ?";
                    $params[] = $eduId;
                }
            }
            
            if (isset($body['status'])) {
                $statusStmt = $pdo->prepare("SELECT id FROM inquiry_statuses WHERE name = ?");
                $statusStmt->execute([$body['status']]);
                $statusId = $statusStmt->fetchColumn();
                if ($statusId) {
                    $updates[] = "status_id = ?";
                    $params[] = $statusId;
                }
            }
            
            if (empty($updates)) {
                $api->error('No valid fields to update', 400);
            }
            
            $params[] = $id;
            $sql = "UPDATE inquiries SET " . implode(", ", $updates) . " WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Recalculate score
            $leadService = new \EduCRM\Services\LeadScoringService($pdo);
            $leadService->updateInquiryScore($id);
            
            // Return updated inquiry (with FK JOINs)
            $stmt = $pdo->prepare("
                SELECT i.*, 
                       COALESCE(c.name, i.intended_country) as intended_country,
                       COALESCE(el.name, i.education_level) as education_level,
                       COALESCE(ist.name, i.status) as status,
                       COALESCE(pl.name, i.priority) as priority
                FROM inquiries i
                LEFT JOIN countries c ON i.country_id = c.id
                LEFT JOIN education_levels el ON i.education_level_id = el.id
                LEFT JOIN inquiry_statuses ist ON i.status_id = ist.id
                LEFT JOIN priority_levels pl ON i.priority_id = pl.id
                WHERE i.id = ?
            ");
            $stmt->execute([$id]);
            $inquiry = $stmt->fetch();
            
            $api->success($inquiry);
            break;
            
        case 'DELETE':
            if (!$id) {
                $api->error('Inquiry ID required', 400);
            }
            
            // Only admin can delete
            if (!in_array('admin', $user['roles'])) {
                $api->error('Only admins can delete inquiries', 403);
            }
            
            $stmt = $pdo->prepare("DELETE FROM inquiries WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() === 0) {
                $api->error('Inquiry not found', 404);
            }
            
            $api->success(['deleted' => true]);
            break;
            
        default:
            $api->error('Method not allowed', 405);
    }
    
} catch (PDOException $e) {
    $api->error('Database error', 500);
}
