# Phase 1 Implementation Plan: Quick Wins (2 Weeks)

> **Timeline**: January 22, 2026 - February 5, 2026  
> **Priority**: 🔴 High Impact, Low Effort  
> **Goal**: Improve performance and usability with minimal risk

---

## 📋 Overview

Phase 1 focuses on **quick wins** that provide immediate value without major architectural changes. These improvements address the most common user pain points and performance bottlenecks.

| # | Feature | Estimated Effort | Module | Status |
|---|---------|-----------------|--------|--------|
| 1 | Server-Side Pagination | 3 days | Tasks, Inquiries, Students | ⬜ Not Started |
| 2 | Students Bulk Actions | 2 days | Students | ⬜ Not Started |
| 3 | Document Expiry Alerts | 2 days | Documents | ⬜ Not Started |
| 4 | Last Contacted Column | 1 day | Inquiries | ⬜ Not Started |

**Total Estimated Effort**: 8 working days

---

## 1️⃣ Server-Side Pagination

### 1.1 Problem Statement
Currently, list pages fetch ALL records and render them at once. With thousands of records, this causes:
- Slow page loads (5-10+ seconds)
- High memory usage on server
- Poor user experience
- Browser crashes on low-end devices

### 1.2 Scope

| Module | File | Current Method | Records Fetched |
|--------|------|---------------|-----------------|
| Tasks | `modules/tasks/list.php` | `getAllTasks()` / `getUserTasks()` | Unlimited |
| Inquiries | `modules/inquiries/list.php` | Direct SQL query | Unlimited |
| Students | `modules/students/list.php` | Direct SQL query | Unlimited |

### 1.3 Technical Specification

#### Database Changes
None required - pagination is handled at query level.

#### Service Layer Changes

**File**: `app/Services/PaginationService.php` (NEW)

```php
<?php
namespace EduCRM\Services;

class PaginationService
{
    private $pdo;
    private $perPage;
    private $currentPage;
    private $totalRecords;
    private $totalPages;

    public function __construct(\PDO $pdo, int $perPage = 20)
    {
        $this->pdo = $pdo;
        $this->perPage = $perPage;
        $this->currentPage = max(1, (int)($_GET['page'] ?? 1));
    }

    public function paginate(string $baseQuery, array $params = [], string $countQuery = null): array
    {
        // Calculate total records
        $countSql = $countQuery ?? "SELECT COUNT(*) FROM ({$baseQuery}) as count_table";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $this->totalRecords = (int)$stmt->fetchColumn();
        $this->totalPages = ceil($this->totalRecords / $this->perPage);

        // Get paginated results
        $offset = ($this->currentPage - 1) * $this->perPage;
        $paginatedQuery = $baseQuery . " LIMIT {$this->perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($paginatedQuery);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getMetadata(): array
    {
        return [
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'total_records' => $this->totalRecords,
            'total_pages' => $this->totalPages,
            'has_previous' => $this->currentPage > 1,
            'has_next' => $this->currentPage < $this->totalPages,
        ];
    }

    public function renderLinks(string $baseUrl = ''): string
    {
        // Returns HTML for pagination controls
    }
}
```

#### TaskService Changes

**File**: `app/Services/TaskService.php`

Add new methods:
```php
public function getPaginatedTasks(int $page = 1, int $perPage = 20, array $filters = []): array
{
    $pagination = new PaginationService($this->pdo, $perPage);
    
    $where = ['1=1'];
    $params = [];
    
    if (!empty($filters['status'])) {
        $where[] = 't.status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['assigned_to'])) {
        $where[] = 't.assigned_to = ?';
        $params[] = $filters['assigned_to'];
    }
    if (!empty($filters['priority'])) {
        $where[] = 't.priority = ?';
        $params[] = $filters['priority'];
    }
    
    $query = "SELECT t.*, u.name as assigned_name 
              FROM tasks t 
              LEFT JOIN users u ON t.assigned_to = u.id 
              WHERE " . implode(' AND ', $where) . "
              ORDER BY t.due_date ASC, t.priority DESC";
    
    $tasks = $pagination->paginate($query, $params);
    
    return [
        'data' => $tasks,
        'pagination' => $pagination->getMetadata()
    ];
}
```

