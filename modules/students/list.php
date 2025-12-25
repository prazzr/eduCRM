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

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>Student Records</h2>
        <a href="add.php" class="btn">Add New Student</a>
    </div>

    <?php renderFlashMessage(); ?>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Contact Info</th>
                <th>Details</th>
                <th>Joined</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($s['name']); ?></strong><br>
                        <small>ID: #<?php echo $s['id']; ?></small>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($s['email']); ?><br>
                        <small><?php echo htmlspecialchars($s['phone']); ?></small>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($s['country']); ?><br>
                        <small><?php echo htmlspecialchars($s['education_level']); ?></small>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($s['created_at'])); ?></td>
                    <td style="text-align: right;">
                        <div style="display: flex; gap: 5px; justify-content: flex-end;">
                            <a href="profile.php?id=<?php echo $s['id']; ?>" class="btn"
                                style="font-size: 11px; padding: 5px 10px; background: #0369a1;">View Profile</a>
                            <a href="edit.php?id=<?php echo $s['id']; ?>" class="btn btn-secondary"
                                style="font-size: 11px; padding: 5px 8px;">Edit</a>
                            <a href="delete.php?id=<?php echo $s['id']; ?>" class="btn"
                                style="font-size: 11px; padding: 5px 8px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;"
                                onclick="return confirm('Delete student? All records (ledger, classes, docs) will be affected.')">Delete</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../includes/footer.php'; ?>