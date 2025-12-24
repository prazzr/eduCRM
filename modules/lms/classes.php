<?php
require_once '../../config.php';
requireLogin();

requireRoles(['admin', 'teacher', 'student']);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (hasRole('admin') || hasRole('teacher'))) {
    $course_id = $_POST['course_id'];
    $teacher_id = $_POST['teacher_id'];
    $name = sanitize($_POST['name']);
    $start_date = $_POST['start_date'];

    if ($name && $course_id) {
        $stmt = $pdo->prepare("INSERT INTO classes (course_id, teacher_id, name, start_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$course_id, $teacher_id, $name, $start_date]);
        redirectWithAlert("classes.php", "Class created!");
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
    ORDER BY u.name
")->fetchAll();

$query = "
    SELECT c.*, co.name as course_name, u.name as teacher_name 
    FROM classes c 
    JOIN courses co ON c.course_id = co.id 
    LEFT JOIN users u ON c.teacher_id = u.id 
";

if (hasRole('admin')) {
    $classes = $pdo->query($query . " ORDER BY c.start_date DESC")->fetchAll();
} elseif (hasRole('teacher')) {
    $classes_stmt = $pdo->prepare($query . " WHERE c.teacher_id = ? ORDER BY c.start_date DESC");
    $classes_stmt->execute([$_SESSION['user_id']]);
    $classes = $classes_stmt->fetchAll();
} else {
    // Student: Show only enrolled classes
    $classes_stmt = $pdo->prepare($query . " JOIN enrollments e ON c.id = e.class_id WHERE e.student_id = ? ORDER BY c.start_date DESC");
    $classes_stmt->execute([$_SESSION['user_id']]);
    $classes = $classes_stmt->fetchAll();
}

$pageDetails = ['title' => 'Manage Classes'];
require_once '../../includes/header.php';
?>

<div class="card">
    <h2>Manage Classes</h2>
    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin: 10px 0;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php renderFlashMessage(); ?>

    <?php if (hasRole('admin')): ?>
        <form method="POST" style="margin-bottom: 30px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Course</label>
                    <select name="course_id" class="form-control" required>
                        <option value="">Select Course</option>
                        <?php renderSelectOptions($courses, 'id', 'name', ''); ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Class Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. IELTS Morning Batch 1" required>
                </div>
                <div class="form-group">
                    <label>Assign Teacher</label>
                    <select name="teacher_id" class="form-control">
                        <option value="">Select Teacher</option>
                        <?php renderSelectOptions($teachers, 'id', 'name', ''); ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="form-control">
                </div>
            </div>
            <button type="submit" class="btn">Create Class</button>
        </form>
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
                            <?php if (hasRole('admin')): ?>
                                <a href="class_edit.php?id=<?php echo $cl['id']; ?>" class="btn btn-secondary"
                                    style="padding: 5px 10px; font-size: 11px;">Edit</a>
                                <a href="class_delete.php?id=<?php echo $cl['id']; ?>" class="btn"
                                    style="padding: 5px 10px; font-size: 11px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;"
                                    onclick="return confirm('Delete this class and all enrollments?')">Delete</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../includes/footer.php'; ?>