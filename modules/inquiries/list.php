<?php
require_once '../../config.php';
requireLogin();

requireAdminOrCounselor();

// Fetch Inquiries
$stmt = $pdo->query("SELECT i.*, u.name as counselor_name FROM inquiries i LEFT JOIN users u ON i.assigned_to = u.id ORDER BY i.created_at DESC");
$inquiries = $stmt->fetchAll();

$pageDetails = ['title' => 'Inquiry List'];
require_once '../../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Inquiries</h2>
    <a href="add.php" class="btn">Add New Inquiry</a>
</div>

<?php renderFlashMessage(); ?>

<div class="card" style="padding: 0;">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Contact</th>
                <th>Interest</th>
                <th>Assigned To</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($inquiries as $inq): ?>
                <tr>
                    <td><?php echo htmlspecialchars($inq['name']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($inq['email']); ?><br>
                        <small style="color: #666;"><?php echo htmlspecialchars($inq['phone']); ?></small>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($inq['intended_country']); ?> -
                        <?php echo htmlspecialchars($inq['intended_course']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($inq['counselor_name']); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo strtolower($inq['status']); ?>">
                            <?php echo ucfirst($inq['status']); ?>
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <a href="edit.php?id=<?php echo $inq['id']; ?>" class="btn btn-secondary"
                                style="padding: 5px 10px; font-size: 11px;">Edit</a>

                            <?php if ($inq['status'] === 'converted'): ?>
                                <?php
                                // Find user ID for this email 
                                $u = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                                $u->execute([$inq['email']]);
                                $uid = $u->fetchColumn();
                                if ($uid):
                                    ?>
                                    <a href="<?php echo BASE_URL; ?>modules/students/profile.php?id=<?php echo $uid; ?>" class="btn"
                                        style="padding: 5px 10px; font-size: 11px; background: #6366f1;">Profile</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="convert.php?id=<?php echo $inq['id']; ?>" class="btn btn-secondary"
                                    style="padding: 5px 10px; font-size: 11px; background: #10b981; color: white; border:none;">Convert</a>
                            <?php endif; ?>

                            <a href="delete.php?id=<?php echo $inq['id']; ?>" class="btn"
                                style="padding: 5px 10px; font-size: 11px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;"
                                onclick="return confirm('Delete this inquiry?')">Delete</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../includes/footer.php'; ?>