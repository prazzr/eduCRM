<?php
require_once '../../config.php';
requireLogin();

requireAdmin();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $amount = (float) $_POST['amount'];

    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO fee_types (name, default_amount) VALUES (?, ?)");
        $stmt->execute([$name, $amount]);
        redirectWithAlert("fee_types.php", "Fee Type added.");
    }
}

$types = $pdo->query("SELECT * FROM fee_types")->fetchAll();

$pageDetails = ['title' => 'Fee Types'];
require_once '../../includes/header.php';
?>

<div class="card">
    <h2>Manage Fee Types</h2>
    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin: 10px 0;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php renderFlashMessage(); ?>

    <form method="POST" style="display: flex; gap: 10px; align-items: flex-end; margin-bottom: 20px;">
        <div class="form-group" style="flex: 2; margin-bottom: 0;">
            <label>Fee Name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. IELTS Tuition" required>
        </div>
        <div class="form-group" style="flex: 1; margin-bottom: 0;">
            <label>Default Amount</label>
            <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00">
        </div>
        <button type="submit" class="btn">Add Type</button>
    </form>

    <h3>Existing Fee Structure</h3>
    <table>
        <thead>
            <tr>
                <th>Fee Name</th>
                <th>Default Amount</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($types as $t): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($t['name']); ?></strong></td>
                    <td>$<?php echo number_format($t['default_amount'], 2); ?></td>
                    <td style="text-align: right;">
                        <a href="fee_edit.php?id=<?php echo $t['id']; ?>" class="btn btn-secondary"
                            style="font-size: 11px; padding: 5px 8px;">Edit</a>
                        <a href="fee_delete.php?id=<?php echo $t['id']; ?>" class="btn"
                            style="font-size: 11px; padding: 5px 8px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;"
                            onclick="return confirm('Delete this fee type?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 20px;">
        <a href="ledger.php" class="btn btn-secondary">Go to Student Ledgers &raquo;</a>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>