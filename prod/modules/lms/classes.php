<?php
require_once '../../app/bootstrap.php';
requireLogin();

requireRoles(['admin', 'teacher', 'student']);

$branchService = new \EduCRM\Services\BranchService($pdo);
$branchFilter = $branchService->getBranchFilter($_SESSION['user_id'], 'u');
$classBranchFilter = $branchService->getBranchFilter($_SESSION['user_id'], 'c');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (hasRole('admin') || hasRole('branch_manager') || hasRole('counselor') || hasRole('accountant') || hasRole('teacher'))) {
    $course_id = $_POST['course_id'];
    $teacher_id = $_POST['teacher_id'];
    $name = sanitize($_POST['name']);
    $start_date = $_POST['start_date'];

    // Auto-set branch from teacher
    $stmt = $pdo->prepare("SELECT branch_id FROM users WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $teacherBranchId = $stmt->fetchColumn();

    if ($name && $course_id) {
        $stmt = $pdo->prepare("INSERT INTO classes (course_id, teacher_id, name, start_date, branch_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$course_id, $teacher_id, $name, $start_date, $teacherBranchId]);
        redirectWithAlert("classes.php", "Class created successfully!", 'success');
    } else {
        redirectWithAlert("classes.php", "Please provide a class name and select a course.", 'error');
    }
}

// Fetch Data
$courses = $pdo->query("SELECT * FROM courses ORDER BY name")->fetchAll();
$teachers = $pdo->query("
    SELECT DISTINCT u.* 
    FROM users u 
    JOIN user_roles ur ON u.id = ur.user_id 
    JOIN roles r ON ur.role_id = r.id 
    WHERE r.name IN ('teacher', 'admin') 
    $branchFilter
    ORDER BY u.name
")->fetchAll();

$query = "
    SELECT c.*, co.name as course_name, u.name as teacher_name 
    FROM classes c 
    JOIN courses co ON c.course_id = co.id 
    LEFT JOIN users u ON c.teacher_id = u.id 
    WHERE 1=1 $classBranchFilter
";

if (hasRole('admin') || hasRole('branch_manager')) {
    $classes = $pdo->query($query . " ORDER BY c.start_date DESC")->fetchAll();
} elseif (hasRole('teacher')) {
    $classes_stmt = $pdo->prepare($query . " AND c.teacher_id = ? ORDER BY c.start_date DESC");
    $classes_stmt->execute([$_SESSION['user_id']]);
    $classes = $classes_stmt->fetchAll();
} else {
    // Student: Show only enrolled classes
    $classes_stmt = $pdo->prepare($query . " AND c.id IN (SELECT class_id FROM enrollments WHERE student_id = ?) ORDER BY c.start_date DESC");
    $classes_stmt->execute([$_SESSION['user_id']]);
    $classes = $classes_stmt->fetchAll();
}

$pageDetails = ['title' => 'Manage Classes'];
require_once '../../templates/header.php';
?>

<div class="card">
    <h2>Manage Classes</h2>


    <?php renderFlashMessage(); ?>

    <?php if (hasRole('admin') || hasRole('branch_manager') || hasRole('counselor') || hasRole('accountant')): ?>
        <form method="POST" style="margin-bottom: 30px;" id="createClassForm">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Course</label>
                    <input type="hidden" name="course_id" id="selectedCourseId" value="">
                    <div style="position: relative;">
                        <input type="text" id="courseSearch" class="form-control" placeholder="🔍 Search course..."
                            autocomplete="off">
                    </div>
                </div>
                <div class="form-group">
                    <label>Class Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. IELTS Morning Batch 1" required>
                </div>
                <div class="form-group">
                    <label>Assign Teacher</label>
                    <input type="hidden" name="teacher_id" id="selectedTeacherId" value="">
                    <div style="position: relative;">
                        <input type="text" id="teacherSearch" class="form-control" placeholder="🔍 Search teacher..."
                            autocomplete="off">
                    </div>
                </div>
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="form-control">
                </div>
            </div>
            <button type="submit" class="btn">Create Class</button>
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Course dropdown
                const courseData = <?php echo json_encode(array_map(function ($c) {
                    return ['id' => $c['id'], 'name' => $c['name']];
                }, $courses)); ?>;

                new SearchableDropdown({
                    inputId: 'courseSearch',
                    hiddenInputId: 'selectedCourseId',
                    data: courseData,
                    displayField: 'name'
                });

                // Teacher dropdown
                const teacherData = <?php echo json_encode(array_map(function ($t) {
                    return ['id' => $t['id'], 'name' => $t['name'], 'email' => $t['email'] ?? ''];
                }, $teachers)); ?>;

                new SearchableDropdown({
                    inputId: 'teacherSearch',
                    hiddenInputId: 'selectedTeacherId',
                    data: teacherData,
                    displayField: 'name',
                    secondaryField: 'email'
                });

                // Validation
                document.getElementById('createClassForm').addEventListener('submit', function (e) {
                    if (!document.getElementById('selectedCourseId').value) {
                        e.preventDefault();
                        alert('Please select a course');
                    }
                });
            });

            function confirmDelete(id) {
                Modal.show({
                    type: 'error',
                    title: 'Delete Class?',
                    message: 'Are you sure you want to delete this class? All enrollments will be lost.',
                    confirmText: 'Yes, Delete It',
                    onConfirm: function () {
                        window.location.href = 'class_delete.php?id=' + id;
                    }
                });
            }
        </script>
    <?php endif; ?>

    <h3>Active Classes</h3>
    <table>
        <thead>
            <tr>
                <th>Class Name</th>
                <th>Course</th>
                <th>Teacher</th>
                <th>Start Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($classes as $cl): ?>
                <tr>
                    <td><?php echo htmlspecialchars($cl['name']); ?></td>
                    <td><?php echo htmlspecialchars($cl['course_name']); ?></td>
                    <td><?php echo htmlspecialchars($cl['teacher_name'] ?? 'Unassigned'); ?></td>
                    <td><?php echo htmlspecialchars($cl['start_date']); ?></td>
                    <td>
                        <div style="display: flex; gap: 5px;">
                            <a href="classroom.php?class_id=<?php echo $cl['id']; ?>" class="btn btn-secondary"
                                style="padding: 5px 10px; font-size: 11px;">Manage Classroom</a>
                            <?php if (hasRole('admin') || hasRole('branch_manager') || hasRole('counselor') || hasRole('accountant')): ?>
                                <a href="class_edit.php?id=<?php echo $cl['id']; ?>" class="btn btn-secondary"
                                    style="padding: 5px 10px; font-size: 11px;">Edit</a>
                                <a href="#" onclick="confirmDelete(<?php echo $cl['id']; ?>)" class="btn"
                                    style="padding: 5px 10px; font-size: 11px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">Delete</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../templates/footer.php'; ?>