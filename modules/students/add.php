<?php
/**
 * Add Student
 * Creates new student accounts with automatic password generation
 */
require_once '../../app/bootstrap.php';



requireLogin();
requireAdminCounselorOrBranchManager();

// Load lookup data using cached service (replaces direct queries)
$lookup = \EduCRM\Services\LookupCacheService::getInstance($pdo);
$countries = $lookup->getAll('countries');
$education_levels = $lookup->getAll('education_levels');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $country_id = !empty($_POST['country_id']) ? (int) $_POST['country_id'] : null;
    $education_level_id = !empty($_POST['education_level_id']) ? (int) $_POST['education_level_id'] : null;
    $passport = sanitize($_POST['passport_number']);
    $password = generateSecurePassword();
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();

        // Insert User with FK columns
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, country_id, education_level_id, passport_number, password_hash, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'student')");
        $stmt->execute([$name, $email, $phone, $country_id, $education_level_id, $passport, $hashed_password]);
        $user_id = $pdo->lastInsertId();

        // Assign Student Role
        $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'student'");
        $roleStmt->execute();
        $role_id = $roleStmt->fetchColumn();

        if ($role_id) {
            $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$user_id, $role_id]);
        }

        // Log the action
        logAction('student_create', "Created student ID: {$user_id}");

        $pdo->commit();

        // Send welcome email with credentials
        try {
            $emailService = new \EduCRM\Services\EmailNotificationService($pdo);
            $emailService->sendWelcomeEmail($user_id, $password);
        } catch (Exception $e) {
            // Log error but don't fail student creation
            error_log("Failed to send welcome email: " . $e->getMessage());
        }

        redirectWithAlert("list.php", "Student added successfully! <br>Temporary Password: <strong>$password</strong>", 'success');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirectWithAlert("add.php", "Unable to add student. Please check the details and try again.", 'error');
    }
}

$pageDetails = ['title' => 'Add New Student'];
require_once '../../templates/header.php';
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
                <select name="country_id" class="form-control">
                    <option value="">-- Select Country --</option>
                    <?php foreach ($countries as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Education Level</label>
                <select name="education_level_id" class="form-control">
                    <option value="">-- Select Level --</option>
                    <?php foreach ($education_levels as $el): ?>
                        <option value="<?php echo $el['id']; ?>"><?php echo htmlspecialchars($el['name']); ?></option>
                    <?php endforeach; ?>
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

<?php require_once '../../templates/footer.php'; ?>