<?php
require_once '../../app/bootstrap.php';
requireLogin();

requireAdmin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$id]);
$course = $stmt->fetch();

if (!$course)
    die("Course not found");

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);

    if ($name) {
        $stmt = $pdo->prepare("UPDATE courses SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $description, $id]);
        redirectWithAlert("courses.php", "Course updated!", 'warning');

        // Refresh data
        $course['name'] = $name;
        $course['description'] = $description;
    } else {
        redirectWithAlert("course_edit.php?id=$id", "Course Name is required.", 'error');
    }
}

$pageDetails = ['title' => 'Edit Course'];
require_once '../../templates/header.php';
?>

<div class="card" style="max-width: 500px; margin: 0 auto;">
    <h2>Edit Course</h2>
    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin: 10px 0;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Course Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($course['name']); ?>"
                required>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" class="form-control"
                rows="3"><?php echo htmlspecialchars($course['description']); ?></textarea>
        </div>
        <button type="submit" class="btn">Update Course</button>
        <a href="courses.php" class="btn btn-secondary">Back</a>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>