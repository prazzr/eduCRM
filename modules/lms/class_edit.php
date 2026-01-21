<?php
require_once '../../app/bootstrap.php';
requireLogin();

requireAdmin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->execute([$id]);
$class = $stmt->fetch();

if (!$class)
    die("Class not found");

$courses = $pdo->query("SELECT * FROM courses ORDER BY name")->fetchAll();
$teachers = $pdo->query("
    SELECT DISTINCT u.* 
    FROM users u 
    JOIN user_roles ur ON u.id = ur.user_id 
    JOIN roles r ON ur.role_id = r.id 
    WHERE r.name IN ('teacher', 'admin') 
    ORDER BY u.name
")->fetchAll();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = $_POST['course_id'];
    $teacher_id = $_POST['teacher_id'];
    $name = sanitize($_POST['name']);
    $start_date = $_POST['start_date'];

    if ($name && $course_id) {
        $stmt = $pdo->prepare("UPDATE classes SET course_id = ?, teacher_id = ?, name = ?, start_date = ? WHERE id = ?");
        $stmt->execute([$course_id, $teacher_id, $name, $start_date, $id]);
        redirectWithAlert("classes.php", "Class updated!", 'warning');

        $class['name'] = $name;
        $class['start_date'] = $start_date;
    } else {
        redirectWithAlert("class_edit.php?id=$id", "Class Name and Course are required.", 'error');
    }
}

$pageDetails = ['title' => 'Edit Class'];
require_once '../../templates/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h2>Edit Class</h2>
    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin: 10px 0;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="editClassForm">
        <div class="form-group">
            <label>Course</label>
            <input type="hidden" name="course_id" id="selectedCourseIdEdit" value="<?php echo $class['course_id']; ?>">
            <div style="position: relative;">
                <input type="text" id="courseSearchEdit" class="form-control" placeholder="🔍 Search course..."
                    autocomplete="off" value="<?php
                    $currentCourse = array_filter($courses, fn($c) => $c['id'] == $class['course_id']);
                    echo htmlspecialchars(reset($currentCourse)['name'] ?? '');
                    ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Class Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($class['name']); ?>"
                required>
        </div>
        <div class="form-group">
            <label>Teacher</label>
            <input type="hidden" name="teacher_id" id="selectedTeacherIdEdit"
                value="<?php echo $class['teacher_id']; ?>">
            <div style="position: relative;">
                <input type="text" id="teacherSearchEdit" class="form-control" placeholder="🔍 Search teacher..."
                    autocomplete="off" value="<?php
                    $currentTeacher = array_filter($teachers, fn($t) => $t['id'] == $class['teacher_id']);
                    echo htmlspecialchars(reset($currentTeacher)['name'] ?? '');
                    ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Start Date</label>
            <input type="date" name="start_date" class="form-control"
                value="<?php echo htmlspecialchars($class['start_date']); ?>">
        </div>
        <button type="submit" class="btn">Update Class</button>
        <a href="classes.php" class="btn btn-secondary">Back</a>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Course dropdown
            const courseData = <?php echo json_encode(array_map(function ($c) {
                return ['id' => $c['id'], 'name' => $c['name']];
            }, $courses)); ?>;

            new SearchableDropdown({
                inputId: 'courseSearchEdit',
                hiddenInputId: 'selectedCourseIdEdit',
                data: courseData,
                displayField: 'name'
            });

            // Teacher dropdown
            const teacherData = <?php echo json_encode(array_map(function ($t) {
                return ['id' => $t['id'], 'name' => $t['name'], 'email' => $t['email'] ?? ''];
            }, $teachers)); ?>;

            new SearchableDropdown({
                inputId: 'teacherSearchEdit',
                hiddenInputId: 'selectedTeacherIdEdit',
                data: teacherData,
                displayField: 'name',
                secondaryField: 'email'
            });

            // Validation
            document.getElementById('editClassForm').addEventListener('submit', function (e) {
                if (!document.getElementById('selectedCourseIdEdit').value) {
                    e.preventDefault();
                    alert('Please select a course');
                }
            });
        });
    </script>
</div>

<?php require_once '../../templates/footer.php'; ?>