### 1.4 UI Component

**File**: `templates/partials/pagination.php` (NEW)

```php
<?php
/**
 * Pagination Component
 * Usage: <?php include 'templates/partials/pagination.php'; ?>
 * Required: $pagination array with metadata
 */
if (!isset($pagination) || $pagination['total_pages'] <= 1) return;

$baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
$queryParams = $_GET;
unset($queryParams['page']);
$queryString = http_build_query($queryParams);
$urlPrefix = $baseUrl . ($queryString ? "?{$queryString}&" : '?');
?>

<div class="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6 mt-4 rounded-lg">
    <div class="flex flex-1 justify-between sm:hidden">
        <!-- Mobile pagination -->
    </div>
    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-slate-700">
                Showing <span class="font-medium"><?php echo (($pagination['current_page'] - 1) * $pagination['per_page']) + 1; ?></span>
                to <span class="font-medium"><?php echo min($pagination['current_page'] * $pagination['per_page'], $pagination['total_records']); ?></span>
                of <span class="font-medium"><?php echo $pagination['total_records']; ?></span> results
            </p>
        </div>
        <div>
            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                <!-- Previous -->
                <?php if ($pagination['has_previous']): ?>
                    <a href="<?php echo $urlPrefix . 'page=' . ($pagination['current_page'] - 1); ?>" 
                       class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                        <span class="sr-only">Previous</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                        </svg>
                    </a>
                <?php endif; ?>
                
                <!-- Page Numbers -->
                <?php
                $range = 2;
                $start = max(1, $pagination['current_page'] - $range);
                $end = min($pagination['total_pages'], $pagination['current_page'] + $range);
                
                for ($i = $start; $i <= $end; $i++):
                    $isActive = $i === $pagination['current_page'];
                ?>
                    <a href="<?php echo $urlPrefix . 'page=' . $i; ?>" 
                       class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo $isActive ? 'z-10 bg-primary-600 text-white' : 'text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <!-- Next -->
                <?php if ($pagination['has_next']): ?>
                    <a href="<?php echo $urlPrefix . 'page=' . ($pagination['current_page'] + 1); ?>" 
                       class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                        <span class="sr-only">Next</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                        </svg>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </div>
</div>
```

### 1.5 Implementation Steps

#### Day 1: Foundation
1. [ ] Create `PaginationService.php`
2. [ ] Create `templates/partials/pagination.php` component
3. [ ] Add helper function `paginate()` in `app/helpers.php`
4. [ ] Write unit tests for PaginationService

#### Day 2: Tasks Module
1. [ ] Update `TaskService.php` with `getPaginatedTasks()`
2. [ ] Modify `modules/tasks/list.php` to use pagination
3. [ ] Update Alpine.js search to work with paginated data
4. [ ] Test with 1000+ task records

#### Day 3: Inquiries & Students Modules
1. [ ] Create `InquiryService::getPaginatedInquiries()`
2. [ ] Update `modules/inquiries/list.php`
3. [ ] Update `modules/students/list.php`
4. [ ] Ensure filters work with pagination
5. [ ] End-to-end testing

### 1.6 Testing Criteria

| Test Case | Expected Result |
|-----------|-----------------|
| Load page with 5000 records | Page loads in < 2 seconds |
| Click "Next" page | Shows next 20 records correctly |
| Apply filter + pagination | Filter persists across pages |
| Direct URL access `?page=5` | Shows page 5 correctly |
| Page number exceeds total | Redirects to last valid page |
| Mobile responsiveness | Pagination works on mobile |

### 1.7 Rollback Plan
If issues occur:
1. Revert to previous `list.php` files
2. Remove pagination parameter handling
3. All data stored in database remains unchanged

---

## 2️⃣ Students Bulk Actions

### 2.1 Problem Statement
The Students module lacks bulk action capabilities that exist in Inquiries and Tasks modules. Staff must perform repetitive actions one student at a time.

