<?php
require_once '../../config.php';
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
        redirectWithAlert("classes.php", "Class updated!");

        $class['course_id'] = $course_id;
        $class['teacher_id'] = $teacher_id;
        $class['name'] = $name;
        $class['start_date'] = $start_date;
    }
}

$pageDetails = ['title' => 'Edit Class'];
require_once '../../includes/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h2>Edit Class</h2>
    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin: 10px 0;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Course</label>
            <select name="course_id" class="form-control" required>
                <?php renderSelectOptions($courses, 'id', 'name', $class['course_id']); ?>
            </select>
        </div>
        <div class="form-group">
            <label>Class Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($class['name']); ?>"
                required>
        </div>
        <div class="form-group">
            <label>Teacher</label>
            <select name="teacher_id" class="form-control">
                <option value="">Unassigned</option>
                <?php renderSelectOptions($teachers, 'id', 'name', $class['teacher_id']); ?>
            </select>
        </div>
        <div class="form-group">
            <label>Start Date</label>
            <input type="date" name="start_date" class="form-control"
                value="<?php echo htmlspecialchars($class['start_date']); ?>">
        </div>
        <button type="submit" class="btn">Update Class</button>
        <a href="classes.php" class="btn btn-secondary">Back</a>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>