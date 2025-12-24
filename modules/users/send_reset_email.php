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

// Generate Token
$token = bin2hex(random_bytes(32));
$expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Update User with Token
$update = $pdo->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE id = ?");
$update->execute([$token, $expiry, $user_id]);

// Reset Link
$reset_link = BASE_URL . "reset_password_public.php?token=" . $token;

// Log the "Email" (Simulating a mail server)
$log_msg = "[" . date('Y-m-d H:i:s') . "] Password reset link sent to " . $user['email'] . ": " . $reset_link . PHP_EOL;
file_put_contents('../../mail_log.txt', $log_msg, FILE_APPEND);

$message = "Password reset link generated and 'sent' to <strong>" . htmlspecialchars($user['email']) . "</strong>.<br><br>";
$message .= "Link: <a href='$reset_link' target='_blank'>$reset_link</a><br>";
$message .= "<small style='color: grey;'>(Simulation: This link has also been logged to mail_log.txt)</small>";

$pageDetails = ['title' => 'Email Password Reset'];
require_once '../../includes/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h2>Email Password Reset</h2>
    <p>Generating reset link for <strong><?php echo htmlspecialchars($user['name']); ?></strong>...</p>

    <div
        style="background: #e0f2fe; color: #0369a1; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #7dd3fc;">
        <?php echo $message; ?>
    </div>

    <div style="margin-top: 20px;">
        <a href="list.php" class="btn">Back to User List</a>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>