### 2.2 Required Bulk Actions

| Action | Description | Access Level |
|--------|-------------|--------------|
| Bulk Email | Send email to selected students | Counselor+ |
| Bulk SMS | Send SMS via messaging gateway | Counselor+ |
| Add to Class | Enroll selected students in a class | Admin, Counselor |
| Change Status | Update status (Active/Inactive/Alumni) | Admin |
| Export Selected | Download CSV of selected students | All |
| Bulk Delete | Delete multiple students | Admin only |

### 2.3 Technical Specification

#### Database Changes

**File**: `database/migrations/add_student_status.sql` (if not exists)

```sql
-- Add status column if missing
ALTER TABLE users ADD COLUMN IF NOT EXISTS status 
    ENUM('active', 'inactive', 'alumni', 'suspended') 
    DEFAULT 'active' AFTER email;

CREATE INDEX idx_users_status ON users(status);
```

#### Service Layer

**File**: `app/Services/StudentBulkService.php` (NEW)

```php
<?php
namespace EduCRM\Services;

class StudentBulkService
{
    private $pdo;
    private $emailService;
    private $messagingService;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function bulkEmail(array $studentIds, string $subject, string $body): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
        
        $stmt = $this->pdo->prepare("SELECT id, name, email FROM users WHERE id IN (" . implode(',', array_fill(0, count($studentIds), '?')) . ")");
        $stmt->execute($studentIds);
        $students = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($students as $student) {
            try {
                // Queue email
                $this->queueEmail($student['email'], $subject, $body, ['name' => $student['name']]);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Failed for {$student['email']}: {$e->getMessage()}";
            }
        }
        
        return $results;
    }

    public function bulkEnroll(array $studentIds, int $classId): array
    {
        $results = ['success' => 0, 'failed' => 0, 'already_enrolled' => 0];
        
        foreach ($studentIds as $studentId) {
            // Check if already enrolled
            $check = $this->pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND class_id = ?");
            $check->execute([$studentId, $classId]);
            
            if ($check->fetch()) {
                $results['already_enrolled']++;
                continue;
            }
            
            try {
                $stmt = $this->pdo->prepare("INSERT INTO enrollments (student_id, class_id, enrolled_at) VALUES (?, ?, NOW())");
                $stmt->execute([$studentId, $classId]);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
            }
        }
        
        return $results;
    }

    public function bulkUpdateStatus(array $studentIds, string $status): int
    {
        $validStatuses = ['active', 'inactive', 'alumni', 'suspended'];
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }
        
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $this->pdo->prepare("UPDATE users SET status = ? WHERE id IN ({$placeholders})");
        $params = array_merge([$status], $studentIds);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }

    public function exportToCsv(array $studentIds): string
    {
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.name, u.email, u.phone, c.name as country, el.name as education_level
            FROM users u
            LEFT JOIN countries c ON u.country_id = c.id
            LEFT JOIN education_levels el ON u.education_level_id = el.id
            WHERE u.id IN ({$placeholders})
        ");
        $stmt->execute($studentIds);
        $students = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $filename = 'students_export_' . date('Y-m-d_His') . '.csv';
        $filepath = __DIR__ . '/../../storage/exports/' . $filename;
        
        $fp = fopen($filepath, 'w');
        fputcsv($fp, ['ID', 'Name', 'Email', 'Phone', 'Country', 'Education Level']);
        foreach ($students as $student) {
            fputcsv($fp, $student);
        }
        fclose($fp);
        
        return $filename;
    }
}
```

### 2.4 UI Changes

**File**: `modules/students/list.php`

Add bulk action toolbar (similar to tasks/inquiries):

