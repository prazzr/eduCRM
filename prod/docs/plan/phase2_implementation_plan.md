# Phase 2 Implementation Plan: UX Improvements (2 Weeks)

> **Timeline**: February 6, 2026 - February 19, 2026  
> **Priority**: 🟡 Medium Impact, Medium Effort  
> **Goal**: Transform user experience with modern interfaces and powerful features  
> **Prerequisites**: Phase 1 completed successfully

---

## 📋 Overview

Phase 2 focuses on **UX improvements** that bring the system up to modern CRM standards. These features significantly enhance productivity and visual workflow management.

| # | Feature | Estimated Effort | Modules | Status |
|---|---------|-----------------|---------|--------|
| 1 | Kanban Board Views | 4 days | Tasks, Inquiries, Visa | ⬜ Not Started |
| 2 | Google Calendar Integration | 3 days | Appointments | ⬜ Not Started |
| 3 | Financial Dashboard | 2 days | Accounting | ⬜ Not Started |
| 4 | PDF Report Generation | 2 days | Reports | ⬜ Not Started |

**Total Estimated Effort**: 11 working days (with 1 day buffer)

---

## 1️⃣ Kanban Board Views

### 1.1 Problem Statement
Traditional table views require mental mapping of workflow stages. Users must:
- Scan multiple rows to understand pipeline health
- Click edit to change status
- Cannot visualize bottlenecks easily
- No drag-and-drop capability

### 1.2 Scope

| Module | Columns (Stages) | Cards Per Column |
|--------|-----------------|------------------|
| **Tasks** | To Do → In Progress → Review → Done | Unlimited |
| **Inquiries** | New → Contacted → Qualified → Proposal → Converted → Lost | Unlimited |
| **Visa** | Doc Collection → Submitted → Interview → Decision → Approved/Rejected | Unlimited |

### 1.3 Technical Specification

#### Library Selection

