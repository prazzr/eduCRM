<?php
require_once '../../app/bootstrap.php';
requireLogin();

// Courses can initially be managed by Admin only
requireRoles(['admin', 'teacher', 'student']);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);

    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO courses (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        redirectWithAlert("courses.php", "Course added!", 'success');
    } else {
        redirectWithAlert("courses.php", "Course name is required.", 'error');
    }
}

$courses = $pdo->query("SELECT * FROM courses ORDER BY name")->fetchAll();

$pageDetails = ['title' => 'Manage Courses'];
require_once '../../templates/header.php';
?>

<div class="card">
    <h2>Manage Courses</h2>


    <?php renderFlashMessage(); ?>

    <?php if (hasRole('admin')): ?>
        <form method="POST" style="display: flex; gap: 10px; align-items: flex-end; margin-bottom: 20px;">
            <div class="form-group" style="flex: 1; margin-bottom: 0;">
                <label>Course Name</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. IELTS">
            </div>
            <div class="form-group" style="flex: 2; margin-bottom: 0;">
                <label>Description</label>
                <input type="text" name="description" class="form-control" placeholder="Short description">
            </div>
            <button type="submit" class="btn">Add Course</button>
        </form>

        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
    <?php endif; ?>

    <h3>Existing Courses</h3>
    <table>
        <thead>
            <tr>
                <th>Course Name</th>
                <th>Description</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($courses as $c): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($c['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($c['description']); ?></td>
                    <td style="text-align: right;">
                        <?php if (hasRole('admin')): ?>
                            <a href="course_edit.php?id=<?php echo $c['id']; ?>" class="btn btn-secondary"
                                style="font-size: 11px; padding: 5px 8px;">Edit</a>
                            <a href="#" onclick="confirmDelete(<?php echo $c['id']; ?>)" class="btn"
                                style="font-size: 11px; padding: 5px 8px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">Delete</a>
                        <?php else: ?>
                            <span style="color: #64748b; font-size: 11px;">View Only</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 20px;">
        <a href="classes.php" class="btn btn-secondary">Manage Classes &raquo;</a>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>

<script>
    function confirmDelete(id) {
        Modal.show({
            type: 'error',
            title: 'Delete Course?',
            message: 'Are you sure you want to delete this course? All associated classes will be deleted.',
            confirmText: 'Yes, Delete It',
            onConfirm: function () {
                window.location.href = 'course_delete.php?id=' + id;
            }
        });
    }
</script>