```php
<!-- Bulk Action Toolbar -->
<div x-show="selectedIds.length > 0" 
     x-transition
     class="fixed bottom-6 left-1/2 transform -translate-x-1/2 bg-slate-800 text-white px-6 py-3 rounded-full shadow-2xl flex items-center gap-4 z-50">
    <span class="text-sm font-medium" x-text="selectedIds.length + ' selected'"></span>
    <div class="h-4 w-px bg-slate-600"></div>
    
    <button @click="showBulkEmailModal()" class="flex items-center gap-2 hover:text-primary-300">
        <?php echo getIcon('mail', 16); ?> Email
    </button>
    
    <button @click="showBulkSmsModal()" class="flex items-center gap-2 hover:text-primary-300">
        <?php echo getIcon('message-square', 16); ?> SMS
    </button>
    
    <button @click="showEnrollModal()" class="flex items-center gap-2 hover:text-primary-300">
        <?php echo getIcon('user-plus', 16); ?> Add to Class
    </button>
    
    <button @click="exportSelected()" class="flex items-center gap-2 hover:text-primary-300">
        <?php echo getIcon('download', 16); ?> Export
    </button>
    
    <?php if (hasRole('admin')): ?>
    <button @click="showStatusModal()" class="flex items-center gap-2 hover:text-primary-300">
        <?php echo getIcon('edit-2', 16); ?> Status
    </button>
    <button @click="confirmBulkDelete()" class="flex items-center gap-2 hover:text-red-400">
        <?php echo getIcon('trash-2', 16); ?> Delete
    </button>
    <?php endif; ?>
    
    <button @click="selectedIds = []" class="ml-2 text-slate-400 hover:text-white">
        <?php echo getIcon('x', 16); ?>
    </button>
</div>
```

### 2.5 API Endpoints

**File**: `modules/students/bulk_actions.php` (NEW)

```php
<?php
require_once '../../app/bootstrap.php';
requireLogin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$studentIds = json_decode($_POST['student_ids'] ?? '[]', true);

if (empty($studentIds)) {
    echo json_encode(['success' => false, 'message' => 'No students selected']);
    exit;
}

$bulkService = new \EduCRM\Services\StudentBulkService($pdo);

try {
    switch ($action) {
        case 'email':
            requireAdminCounselorOrBranchManager();
            $result = $bulkService->bulkEmail($studentIds, $_POST['subject'], $_POST['body']);
            break;
            
        case 'enroll':
            requireAdminCounselorOrBranchManager();
            $result = $bulkService->bulkEnroll($studentIds, (int)$_POST['class_id']);
            break;
            
        case 'status':
            requireAdmin();
            $count = $bulkService->bulkUpdateStatus($studentIds, $_POST['status']);
            $result = ['success' => true, 'updated' => $count];
            break;
            
        case 'export':
            $filename = $bulkService->exportToCsv($studentIds);
            $result = ['success' => true, 'filename' => $filename];
            break;
            
        case 'delete':
            requireAdmin();
            // Implement with proper cascade handling
            break;
            
        default:
            throw new \Exception('Invalid action');
    }
    
    echo json_encode($result);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
```

### 2.6 Implementation Steps

#### Day 1: Backend
1. [ ] Create `StudentBulkService.php`
2. [ ] Create `modules/students/bulk_actions.php` endpoint
3. [ ] Add database migration for status column if needed
4. [ ] Write unit tests

#### Day 2: Frontend
1. [ ] Add checkbox column to student table
2. [ ] Add Alpine.js selection management
3. [ ] Create bulk action toolbar component
4. [ ] Create modals for each action (Email, SMS, Enroll, Status)
5. [ ] Implement export download handling
6. [ ] End-to-end testing

### 2.7 Testing Criteria

| Test Case | Expected Result |
|-----------|-----------------|
| Select 10 students, click Email | Email modal opens with count |
| Send bulk email | All 10 students receive email |
| Enroll in class already enrolled | Shows "already enrolled" count |
| Export 50 students | CSV downloads with all fields |
| Non-admin clicks Delete | Button not visible |
| Clear selection | Toolbar disappears |

---

## 3️⃣ Document Expiry Alerts

### 3.1 Problem Statement
Critical documents (Passports, Visas, Insurance) have expiry dates. Currently:
- No tracking of expiry dates
- No proactive alerts
- Manual checking required
- Compliance risks

