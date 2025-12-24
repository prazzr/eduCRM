<?php
require_once '../../config.php';
requireLogin();

requireAdmin();

$user_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$user_id)
    die("Invalid User ID");

// Fetch User
$stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user)
    die("User not found.");

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];

    if (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $update->execute([$hash, $user_id]);

        redirectWithAlert("list.php", "Password updated successfully for <strong>" . htmlspecialchars($user['name']) . "</strong>. <br> New Password: <strong>" . htmlspecialchars($new_password) . "</strong>");
    }
}

$pageDetails = ['title' => 'Reset Password'];
require_once '../../includes/header.php';
?>

<div class="card" style="max-width: 500px; margin: 0 auto;">
    <h2>Reset Password</h2>
    <p>Target User: <strong><?php echo htmlspecialchars($user['name']); ?></strong>
        (<?php echo htmlspecialchars($user['email']); ?>)</p>

    <?php if ($message): ?>
        <div
            style="background: #dcfce7; color: #166534; padding: 20px; border-radius: 6px; margin: 20px 0; border: 2px solid #16a34a;">
            <?php echo $message; ?>
            <div style="margin-top: 15px;">
                <a href="list.php" class="btn">Back to User List</a>
            </div>
        </div>
    <?php else: ?>
        <?php if ($error): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; margin: 15px 0;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>New Password</label>
                <input type="text" name="new_password" class="form-control" value="<?php echo generateSecurePassword(); ?>"
                    required>
                <small style="color: grey;">A secure password has been pre-filled for you.</small>
            </div>
            <button type="submit" class="btn">Update Password</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>