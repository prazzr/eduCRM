<?php
/**
 * Staff User Management List
 * Displays all staff users (non-students) with search and filtering
 */
require_once '../../app/bootstrap.php';
requireLogin();

// Admin or Branch Manager access only
requireBranchManager();


$branchService = new \EduCRM\Services\BranchService($pdo);
$branchFilter = $branchService->getBranchFilter($_SESSION['user_id'], 'u');

// Fetch all staff (not students)
$users = $pdo->query("
    SELECT u.id, u.name, u.email, u.phone, u.created_at, GROUP_CONCAT(r.name SEPARATOR ', ') as roles
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    WHERE r.name != 'student' $branchFilter
    GROUP BY u.id
    ORDER BY u.id DESC
")->fetchAll();

$pageDetails = ['title' => 'User Management'];
require_once '../../templates/header.php';
?>

<div class="card">
    <div class="page-header">
        <h2 class="page-title">Staff Management</h2>
        <a href="add.php" class="btn btn-primary">
            <?php echo \EduCRM\Services\NavigationService::getIcon('plus', 16); ?> Add New Staff
        </a>
    </div>

    <?php renderFlashMessage(); ?>

    <!-- Quick Search with Alpine.js -->
    <div class="bg-slate-50 px-4 py-3 rounded-lg border border-slate-200 mb-4">
        <div x-data='searchFilter({
            data: <?php echo json_encode(array_map(function ($u) {
                return [
                    'id' => $u['id'],
                    'name' => $u['name'],
                    'email' => $u['email'],
                    'phone' => $u['phone'] ?? '',
                    'roles' => $u['roles'] ?? ''
                ];
            }, $users)); ?>,
            searchFields: ["name", "email", "roles"],
            minLength: 1,
            maxResults: 8
        })' class="relative">
            <div class="flex items-center gap-3">
                <span class="text-slate-400">🔍</span>
                <input type="text" x-model="query" @input="search()" @focus="if(query.length >= 1) showResults = true"
                    @keydown="handleKeydown($event)" @keydown.escape="showResults = false"
                    class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500"
                    placeholder="Quick search by name, email, or role..." autocomplete="off">

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
                        <div class="w-9 h-9 bg-gradient-to-br from-violet-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-xs"
                            x-text="item.name.substring(0, 2).toUpperCase()"></div>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-slate-800" x-text="item.name"></div>
                            <div class="text-xs text-slate-500" x-text="item.email"></div>
                        </div>
                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-medium"
                            x-text="item.roles || 'No role'"></span>
                    </a>
                </template>

                <div x-show="results.length === 0 && query.length >= 1 && !loading"
                    class="px-4 py-3 text-center text-slate-500 text-sm">
                    No staff found
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(id) {
            Modal.show({
                type: 'error',
                title: 'Delete Staff Member?',
                message: 'Are you sure you want to delete this staff member? This action cannot be undone.',
                confirmText: 'Yes, Delete It',
                onConfirm: function () {
                    window.location.href = 'delete.php?id=' + id;
                }
            });
        }
    </script>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Roles</th>
                <th>Joined</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($u['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td><?php echo htmlspecialchars($u['phone']); ?></td>
                    <td>
                        <span class="status-badge" style="background: #f1f5f9; color: #475569;">
                            <?php echo htmlspecialchars($u['roles'] ?: 'None'); ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($u['created_at'])); ?></td>
                    <td>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <a href="edit.php?id=<?php echo $u['id']; ?>" class="action-btn default" title="Edit">
                                <?php echo \EduCRM\Services\NavigationService::getIcon('edit', 16); ?>
                            </a>
                            <a href="reset_password.php?id=<?php echo $u['id']; ?>" class="action-btn orange"
                                title="Reset Password">
                                <?php echo \EduCRM\Services\NavigationService::getIcon('settings', 16); ?>
                            </a>
                            <a href="#" onclick="confirmDelete(<?php echo $u['id']; ?>)" class="action-btn red"
                                title="Delete">
                                <?php echo \EduCRM\Services\NavigationService::getIcon('trash', 16); ?>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../templates/footer.php'; ?>