### 3.2 Scope

Documents to track:
- Passport (typically expires in 10 years, critical 6 months before travel)
- Visa (various validity periods)
- Health Insurance
- Offer Letters (acceptance deadlines)
- Test Score Reports (usually valid 2 years)

### 3.3 Technical Specification

#### Database Changes

**File**: `database/migrations/add_document_expiry.sql`

```sql
-- Add expiry tracking to documents table
ALTER TABLE documents 
ADD COLUMN expiry_date DATE NULL AFTER description,
ADD COLUMN expiry_alert_sent TINYINT(1) DEFAULT 0 AFTER expiry_date,
ADD COLUMN expiry_alert_days INT DEFAULT 30 AFTER expiry_alert_sent;

-- Create index for efficient querying
CREATE INDEX idx_documents_expiry ON documents(expiry_date, expiry_alert_sent);

-- Create document expiry alerts table for tracking
CREATE TABLE IF NOT EXISTS document_expiry_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    alert_type ENUM('30_days', '14_days', '7_days', 'expired') NOT NULL,
    sent_at DATETIME NOT NULL,
    sent_to INT NOT NULL,
    channel ENUM('email', 'notification', 'sms') DEFAULT 'notification',
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (sent_to) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_alert (document_id, alert_type)
);
```

#### Service Layer

**File**: `app/Services/DocumentExpiryService.php` (NEW)

```php
<?php
namespace EduCRM\Services;

class DocumentExpiryService
{
    private $pdo;
    private $notificationService;

    private const ALERT_THRESHOLDS = [
        '30_days' => 30,
        '14_days' => 14,
        '7_days' => 7,
        'expired' => 0
    ];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get documents expiring within given days
     */
    public function getExpiringDocuments(int $daysAhead = 30): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, u.name as student_name, u.email as student_email,
                   DATEDIFF(d.expiry_date, CURDATE()) as days_until_expiry
            FROM documents d
            JOIN users u ON d.entity_type = 'student' AND d.entity_id = u.id
            WHERE d.expiry_date IS NOT NULL
              AND d.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
              AND d.expiry_date >= CURDATE()
            ORDER BY d.expiry_date ASC
        ");
        $stmt->execute([$daysAhead]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get already expired documents
     */
    public function getExpiredDocuments(): array
    {
        $stmt = $this->pdo->query("
            SELECT d.*, u.name as student_name, u.email as student_email,
                   DATEDIFF(CURDATE(), d.expiry_date) as days_expired
            FROM documents d
            JOIN users u ON d.entity_type = 'student' AND d.entity_id = u.id
            WHERE d.expiry_date IS NOT NULL
              AND d.expiry_date < CURDATE()
            ORDER BY d.expiry_date DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Process and send expiry alerts (called by cron)
     */
    public function processExpiryAlerts(): array
    {
        $results = ['alerts_sent' => 0, 'errors' => []];

        foreach (self::ALERT_THRESHOLDS as $alertType => $days) {
            $documents = $this->getDocumentsForAlert($alertType, $days);
            
            foreach ($documents as $doc) {
                try {
                    $this->sendExpiryAlert($doc, $alertType);
                    $this->recordAlertSent($doc['id'], $alertType, $doc['uploaded_by']);
                    $results['alerts_sent']++;
                } catch (\Exception $e) {
                    $results['errors'][] = "Doc #{$doc['id']}: {$e->getMessage()}";
                }
            }
        }

        return $results;
    }

    private function getDocumentsForAlert(string $alertType, int $days): array
    {
        $dateCondition = $days === 0 
            ? "d.expiry_date = CURDATE()" 
            : "d.expiry_date = DATE_ADD(CURDATE(), INTERVAL {$days} DAY)";

        $stmt = $this->pdo->prepare("
            SELECT d.*, u.name as student_name, u.email as student_email
            FROM documents d
            JOIN users u ON d.entity_type = 'student' AND d.entity_id = u.id
            LEFT JOIN document_expiry_alerts dea ON d.id = dea.document_id AND dea.alert_type = ?
            WHERE d.expiry_date IS NOT NULL
              AND {$dateCondition}
              AND dea.id IS NULL
        ");
        $stmt->execute([$alertType]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function sendExpiryAlert(array $document, string $alertType): void
    {
        $daysText = match($alertType) {
            '30_days' => '30 days',
            '14_days' => '14 days',
            '7_days' => '7 days',
            'expired' => 'TODAY (EXPIRED)'
        };

        // Create in-app notification
        $notification = [
            'user_id' => $document['uploaded_by'],
            'type' => 'document_expiry',
            'title' => "Document Expiring: {$document['file_name']}",
            'message' => "The document '{$document['file_name']}' for {$document['student_name']} expires in {$daysText}.",
            'link' => "/modules/documents/view.php?id={$document['id']}",
            'priority' => $alertType === 'expired' || $alertType === '7_days' ? 'high' : 'normal'
        ];

        // Insert notification
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, priority, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute(array_values($notification));
    }

    private function recordAlertSent(int $documentId, string $alertType, int $userId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO document_expiry_alerts (document_id, alert_type, sent_at, sent_to)
            VALUES (?, ?, NOW(), ?)
        ");
        $stmt->execute([$documentId, $alertType, $userId]);
    }

    /**
     * Get expiry summary for dashboard widget
     */
    public function getExpirySummary(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                SUM(CASE WHEN expiry_date < CURDATE() THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as expiring_7_days,
                SUM(CASE WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_30_days
            FROM documents
            WHERE expiry_date IS NOT NULL
        ");
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
```

