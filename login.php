<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];

        // Fetch all roles for this user
        $roleStmt = $pdo->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
        $roleStmt->execute([$user['id']]);
        $roles = $roleStmt->fetchAll(PDO::FETCH_COLUMN);

        $_SESSION['roles'] = $roles;
        $_SESSION['role'] = !empty($roles) ? $roles[0] : 'student'; // Primary role for UI logic

        header("Location: " . BASE_URL);
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Education CRM</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 40px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            font-size: 24px;
            color: var(--primary-color);
        }
    </style>
</head>

<body>

    <div class="card login-card">
        <div class="login-header">
            <h1>EduCRM Login</h1>
            <p>Please sign in to continue</p>
        </div>

        <?php if ($error): ?>
            <div
                style="background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; margin-bottom: 20px; text-align: center;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn" style="width: 100%;">Sign In</button>
        </form>

        <div style="text-align: center; margin-top: 20px; font-size: 13px; color: var(--text-secondary);">
            <p>Default Admin: admin@example.com / password</p>
        </div>
    </div>

</body>

</html>