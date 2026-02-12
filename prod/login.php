<?php
require_once 'app/bootstrap.php';

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
        // Set primary role by privilege priority (highest first) for UI/navigation logic
        $rolePriority = ['admin', 'branch_manager', 'accountant', 'counselor', 'teacher', 'student'];
        $_SESSION['role'] = 'student'; // default
        foreach ($rolePriority as $priorityRole) {
            if (in_array($priorityRole, $roles)) {
                $_SESSION['role'] = $priorityRole;
                break;
            }
        }

        // Set branch context for data isolation
        // Admin users: NULL (sees all branches), branch users: their branch_id
        $_SESSION['branch_id'] = $user['branch_id'] ?? null;
        $_SESSION['user_name'] = $user['name'];

        header("Location: " . BASE_URL);
        exit;
    } else {
        redirectWithAlert("login.php", "Invalid email or password.", "error");
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Education CRM</title>

    <!-- Favicon -->
    <link rel="icon" href="<?php echo BASE_URL; ?>public/favicon.svg">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Local Assets -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/assets/css/tailwind.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/assets/css/sidebar.css?v=<?php echo time(); ?>">

    <style>
        /* Login Specific overrides using Design System Variables */
        body {
            background-color: var(--bg);
            color: var(--text);
        }

        .login-card {
            background-color: var(--surface);
            border: 1px solid var(--border);
        }

        .login-title {
            color: var(--primary);
        }
    </style>
</head>

<body class="flex items-center justify-center min-h-screen">

    <div class="w-full max-w-md login-card rounded-2xl shadow-xl p-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold login-title mb-2">EduCRM</h1>
            <p class="text-slate-500">Please sign in to continue</p>
        </div>

        <?php renderFlashMessage(); ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email Address</label>
                <input type="email" name="email"
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all"
                    style="focus: border-color: var(--primary); --tw-ring-color: var(--primary);" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input type="password" name="password"
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all"
                    style="focus: border-color: var(--primary); --tw-ring-color: var(--primary);" required>
            </div>
            <button type="submit" class="w-full text-white font-medium py-2.5 rounded-lg transition-colors shadow-lg"
                style="background-color: var(--primary); box-shadow: 0 4px 6px -1px rgba(15, 118, 110, 0.3);">
                Sign In
            </button>
        </form>

        <div class="text-center mt-6 text-xs text-slate-400">
            <p>Default Admin: admin@example.com / password</p>
        </div>
    </div>

</body>

</html>