#### Cron Job

**File**: `cron/document_expiry_check.php` (NEW)

```php
<?php
/**
 * Document Expiry Alert Cron
 * Run daily: 0 8 * * * php /path/to/cron/document_expiry_check.php
 */

require_once __DIR__ . '/../app/bootstrap.php';

$expiryService = new \EduCRM\Services\DocumentExpiryService($pdo);
$results = $expiryService->processExpiryAlerts();

echo "Document Expiry Check Complete\n";
echo "Alerts Sent: {$results['alerts_sent']}\n";
if (!empty($results['errors'])) {
    echo "Errors:\n";
    foreach ($results['errors'] as $error) {
        echo "  - {$error}\n";
    }
}
```

### 3.4 UI Changes

#### Upload Form Update

**File**: `modules/documents/upload.php`

Add expiry date field:
```php
<div class="form-group">
    <label for="expiry_date" class="form-label">
        Expiry Date <span class="text-slate-400 text-sm">(if applicable)</span>
    </label>
    <input type="date" name="expiry_date" id="expiry_date" 
           class="form-input" min="<?php echo date('Y-m-d'); ?>">
    <p class="text-xs text-slate-500 mt-1">
        Set for documents like Passports, Visas, Insurance policies
    </p>
</div>
```

#### Document List Update

**File**: `modules/documents/list.php`

Add expiry badge:
```php
<?php if ($doc['expiry_date']): 
    $daysUntil = (strtotime($doc['expiry_date']) - time()) / 86400;
    $badgeClass = $daysUntil < 0 ? 'bg-red-100 text-red-700' : 
                  ($daysUntil < 7 ? 'bg-orange-100 text-orange-700' : 
                  ($daysUntil < 30 ? 'bg-yellow-100 text-yellow-700' : 'bg-slate-100 text-slate-600'));
?>
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $badgeClass; ?>">
        <?php if ($daysUntil < 0): ?>
            Expired <?php echo abs(floor($daysUntil)); ?> days ago
        <?php else: ?>
            Expires <?php echo date('M j, Y', strtotime($doc['expiry_date'])); ?>
        <?php endif; ?>
    </span>
<?php endif; ?>
```

#### Dashboard Widget

