<?php
require_once '../../config.php';
requireLogin();

requireAdmin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

$stmt = $pdo->prepare("SELECT * FROM fee_types WHERE id = ?");
$stmt->execute([$id]);
$type = $stmt->fetch();

if (!$type)
    die("Fee type not found");

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $amount = (float) $_POST['amount'];

    if ($name) {
        $stmt = $pdo->prepare("UPDATE fee_types SET name = ?, default_amount = ? WHERE id = ?");
        $stmt->execute([$name, $amount, $id]);
        redirectWithAlert("fee_types.php", "Fee Type updated!");

        $type['name'] = $name;
        $type['default_amount'] = $amount;
    }
}

$pageDetails = ['title' => 'Edit Fee Type'];
require_once '../../includes/header.php';
?>

<div class="card" style="max-width: 500px; margin: 0 auto;">
    <h2>Edit Fee Type</h2>
    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin: 10px 0;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Fee Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($type['name']); ?>"
                required>
        </div>
        <div class="form-group">
            <label>Default Amount</label>
            <input type="number" step="0.01" name="amount" class="form-control"
                value="<?php echo htmlspecialchars($type['default_amount']); ?>" required>
        </div>
        <button type="submit" class="btn">Update Type</button>
        <a href="fee_types.php" class="btn btn-secondary">Back</a>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>