**Recommended**: [SortableJS](https://sortablejs.github.io/Sortable/) + Alpine.js
- Lightweight (10KB gzipped)
- No jQuery dependency
- Touch-friendly
- Works with existing Alpine.js setup

**Alternative**: [dragula](https://bevacqua.github.io/dragula/) - simpler API but less features

#### Database Changes

No schema changes required - Kanban uses existing `status` columns.

#### Base Kanban Service

**File**: `app/Services/KanbanService.php` (NEW)

```php
<?php
namespace EduCRM\Services;

class KanbanService
{
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get items grouped by status for Kanban display
     */
    public function getKanbanData(string $entity, array $columns, array $filters = []): array
    {
        $data = [];
        
        foreach ($columns as $columnKey => $columnConfig) {
            $data[$columnKey] = [
                'title' => $columnConfig['title'],
                'color' => $columnConfig['color'] ?? 'slate',
                'icon' => $columnConfig['icon'] ?? null,
                'items' => $this->getItemsForColumn($entity, $columnKey, $filters),
                'count' => 0
            ];
            $data[$columnKey]['count'] = count($data[$columnKey]['items']);
        }
        
        return $data;
    }

    private function getItemsForColumn(string $entity, string $status, array $filters): array
    {
        switch ($entity) {
            case 'tasks':
                return $this->getTasksForColumn($status, $filters);
            case 'inquiries':
                return $this->getInquiriesForColumn($status, $filters);
            case 'visa':
                return $this->getVisaForColumn($status, $filters);
            default:
                return [];
        }
    }

    private function getTasksForColumn(string $status, array $filters): array
    {
        $where = ['t.status = ?'];
        $params = [$status];

        if (!empty($filters['assigned_to'])) {
            $where[] = 't.assigned_to = ?';
            $params[] = $filters['assigned_to'];
        }

        $stmt = $this->pdo->prepare("
            SELECT t.id, t.title, t.priority, t.due_date, t.status,
                   u.name as assigned_name, u.avatar as assigned_avatar,
                   CASE 
                       WHEN t.due_date < CURDATE() AND t.status != 'completed' THEN 1 
                       ELSE 0 
                   END as is_overdue
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY t.priority DESC, t.due_date ASC
            LIMIT 100
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getInquiriesForColumn(string $status, array $filters): array
    {
        $where = ['i.status = ?'];
        $params = [$status];

        if (!empty($filters['assigned_to'])) {
            $where[] = 'i.assigned_to = ?';
            $params[] = $filters['assigned_to'];
        }
        if (!empty($filters['priority'])) {
            $where[] = 'i.priority = ?';
            $params[] = $filters['priority'];
        }

        $stmt = $this->pdo->prepare("
            SELECT i.id, i.name, i.email, i.phone, i.priority, i.status,
                   i.lead_score, i.source, i.created_at,
                   c.name as country_name,
                   u.name as assigned_name
            FROM inquiries i
            LEFT JOIN countries c ON i.country_id = c.id
            LEFT JOIN users u ON i.assigned_to = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.lead_score DESC, i.created_at DESC
            LIMIT 100
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getVisaForColumn(string $stage, array $filters): array
    {
        $stmt = $this->pdo->prepare("
            SELECT vw.id, vw.student_id, vw.current_stage, vw.expected_date,
                   vw.priority, vw.created_at,
                   u.name as student_name, u.email as student_email,
                   c.name as destination_country,
                   CASE 
                       WHEN vw.expected_date < CURDATE() AND vw.current_stage NOT IN ('approved', 'rejected') THEN 1 
                       ELSE 0 
                   END as is_overdue
            FROM visa_workflows vw
            JOIN users u ON vw.student_id = u.id
            LEFT JOIN countries c ON vw.destination_country_id = c.id
            WHERE vw.current_stage = ?
            ORDER BY vw.priority DESC, vw.expected_date ASC
            LIMIT 100
        ");
        $stmt->execute([$stage]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Move item to new column (status update)
     */
    public function moveItem(string $entity, int $itemId, string $newStatus, int $newPosition = null): bool
    {
        $table = match($entity) {
            'tasks' => 'tasks',
            'inquiries' => 'inquiries',
            'visa' => 'visa_workflows',
            default => throw new \InvalidArgumentException("Unknown entity: {$entity}")
        };

        $column = $entity === 'visa' ? 'current_stage' : 'status';

        $stmt = $this->pdo->prepare("UPDATE {$table} SET {$column} = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$newStatus, $itemId]);

        // Log the status change
        if ($result) {
            $this->logStatusChange($entity, $itemId, $newStatus);
        }

        return $result;
    }

    private function logStatusChange(string $entity, int $itemId, string $newStatus): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO activity_logs (entity_type, entity_id, action, details, user_id, created_at)
            VALUES (?, ?, 'status_change', ?, ?, NOW())
        ");
        $stmt->execute([
            $entity,
            $itemId,
            json_encode(['new_status' => $newStatus]),
            $_SESSION['user_id'] ?? null
        ]);
    }
}
```

#### API Endpoints

**File**: `api/v1/kanban/index.php` (NEW)

```php
<?php
/**
 * Kanban API Endpoints
 * GET /api/v1/kanban/{entity} - Get board data
 * PUT /api/v1/kanban/{entity}/{id}/move - Move card to new column
 */

require_once __DIR__ . '/../../../app/bootstrap.php';

header('Content-Type: application/json');
requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$pathParts = explode('/', trim($_GET['path'] ?? '', '/'));
$entity = $pathParts[0] ?? '';

$kanbanService = new \EduCRM\Services\KanbanService($pdo);

// Define columns for each entity
$entityColumns = [
    'tasks' => [
        'pending' => ['title' => 'To Do', 'color' => 'slate', 'icon' => 'circle'],
        'in_progress' => ['title' => 'In Progress', 'color' => 'blue', 'icon' => 'loader'],
        'review' => ['title' => 'Review', 'color' => 'yellow', 'icon' => 'eye'],
        'completed' => ['title' => 'Done', 'color' => 'green', 'icon' => 'check-circle'],
    ],
    'inquiries' => [
        'new' => ['title' => 'New', 'color' => 'slate', 'icon' => 'inbox'],
        'contacted' => ['title' => 'Contacted', 'color' => 'blue', 'icon' => 'phone'],
        'qualified' => ['title' => 'Qualified', 'color' => 'indigo', 'icon' => 'star'],
        'proposal' => ['title' => 'Proposal', 'color' => 'purple', 'icon' => 'file-text'],
        'converted' => ['title' => 'Converted', 'color' => 'green', 'icon' => 'user-check'],
        'lost' => ['title' => 'Lost', 'color' => 'red', 'icon' => 'user-x'],
    ],
    'visa' => [
        'doc_collection' => ['title' => 'Doc Collection', 'color' => 'slate', 'icon' => 'folder'],
        'submitted' => ['title' => 'Submitted', 'color' => 'blue', 'icon' => 'send'],
        'interview' => ['title' => 'Interview', 'color' => 'yellow', 'icon' => 'users'],
        'decision' => ['title' => 'Decision Pending', 'color' => 'orange', 'icon' => 'clock'],
        'approved' => ['title' => 'Approved', 'color' => 'green', 'icon' => 'check-circle'],
        'rejected' => ['title' => 'Rejected', 'color' => 'red', 'icon' => 'x-circle'],
    ],
];

if (!isset($entityColumns[$entity])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid entity']);
    exit;
}

try {
    if ($method === 'GET') {
        // Get Kanban board data
        $filters = [
            'assigned_to' => $_GET['assigned_to'] ?? null,
            'priority' => $_GET['priority'] ?? null,
        ];
        
        // Non-admins see only their items
        if (!hasRole('admin') && in_array($entity, ['tasks', 'inquiries'])) {
            $filters['assigned_to'] = $_SESSION['user_id'];
        }
        
        $data = $kanbanService->getKanbanData($entity, $entityColumns[$entity], $filters);
        echo json_encode(['success' => true, 'data' => $data]);
        
    } elseif ($method === 'PUT' || $method === 'POST') {
        // Move card
        $input = json_decode(file_get_contents('php://input'), true);
        $itemId = (int)($pathParts[1] ?? 0);
        $newStatus = $input['status'] ?? '';
        
        if (!$itemId || !$newStatus) {
            throw new \Exception('Missing item ID or status');
        }
        
        // Validate status exists in columns
        if (!isset($entityColumns[$entity][$newStatus])) {
            throw new \Exception('Invalid status');
        }
        
        $result = $kanbanService->moveItem($entity, $itemId, $newStatus);
        echo json_encode(['success' => $result]);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

### 1.4 Kanban UI Component

**File**: `templates/components/kanban-board.php` (NEW)

```php
<?php
/**
 * Reusable Kanban Board Component
 * 
 * Usage:
 * $kanbanConfig = [
 *     'entity' => 'tasks',
 *     'columns' => [...],
 *     'apiEndpoint' => '/api/v1/kanban/tasks'
 * ];
 * include 'templates/components/kanban-board.php';
 */
?>

<!-- Include SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<div x-data="kanbanBoard()" x-init="init()" class="kanban-container">
    <!-- Loading State -->
    <div x-show="loading" class="flex items-center justify-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
    </div>

    <!-- Kanban Board -->
    <div x-show="!loading" class="flex gap-4 overflow-x-auto pb-4" style="min-height: 600px;">
        <template x-for="(column, columnKey) in columns" :key="columnKey">
            <div class="kanban-column flex-shrink-0 w-80 bg-slate-100 rounded-xl">
                <!-- Column Header -->
                <div class="p-4 border-b border-slate-200 bg-slate-50 rounded-t-xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full" 
                                  :class="`bg-${column.color}-500`"></span>
                            <h3 class="font-semibold text-slate-800" x-text="column.title"></h3>
                        </div>
                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-slate-200 text-slate-700"
                              x-text="column.count"></span>
                    </div>
                </div>

                <!-- Cards Container (Sortable) -->
                <div class="kanban-cards p-2 space-y-2 min-h-[400px]" 
                     :data-column="columnKey"
                     :id="'column-' + columnKey">
                    
                    <template x-for="item in column.items" :key="item.id">
                        <div class="kanban-card bg-white rounded-lg shadow-sm border border-slate-200 p-3 cursor-grab active:cursor-grabbing hover:shadow-md transition-shadow"
                             :data-id="item.id"
                             @click="openCard(item)">
                            
                            <!-- Card Content - Tasks -->
                            <template x-if="entity === 'tasks'">
                                <div>
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <span class="font-medium text-slate-800 text-sm line-clamp-2" x-text="item.title"></span>
                                        <span class="flex-shrink-0 px-2 py-0.5 text-xs rounded-full"
                                              :class="getPriorityClass(item.priority)"
                                              x-text="item.priority"></span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs text-slate-500">
                                        <span x-text="item.assigned_name || 'Unassigned'"></span>
                                        <span :class="item.is_overdue ? 'text-red-600 font-medium' : ''"
                                              x-text="formatDate(item.due_date)"></span>
                                    </div>
                                </div>
                            </template>

                            <!-- Card Content - Inquiries -->
                            <template x-if="entity === 'inquiries'">
                                <div>
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <span class="font-medium text-slate-800 text-sm" x-text="item.name"></span>
                                        <span class="flex-shrink-0 px-2 py-0.5 text-xs rounded-full"
                                              :class="getPriorityClass(item.priority)"
                                              x-text="item.priority"></span>
                                    </div>
                                    <div class="text-xs text-slate-500 space-y-1">
                                        <div class="flex items-center gap-1">
                                            <span>📧</span>
                                            <span x-text="item.email"></span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span x-text="item.country_name || 'No country'"></span>
                                            <span class="font-medium text-primary-600" x-text="'Score: ' + item.lead_score"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Card Content - Visa -->
                            <template x-if="entity === 'visa'">
                                <div>
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <span class="font-medium text-slate-800 text-sm" x-text="item.student_name"></span>
                                        <span class="flex-shrink-0 px-2 py-0.5 text-xs rounded-full"
                                              :class="getPriorityClass(item.priority)"
                                              x-text="item.priority"></span>
                                    </div>
                                    <div class="text-xs text-slate-500 space-y-1">
                                        <div>🌍 <span x-text="item.destination_country"></span></div>
                                        <div class="flex items-center justify-between">
                                            <span>Expected:</span>
                                            <span :class="item.is_overdue ? 'text-red-600 font-medium' : ''"
                                                  x-text="formatDate(item.expected_date)"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Empty State -->
                    <div x-show="column.items.length === 0" 
                         class="text-center py-8 text-slate-400 text-sm">
                        No items
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Card Detail Modal -->
    <div x-show="selectedCard" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="selectedCard = null"
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 max-h-[80vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex items-start justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-800" x-text="selectedCard?.title || selectedCard?.name || selectedCard?.student_name"></h3>
                    <button @click="selectedCard = null" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <!-- Card details would go here -->
                <div class="mt-4 flex gap-3">
                    <a :href="getEditUrl(selectedCard)" class="btn btn-primary flex-1">
                        Edit Details
                    </a>
                    <button @click="selectedCard = null" class="btn btn-secondary">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function kanbanBoard() {
    return {
        entity: '<?php echo $kanbanConfig['entity']; ?>',
        apiEndpoint: '<?php echo $kanbanConfig['apiEndpoint']; ?>',
        columns: {},
        loading: true,
        selectedCard: null,
        sortables: [],

        async init() {
            await this.loadData();
            this.$nextTick(() => this.initSortables());
        },

        async loadData() {
            this.loading = true;
            try {
                const response = await fetch(this.apiEndpoint);
                const result = await response.json();
                if (result.success) {
                    this.columns = result.data;
                }
            } catch (error) {
                console.error('Failed to load Kanban data:', error);
            }
            this.loading = false;
        },

        initSortables() {
            // Destroy existing sortables
            this.sortables.forEach(s => s.destroy());
            this.sortables = [];

            // Initialize sortable for each column
            document.querySelectorAll('.kanban-cards').forEach(el => {
                const sortable = new Sortable(el, {
                    group: 'kanban',
                    animation: 150,
                    ghostClass: 'opacity-50',
                    chosenClass: 'shadow-lg',
                    dragClass: 'rotate-2',
                    onEnd: (evt) => this.onCardMove(evt)
                });
                this.sortables.push(sortable);
            });
        },

        async onCardMove(evt) {
            const itemId = evt.item.dataset.id;
            const newColumn = evt.to.dataset.column;
            const oldColumn = evt.from.dataset.column;

            if (newColumn === oldColumn) return;

            // Optimistic update - move card in local state
            const item = this.columns[oldColumn].items.find(i => i.id == itemId);
            if (item) {
                this.columns[oldColumn].items = this.columns[oldColumn].items.filter(i => i.id != itemId);
                this.columns[oldColumn].count--;
                this.columns[newColumn].items.push(item);
                this.columns[newColumn].count++;
            }

            // API call
            try {
                const response = await fetch(`${this.apiEndpoint}/${itemId}/move`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status: newColumn })
                });
                const result = await response.json();
                if (!result.success) {
                    // Revert on failure
                    await this.loadData();
                    this.showToast('Failed to move card', 'error');
                } else {
                    this.showToast('Card moved successfully', 'success');
                }
            } catch (error) {
                await this.loadData();
                this.showToast('Network error', 'error');
            }
        },

        openCard(item) {
            this.selectedCard = item;
        },

        getEditUrl(item) {
            if (!item) return '#';
            switch(this.entity) {
                case 'tasks': return `/modules/tasks/edit.php?id=${item.id}`;
                case 'inquiries': return `/modules/inquiries/edit.php?id=${item.id}`;
                case 'visa': return `/modules/visa/update.php?student_id=${item.student_id}`;
                default: return '#';
            }
        },

        getPriorityClass(priority) {
            const classes = {
                'urgent': 'bg-red-100 text-red-700',
                'high': 'bg-orange-100 text-orange-700',
                'hot': 'bg-red-100 text-red-700',
                'warm': 'bg-yellow-100 text-yellow-700',
                'medium': 'bg-yellow-100 text-yellow-700',
                'normal': 'bg-slate-100 text-slate-700',
                'low': 'bg-slate-100 text-slate-700',
                'cold': 'bg-blue-100 text-blue-700',
                'critical': 'bg-red-100 text-red-700',
            };
            return classes[priority?.toLowerCase()] || 'bg-slate-100 text-slate-700';
        },

        formatDate(dateStr) {
            if (!dateStr) return 'No date';
            const date = new Date(dateStr);
            const today = new Date();
            const diffDays = Math.ceil((date - today) / (1000 * 60 * 60 * 24));
            
            if (diffDays < 0) return `${Math.abs(diffDays)}d overdue`;
            if (diffDays === 0) return 'Today';
            if (diffDays === 1) return 'Tomorrow';
            if (diffDays <= 7) return `${diffDays}d`;
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        },

        showToast(message, type = 'info') {
            // Implement toast notification
            const toast = document.createElement('div');
            toast.className = `fixed bottom-4 right-4 px-4 py-2 rounded-lg shadow-lg text-white z-50 ${type === 'error' ? 'bg-red-600' : 'bg-green-600'}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
    };
}
</script>

<style>
.kanban-container {
    --column-width: 320px;
}

.kanban-column {
    width: var(--column-width);
    min-width: var(--column-width);
}

.kanban-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.kanban-card:hover {
    transform: translateY(-2px);
}

.kanban-card.sortable-ghost {
    opacity: 0.4;
}

.kanban-card.sortable-chosen {
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Scrollbar styling for kanban */
.kanban-container::-webkit-scrollbar {
    height: 8px;
}

.kanban-container::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.kanban-container::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.kanban-container::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
```

### 1.5 Module Integration

**File**: `modules/tasks/kanban.php` (NEW)

```php
<?php
require_once '../../app/bootstrap.php';
requireLogin();

$pageDetails = ['title' => 'Tasks - Kanban Board'];
require_once '../../templates/header.php';

$kanbanConfig = [
    'entity' => 'tasks',
    'apiEndpoint' => '/api/v1/kanban/tasks'
];
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Task Board</h1>
        <p class="text-slate-500 text-sm">Drag and drop tasks to update status</p>
    </div>
    <div class="flex gap-3">
        <a href="list.php" class="btn btn-secondary">
            <?php echo getIcon('list', 16); ?> List View
        </a>
        <a href="add.php" class="btn btn-primary">
            <?php echo getIcon('plus', 16); ?> Add Task
        </a>
    </div>
</div>

<?php include '../../templates/components/kanban-board.php'; ?>

<?php require_once '../../templates/footer.php'; ?>
```

### 1.6 Implementation Steps

#### Day 1: Foundation
1. [ ] Install SortableJS via CDN or npm
2. [ ] Create `KanbanService.php`
3. [ ] Create API endpoints
4. [ ] Write unit tests for service

#### Day 2: Kanban Component
1. [ ] Create `kanban-board.php` component
2. [ ] Implement drag-and-drop functionality
3. [ ] Add card templates for each entity type
4. [ ] Implement API calls and optimistic updates

#### Day 3: Tasks Integration
1. [ ] Create `modules/tasks/kanban.php`
2. [ ] Add view toggle to tasks list header
3. [ ] Test all task status transitions
4. [ ] Handle edge cases (permissions, empty states)

#### Day 4: Inquiries & Visa Integration
1. [ ] Create `modules/inquiries/kanban.php`
2. [ ] Create `modules/visa/kanban.php`
3. [ ] Add view toggle buttons to list pages
4. [ ] End-to-end testing for all three modules

### 1.7 Testing Criteria

| Test Case | Expected Result |
|-----------|-----------------|
| Load Kanban with 50 tasks | Board renders in < 2 seconds |
| Drag card from "To Do" to "In Progress" | Status updates, card moves smoothly |
| Rapid drag multiple cards | All updates persist correctly |
| Refresh page after moves | Changes are persisted |
| Click card | Detail modal opens |
| Non-admin drags admin-only task | Action should be blocked |
| Mobile touch drag | Works on touch devices |
| Keyboard accessibility | Can navigate with keyboard |

---

## 2️⃣ Google Calendar Integration

### 2.1 Problem Statement
Appointments created in EduCRM don't sync with counselors' personal calendars, leading to:
- Missed appointments
- Double-booking
- Manual calendar entry duplication
- Lack of mobile notifications

### 2.2 Scope

| Feature | Description |
|---------|-------------|
| One-way sync | EduCRM → Google Calendar |
| Two-way sync | Google Calendar ↔ EduCRM (Phase 3) |
| OAuth2 | Secure authentication |
| Webhook | Real-time updates |

**Phase 2 Focus**: One-way sync (EduCRM → Google Calendar)

### 2.3 Technical Specification

#### Prerequisites
1. Google Cloud Console project
2. OAuth 2.0 credentials
3. Calendar API enabled

#### Database Changes

**File**: `database/migrations/add_calendar_integration.sql`

```sql
-- Store user calendar tokens
CREATE TABLE IF NOT EXISTS user_calendar_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    provider ENUM('google', 'outlook') DEFAULT 'google',
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at DATETIME,
    calendar_id VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Track synced events
CREATE TABLE IF NOT EXISTS calendar_sync_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    user_id INT NOT NULL,
    provider_event_id VARCHAR(255),
    last_synced_at DATETIME,
    sync_status ENUM('synced', 'pending', 'failed') DEFAULT 'pending',
    error_message TEXT,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_sync (appointment_id, user_id)
);
```

#### Google Calendar Service

**File**: `app/Services/GoogleCalendarService.php` (NEW)

```php
<?php
namespace EduCRM\Services;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;

class GoogleCalendarService
{
    private $pdo;
    private $client;
    private $clientId;
    private $clientSecret;
    private $redirectUri;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->clientId = getenv('GOOGLE_CLIENT_ID') ?: config('google.client_id');
        $this->clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: config('google.client_secret');
        $this->redirectUri = getenv('GOOGLE_REDIRECT_URI') ?: config('google.redirect_uri');
        
        $this->client = new Client();
        $this->client->setClientId($this->clientId);
        $this->client->setClientSecret($this->clientSecret);
        $this->client->setRedirectUri($this->redirectUri);
        $this->client->addScope(Calendar::CALENDAR_EVENTS);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    /**
     * Get OAuth authorization URL
     */
    public function getAuthUrl(int $userId): string
    {
        $this->client->setState($userId);
        return $this->client->createAuthUrl();
    }

    /**
     * Handle OAuth callback and store tokens
     */
    public function handleCallback(string $code, int $userId): bool
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);
        
        if (isset($token['error'])) {
            throw new \Exception('Failed to get access token: ' . $token['error_description']);
        }

        // Store tokens
        $stmt = $this->pdo->prepare("
            INSERT INTO user_calendar_tokens 
            (user_id, provider, access_token, refresh_token, token_expires_at, is_active)
            VALUES (?, 'google', ?, ?, ?, TRUE)
            ON DUPLICATE KEY UPDATE 
                access_token = VALUES(access_token),
                refresh_token = COALESCE(VALUES(refresh_token), refresh_token),
                token_expires_at = VALUES(token_expires_at),
                is_active = TRUE
        ");

        $expiresAt = date('Y-m-d H:i:s', time() + $token['expires_in']);
        
        return $stmt->execute([
            $userId,
            $token['access_token'],
            $token['refresh_token'] ?? null,
            $expiresAt
        ]);
    }

    /**
     * Get authenticated service for user
     */
    private function getServiceForUser(int $userId): ?Calendar
    {
        $stmt = $this->pdo->prepare("SELECT * FROM user_calendar_tokens WHERE user_id = ? AND is_active = TRUE");
        $stmt->execute([$userId]);
        $tokenData = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$tokenData) {
            return null;
        }

        // Check if token needs refresh
        if (strtotime($tokenData['token_expires_at']) < time()) {
            $this->refreshToken($userId, $tokenData['refresh_token']);
            
            // Re-fetch updated token
            $stmt->execute([$userId]);
            $tokenData = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        $this->client->setAccessToken($tokenData['access_token']);
        return new Calendar($this->client);
    }

    /**
     * Refresh expired token
     */
    private function refreshToken(int $userId, string $refreshToken): void
    {
        $this->client->refreshToken($refreshToken);
        $newToken = $this->client->getAccessToken();

        $stmt = $this->pdo->prepare("
            UPDATE user_calendar_tokens 
            SET access_token = ?, token_expires_at = ?
            WHERE user_id = ?
        ");

        $expiresAt = date('Y-m-d H:i:s', time() + $newToken['expires_in']);
        $stmt->execute([$newToken['access_token'], $expiresAt, $userId]);
    }

    /**
     * Create calendar event from appointment
     */
    public function syncAppointment(array $appointment): array
    {
        $userId = $appointment['counselor_id'];
        $service = $this->getServiceForUser($userId);

        if (!$service) {
            return ['success' => false, 'error' => 'Calendar not connected'];
        }

        $event = new Event([
            'summary' => $appointment['title'],
            'description' => $this->buildDescription($appointment),
            'start' => [
                'dateTime' => $this->formatDateTime($appointment['appointment_date']),
                'timeZone' => 'Asia/Kathmandu', // Or from config
            ],
            'end' => [
                'dateTime' => $this->formatDateTime($appointment['end_date'] ?? $appointment['appointment_date'], 60),
                'timeZone' => 'Asia/Kathmandu',
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 1440], // 24 hours
                    ['method' => 'popup', 'minutes' => 60],   // 1 hour
                ],
            ],
        ]);

        // Add video meeting link if exists
        if (!empty($appointment['meeting_link'])) {
            $event->setLocation($appointment['meeting_link']);
        } elseif (!empty($appointment['location'])) {
            $event->setLocation($appointment['location']);
        }

        try {
            // Check if already synced
            $existingSync = $this->getSyncRecord($appointment['id'], $userId);
            
            if ($existingSync && $existingSync['provider_event_id']) {
                // Update existing event
                $result = $service->events->update('primary', $existingSync['provider_event_id'], $event);
            } else {
                // Create new event
                $result = $service->events->insert('primary', $event);
            }

            // Record sync
            $this->recordSync($appointment['id'], $userId, $result->getId(), 'synced');

            return ['success' => true, 'event_id' => $result->getId()];
        } catch (\Exception $e) {
            $this->recordSync($appointment['id'], $userId, null, 'failed', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete calendar event when appointment is cancelled
     */
    public function deleteEvent(int $appointmentId, int $userId): bool
    {
        $service = $this->getServiceForUser($userId);
        if (!$service) return false;

        $syncRecord = $this->getSyncRecord($appointmentId, $userId);
        if (!$syncRecord || !$syncRecord['provider_event_id']) return false;

        try {
            $service->events->delete('primary', $syncRecord['provider_event_id']);
            $this->pdo->prepare("DELETE FROM calendar_sync_events WHERE appointment_id = ? AND user_id = ?")->execute([$appointmentId, $userId]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if user has connected calendar
     */
    public function isConnected(int $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM user_calendar_tokens WHERE user_id = ? AND is_active = TRUE");
        $stmt->execute([$userId]);
        return (bool)$stmt->fetch();
    }

    /**
     * Disconnect calendar
     */
    public function disconnect(int $userId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE user_calendar_tokens SET is_active = FALSE WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }

    private function buildDescription(array $appointment): string
    {
        $desc = [];
        if (!empty($appointment['client_name'])) {
            $desc[] = "Client: {$appointment['client_name']}";
        }
        if (!empty($appointment['client_email'])) {
            $desc[] = "Email: {$appointment['client_email']}";
        }
        if (!empty($appointment['client_phone'])) {
            $desc[] = "Phone: {$appointment['client_phone']}";
        }
        if (!empty($appointment['notes'])) {
            $desc[] = "\nNotes: {$appointment['notes']}";
        }
        $desc[] = "\n---\nManaged by EduCRM";
        return implode("\n", $desc);
    }

    private function formatDateTime(string $dateTime, int $addMinutes = 0): string
    {
        $timestamp = strtotime($dateTime) + ($addMinutes * 60);
        return date('c', $timestamp);
    }

    private function getSyncRecord(int $appointmentId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM calendar_sync_events WHERE appointment_id = ? AND user_id = ?");
        $stmt->execute([$appointmentId, $userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private function recordSync(int $appointmentId, int $userId, ?string $eventId, string $status, ?string $error = null): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO calendar_sync_events (appointment_id, user_id, provider_event_id, last_synced_at, sync_status, error_message)
            VALUES (?, ?, ?, NOW(), ?, ?)
            ON DUPLICATE KEY UPDATE 
                provider_event_id = COALESCE(VALUES(provider_event_id), provider_event_id),
                last_synced_at = NOW(),
                sync_status = VALUES(sync_status),
                error_message = VALUES(error_message)
        ");
        $stmt->execute([$appointmentId, $userId, $eventId, $status, $error]);
    }
}
```

#### Configuration

**File**: `config/google.php` (NEW)

```php
<?php
return [
    'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
    'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
    'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: 'https://your-domain.com/modules/appointments/google-callback.php',
];
```

### 2.4 UI Integration

**File**: `modules/appointments/google-connect.php` (NEW)

```php
<?php
require_once '../../app/bootstrap.php';
requireLogin();

$calendarService = new \EduCRM\Services\GoogleCalendarService($pdo);

if (isset($_GET['action']) && $_GET['action'] === 'disconnect') {
    $calendarService->disconnect($_SESSION['user_id']);
    redirectWithAlert('list.php', 'Google Calendar disconnected.', 'success');
}

$authUrl = $calendarService->getAuthUrl($_SESSION['user_id']);
header('Location: ' . $authUrl);
exit;
```

**File**: `modules/appointments/google-callback.php` (NEW)

```php
<?php
require_once '../../app/bootstrap.php';
requireLogin();

$code = $_GET['code'] ?? '';
$userId = (int)($_GET['state'] ?? $_SESSION['user_id']);

if (!$code) {
    redirectWithAlert('list.php', 'Calendar connection failed.', 'error');
}

$calendarService = new \EduCRM\Services\GoogleCalendarService($pdo);

try {
    $calendarService->handleCallback($code, $userId);
    redirectWithAlert('list.php', 'Google Calendar connected successfully! Your appointments will now sync.', 'success');
} catch (\Exception $e) {
    redirectWithAlert('list.php', 'Failed to connect: ' . $e->getMessage(), 'error');
}
```

**Add to appointment list header:**

```php
<?php 
$calendarService = new \EduCRM\Services\GoogleCalendarService($pdo);
$isConnected = $calendarService->isConnected($_SESSION['user_id']);
?>

<div class="flex items-center gap-3">
    <?php if ($isConnected): ?>
        <span class="flex items-center gap-1 text-sm text-green-600">
            <?php echo getIcon('check-circle', 16); ?> Calendar Connected
        </span>
        <a href="google-connect.php?action=disconnect" class="text-sm text-slate-500 hover:text-slate-700">
            Disconnect
        </a>
    <?php else: ?>
        <a href="google-connect.php" class="btn btn-secondary">
            <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24"><!-- Google icon --></svg>
            Connect Google Calendar
        </a>
    <?php endif; ?>
</div>
```

### 2.5 Auto-Sync on Appointment Changes

**Update**: `modules/appointments/add.php` and `edit.php`

After saving appointment, add:
```php
// Sync to Google Calendar if connected
$calendarService = new \EduCRM\Services\GoogleCalendarService($pdo);
if ($calendarService->isConnected($appointment['counselor_id'])) {
    $syncResult = $calendarService->syncAppointment($appointment);
    if (!$syncResult['success']) {
        // Log error but don't block user
        error_log("Calendar sync failed: " . $syncResult['error']);
    }
}
```

### 2.6 Composer Dependency

Add to `composer.json`:
```json
{
    "require": {
        "google/apiclient": "^2.15"
    }
}
```

Run: `composer require google/apiclient`

### 2.7 Implementation Steps

#### Day 1: Setup & Service
1. [ ] Create Google Cloud project and OAuth credentials
2. [ ] Run `composer require google/apiclient`
3. [ ] Run database migration
4. [ ] Create `GoogleCalendarService.php`
5. [ ] Create config file

#### Day 2: OAuth Flow
1. [ ] Create `google-connect.php` (initiate OAuth)
2. [ ] Create `google-callback.php` (handle token)
3. [ ] Add connect/disconnect buttons to UI
4. [ ] Test OAuth flow

#### Day 3: Sync Integration
1. [ ] Integrate sync into appointment add/edit/delete
2. [ ] Create background sync job for existing appointments
3. [ ] Add sync status indicators
4. [ ] End-to-end testing

### 2.8 Testing Criteria

| Test Case | Expected Result |
|-----------|-----------------|
| Click "Connect Google Calendar" | Redirects to Google OAuth |
| Complete OAuth flow | Token stored, shows "Connected" |
| Create new appointment | Appears in Google Calendar within 10s |
| Edit appointment | Updates in Google Calendar |
| Delete/Cancel appointment | Removed from Google Calendar |
| Token expires | Auto-refreshes without user action |
| Disconnect calendar | Future appointments don't sync |

---

## 3️⃣ Financial Dashboard

### 3.1 Problem Statement
The Accounting module entry page is just a student list. Managers cannot quickly see:
- Total revenue vs. outstanding
- Payment trends
- Defaulter counts
- Cash flow projections

### 3.2 Technical Specification

#### New Service

**File**: `app/Services/FinancialReportService.php` (NEW)

```php
<?php
namespace EduCRM\Services;

class FinancialReportService
{
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get financial overview KPIs
     */
    public function getOverview(string $startDate = null, string $endDate = null): array
    {
        $dateFilter = '';
        $params = [];
        
        if ($startDate && $endDate) {
            $dateFilter = 'AND created_at BETWEEN ? AND ?';
            $params = [$startDate, $endDate];
        }

        // Total Revenue (Payments Received)
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM payments
            WHERE status = 'completed' {$dateFilter}
        ");
        $stmt->execute($params);
        $revenue = $stmt->fetchColumn();

        // Total Invoiced
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM invoices
            WHERE status != 'cancelled' {$dateFilter}
        ");
        $stmt->execute($params);
        $invoiced = $stmt->fetchColumn();

        // Outstanding Balance
        $outstanding = $this->pdo->query("
            SELECT COALESCE(SUM(
                (SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE student_id = u.id AND status = 'unpaid') -
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_id = u.id AND status = 'completed')
            ), 0) as total
            FROM users u
            WHERE u.id IN (SELECT DISTINCT student_id FROM invoices)
        ")->fetchColumn();

        // Students with Overdue Payments
        $overdueCount = $this->pdo->query("
            SELECT COUNT(DISTINCT student_id) 
            FROM invoices 
            WHERE status = 'unpaid' AND due_date < CURDATE()
        ")->fetchColumn();

        // This Month vs Last Month
        $thisMonth = $this->pdo->query("
            SELECT COALESCE(SUM(amount), 0) 
            FROM payments 
            WHERE status = 'completed' 
            AND MONTH(created_at) = MONTH(CURDATE()) 
            AND YEAR(created_at) = YEAR(CURDATE())
        ")->fetchColumn();

        $lastMonth = $this->pdo->query("
            SELECT COALESCE(SUM(amount), 0) 
            FROM payments 
            WHERE status = 'completed' 
            AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
            AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        ")->fetchColumn();

        $monthChange = $lastMonth > 0 ? (($thisMonth - $lastMonth) / $lastMonth) * 100 : 0;

        return [
            'total_revenue' => (float)$revenue,
            'total_invoiced' => (float)$invoiced,
            'outstanding' => (float)$outstanding,
            'collection_rate' => $invoiced > 0 ? ($revenue / $invoiced) * 100 : 0,
            'overdue_students' => (int)$overdueCount,
            'this_month' => (float)$thisMonth,
            'last_month' => (float)$lastMonth,
            'month_change' => round($monthChange, 1),
        ];
    }

    /**
     * Get revenue trend (last 6 months)
     */
    public function getRevenueTrend(int $months = 6): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                DATE_FORMAT(created_at, '%b %Y') as label,
                SUM(amount) as revenue
            FROM payments
            WHERE status = 'completed'
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->execute([$months]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get payment method breakdown
     */
    public function getPaymentMethodBreakdown(): array
    {
        return $this->pdo->query("
            SELECT 
                COALESCE(payment_method, 'Unknown') as method,
                COUNT(*) as count,
                SUM(amount) as total
            FROM payments
            WHERE status = 'completed'
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY payment_method
            ORDER BY total DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get fee type breakdown
     */
    public function getFeeTypeBreakdown(): array
    {
        return $this->pdo->query("
            SELECT 
                ft.name as fee_type,
                COUNT(i.id) as invoice_count,
                SUM(i.amount) as total_amount
            FROM invoices i
            JOIN fee_types ft ON i.fee_type_id = ft.id
            WHERE i.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY ft.id, ft.name
            ORDER BY total_amount DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get top defaulters
     */
    public function getTopDefaulters(int $limit = 10): array
    {
        return $this->pdo->query("
            SELECT 
                u.id, u.name, u.email, u.phone,
                (SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE student_id = u.id AND status = 'unpaid') as outstanding,
                (SELECT MIN(due_date) FROM invoices WHERE student_id = u.id AND status = 'unpaid' AND due_date < CURDATE()) as oldest_due
            FROM users u
            WHERE u.id IN (SELECT DISTINCT student_id FROM invoices WHERE status = 'unpaid')
            HAVING outstanding > 0
            ORDER BY outstanding DESC
            LIMIT {$limit}
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get recent transactions
     */
    public function getRecentTransactions(int $limit = 10): array
    {
        return $this->pdo->query("
            SELECT p.*, u.name as student_name
            FROM payments p
            JOIN users u ON p.student_id = u.id
            ORDER BY p.created_at DESC
            LIMIT {$limit}
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get projected collections (based on pending invoices)
     */
    public function getProjectedCollections(): array
    {
        return $this->pdo->query("
            SELECT 
                CASE 
                    WHEN due_date < CURDATE() THEN 'Overdue'
                    WHEN due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'This Week'
                    WHEN due_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 8 DAY) AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'This Month'
                    ELSE 'Future'
                END as period,
                SUM(amount) as amount,
                COUNT(*) as count
            FROM invoices
            WHERE status = 'unpaid'
            GROUP BY period
            ORDER BY FIELD(period, 'Overdue', 'This Week', 'This Month', 'Future')
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }
}
```

### 3.3 Dashboard UI

**File**: `modules/accounting/dashboard.php` (NEW)

```php
<?php
require_once '../../app/bootstrap.php';
requireLogin();
requireAdminCounselorOrBranchManager();

$pageDetails = ['title' => 'Financial Dashboard'];
require_once '../../templates/header.php';

$financeService = new \EduCRM\Services\FinancialReportService($pdo);
$overview = $financeService->getOverview();
$trend = $financeService->getRevenueTrend(6);
$methods = $financeService->getPaymentMethodBreakdown();
$feeTypes = $financeService->getFeeTypeBreakdown();
$defaulters = $financeService->getTopDefaulters(5);
$recentTx = $financeService->getRecentTransactions(5);
$projected = $financeService->getProjectedCollections();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Financial Dashboard</h1>
        <p class="text-slate-500 text-sm">Overview of revenue, collections, and outstanding balances</p>
    </div>
    <div class="flex gap-3">
        <a href="ledger.php" class="btn btn-secondary">
            <?php echo getIcon('users', 16); ?> Student Ledgers
        </a>
        <a href="fee_types.php" class="btn btn-secondary">
            <?php echo getIcon('settings', 16); ?> Fee Types
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Revenue -->
    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-green-100 text-sm font-medium">Total Revenue</span>
            <?php echo getIcon('trending-up', 24, 'text-green-200'); ?>
        </div>
        <div class="text-3xl font-bold mb-1">
            $<?php echo number_format($overview['total_revenue'], 2); ?>
        </div>
        <div class="text-green-100 text-sm flex items-center gap-1">
            <?php if ($overview['month_change'] >= 0): ?>
                <span class="text-green-200">↑ <?php echo abs($overview['month_change']); ?>%</span>
            <?php else: ?>
                <span class="text-red-200">↓ <?php echo abs($overview['month_change']); ?>%</span>
            <?php endif; ?>
            vs last month
        </div>
    </div>

    <!-- Outstanding -->
    <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-orange-100 text-sm font-medium">Outstanding</span>
            <?php echo getIcon('alert-circle', 24, 'text-orange-200'); ?>
        </div>
        <div class="text-3xl font-bold mb-1">
            $<?php echo number_format($overview['outstanding'], 2); ?>
        </div>
        <div class="text-orange-100 text-sm">
            <?php echo $overview['overdue_students']; ?> students overdue
        </div>
    </div>

    <!-- Collection Rate -->
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-blue-100 text-sm font-medium">Collection Rate</span>
            <?php echo getIcon('percent', 24, 'text-blue-200'); ?>
        </div>
        <div class="text-3xl font-bold mb-1">
            <?php echo number_format($overview['collection_rate'], 1); ?>%
        </div>
        <div class="text-blue-100 text-sm">
            of invoiced amount collected
        </div>
    </div>

    <!-- This Month -->
    <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-indigo-100 text-sm font-medium">This Month</span>
            <?php echo getIcon('calendar', 24, 'text-indigo-200'); ?>
        </div>
        <div class="text-3xl font-bold mb-1">
            $<?php echo number_format($overview['this_month'], 2); ?>
        </div>
        <div class="text-indigo-100 text-sm">
            Last month: $<?php echo number_format($overview['last_month'], 2); ?>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Revenue Trend Chart -->
    <div class="card">
        <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800">Revenue Trend</h2>
        </div>
        <div class="p-6">
            <canvas id="revenueTrendChart" height="250"></canvas>
        </div>
    </div>

    <!-- Fee Type Breakdown -->
    <div class="card">
        <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800">Revenue by Fee Type</h2>
        </div>
        <div class="p-6">
            <canvas id="feeTypeChart" height="250"></canvas>
        </div>
    </div>
</div>

<!-- Projected Collections & Payment Methods -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Projected Collections -->
    <div class="card">
        <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800">Expected Collections</h2>
        </div>
        <div class="p-4">
            <?php foreach ($projected as $proj): ?>
                <div class="flex items-center justify-between py-3 border-b border-slate-100 last:border-0">
                    <div>
                        <span class="font-medium text-slate-800"><?php echo $proj['period']; ?></span>
                        <span class="text-sm text-slate-500 ml-2">(<?php echo $proj['count']; ?> invoices)</span>
                    </div>
                    <span class="font-semibold <?php echo $proj['period'] === 'Overdue' ? 'text-red-600' : 'text-slate-700'; ?>">
                        $<?php echo number_format($proj['amount'], 2); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="card">
        <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800">Payment Methods</h2>
            <p class="text-xs text-slate-500">Last 30 days</p>
        </div>
        <div class="p-6">
            <canvas id="paymentMethodChart" height="200"></canvas>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="card">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-slate-800">Recent Payments</h2>
            <a href="payments.php" class="text-sm text-primary-600 hover:text-primary-700">View All →</a>
        </div>
        <div class="divide-y divide-slate-100">
            <?php foreach ($recentTx as $tx): ?>
                <div class="px-4 py-3 flex items-center justify-between">
                    <div>
                        <span class="font-medium text-slate-800 text-sm"><?php echo htmlspecialchars($tx['student_name']); ?></span>
                        <p class="text-xs text-slate-500"><?php echo date('M j, g:i A', strtotime($tx['created_at'])); ?></p>
                    </div>
                    <span class="font-semibold text-green-600">+$<?php echo number_format($tx['amount'], 2); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Top Defaulters -->
<div class="card">
    <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
        <div>
            <h2 class="text-lg font-semibold text-slate-800">Students with Outstanding Dues</h2>
            <p class="text-xs text-slate-500">Sorted by highest outstanding amount</p>
        </div>
        <a href="ledger.php?filter=overdue" class="btn btn-secondary text-sm">
            View All Defaulters
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Student</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Outstanding</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Oldest Due</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php foreach ($defaulters as $d): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">
                            <span class="font-medium text-slate-800"><?php echo htmlspecialchars($d['name']); ?></span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600">
                            <div><?php echo htmlspecialchars($d['email']); ?></div>
                            <div><?php echo htmlspecialchars($d['phone']); ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="font-semibold text-red-600">$<?php echo number_format($d['outstanding'], 2); ?></span>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <?php if ($d['oldest_due']): ?>
                                <span class="text-red-600"><?php echo date('M j, Y', strtotime($d['oldest_due'])); ?></span>
                            <?php else: ?>
                                <span class="text-slate-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <a href="student_ledger.php?id=<?php echo $d['id']; ?>" class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                                View Ledger →
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Revenue Trend Chart
new Chart(document.getElementById('revenueTrendChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($trend, 'label')); ?>,
        datasets: [{
            label: 'Revenue',
            data: <?php echo json_encode(array_column($trend, 'revenue')); ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { 
                beginAtZero: true,
                ticks: { callback: value => '$' + value.toLocaleString() }
            }
        }
    }
});

// Fee Type Breakdown Chart
new Chart(document.getElementById('feeTypeChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($feeTypes, 'fee_type')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($feeTypes, 'total_amount')); ?>,
            backgroundColor: ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ef4444', '#6366f1']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Payment Methods Chart
new Chart(document.getElementById('paymentMethodChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($methods, 'method')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($methods, 'total')); ?>,
            backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6']
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { 
                beginAtZero: true,
                ticks: { callback: value => '$' + value.toLocaleString() }
            }
        }
    }
});
</script>

<?php require_once '../../templates/footer.php'; ?>
```

### 3.4 Implementation Steps

#### Day 1: Service & Data
1. [ ] Create `FinancialReportService.php`
2. [ ] Write SQL queries for all metrics
3. [ ] Test with sample data
4. [ ] Handle edge cases (no data, null values)

#### Day 2: Dashboard UI
1. [ ] Create `modules/accounting/dashboard.php`
2. [ ] Implement KPI cards
3. [ ] Add Chart.js visualizations
4. [ ] Add defaulters table
5. [ ] Link from existing ledger.php
6. [ ] End-to-end testing

### 3.5 Testing Criteria

| Test Case | Expected Result |
|-----------|-----------------|
| No payments exist | Shows $0 gracefully |
| Month-over-month calculation | Correct percentage |
| Collection rate calculation | Accurate percentage |
| Click "View All Defaulters" | Filters to overdue only |
| Charts render | All 3 charts display correctly |
| Mobile responsiveness | Dashboard works on mobile |

---

## 4️⃣ PDF Report Generation

### 4.1 Problem Statement
Reports can only be viewed on screen or exported as CSV. Management meetings require:
- Printable PDF reports
- Professional formatting
- Charts embedded
- Branded headers/footers

### 4.2 Technical Specification

#### Library Selection

**Recommended**: [TCPDF](https://github.com/tecnickcom/TCPDF) or [FPDF](http://www.fpdf.org/)
- Lightweight
- No external dependencies
- Good PHP 8.x support

**Alternative**: [Dompdf](https://github.com/dompdf/dompdf) - HTML to PDF (easier styling)

#### Composer Dependency

```json
{
    "require": {
        "dompdf/dompdf": "^2.0"
    }
}
```

Run: `composer require dompdf/dompdf`

#### PDF Service

**File**: `app/Services/PdfReportService.php` (NEW)

```php
<?php
namespace EduCRM\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfReportService
{
    private $pdo;
    private $dompdf;
    private $companyName = 'EduCRM';
    private $companyLogo = '/public/assets/images/logo.png';

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        
        $this->dompdf = new Dompdf($options);
        $this->dompdf->setPaper('A4', 'portrait');
    }

    /**
     * Generate Counselor Performance Report
     */
    public function generateCounselorReport(string $startDate, string $endDate): string
    {
        $reportingService = new ReportingService($this->pdo);
        $counselorPerf = $reportingService->getCounselorPerformance($startDate, $endDate);
        $taskStats = $reportingService->getTaskCompletionRate($startDate, $endDate);
        $conversionRate = $reportingService->getLeadConversionFunnel($startDate, $endDate);

        $html = $this->getReportHeader('Counselor Performance Report', $startDate, $endDate);
        
        $html .= '<h2 style="color: #1e293b; margin-top: 30px;">Summary</h2>';
        $html .= '<table class="summary-table">
            <tr>
                <td><strong>Task Completion Rate</strong></td>
                <td>' . number_format($taskStats['completion_rate'], 1) . '%</td>
            </tr>
            <tr>
                <td><strong>Conversion Rate</strong></td>
                <td>' . number_format($conversionRate['conversion_rate'] ?? 0, 1) . '%</td>
            </tr>
        </table>';

        $html .= '<h2 style="color: #1e293b; margin-top: 30px;">Individual Performance</h2>';
        $html .= '<table class="data-table">
            <thead>
                <tr>
                    <th>Counselor</th>
                    <th>Tasks Completed</th>
                    <th>Appointments</th>
                    <th>Conversions</th>
                    <th>Score</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($counselorPerf as $c) {
            $html .= '<tr>
                <td>' . htmlspecialchars($c['name']) . '</td>
                <td>' . $c['tasks_completed'] . '</td>
                <td>' . $c['appointments_completed'] . '</td>
                <td>' . $c['conversions'] . '</td>
                <td><strong>' . $c['performance_score'] . '</strong></td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= $this->getReportFooter();

        return $this->generatePdf($html);
    }

    /**
     * Generate Financial Summary Report
     */
    public function generateFinancialReport(string $startDate, string $endDate): string
    {
        $financeService = new FinancialReportService($this->pdo);
        $overview = $financeService->getOverview($startDate, $endDate);
        $feeTypes = $financeService->getFeeTypeBreakdown();
        $defaulters = $financeService->getTopDefaulters(20);

        $html = $this->getReportHeader('Financial Summary Report', $startDate, $endDate);
        
        $html .= '<h2 style="color: #1e293b; margin-top: 30px;">Financial Overview</h2>';
        $html .= '<div style="display: flex; gap: 20px; margin-bottom: 20px;">
            <div class="kpi-box" style="background: #dcfce7; border-color: #10b981;">
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-value">$' . number_format($overview['total_revenue'], 2) . '</div>
            </div>
            <div class="kpi-box" style="background: #fef3c7; border-color: #f59e0b;">
                <div class="kpi-label">Outstanding</div>
                <div class="kpi-value">$' . number_format($overview['outstanding'], 2) . '</div>
            </div>
            <div class="kpi-box" style="background: #dbeafe; border-color: #3b82f6;">
                <div class="kpi-label">Collection Rate</div>
                <div class="kpi-value">' . number_format($overview['collection_rate'], 1) . '%</div>
            </div>
        </div>';

        $html .= '<h2 style="color: #1e293b; margin-top: 30px;">Revenue by Fee Type</h2>';
        $html .= '<table class="data-table">
            <thead>
                <tr>
                    <th>Fee Type</th>
                    <th>Invoices</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($feeTypes as $ft) {
            $html .= '<tr>
                <td>' . htmlspecialchars($ft['fee_type']) . '</td>
                <td>' . $ft['invoice_count'] . '</td>
                <td>$' . number_format($ft['total_amount'], 2) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';

        if (!empty($defaulters)) {
            $html .= '<h2 style="color: #1e293b; margin-top: 30px; page-break-before: always;">Outstanding Dues by Student</h2>';
            $html .= '<table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Outstanding</th>
                        <th>Oldest Due</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($defaulters as $d) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($d['name']) . '</td>
                    <td>' . htmlspecialchars($d['email']) . '</td>
                    <td style="color: #dc2626; font-weight: bold;">$' . number_format($d['outstanding'], 2) . '</td>
                    <td>' . ($d['oldest_due'] ? date('M j, Y', strtotime($d['oldest_due'])) : '-') . '</td>
                </tr>';
            }
            
            $html .= '</tbody></table>';
        }

        $html .= $this->getReportFooter();

        return $this->generatePdf($html);
    }

    /**
     * Generate Inquiry Pipeline Report
     */
    public function generatePipelineReport(string $startDate, string $endDate): string
    {
        $reportingService = new ReportingService($this->pdo);
        $funnel = $reportingService->getLeadConversionFunnel($startDate, $endDate);
        $priorityDist = $reportingService->getPriorityDistribution($startDate, $endDate);

        $html = $this->getReportHeader('Inquiry Pipeline Report', $startDate, $endDate);
        
        $html .= '<h2 style="color: #1e293b; margin-top: 30px;">Pipeline Summary</h2>';
        $html .= '<table class="data-table">
            <thead>
                <tr>
                    <th>Stage</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($funnel['stages'] ?? [] as $stage) {
            $html .= '<tr>
                <td>' . htmlspecialchars($stage['name']) . '</td>
                <td>' . $stage['count'] . '</td>
                <td>' . number_format($stage['percentage'], 1) . '%</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';

        $html .= '<h2 style="color: #1e293b; margin-top: 30px;">Priority Distribution</h2>';
        $html .= '<table class="data-table">
            <thead>
                <tr>
                    <th>Priority</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($priorityDist as $priority => $count) {
            $html .= '<tr>
                <td>' . ucfirst($priority) . '</td>
                <td>' . $count . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= $this->getReportFooter();

        return $this->generatePdf($html);
    }

    private function getReportHeader(string $title, string $startDate, string $endDate): string
    {
        $dateRange = date('M j, Y', strtotime($startDate)) . ' - ' . date('M j, Y', strtotime($endDate));
        $generatedAt = date('F j, Y g:i A');

        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1e293b; }
                .header { border-bottom: 2px solid #0d9488; padding-bottom: 15px; margin-bottom: 20px; }
                .header h1 { color: #0d9488; margin: 0 0 5px 0; font-size: 24px; }
                .header .meta { color: #64748b; font-size: 11px; }
                .data-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                .data-table th { background: #f1f5f9; padding: 10px; text-align: left; border-bottom: 2px solid #e2e8f0; }
                .data-table td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
                .data-table tr:nth-child(even) { background: #f8fafc; }
                .summary-table { width: 50%; margin: 15px 0; }
                .summary-table td { padding: 8px; border-bottom: 1px solid #e2e8f0; }
                .kpi-box { flex: 1; padding: 15px; border-radius: 8px; border-left: 4px solid; }
                .kpi-label { font-size: 11px; color: #64748b; }
                .kpi-value { font-size: 20px; font-weight: bold; margin-top: 5px; }
                .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 10px; text-align: center; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>' . htmlspecialchars($title) . '</h1>
                <div class="meta">
                    <strong>Period:</strong> ' . $dateRange . ' &nbsp;|&nbsp; 
                    <strong>Generated:</strong> ' . $generatedAt . '
                </div>
            </div>';
    }

    private function getReportFooter(): string
    {
        return '<div class="footer">
                <p>' . $this->companyName . ' &copy; ' . date('Y') . ' | Confidential Report</p>
            </div>
        </body>
        </html>';
    }

    private function generatePdf(string $html): string
    {
        $this->dompdf->loadHtml($html);
        $this->dompdf->render();
        
        // Generate filename
        $filename = 'report_' . date('Y-m-d_His') . '.pdf';
        $filepath = __DIR__ . '/../../storage/exports/' . $filename;
        
        // Save to file
        file_put_contents($filepath, $this->dompdf->output());
        
        return $filename;
    }

    /**
     * Stream PDF directly to browser
     */
    public function streamPdf(string $html, string $filename = 'report.pdf'): void
    {
        $this->dompdf->loadHtml($html);
        $this->dompdf->render();
        $this->dompdf->stream($filename, ['Attachment' => false]);
    }
}
```

### 4.3 Report Generation Endpoint

**File**: `modules/reports/generate_pdf.php` (NEW)

```php
<?php
require_once '../../app/bootstrap.php';
requireLogin();
requireAdminCounselorOrBranchManager();

$reportType = $_GET['type'] ?? 'counselor';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$pdfService = new \EduCRM\Services\PdfReportService($pdo);

try {
    switch ($reportType) {
        case 'counselor':
            $filename = $pdfService->generateCounselorReport($startDate, $endDate);
            $title = 'Counselor Performance Report';
            break;
            
        case 'financial':
            $filename = $pdfService->generateFinancialReport($startDate, $endDate);
            $title = 'Financial Summary Report';
            break;
            
        case 'pipeline':
            $filename = $pdfService->generatePipelineReport($startDate, $endDate);
            $title = 'Inquiry Pipeline Report';
            break;
            
        default:
            throw new \Exception('Invalid report type');
    }

    // Redirect to download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $title . '.pdf"');
    readfile(__DIR__ . '/../../storage/exports/' . $filename);
    
    // Clean up
    unlink(__DIR__ . '/../../storage/exports/' . $filename);
    
} catch (\Exception $e) {
    redirectWithAlert('dashboard.php', 'Failed to generate report: ' . $e->getMessage(), 'error');
}
```

### 4.4 UI Integration

**Add to**: `modules/reports/dashboard.php`

```php
<!-- PDF Export Buttons -->
<div class="flex gap-3">
    <div class="relative" x-data="{ open: false }">
        <button @click="open = !open" class="btn btn-primary flex items-center gap-2">
            <?php echo getIcon('download', 16); ?> Download PDF
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="open" @click.away="open = false"
             class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-slate-200 z-10">
            <a href="generate_pdf.php?type=counselor&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
               class="block px-4 py-3 hover:bg-slate-50 text-sm">
                📊 Counselor Performance
            </a>
            <a href="generate_pdf.php?type=financial&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
               class="block px-4 py-3 hover:bg-slate-50 text-sm">
                💰 Financial Summary
            </a>
            <a href="generate_pdf.php?type=pipeline&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
               class="block px-4 py-3 hover:bg-slate-50 text-sm">
                📈 Inquiry Pipeline
            </a>
        </div>
    </div>
</div>
```

### 4.5 Implementation Steps

#### Day 1: Setup & Service
1. [ ] Run `composer require dompdf/dompdf`
2. [ ] Create `PdfReportService.php`
3. [ ] Create report HTML templates
4. [ ] Test PDF generation locally

#### Day 2: Integration
1. [ ] Create `generate_pdf.php` endpoint
2. [ ] Add download buttons to reports dashboard
3. [ ] Style PDFs with branding
4. [ ] Add page breaks for long reports
5. [ ] End-to-end testing

### 4.6 Testing Criteria

| Test Case | Expected Result |
|-----------|-----------------|
| Generate Counselor report | PDF downloads correctly |
| Generate Financial report | All data appears formatted |
| Report with 100+ rows | Proper pagination in PDF |
| Date range filter | Correct data for period |
| Mobile download | Works on mobile browsers |
| Empty data | Shows "No data" gracefully |

---

## 📊 Phase 2 Summary

### Files to Create

| File | Description |
|------|-------------|
| `app/Services/KanbanService.php` | Kanban board data handling |
| `app/Services/GoogleCalendarService.php` | Calendar sync logic |
| `app/Services/FinancialReportService.php` | Financial metrics |
| `app/Services/PdfReportService.php` | PDF generation |
| `templates/components/kanban-board.php` | Reusable Kanban component |
| `api/v1/kanban/index.php` | Kanban API endpoints |
| `modules/tasks/kanban.php` | Tasks Kanban view |
| `modules/inquiries/kanban.php` | Inquiries Kanban view |
| `modules/visa/kanban.php` | Visa Kanban view |
| `modules/appointments/google-connect.php` | OAuth initiation |
| `modules/appointments/google-callback.php` | OAuth callback |
| `modules/accounting/dashboard.php` | Financial dashboard |
| `modules/reports/generate_pdf.php` | PDF generation endpoint |
| `database/migrations/add_calendar_integration.sql` | Calendar tables |
| `config/google.php` | Google API config |

### Composer Dependencies

```bash
composer require google/apiclient dompdf/dompdf sortablejs/sortablejs
```

### Estimated Timeline

| Week | Days | Tasks |
|------|------|-------|
| Week 1 | Day 1-4 | Kanban Boards (all modules) |
| Week 2 | Day 1-3 | Google Calendar Integration |
| Week 2 | Day 4-5 | Financial Dashboard |
| Week 2 | Day 6-7 | PDF Reports + Testing |

### Success Metrics

| Metric | Target |
|--------|--------|
| Kanban drag latency | < 200ms |
| Calendar sync success rate | > 95% |
| Dashboard load time | < 3 seconds |
| PDF generation time | < 5 seconds |
| User adoption of Kanban | 70%+ usage |

---

## 🚀 Getting Started

1. **Complete Phase 1** before starting Phase 2
2. **Create feature branch**: `git checkout -b feature/phase2-ux-improvements`
3. **Start with Kanban** as it has highest user impact
4. **Google Calendar** requires OAuth setup (may need Google Cloud Console access)
5. **Daily testing** during development
6. **Staging deployment** for QA
7. **User training** for new features

---

*Document Version: 1.0*  
*Created: January 22, 2026*  
*Author: Development Team*  
*Prerequisites: Phase 1 Completed*
