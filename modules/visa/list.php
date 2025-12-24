<?php
require_once '../../config.php';
requireLogin();

requireAdminOrCounselor();

// Fetch all students (users with 'student' role in user_roles)
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, vw.country, vw.current_stage, vw.updated_at 
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    LEFT JOIN visa_workflows vw ON u.id = vw.student_id
    WHERE r.name = 'student'
    ORDER BY u.name ASC
");
$stmt->execute();
$students = $stmt->fetchAll();

$pageDetails = ['title' => 'Visa Tracking'];
require_once '../../includes/header.php';
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>Visa Tracking Workflow</h2>
    </div>

    <?php renderFlashMessage(); ?>

    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Country</th>
                <th>Current Stage</th>
                <th>Last Update</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($s['name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($s['email']); ?></small>
                    </td>
                    <td><?php echo $s['country'] ?: 'Not Started'; ?></td>
                    <td>
                        <span class="status-badge" style="background: #e0f2fe; color: #0369a1;">
                            <?php echo $s['current_stage'] ?: 'N/A'; ?>
                        </span>
                    </td>
                    <td><?php echo $s['updated_at'] ? date('Y-m-d H:i', strtotime($s['updated_at'])) : '-'; ?></td>
                    <td>
                        <a href="update.php?student_id=<?php echo $s['id']; ?>" class="btn btn-secondary"
                            style="font-size: 12px; padding: 5px 10px;">Update Stage</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../includes/footer.php'; ?>