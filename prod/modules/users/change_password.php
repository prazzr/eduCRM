<?php
require_once '../../app/bootstrap.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($new_password) < 6) {
        redirectWithAlert('change_password.php', "New password must be at least 6 characters long.", 'error');
    } elseif ($new_password !== $confirm_password) {
        redirectWithAlert('change_password.php', "New passwords do not match.", 'error');
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($current_password, $user['password_hash'])) {
            // Update password
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $update->execute([$new_hash, $user_id]);
            redirectWithAlert('change_password.php', "Password changed successfully.", 'success');
        } else {
            redirectWithAlert('change_password.php', "Incorrect current password.", 'error');
        }
    }
}

$pageDetails = ['title' => 'Change Password'];
require_once '../../templates/header.php';
?>

<div class="card" style="max-width: 500px; margin: 40px auto;">
    <h2>Change Password</h2>

    <?php if ($message): ?>
        <div
            style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div
            style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #fecaca;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" class="form-control" required>
            <small style="color: #64748b;">Minimum 6 characters</small>
        </div>
        <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
        </div>
        <button type="submit" class="btn">Update Password</button>
        <a href="<?php echo BASE_URL; ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>