Add expiry alert widget to dashboard:
```php
<!-- Document Expiry Alert Widget -->
<?php 
$expiryService = new \EduCRM\Services\DocumentExpiryService($pdo);
$expirySummary = $expiryService->getExpirySummary();
?>
<?php if ($expirySummary['expired'] > 0 || $expirySummary['expiring_7_days'] > 0): ?>
<div class="card border-l-4 border-l-red-500">
    <div class="p-4">
        <h3 class="font-semibold text-slate-800 flex items-center gap-2">
            <?php echo getIcon('alert-triangle', 18); ?> Document Alerts
        </h3>
        <div class="mt-3 space-y-2">
            <?php if ($expirySummary['expired'] > 0): ?>
                <a href="modules/documents/list.php?filter=expired" 
                   class="flex items-center justify-between text-red-600 hover:text-red-800">
                    <span><?php echo $expirySummary['expired']; ?> documents expired</span>
                    <span>→</span>
                </a>
            <?php endif; ?>
            <?php if ($expirySummary['expiring_7_days'] > 0): ?>
                <a href="modules/documents/list.php?filter=expiring_soon" 
                   class="flex items-center justify-between text-orange-600 hover:text-orange-800">
                    <span><?php echo $expirySummary['expiring_7_days']; ?> expiring within 7 days</span>
                    <span>→</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
```

### 3.5 Implementation Steps

#### Day 1: Database & Service
1. [ ] Run database migration for expiry columns
2. [ ] Create `DocumentExpiryService.php`
3. [ ] Create cron job script
4. [ ] Write unit tests

#### Day 2: UI & Integration
1. [ ] Update document upload form
2. [ ] Update document list with expiry badges
3. [ ] Add expiry filter to document list
4. [ ] Add dashboard widget
5. [ ] Configure cron job on server
6. [ ] End-to-end testing

### 3.6 Testing Criteria

| Test Case | Expected Result |
|-----------|-----------------|
| Upload document with expiry date | Date saved correctly |
| Document expires in 30 days | Yellow badge shown |
| Document expires in 7 days | Orange badge shown |
| Document expired yesterday | Red "Expired" badge shown |
| Cron runs at 8 AM | Notifications created for matching documents |
| Dashboard widget | Shows correct counts |
| Filter by "Expired" | Only expired documents shown |

---

## 4️⃣ Last Contacted Column (Inquiries)

### 4.1 Problem Statement
Counselors cannot easily identify which leads have been neglected. They must open each inquiry to see communication history.

### 4.2 Technical Specification

#### Database Query

The "last contacted" date should be derived from:
1. Last email sent
2. Last call logged
3. Last note added with contact type
4. Last appointment completed

#### Service Layer

**File**: `app/Services/InquiryService.php` (update existing or create)

```php
public function getInquiriesWithLastContact(array $filters = []): array
{
    $query = "
        SELECT i.*, 
               u.name as assigned_name,
               (
                   SELECT MAX(created_at) 
                   FROM (
                       SELECT created_at FROM email_queue WHERE recipient_email = i.email
                       UNION ALL
                       SELECT created_at FROM inquiry_notes WHERE inquiry_id = i.id AND note_type = 'call'
                       UNION ALL
                       SELECT completed_at as created_at FROM appointments 
                       WHERE entity_type = 'inquiry' AND entity_id = i.id AND status = 'completed'
                   ) as contacts
               ) as last_contacted_at
        FROM inquiries i
        LEFT JOIN users u ON i.assigned_to = u.id
        WHERE 1=1
    ";
    
    // Apply filters...
    
    $query .= " ORDER BY last_contacted_at ASC NULLS FIRST, i.created_at DESC";
    
    // This puts never-contacted leads at top
}
```

#### Simpler Alternative (Using inquiry_notes only)

If the above is too complex, use a simpler approach:

```sql
-- Add last_contacted column to inquiries table
ALTER TABLE inquiries ADD COLUMN last_contacted_at DATETIME NULL;

-- Update trigger or application logic to update this when:
-- 1. Note is added
-- 2. Email is sent
-- 3. Appointment is completed
```

### 4.3 UI Changes

**File**: `modules/inquiries/list.php`

