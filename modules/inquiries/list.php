<?php
/**
 * Inquiry List
 * Displays all inquiries with lead scoring and priority filtering
 */
require_once '../../app/bootstrap.php';

requireLogin();

// Admin, Counselor, or Branch Manager access only
requireCRMAccess();


$branchService = new \EduCRM\Services\BranchService($pdo);
$branchFilter = $branchService->getBranchFilter($_SESSION['user_id'], 'i');

$leadScoringService = new \EduCRM\Services\LeadScoringService($pdo);

// Get filter parameter
$priorityFilter = $_GET['priority'] ?? null;
$statusFilter = $_GET['status'] ?? null;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

// Build query with filters - Using FK columns with JOINs
$sql = "SELECT i.*, 
        u.name as counselor_name,
        c.name as country_name,
        el.name as education_level_name,
        ist.name as status_name,
        pl.name as priority_name,
        pl.color_code as priority_color,
        DATEDIFF(CURDATE(), i.last_contacted) as days_since_contact
        FROM inquiries i 
        LEFT JOIN users u ON i.assigned_to = u.id 
        LEFT JOIN countries c ON i.country_id = c.id
        LEFT JOIN education_levels el ON i.education_level_id = el.id
        LEFT JOIN inquiry_statuses ist ON i.status_id = ist.id
        LEFT JOIN priority_levels pl ON i.priority_id = pl.id
        WHERE 1=1 $branchFilter";
$params = [];


if ($priorityFilter) {
    $sql .= " AND pl.name = ?";
    $params[] = $priorityFilter;
}

if ($statusFilter) {
    $sql .= " AND ist.name = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY i.score DESC, i.created_at DESC";

// Use PaginationService for server-side pagination
$paginationService = new \EduCRM\Services\PaginationService($pdo, $perPage);
$paginationService->setPage($currentPage);
$inquiries = $paginationService->paginate($sql, $params);
$pagination = $paginationService->getMetadata();

// Get priority stats for filter chips
$priorityStats = $leadScoringService->getPriorityStats();

// Get all counselors for bulk assign
$counselorsStmt = $pdo->query("
    SELECT DISTINCT u.id, u.name 
    FROM users u 
    JOIN user_roles ur ON u.id = ur.user_id 
    JOIN roles r ON ur.role_id = r.id 
    WHERE r.name IN ('admin', 'counselor')
    ORDER BY u.name
");
$counselors = $counselorsStmt->fetchAll(PDO::FETCH_ASSOC);

$pageDetails = ['title' => 'Inquiry List'];
require_once '../../templates/header.php';
?>

<div class="page-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h1 class="page-title">Inquiries</h1>
    <div class="flex gap-3">
        <!-- View Toggle -->
        <div
            class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 p-1 bg-slate-100 dark:bg-slate-800">
            <span
                class="px-3 py-1.5 text-sm rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-200 shadow-sm flex items-center gap-1">
                <?php echo \EduCRM\Services\NavigationService::getIcon('list', 16); ?> List
            </span>
            <a href="kanban.php"
                class="px-3 py-1.5 text-sm rounded-md text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 transition-colors flex items-center gap-1">
                <?php echo \EduCRM\Services\NavigationService::getIcon('columns', 16); ?> Board
            </a>
        </div>
        <a href="add.php" class="btn btn-primary">
            <?php echo \EduCRM\Services\NavigationService::getIcon('plus', 16); ?> Add New Inquiry
        </a>
    </div>
</div>

<!-- Quick Search with Alpine.js -->
<div class="bg-white px-4 py-3 rounded-xl border border-slate-200 shadow-sm mb-4">
    <div x-data='searchFilter({
        data: <?php echo json_encode(array_map(function ($i) {
            return [
                'id' => $i['id'],
                'name' => $i['name'],
                'email' => $i['email'],
                'phone' => $i['phone'] ?? '',
                'priority' => $i['priority_name'] ?? $i['priority'] ?? 'cold',
                'status' => $i['status_name'] ?? $i['status'] ?? 'new'
            ];
        }, $inquiries)); ?>,
        searchFields: ["name", "email", "phone", "priority", "status"],
        minLength: 2,
        maxResults: 8
    })' class="relative">
        <div class="flex items-center gap-3">
            <span class="text-slate-400">🔍</span>
            <input type="text" x-model="query" @input="search()" @focus="if(query.length >= 2) showResults = true"
                @keydown="handleKeydown($event)" @keydown.escape="showResults = false"
                class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Quick search by name, email, or phone..." autocomplete="off">

            <span x-show="loading" class="spinner text-slate-400"></span>
        </div>

        <!-- Search Results Dropdown -->
        <div x-show="showResults && results.length > 0" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 transform -translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" @click.outside="showResults = false"
            class="search-results-container absolute top-full left-0 right-0 mt-2 bg-white border border-slate-200 rounded-xl shadow-lg max-h-80 overflow-y-auto z-50">

            <template x-for="(item, index) in results" :key="item.id">
                <a :href="'edit.php?id=' + item.id" :data-index="index" @mouseenter="setSelectedIndex(index)"
                    class="flex items-center gap-3 px-4 py-3 border-b border-slate-100 transition-colors"
                    :class="{ 'bg-primary-50 border-l-4 border-l-teal-600': isSelected(index), 'hover:bg-slate-50': !isSelected(index) }">
                    <div class="w-9 h-9 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-full flex items-center justify-center text-white font-bold text-sm flex-shrink-0"
                        x-text="item.name.charAt(0).toUpperCase()"></div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-slate-800" x-text="item.name"></div>
                        <div class="text-xs text-slate-500 truncate">
                            <span x-text="item.email"></span> • <span x-text="item.phone || 'No phone'"></span>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 rounded text-xs font-bold" :class="{
                              'bg-red-100 text-red-700': item.priority === 'hot',
                              'bg-orange-100 text-orange-700': item.priority === 'warm',
                              'bg-blue-100 text-blue-700': item.priority === 'cold'
                          }">
                        <span x-text="item.priority === 'hot' ? '🔥' : (item.priority === 'warm' ? '☀️' : '❄️')"></span>
                        <span x-text="item.priority.toUpperCase()"></span>
                    </span>
                </a>
            </template>

            <div x-show="results.length === 0 && query.length >= 2 && !loading"
                class="px-4 py-3 text-center text-slate-500 text-sm">
                No inquiries found
            </div>
        </div>
    </div>
