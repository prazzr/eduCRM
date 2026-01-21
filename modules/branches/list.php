<?php
/**
 * Branch Management - List View
 * Manage all branch offices
 */

require_once '../../app/bootstrap.php';

requireLogin();

// Only admin can manage branches
if (!hasRole('admin')) {
    die("Access denied. Admin only.");
}

$branchService = new \EduCRM\Services\BranchService($pdo);
$message = '';
$error = '';

// Handle branch selection for data filtering
if (isset($_GET['select_branch'])) {
    $_SESSION['selected_branch_id'] = (int) $_GET['select_branch'];
    header("Location: list.php");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];
    if ($branchService->deleteBranch($deleteId)) {
        redirectWithAlert("list.php", "Branch deactivated successfully.", "danger");
    } else {
        redirectWithAlert("list.php", "Cannot delete headquarters or branch with active data.", "danger");
    }
}

// Get all branches
$showInactive = isset($_GET['show_inactive']);
$branches = $branchService->getBranches(!$showInactive);

$pageDetails = ['title' => 'Branch Management'];
require_once '../../templates/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Branch Offices</h1>
    <div class="flex gap-2">
        <?php if ($showInactive): ?>
            <a href="list.php" class="btn btn-secondary">
                <?php echo \EduCRM\Services\NavigationService::getIcon('eye-off', 16); ?> Hide Inactive
            </a>
        <?php else: ?>
            <a href="?show_inactive=1" class="btn btn-secondary">
                <?php echo \EduCRM\Services\NavigationService::getIcon('eye', 16); ?> Show Inactive
            </a>
        <?php endif; ?>
        <a href="add.php" class="btn btn-primary">
            <?php echo \EduCRM\Services\NavigationService::getIcon('plus', 16); ?> Add Branch
        </a>
    </div>
</div>

<?php renderFlashMessage(); ?>

<!-- Quick Search with Alpine.js -->
<div class="bg-white px-4 py-3 rounded-xl border border-slate-200 shadow-sm mb-4">
    <div x-data='searchFilter({
        data: <?php echo json_encode(array_map(function ($b) {
            return [
                'id' => $b['id'],
                'name' => $b['name'],
                'code' => $b['code'],
                'city' => $b['city'] ?? '',
                'manager' => $b['manager_name'] ?? '',
                'active' => $b['is_active'],
                'hq' => $b['is_headquarters']
            ];
        }, $branches)); ?>,
        searchFields: ["name", "code", "city"],
        minLength: 1,
        maxResults: 8
    })' class="relative">
        <div class="flex items-center gap-3">
            <span class="text-slate-400">🔍</span>
            <input type="text" x-model="query" @input="search()" @focus="if(query.length >= 1) showResults = true"
                @keydown="handleKeydown($event)" @keydown.escape="showResults = false"
                class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500"
                placeholder="Quick search by branch name, code, or city..." autocomplete="off">

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
                    <div class="w-9 h-9 bg-gradient-to-br from-amber-500 to-orange-600 rounded-lg flex items-center justify-center text-white font-bold text-xs"
                        x-text="item.code"></div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-slate-800">
                            <span x-text="item.name"></span>
                            <span x-show="item.hq" class="ml-1">⭐</span>
                        </div>
                        <div class="text-xs text-slate-500">
                            <span x-text="item.city || 'No city'"></span> •
                            <span x-text="item.manager || 'No manager'"></span>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                        :class="item.active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                        x-text="item.active ? 'Active' : 'Inactive'"></span>
                </a>
            </template>

            <div x-show="results.length === 0 && query.length >= 1 && !loading"
                class="px-4 py-3 text-center text-slate-500 text-sm">
                No branches found
            </div>
        </div>
    </div>
</div>

<!-- Current Branch Filter Info -->
<?php if (isset($_SESSION['selected_branch_id']) && $_SESSION['selected_branch_id'] > 0): ?>
    <?php $selectedBranch = $branchService->getBranch($_SESSION['selected_branch_id']); ?>
    <div class="bg-blue-50 border border-blue-200 p-3 rounded-lg mb-6 flex justify-between items-center">
        <span class="text-blue-700 flex items-center gap-2">
            <?php echo \EduCRM\Services\NavigationService::getIcon('filter', 16); ?>
            <span>
                <strong>Data Filter Active:</strong> Showing data for <strong>
                    <?php echo htmlspecialchars($selectedBranch['name']); ?>
                </strong>
            </span>
        </span>
        <a href="?select_branch=0" class="text-blue-600 hover:underline text-sm font-medium">Clear Filter</a>
    </div>