Add column to table:
```php
<th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
    Last Contacted
</th>

<!-- In row -->
<td class="px-6 py-4 whitespace-nowrap text-sm">
    <?php if ($inquiry['last_contacted_at']): 
        $days = floor((time() - strtotime($inquiry['last_contacted_at'])) / 86400);
        $colorClass = $days > 7 ? 'text-red-600' : ($days > 3 ? 'text-orange-600' : 'text-slate-600');
    ?>
        <span class="<?php echo $colorClass; ?>">
            <?php if ($days === 0): ?>
                Today
            <?php elseif ($days === 1): ?>
                Yesterday
            <?php else: ?>
                <?php echo $days; ?> days ago
            <?php endif; ?>
        </span>
    <?php else: ?>
        <span class="text-red-500 font-medium">Never</span>
    <?php endif; ?>
</td>
```

Add filter option:
```php
<select name="contact_filter" class="form-select">
    <option value="">All</option>
    <option value="never">Never Contacted</option>
    <option value="week">Not contacted in 7+ days</option>
    <option value="month">Not contacted in 30+ days</option>
</select>
```

### 4.4 Implementation Steps

#### Day 1: Complete Implementation
1. [ ] Add `last_contacted_at` column to inquiries table
2. [ ] Update InquiryService with calculation logic
3. [ ] Update list.php with new column
4. [ ] Add color-coded relative time display
5. [ ] Add filter by contact recency
6. [ ] Update note/email/appointment handlers to update timestamp
7. [ ] Test with various scenarios

### 4.5 Testing Criteria

| Test Case | Expected Result |
|-----------|-----------------|
| Inquiry never contacted | Shows "Never" in red |
| Contacted today | Shows "Today" in gray |
| Contacted 5 days ago | Shows "5 days ago" in orange |
| Contacted 10 days ago | Shows "10 days ago" in red |
| Filter "Never Contacted" | Only shows uncontacted inquiries |
| Add note to inquiry | last_contacted_at updates |

---

## 📊 Phase 1 Summary

### Files to Create

| File | Description |
|------|-------------|
| `app/Services/PaginationService.php` | Reusable pagination logic |
| `app/Services/StudentBulkService.php` | Bulk actions for students |
| `app/Services/DocumentExpiryService.php` | Expiry tracking and alerts |
| `templates/partials/pagination.php` | Pagination UI component |
| `modules/students/bulk_actions.php` | Bulk action API endpoint |
| `cron/document_expiry_check.php` | Daily expiry check cron |
| `database/migrations/add_document_expiry.sql` | Schema changes |

### Files to Modify

| File | Changes |
|------|---------|
| `modules/tasks/list.php` | Add pagination |
| `modules/inquiries/list.php` | Add pagination + last contacted column |
| `modules/students/list.php` | Add pagination + bulk actions |
| `modules/documents/upload.php` | Add expiry date field |
| `modules/documents/list.php` | Add expiry badges and filter |
| `index.php` | Add document expiry widget |
| `app/Services/TaskService.php` | Add paginated methods |

### Estimated Timeline

| Week | Days | Tasks |
|------|------|-------|
| Week 1 | Day 1-3 | Pagination (all modules) |
| Week 1 | Day 4-5 | Students Bulk Actions |
| Week 2 | Day 1-2 | Document Expiry Alerts |
| Week 2 | Day 3 | Last Contacted Column |
| Week 2 | Day 4-5 | Testing & Bug Fixes |

### Success Metrics

| Metric | Target |
|--------|--------|
| Page load time (1000+ records) | < 2 seconds |
| Bulk action completion rate | 100% success |
| Document expiry alert accuracy | No missed alerts |
| User satisfaction | Positive feedback |

---

## 🚀 Getting Started

1. **Review this plan** with the development team
2. **Create feature branch**: `git checkout -b feature/phase1-quick-wins`
3. **Start with Pagination** as foundation for other features
4. **Daily standups** to track progress
5. **Code review** before merging each feature
6. **Deploy to staging** for QA testing
7. **Production release** after approval

---

*Document Version: 1.0*  
*Created: January 22, 2026*  
*Author: Development Team*
