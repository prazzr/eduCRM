<?php
require_once '../../config.php';
requireLogin();

// If Student, redirect to their own ledger
if (hasRole('student')) {
    header("Location: student_ledger.php?student_id=" . $_SESSION['user_id']);
    exit;
}

requireRoles(['admin', 'accountant']);

// Fetch Students with balance summary (optional advanced query)
// For now, simple list
$students = $pdo->query("SELECT * FROM users WHERE role='student' ORDER BY name")->fetchAll();

$pageDetails = ['title' => 'Financial Ledgers'];
require_once '../../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Student Financial Ledgers</h2>
    <a href="fee_types.php" class="btn btn-secondary">Manage Fee Structure</a>
</div>

<div class="card" style="padding: 0;">
    <table>
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['name']); ?></td>
                    <td><?php echo htmlspecialchars($s['email']); ?></td>
                    <td><?php echo htmlspecialchars($s['phone']); ?></td>
                    <td>
                        <a href="student_ledger.php?student_id=<?php echo $s['id']; ?>" class="btn">View Ledger</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../includes/footer.php'; ?>