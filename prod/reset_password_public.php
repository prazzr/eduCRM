<?php
require_once 'app/bootstrap.php';

// PRG: Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = isset($_GET['token']) ? sanitize($_GET['token']) : '';
$error = '';
$success = '';

// PRG: Handle Flash Messages
if (isset($_SESSION['flash_msg'])) {
    if (is_array($_SESSION['flash_msg'])) {
        $msg = $_SESSION['flash_msg'];
        if ($msg['type'] === 'success')
            $success = $msg['message'];
        else
            $error = $msg['message'];
    }
    unset($_SESSION['flash_msg']);
}

if (!$token) {
    die("Invalid or missing token.");
}

// Verify Token and Expiry
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE reset_token = ? AND token_expiry > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die("Invalid or expired token. Please request a new reset link from your administrator.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (strlen($password) < 6) {
        $_SESSION['flash_msg'] = ['message' => "Password must be at least 6 characters long.", 'type' => 'error'];
    } elseif ($password !== $confirm) {
        $_SESSION['flash_msg'] = ['message' => "Passwords do not match.", 'type' => 'error'];
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Update Password and Clear Token
        $update = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, token_expiry = NULL WHERE id = ?");
        $update->execute([$hash, $user['id']]);

        $_SESSION['flash_msg'] = ['message' => "Password updated successfully! You can now <a href='login.php'>Login</a>.", 'type' => 'success'];
    }

    // Redirect
    header("Location: reset_password_public.php?token=" . urlencode($token));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - Education CRM</title>
    <link rel="stylesheet" href="public/assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background: #f8fafc;
        }

        .reset-card {
            width: 100%;
            max-width: 400px;
            padding: 30px;
        }
    </style>
</head>

<body>

    <div class="card reset-card">
        <h2>Set New Password</h2>
        <p>Hello <strong><?php echo htmlspecialchars($user['name']); ?></strong>, please enter your new password below.
        </p>

        <?php if ($success): ?>
            <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 6px; margin: 20px 0;">
                <?php echo $success; ?>
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
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn" style="width: 100%;">Update Password</button>
            </form>
        <?php endif; ?>
    </div>

</body>

</html>