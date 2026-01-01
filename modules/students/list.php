<?php
require_once '../../config.php';
requireLogin();

// Role Check
if (hasRole('student')) {
    die("Unauthorized access.");
}

$is_teacher_only = hasRole('teacher') && !hasRole('admin') && !hasRole('counselor');

// Build Query
$sql = "
    SELECT DISTINCT u.id, u.name, u.email, u.phone, u.country, u.education_level, u.created_at
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
";

// If Teacher, join enrollments to filter
if ($is_teacher_only) {
    $sql .= "
        JOIN enrollments e ON u.id = e.student_id
        JOIN classes c ON e.class_id = c.id
        WHERE r.name = 'student' AND c.teacher_id = ?
    ";
    $params = [$_SESSION['user_id']];
} else {
    $sql .= " WHERE r.name = 'student' ";
    $params = [];
}

$sql .= " ORDER BY u.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$pageDetails = ['title' => 'Student Management'];
require_once '../../includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold text-slate-800">Student Records</h1>
    <a href="add.php" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
        + Add New Student
    </a>
</div>

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
                            <div class="text-sm text-slate-900"><?php echo htmlspecialchars($s['country']); ?></div>
                            <div class="text-xs text-slate-500"><?php echo htmlspecialchars($s['education_level']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                            <?php echo date('Y-m-d', strtotime($s['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex gap-2 justify-end">
                                <a href="profile.php?id=<?php echo $s['id']; ?>"
                                    class="px-3 py-1.5 bg-sky-50 text-sky-700 rounded-lg hover:bg-sky-100 transition-colors text-xs">
                                    View Profile
                                </a>
                                <a href="edit.php?id=<?php echo $s['id']; ?>"
                                    class="px-3 py-1.5 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition-colors text-xs">
                                    Edit
                                </a>
                                <a href="delete.php?id=<?php echo $s['id']; ?>"
                                    class="px-3 py-1.5 bg-red-50 text-red-700 border border-red-200 rounded-lg hover:bg-red-100 transition-colors text-xs"
                                    onclick="return confirm('Delete student? All records (ledger, classes, docs) will be affected.')">
                                    Delete
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

<?php require_once '../../includes/footer.php'; ?>