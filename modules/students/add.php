<?php
require_once '../../config.php';
requireLogin();

requireAdminOrCounselor();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $country = sanitize($_POST['country']);
    $education = sanitize($_POST['education_level']);
    $passport = sanitize($_POST['passport_number']);
    $password = generateSecurePassword();
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();

        // 1. Insert User
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, country, education_level, passport_number, password_hash, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'student')");
        $stmt->execute([$name, $email, $phone, $country, $education, $passport, $hashed_password]);
        $user_id = $pdo->lastInsertId();

        // 2. Assign Student Role
        $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'student'");
        $roleStmt->execute();
        $role_id = $roleStmt->fetchColumn();

        if ($role_id) {
            $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$user_id, $role_id]);
        }

        $pdo->commit();
        redirectWithAlert("list.php", "Student created! Temporary Password: <strong>$password</strong>");
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

$pageDetails = ['title' => 'Add New Student'];
require_once '../../includes/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h2>Add New Student</h2>

    <?php if ($message): ?>
        <div style="background: #e0f2fe; color: #0369a1; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $message; ?>
            <br><small>Please share this password with the student.</small>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Phone Number</label>
            <input type="text" name="phone" class="form-control" required>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div class="form-group">
                <label>Intended Country</label>
                <input type="text" name="country" class="form-control">
            </div>
            <div class="form-group">
                <label>Education Level</label>
                <select name="education_level" class="form-control">
                    <option value="High School">High School</option>
                    <option value="Bachelor">Bachelor</option>
                    <option value="Master">Master</option>
                    <option value="PhD">PhD</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Passport Number</label>
            <input type="text" name="passport_number" class="form-control">
        </div>

        <button type="submit" class="btn">Create Student</button>
        <a href="list.php" class="btn btn-secondary">Back to List</a>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>