</div>

<?php renderFlashMessage(); ?>

<!-- Priority Filter Bar - Single Row -->
<div class="bg-white px-5 py-4 rounded-xl border border-slate-200 shadow-sm mb-6">
    <div class="flex items-center justify-between gap-6">
        <!-- Priority Pills -->
        <div class="flex items-center gap-3">
            <span class="text-sm font-medium text-slate-500">Priority:</span>

            <a href="list.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all
                       <?php echo !$priorityFilter
                           ? 'bg-slate-700 text-white shadow-sm'
                           : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                <span>📋</span>
                <span>All</span>
                <span
                    class="<?php echo !$priorityFilter ? 'bg-slate-600 text-slate-200' : 'bg-slate-200 text-slate-600'; ?> px-1.5 py-0.5 rounded-full text-xs font-bold"><?php echo array_sum($priorityStats); ?></span>
            </a>

            <a href="?priority=hot<?php echo $statusFilter ? "&status=$statusFilter" : ''; ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all
                       <?php echo $priorityFilter === 'hot'
                           ? 'bg-red-500 text-white shadow-sm'
                           : 'bg-red-50 text-red-600 hover:bg-red-100'; ?>">
                <span>🔥</span>
                <span>Hot</span>
                <span
                    class="<?php echo $priorityFilter === 'hot' ? 'bg-red-400 text-white' : 'bg-red-100 text-red-600'; ?> px-1.5 py-0.5 rounded-full text-xs font-bold"><?php echo $priorityStats['hot']; ?></span>
            </a>

            <a href="?priority=warm<?php echo $statusFilter ? "&status=$statusFilter" : ''; ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all
                       <?php echo $priorityFilter === 'warm'
                           ? 'bg-orange-500 text-white shadow-sm'
                           : 'bg-orange-50 text-orange-600 hover:bg-orange-100'; ?>">
                <span>☀️</span>
                <span>Warm</span>
                <span
                    class="<?php echo $priorityFilter === 'warm' ? 'bg-orange-400 text-white' : 'bg-orange-100 text-orange-600'; ?> px-1.5 py-0.5 rounded-full text-xs font-bold"><?php echo $priorityStats['warm']; ?></span>
            </a>

            <a href="?priority=cold<?php echo $statusFilter ? "&status=$statusFilter" : ''; ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all
                       <?php echo $priorityFilter === 'cold'
                           ? 'bg-blue-500 text-white shadow-sm'
                           : 'bg-blue-50 text-blue-600 hover:bg-blue-100'; ?>">
                <span>❄️</span>
                <span>Cold</span>
                <span
                    class="<?php echo $priorityFilter === 'cold' ? 'bg-blue-400 text-white' : 'bg-blue-100 text-blue-600'; ?> px-1.5 py-0.5 rounded-full text-xs font-bold"><?php echo $priorityStats['cold']; ?></span>
            </a>
        </div>

        <!-- Status Filter Pills -->
        <div class="hidden md:flex items-center gap-2 pl-6 border-l border-slate-200">
            <span class="text-sm font-medium text-slate-500">Status:</span>
            <?php
            $statuses = [
                'new' => ['label' => 'New', 'color' => 'bg-blue-100 text-blue-700 hover:bg-blue-200'],
                'contacted' => ['label' => 'Contacted', 'color' => 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200'],
                'converted' => ['label' => 'Converted', 'color' => 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'],
                'closed' => ['label' => 'Closed', 'color' => 'bg-slate-100 text-slate-700 hover:bg-slate-200']
            ];

            foreach ($statuses as $k => $s):
                $isActive = $statusFilter === $k;
                $activeClass = match ($k) {
                    'new' => 'bg-blue-600 text-white shadow-sm',
                    'contacted' => 'bg-yellow-500 text-white shadow-sm',
                    'converted' => 'bg-emerald-600 text-white shadow-sm',
                    'closed' => 'bg-slate-600 text-white shadow-sm',
                };
                ?>
                <a href="?status=<?php echo $k; ?><?php echo $priorityFilter ? "&priority=$priorityFilter" : ''; ?>"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all <?php echo $isActive ? $activeClass : $s['color']; ?>">
                    <?php echo $s['label']; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Right Side: Clear only -->
        <?php if ($priorityFilter || $statusFilter): ?>
            <a href="list.php" class="text-sm text-slate-400 hover:text-red-500 transition-colors" title="Clear filters">
                ✕ Clear filters
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Bulk Action Toolbar with Alpine.js -->
<script>
    function inquiryBulkActions() {
        return {
            selected: [],
            action: '',
            assignTo: '',
            priority: '',
            status: '',
            loading: false,
            allIds: <?php echo json_encode(array_column($inquiries, 'id')); ?>,

            get count() { return this.selected.length; },
            get hasSelection() { return this.selected.length > 0; },
            get allSelected() { return this.selected.length === this.allIds.length && this.allIds.length > 0; },

            toggleAll() {
                this.selected = this.allSelected ? [] : [...this.allIds];
            },
            toggle(id) {
                const idx = this.selected.indexOf(id);
                if (idx === -1) this.selected.push(id);
                else this.selected.splice(idx, 1);
            },
            isSelected(id) { return this.selected.includes(id); },
            clear() { this.selected = []; this.action = ''; },

            async apply() {
                if (!this.action) { Modal.show({ type: 'warning', title: 'Select Action', message: 'Please select an action' }); return; }
                if (this.selected.length === 0) { Modal.show({ type: 'warning', title: 'No Selection', message: 'Please select at least one inquiry' }); return; }

                if (this.action === 'assign' && !this.assignTo) { Modal.show({ type: 'warning', title: 'Select Counselor', message: 'Please select a counselor' }); return; }
                if (this.action === 'priority' && !this.priority) { Modal.show({ type: 'warning', title: 'Select Priority', message: 'Please select a priority' }); return; }
                if (this.action === 'status' && !this.status) { Modal.show({ type: 'warning', title: 'Select Status', message: 'Please select a status' }); return; }

                if (this.action === 'email') {
                    document.getElementById('recipientCount').textContent = this.selected.length;
                    document.getElementById('emailModal').classList.remove('hidden');
                    return;
                }

                const actionNames = { assign: 'assign', priority: 'update priority for', status: 'update status for', delete: 'delete' };
                const self = this;

                Modal.show({
                    type: this.action === 'delete' ? 'error' : 'warning',
                    title: 'Confirm Bulk Action',
                    message: `Are you sure you want to ${actionNames[this.action] || this.action} ${this.selected.length} inquiry(ies)?`,
                    confirmText: 'Yes, Proceed',
                    onConfirm: async function () {
                        self.loading = true;
                        const formData = new FormData();
                        formData.append('action', self.action);
                        self.selected.forEach(id => formData.append('inquiry_ids[]', id));
                        if (self.action === 'assign') formData.append('assign_to', self.assignTo);
                        if (self.action === 'priority') formData.append('priority', self.priority);
                        if (self.action === 'status') formData.append('status', self.status);

                        try {
                            const response = await fetch('bulk_action.php', { method: 'POST', body: formData });
                            const data = await response.json();
                            if (data.success) {
                                Modal.show({ type: 'success', title: 'Success', message: data.message });
                                setTimeout(() => window.location.reload(), 1500);
                            } else {
                                Modal.show({ type: 'error', title: 'Error', message: data.message });
                            }
                        } catch (e) {
                            Modal.show({ type: 'error', title: 'Error', message: 'An error occurred: ' + e });
                        }
                        self.loading = false;
                    }
                });
            }
        };
    }
</script>
<div x-data="inquiryBulkActions()" x-ref="bulkContainer">

    <!-- Toolbar (shows when items selected) -->
    <div x-show="hasSelection" x-cloak x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 transform -translate-y-2"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        class="bg-primary-50 border border-primary-200 p-4 rounded-xl mb-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <span class="text-primary-700 font-medium">
                    <span x-text="count"></span> inquiry(ies) selected
                </span>

                <select x-model="action" class="px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white">
                    <option value="">Choose Action...</option>
                    <option value="assign">Assign Counselor...</option>
                    <option value="priority">Change Priority...</option>
                    <option value="status">Change Status...</option>
                    <option value="email">Send Email...</option>
                    <?php if (hasRole('admin')): ?>
                        <option value="delete">Delete Selected</option>
                    <?php endif; ?>
                </select>

                <select x-show="action === 'assign'" x-model="assignTo" x-transition
                    class="px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white">
                    <option value="">Select Counselor...</option>
                    <?php foreach ($counselors as $counselor): ?>
                        <option value="<?php echo $counselor['id']; ?>"><?php echo htmlspecialchars($counselor['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select x-show="action === 'priority'" x-model="priority" x-transition
                    class="px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white">
                    <option value="">Select Priority...</option>
                    <option value="hot">🔥 Hot</option>
                    <option value="warm">☀️ Warm</option>
                    <option value="cold">❄️ Cold</option>
                </select>

                <select x-show="action === 'status'" x-model="status" x-transition
                    class="px-3 py-2 border border-primary-300 rounded-lg text-sm bg-white">
                    <option value="">Select Status...</option>
                    <option value="new">New</option>
                    <option value="contacted">Contacted</option>
                    <option value="converted">Converted</option>
                    <option value="closed">Closed</option>
                </select>

                <button @click="apply()" :disabled="loading" class="btn px-4 py-2 text-sm">
                    <span x-show="!loading">Apply</span>
                    <span x-show="loading">Processing...</span>
                </button>
            </div>

            <button @click="clear()" class="text-sm text-primary-600 hover:text-primary-800">Clear Selection</button>
        </div>
    </div>

    <!-- Inquiries Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-sm">
                        <th class="p-3 w-12">
                            <input type="checkbox" @click="toggleAll()" :checked="allSelected"
                                class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                        </th>
                        <th class="p-3 font-semibold">Name</th>
                        <th class="p-3 font-semibold">Contact</th>
                        <th class="p-3 font-semibold">Interest</th>
                        <th class="p-3 font-semibold">Priority</th>
                        <th class="p-3 font-semibold">Score</th>
                        <th class="p-3 font-semibold">Assigned To</th>
                        <th class="p-3 font-semibold">Last Contacted</th>
                        <th class="p-3 font-semibold">Status</th>
                        <th class="p-3 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (count($inquiries) > 0):
                        foreach ($inquiries as $inq): ?>
                            <?php
                            $priorityColors = [
                                'hot' => 'bg-red-100 text-red-700 border-red-200',
                                'warm' => 'bg-orange-100 text-orange-700 border-orange-200',
                                'cold' => 'bg-blue-100 text-blue-700 border-blue-200'
                            ];
                            $priorityIcons = ['hot' => '🔥', 'warm' => '☀️', 'cold' => '❄️'];

                            $statusColors = [
                                'new' => 'bg-blue-100 text-blue-700',
                                'contacted' => 'bg-yellow-100 text-yellow-700',
                                'converted' => 'bg-emerald-100 text-emerald-700',
                                'closed' => 'bg-slate-100 text-slate-700'
                            ];
                            ?>
                            <tr class="hover:bg-slate-50 transition-colors"
                                :class="{ 'bg-primary-50': isSelected(<?php echo $inq['id']; ?>) }">
                                <td class="p-3">
                                    <input type="checkbox" @click="toggle(<?php echo $inq['id']; ?>)"
                                        :checked="isSelected(<?php echo $inq['id']; ?>)"
                                        class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                                </td>
                                <td class="p-3">
                                    <strong class="text-slate-800"><?php echo htmlspecialchars($inq['name']); ?></strong>
                                </td>
                                <td class="p-3 text-sm text-slate-600">
                                    <div><?php echo htmlspecialchars($inq['email']); ?></div>
                                    <div class="text-xs text-slate-500"><?php echo htmlspecialchars($inq['phone']); ?></div>
                                </td>
                                <td class="p-3 text-sm text-slate-600">
                                    <div><?php echo htmlspecialchars($inq['country_name'] ?? $inq['intended_country'] ?? ''); ?>
                                    </div>
                                    <div class="text-xs text-slate-500"><?php echo htmlspecialchars($inq['intended_course']); ?>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <?php $displayPriority = $inq['priority_name'] ?? $inq['priority'] ?? 'cold'; ?>
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded border text-xs font-bold uppercase <?php echo $priorityColors[$displayPriority] ?? $priorityColors['cold']; ?>">
                                        <?php echo $priorityIcons[$displayPriority] ?? '❄️'; ?>
                                        <?php echo $displayPriority; ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <span class="text-lg font-bold text-slate-800"><?php echo $inq['score']; ?></span>
                                    <span class="text-xs text-slate-500">/100</span>
                                </td>
                                <td class="p-3 text-sm text-slate-600">
                                    <?php echo htmlspecialchars($inq['counselor_name'] ?? 'Unassigned'); ?>
                                </td>
                                <td class="p-3">
                                    <?php
                                    // Last Contacted with color-coded display
                                    $daysSinceContact = $inq['days_since_contact'] ?? null;
                                    $lastContactedDate = $inq['last_contacted'] ?? null;

                                    if ($lastContactedDate) {
                                        $contactClass = match (true) {
                                            $daysSinceContact === null || $daysSinceContact < 0 => 'text-slate-400',
                                            $daysSinceContact <= 3 => 'text-emerald-600 bg-emerald-50',
                                            $daysSinceContact <= 7 => 'text-blue-600 bg-blue-50',
                                            $daysSinceContact <= 14 => 'text-yellow-600 bg-yellow-50',
                                            default => 'text-red-600 bg-red-50'
                                        };

                                        $relativeTime = match (true) {
                                            $daysSinceContact === 0 => 'Today',
                                            $daysSinceContact === 1 => 'Yesterday',
                                            $daysSinceContact <= 7 => $daysSinceContact . 'd ago',
                                            $daysSinceContact <= 30 => floor($daysSinceContact / 7) . 'w ago',
                                            default => floor($daysSinceContact / 30) . 'mo ago'
                                        };
                                        ?>
                                        <span
                                            class="inline-block px-2 py-0.5 rounded text-xs font-medium <?php echo $contactClass; ?>"
                                            title="<?php echo date('M d, Y', strtotime($lastContactedDate)); ?>">
                                            <?php echo $relativeTime; ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="text-xs text-red-500 bg-red-50 px-2 py-0.5 rounded">Never</span>
                                    <?php } ?>
                                </td>
                                <td class="p-3">
                                    <?php $displayStatus = $inq['status_name'] ?? $inq['status'] ?? 'new'; ?>
                                    <span
                                        class="inline-block px-2 py-0.5 rounded text-xs font-bold uppercase <?php echo $statusColors[$displayStatus] ?? $statusColors['new']; ?>">
                                        <?php echo $displayStatus; ?>
                                    </span>
                                </td>
                                <td class="p-3 text-right">
                                    <div class="flex gap-2 justify-end">
                                        <a href="edit.php?id=<?php echo $inq['id']; ?>" class="action-btn default" title="Edit">
                                            <?php echo \EduCRM\Services\NavigationService::getIcon('edit', 16); ?>
                                        </a>
                                        <a href="convert.php?id=<?php echo $inq['id']; ?>" class="action-btn green"
                                            title="Convert to Student">
                                            <?php echo \EduCRM\Services\NavigationService::getIcon('check-square', 16); ?>
                                        </a>
                                        <?php if (hasRole('admin') || hasRole('branch_manager')): ?>
                                            <a href="#" onclick="confirmDelete(<?php echo $inq['id']; ?>)" class="action-btn red"
                                                title="Delete">
                                                <?php echo \EduCRM\Services\NavigationService::getIcon('trash', 16); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="9" class="p-6 text-center text-slate-500">
                                No inquiries found. <a href="add.php" class="text-primary-600 hover:underline">Add your
                                    first
                                    inquiry</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- End of Alpine.js bulk container -->

<?php // Pagination Controls ?>
<?php include __DIR__ . '/../../templates/partials/pagination.php'; ?>

<!-- Phase 2C: Bulk Email Composer Modal -->
<div id="emailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4">
        <div class="p-6">
            <div class="flex justify-between items-start mb-4">
                <h2 class="text-xl font-bold text-slate-800">Compose Bulk Email</h2>
                <button onclick="closeEmailModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-700">
                    <strong>Recipients:</strong> <span id="recipientCount">0</span> selected inquiries with valid email
                    addresses
                </p>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Subject *</label>
                    <input type="text" id="emailSubject"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                        placeholder="Email subject...">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Message *</label>
                    <textarea id="emailBody" rows="8"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                        placeholder="Email message..."></textarea>
                </div>
            </div>

            <div class="flex gap-3 mt-6 pt-4 border-t border-slate-200">
                <button onclick="sendBulkEmail()" class="btn">Send Email</button>
                <button onclick="closeEmailModal()"
                    class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
            </div>
        </div>
    </div>
</div>



<!-- Standardized Delete Script -->
<script>
    function confirmDelete(id) {
        Modal.show({
            type: 'error',
            title: 'Delete Inquiry?',
            message: 'Are you sure you want to delete this inquiry? This action cannot be undone.',
            confirmText: 'Yes, Delete It',
            onConfirm: function () {
                window.location.href = 'delete.php?id=' + id;
            }
        });
    }
</script>

<!-- Email Modal Functions -->
<script>
    // Email Modal Functions
    window.closeEmailModal = function () {
        document.getElementById('emailModal').classList.add('hidden');
        document.getElementById('emailSubject').value = '';
        document.getElementById('emailBody').value = '';
    };

    window.sendBulkEmail = function () {
        const subject = document.getElementById('emailSubject').value.trim();
        const body = document.getElementById('emailBody').value.trim();

        if (!subject || !body) {
            Modal.show({ type: 'warning', title: 'Missing Fields', message: 'Please fill in both subject and message' });
            return;
        }

        // Get selected IDs from Alpine.js component
        const bulkContainer = document.querySelector('[x-ref="bulkContainer"]');
        if (!bulkContainer || !bulkContainer.__x) {
            Modal.show({ type: 'error', title: 'Error', message: 'Could not find selection data' });
            return;
        }
        const selectedIds = bulkContainer.__x.$data.selected;

        Modal.show({
            type: 'info',
            title: 'Confirm Send Email',
            message: `Send email to ${selectedIds.length} recipient(s)?`,
            confirmText: 'Send Email',
            onConfirm: function () {
                const formData = new FormData();
                formData.append('action', 'email');
                selectedIds.forEach(id => formData.append('inquiry_ids[]', id));
                formData.append('email_subject', subject);
                formData.append('email_body', body);

                fetch('bulk_action.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Modal.show({ type: 'success', title: 'Success', message: data.message });
                            closeEmailModal();
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            Modal.show({ type: 'error', title: 'Error', message: data.message });
                        }
                    })
                    .catch(error => {
                        Modal.show({ type: 'error', title: 'Error', message: 'An error occurred: ' + error });
                    });
            }
        });
    };
</script>

<?php require_once '../../templates/footer.php'; ?>