<?php endif; ?>

<div class="card">
    <?php if (empty($branches)): ?>
        <p class="text-slate-500 text-center py-8">No branches found. Add your first branch office.</p>
    <?php else: ?>
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 text-slate-500 text-sm">
                    <th class="pb-3 pl-2">Name</th>
                    <th class="pb-3">Contact</th>
                    <th class="pb-3">Manager</th>
                    <th class="pb-3 text-center">Stats</th>
                    <th class="pb-3 text-center">Status</th>
                    <th class="pb-3 text-right pr-2">Actions</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <?php foreach ($branches as $branch): ?>
                    <tr class="border-b last:border-0 border-slate-100 hover:bg-slate-50 transition-colors">
                        <td class="py-4 pl-2">
                            <div class="font-medium text-slate-800 flex items-center gap-2">
                                <?php echo htmlspecialchars($branch['name']); ?>
                                <?php if ($branch['is_headquarters']): ?>
                                    <span title="Headquarters" class="text-yellow-500">
                                        <?php echo \EduCRM\Services\NavigationService::getIcon('star', 14); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5">Code:
                                <?php echo htmlspecialchars($branch['code']); ?>
                            </div>
                        </td>
                        <td class="py-4">
                            <?php if ($branch['city']): ?>
                                <div class="flex items-center gap-1.5 text-slate-600">
                                    <?php echo \EduCRM\Services\NavigationService::getIcon('map-pin', 14); ?>
                                    <?php echo htmlspecialchars($branch['city']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($branch['phone']): ?>
                                <div class="flex items-center gap-1.5 text-slate-500 mt-1">
                                    <?php echo \EduCRM\Services\NavigationService::getIcon('phone', 14); ?>
                                    <?php echo htmlspecialchars($branch['phone']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="py-4">
                            <?php if ($branch['manager_name']): ?>
                                <div class="flex items-center gap-1.5 text-slate-700">
                                    <?php echo \EduCRM\Services\NavigationService::getIcon('user', 14); ?>
                                    <?php echo htmlspecialchars($branch['manager_name']); ?>
                                </div>
                            <?php else: ?>
                                <span class="text-slate-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-4">
                            <div class="flex justify-center gap-3 text-xs text-slate-500">
                                <span class="flex items-center gap-1" title="Users">
                                    <?php echo \EduCRM\Services\NavigationService::getIcon('users', 14); ?>
                                    <?php echo $branch['user_count']; ?>
                                </span>
                                <span class="flex items-center gap-1" title="Inquiries">
                                    <?php echo \EduCRM\Services\NavigationService::getIcon('file-text', 14); ?>
                                    <?php echo $branch['inquiry_count']; ?>
                                </span>
                            </div>
                        </td>
                        <td class="py-4 text-center">
                            <?php if ($branch['is_active']): ?>
                                <span
                                    class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-xs rounded-full font-medium border border-emerald-100">
                                    Active
                                </span>
                            <?php else: ?>
                                <span
                                    class="px-2.5 py-1 bg-slate-100 text-slate-500 text-xs rounded-full font-medium border border-slate-200">
                                    Inactive
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-4 pr-2 text-right">
                            <div class="flex justify-end gap-2">
                                <a href="?select_branch=<?php echo $branch['id']; ?>" class="action-btn blue" title="View Data">
                                    <?php echo \EduCRM\Services\NavigationService::getIcon('eye', 16); ?>
                                </a>
                                <a href="edit.php?id=<?php echo $branch['id']; ?>" class="action-btn default" title="Edit">
                                    <?php echo \EduCRM\Services\NavigationService::getIcon('edit', 16); ?>
                                </a>
                                <?php if (!$branch['is_headquarters']): ?>
                                    <a href="#" onclick="confirmDeactivate(<?php echo $branch['id']; ?>)" class="action-btn red"
                                        title="Deactivate">
                                        <?php echo \EduCRM\Services\NavigationService::getIcon('trash', 16); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once '../../templates/footer.php'; ?>

<script>
    function confirmDeactivate(id) {
        Modal.show({
            type: 'error',
            title: 'Deactivate Branch?',
            message: 'Are you sure you want to deactivate this branch? This is a destructive action.',
            confirmText: 'Yes, Deactivate',
            onConfirm: function () {
                window.location.href = '?delete=' + id;
            }
        });
    }
</script>