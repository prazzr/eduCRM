<?php
require_once '../../config.php';
requireLogin();

requireAdmin();
// Fetch all staff (not students)
$users = $pdo->query("
    SELECT u.id, u.name, u.email, u.phone, u.created_at, GROUP_CONCAT(r.name SEPARATOR ', ') as roles
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    WHERE r.name != 'student'
    GROUP BY u.id
    ORDER BY u.id DESC
")->fetchAll();

$pageDetails = ['title' => 'User Management'];
require_once '../../includes/header.php';
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>Staff Management</h2>
        <a href="add.php" class="btn">Add New Staff</a>
    </div>

    <?php renderFlashMessage(); ?>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Roles</th>
                <th>Joined</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($u['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td><?php echo htmlspecialchars($u['phone']); ?></td>
                    <td>
                        <span class="status-badge" style="background: #f1f5f9; color: #475569;">
                            <?php echo htmlspecialchars($u['roles'] ?: 'None'); ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($u['created_at'])); ?></td>
                    <td>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <a href="edit.php?id=<?php echo $u['id']; ?>" class="btn btn-secondary"
                                style="font-size: 11px; padding: 5px 8px;">Edit</a>
                            <a href="reset_password.php?id=<?php echo $u['id']; ?>" class="btn btn-secondary"
                                style="font-size: 11px; padding: 5px 8px;" title="Reset Manually">Direct Reset</a>
                            <a href="send_reset_email.php?id=<?php echo $u['id']; ?>" class="btn btn-secondary"
                                style="font-size: 11px; padding: 5px 8px; background: #e0f2fe; color: #0369a1; border-color: #7dd3fc;"
                                title="Send Link to User">Email Reset</a>
                            <a href="delete.php?id=<?php echo $u['id']; ?>" class="btn"
                                style="font-size: 11px; padding: 5px 8px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;"
                                onclick="return confirm('Are you sure you want to delete this user? Role data will be lost.')">Delete</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../includes/footer.php'; ?>