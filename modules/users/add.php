<?php
require_once '../../app/bootstrap.php';
requireLogin();



requireLogin();

// Permission check: Admin, Counselor, or Branch Manager
if (!hasRole('admin') && !hasRole('counselor') && !hasRole('branch_manager')) {
    die("Access denied. Authorized roles only.");
}

$branchService = new \EduCRM\Services\BranchService($pdo);
$currentUserBranch = $branchService->getUserBranch($_SESSION['user_id']);

// Get available notification channels
$notifPrefService = new \EduCRM\Services\NotificationPreferenceService($pdo);
$availableChannels = $notifPrefService->getAvailableChannels();

// Fetch all roles for the form
$all_roles = $pdo->query("SELECT * FROM roles ORDER BY name")->fetchAll();

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $selected_role_ids = isset($_POST['roles']) ? $_POST['roles'] : [];

    $password = generateSecurePassword();
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Validation: At least one role
    if (empty($selected_role_ids)) {
        redirectWithAlert("add.php", "Please assign at least one role.", 'error');
    } else {
        // Security: Counselors can only create Students
        if (!hasRole('admin')) {
            // Fetch student role ID
            $student_role = array_filter($all_roles, fn($r) => $r['name'] === 'student');
            $student_role_id = $student_role ? reset($student_role)['id'] : 0;

            foreach ($selected_role_ids as $rid) {
                if ($rid != $student_role_id) {
                    redirectWithAlert("add.php", "Unauthorized: Counselors can only create Student accounts.", 'error');
                }
            }
        }

        // Check if email exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
            redirectWithAlert("add.php", "A user with this email already exists.", 'error');
        } else {
            try {
                $pdo->beginTransaction();

                // Determine Branch ID
                $target_branch_id = null;
                if (hasRole('branch_manager') && $currentUserBranch) {
                    $target_branch_id = $currentUserBranch['id'];
                }

                // Insert User
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, phone, branch_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $password_hash, $phone, $target_branch_id]);
                $user_id = $pdo->lastInsertId();

                // Insert User Roles
                $linkStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                foreach ($selected_role_ids as $rid) {
                    $linkStmt->execute([$user_id, $rid]);
                }

                $pdo->commit();

                // Save notification preferences
                $selectedChannels = $_POST['notification_channels'] ?? ['email'];
                $notifPrefService->saveUserPreferences($user_id, $selectedChannels);

                // Send welcome email with credentials
                try {
                    $emailService = new \EduCRM\Services\EmailNotificationService($pdo);
                    $emailService->sendWelcomeEmail($user_id, $password);
                } catch (Exception $e) {
                    // Log error but don't fail user creation
                    error_log("Failed to send welcome email: " . $e->getMessage());
                }

                redirectWithAlert("list.php", "User account created successfully! <br><strong>Password:</strong> $password <br>(Please secure this password. A welcome email has also been sent.)", 'success');
            } catch (PDOException $e) {
                if ($pdo->inTransaction())
                    $pdo->rollBack();
                redirectWithAlert("add.php", "Unable to create user. Please check the details and try again.", 'error');
            }
        }
    }
}

$pageDetails = ['title' => 'Add New User'];
require_once '../../templates/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h2>Add New User / Staff</h2>

    <?php if ($error): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; margin-bottom: 20px;">
                <?php echo $error; ?>
            </div>
    <?php endif; ?>

    <?php if ($message): ?>
            <div
                style="background: #dcfce7; color: #166534; padding: 20px; border-radius: 6px; margin-bottom: 20px; border: 2px solid #16a34a;">
                <?php echo $message; ?>
                <div style="margin-top: 15px;">
                    <a href="list.php" class="btn">View All Users</a>
                </div>
            </div>
    <?php else: ?>
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
                    <input type="text" name="phone" class="form-control">
                </div>
                <div class="form-group">
                    <label>Assigned Roles (Select multiple)</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; background: #f8fafc; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px;">
                        <?php foreach ($all_roles as $role): ?>
                                <?php
                                // Visibility Check:
                                // 1. Counselors only see Student option
                                // 2. Branch Managers: Can assign teacher, student, counselor, accountant. Cannot assign admin or branch_manager.
                        
                                if (hasRole('counselor') && !hasRole('admin') && !hasRole('branch_manager')) {
                                    if ($role['name'] !== 'student')
                                        continue;
                                }

                                if (hasRole('branch_manager') && !hasRole('admin')) {
                                    // Branch Managers cannot create other Branch Managers or Admins
                                    if (in_array($role['name'], ['admin', 'branch_manager']))
                                        continue;
                                }
                                ?>
                                <label style="cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                    <input type="checkbox" name="roles[]" value="<?php echo $role['id']; ?>" 
                                        <?php echo ($role['name'] === 'student' && hasRole('counselor') && !hasRole('admin') && !hasRole('branch_manager')) ? 'checked onclick="return false;"' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $role['name'])); ?>
                                </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (hasRole('counselor') && !hasRole('admin') && !hasRole('branch_manager')): ?>
                            <small style="color: grey;">(Restricted: Counselors can only add Students)</small>
                    <?php endif; ?>
                    <?php if (hasRole('branch_manager') && !hasRole('admin')): ?>
                            <small style="color: grey;">(Branch Managers can create staff for their branch)</small>
                    <?php endif; ?>
                </div>

                <!-- Notification Preferences -->
                <div class="form-group">
                    <label>Notification Preferences</label>
                    <div style="background: #f8fafc; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <p style="margin: 0 0 10px 0; font-size: 13px; color: #64748b;">Select how this user receives notifications:</p>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px;">
                            <?php foreach ($availableChannels as $channel): ?>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: white; border: 1px solid #e2e8f0; border-radius: 6px;">
                                    <input type="checkbox" name="notification_channels[]" value="<?php echo $channel['type']; ?>"
                                        <?php echo $channel['default'] ? 'checked' : ''; ?>
                                        style="width: 16px; height: 16px;">
                                    <span style="font-size: 16px;"><?php echo $channel['icon']; ?></span>
                                    <span style="font-size: 14px; font-weight: 500;"><?php echo htmlspecialchars($channel['label']); ?></span>
                                    <?php if (!empty($channel['gateways']) && $channel['gateways'] !== 'System Email'): ?>
                                        <span style="font-size: 11px; color: #94a3b8;">(<?php echo htmlspecialchars($channel['gateways']); ?>)</span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn">Create User</button>
                <a href="list.php" class="btn btn-secondary">Cancel</a>
            </form>
    <?php endif; ?>
</div>

<?php require_once '../../templates/footer.php'; ?>