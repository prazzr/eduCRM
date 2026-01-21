<?php
/**
 * Student List
 * Displays all students with search and filtering capabilities
 */
require_once '../../app/bootstrap.php';
requireLogin();

// Staff access only (students cannot view student list)
requireStaffMember();

$is_teacher_only = hasRole('teacher') && !hasRole('admin') && !hasRole('counselor') && !hasRole('branch_manager');


$branchService = new \EduCRM\Services\BranchService($pdo);
$branchFilter = $branchService->getBranchFilter($_SESSION['user_id'], 'u');

// Build Query with FK columns
$sql = "
    SELECT DISTINCT u.id, u.name, u.email, u.phone, 
           c.name as country_name, 
           el.name as education_level_name,
           u.country as legacy_country, 
           u.education_level as legacy_education,
           u.created_at
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    LEFT JOIN countries c ON u.country_id = c.id
    LEFT JOIN education_levels el ON u.education_level_id = el.id
";

// If Teacher, join enrollments to filter
if ($is_teacher_only) {
    $sql .= "
        JOIN enrollments e ON u.id = e.student_id
        JOIN classes c ON e.class_id = c.id
        WHERE r.name = 'student' AND c.teacher_id = ? $branchFilter
    ";
    $params = [$_SESSION['user_id']];
} else {
    $sql .= " WHERE r.name = 'student' $branchFilter ";
    $params = [];
}

$sql .= " ORDER BY u.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$pageDetails = ['title' => 'Student Management'];
require_once '../../templates/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Student Records</h1>
    <a href="add.php" class="btn btn-primary">
        <?php echo \EduCRM\Services\NavigationService::getIcon('plus', 16); ?> Add New Student
    </a>
</div>

<!-- Quick Search with Alpine.js -->
<div class="bg-white px-4 py-3 rounded-xl border border-slate-200 shadow-sm mb-4">
    <div x-data='searchFilter({
        data: <?php echo json_encode(array_map(function ($s) {
            return [
                'id' => $s['id'],
                'name' => $s['name'],
                'email' => $s['email'],
                'phone' => $s['phone'] ?? '',
                'country' => $s['country_name'] ?? $s['legacy_country'] ?? '',
                'education' => $s['education_level_name'] ?? $s['legacy_education'] ?? ''
            ];
        }, $students)); ?>,
        searchFields: ["name", "email", "phone"],
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
                <a :href="'profile.php?id=' + item.id" :data-index="index" @mouseenter="setSelectedIndex(index)"
                    class="flex items-center gap-3 px-4 py-3 border-b border-slate-100 transition-colors"
                    :class="{ 'bg-primary-50 border-l-4 border-l-teal-600': isSelected(index), 'hover:bg-slate-50': !isSelected(index) }">
                    <div class="w-9 h-9 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-full flex items-center justify-center text-white font-bold text-sm flex-shrink-0"
                        x-text="item.name.charAt(0).toUpperCase()"></div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-slate-800" x-text="item.name"></div>
                        <div class="text-xs text-slate-500 truncate">
                            <span x-text="item.email"></span> • <span x-text="item.phone || 'No phone'"></span>
                        </div>
                    </div>
                    <div class="text-right text-xs text-slate-400">
                        <div x-text="item.country || ''"></div>
                        <div x-text="item.education || ''"></div>
                    </div>
                </a>
            </template>

            <div x-show="results.length === 0 && query.length >= 2 && !loading"
                class="px-4 py-3 text-center text-slate-500 text-sm">
                No students found
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(id) {
        Modal.show({
            type: 'error',
            title: 'Delete Student?',
            message: 'Are you sure you want to delete this student? This action cannot be undone.',
            confirmText: 'Yes, Delete It',
            onConfirm: function () {
                window.location.href = 'delete.php?id=' + id;
            }
        });
    }
</script>

<?php renderFlashMessage(); ?>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                        Student
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                        Contact Info
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                        Details
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                        Joined
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-200">
                <?php foreach ($students as $s): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div
                                    class="flex-shrink-0 h-10 w-10 bg-primary-100 rounded-full flex items-center justify-center">
                                    <span class="text-primary-600 font-semibold text-sm">
                                        <?php echo strtoupper(substr($s['name'], 0, 2)); ?>
                                    </span>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-slate-900">
                                        <?php echo htmlspecialchars($s['name']); ?>
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        ID: #<?php echo $s['id']; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-slate-900"><?php echo htmlspecialchars($s['email']); ?></div>
                            <div class="text-xs text-slate-500"><?php echo htmlspecialchars($s['phone']); ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-slate-900">
                                <?php echo htmlspecialchars($s['country_name'] ?? $s['legacy_country'] ?? ''); ?>
                            </div>
                            <div class="text-xs text-slate-500">
                                <?php echo htmlspecialchars($s['education_level_name'] ?? $s['legacy_education'] ?? ''); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                            <?php echo date('Y-m-d', strtotime($s['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex gap-2 justify-end">
                                <a href="profile.php?id=<?php echo $s['id']; ?>" class="action-btn blue"
                                    title="View Profile">
                                    <?php echo \EduCRM\Services\NavigationService::getIcon('eye', 16); ?>
                                </a>
                                <a href="edit.php?id=<?php echo $s['id']; ?>" class="action-btn default" title="Edit">
                                    <?php echo \EduCRM\Services\NavigationService::getIcon('edit', 16); ?>
                                </a>
                                <a href="#" onclick="confirmDelete(<?php echo $s['id']; ?>)" class="action-btn red"
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

    <?php if (empty($students)): ?>
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-slate-900">No students</h3>
            <p class="mt-1 text-sm text-slate-500">Get started by adding a new student.</p>
            <div class="mt-6">
                <a href="add.php"
                    class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                    + Add Student
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../templates/footer.php'; ?>