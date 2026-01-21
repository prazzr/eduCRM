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
        $_SESSION['role'] = !empty($roles) ? $roles[0] : 'student'; // Primary role for UI logic

        header("Location: " . BASE_URL);
        exit;
    } else {
        redirectWithAlert("login.php", "Invalid email or password.", "error");
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<title>Login - Education CRM</title>
<!-- Tailwind CSS via CDN -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: {
                        50: '#eef2ff',
                        100: '#e0e7ff',
                        500: '#6366f1',
                        600: '#4f46e5',
                        700: '#4338ca',
                    },
                    surface: '#ffffff',
                    background: '#f8fafc',
                },
                fontFamily: {
                    sans: ['Inter', 'sans-serif'],
                }
            }
        }
    }
</script>
</head>

<body class="bg-slate-50 flex items-center justify-center min-h-screen">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-8 border border-slate-100">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-primary-600 mb-2">EduCRM</h1>
            <p class="text-slate-500">Please sign in to continue</p>
        </div>

        <?php renderFlashMessage(); ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email Address</label>
                <input type="email" name="email"
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-all"
                    required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input type="password" name="password"
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-all"
                    required>
            </div>
            <button type="submit"
                class="w-full bg-primary-600 text-white font-medium py-2.5 rounded-lg hover:bg-primary-700 transition-colors shadow-lg shadow-primary-500/30">
                Sign In
            </button>
        </form>

        <div class="text-center mt-6 text-xs text-slate-400">
            <p>Default Admin: admin@example.com / password</p>
        </div>
    </div>

</body>

</html>