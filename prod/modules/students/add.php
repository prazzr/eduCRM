<?php
/**
 * Add Student
 * Creates new student accounts with automatic password generation
 */
require_once '../../app/bootstrap.php';



requireLogin();
requireCRMAccess();

// Load lookup data using cached service (replaces direct queries)
$lookup = \EduCRM\Services\LookupCacheService::getInstance($pdo);
$countries = $lookup->getAll('countries');
$education_levels = $lookup->getAll('education_levels');

// Get available notification channels
$notifPrefService = new \EduCRM\Services\NotificationPreferenceService($pdo);
$availableChannels = $notifPrefService->getAvailableChannels();

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

        // Determine Branch
        $branch_id = $_SESSION['branch_id'];
        if (hasRole('admin') && !empty($_POST['branch_id'])) {
            $branch_id = $_POST['branch_id'];
        }

        // Insert User with FK columns
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, country_id, education_level_id, passport_number, password_hash, role, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'student', ?)");
        $stmt->execute([$name, $email, $phone, $country_id, $education_level_id, $passport, $hashed_password, $branch_id]);
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

        // Save notification preferences
        $selectedChannels = $_POST['notification_channels'] ?? ['email']; // Default to email
        $notifPrefService->saveUserPreferences($user_id, $selectedChannels);

        $pdo->commit();

        // Send welcome email with credentials
        try {
            $emailService = new \EduCRM\Services\EmailNotificationService($pdo);
            $emailService->sendWelcomeEmail($user_id, $password);
            logAction('notification_sent', "Welcome email sent to Student ID: $user_id");
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

        <?php if (hasRole('admin')):
            $branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
            ?>
            <div class="form-group">
                <label>Branch (Optional)</label>
                <select name="branch_id" class="form-control">
                    <option value="">-- Global / Head Office --</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color: grey;">Assign to a specific branch.</small>
            </div>
        <?php endif; ?>
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

        <!-- Notification Preferences -->
        <div class="form-group">
            <label>Notification Preferences</label>
            <div style="background: #f8fafc; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;">
                <p style="margin: 0 0 10px 0; font-size: 13px; color: #64748b;">Select how this student receives
                    notifications:</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px;">
                    <?php foreach ($availableChannels as $channel): ?>
                        <label
                            style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: white; border: 1px solid #e2e8f0; border-radius: 6px;">
                            <input type="checkbox" name="notification_channels[]" value="<?php echo $channel['type']; ?>"
                                <?php echo $channel['default'] ? 'checked' : ''; ?> style="width: 16px; height: 16px;">
                            <span style="font-size: 16px;"><?php echo $channel['icon']; ?></span>
                            <span
                                style="font-size: 14px; font-weight: 500;"><?php echo htmlspecialchars($channel['label']); ?></span>
                            <?php if (!empty($channel['gateways']) && $channel['gateways'] !== 'System Email'): ?>
                                <span
                                    style="font-size: 11px; color: #94a3b8;">(<?php echo htmlspecialchars($channel['gateways']); ?>)</span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <button type="submit" class="btn">Create Student</button>
        <a href="list.php" class="btn btn-secondary">Back to List</a>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>