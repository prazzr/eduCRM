<?php
require_once '../../app/bootstrap.php';
requireLogin();

requireBranchManager();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user)
    die("User not found");

// Fetch active roles for this user
$stmt = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
$stmt->execute([$id]);
$active_role_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

$all_roles = $pdo->query("SELECT * FROM roles")->fetchAll();

// Security: Branch Managers cannot edit Admins
if (!hasRole('admin') && hasRole('branch_manager')) {
    foreach ($all_roles as $r) {
        if ($r['name'] === 'admin' && in_array($r['id'], $active_role_ids)) {
            die("Unauthorized: Branch Managers cannot edit Administrator accounts.");
        }
    }
}

// Get available notification channels and user's preferences
$notifPrefService = new \EduCRM\Services\NotificationPreferenceService($pdo);
$availableChannels = $notifPrefService->getAvailableChannels();
$userPrefs = $notifPrefService->getUserPreferences($id);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $new_roles = isset($_POST['roles']) ? $_POST['roles'] : [];

    // Security: Branch Managers cannot assign Admin role
    if (!hasRole('admin') && hasRole('branch_manager')) {
        $admin_role_id = 0;
        foreach ($all_roles as $r) { if ($r['name'] === 'admin') { $admin_role_id = $r['id']; break; } }
        if ($admin_role_id && in_array($admin_role_id, $new_roles)) {
             redirectWithAlert("edit.php?id=$id", "Unauthorized: Branch Managers cannot assign Admin role.", 'error');
        }
    }

    if ($name && $email) {
        $pdo->beginTransaction();

        // Update user basics
        $branch_id = $user['branch_id']; // Default to current
        if (hasRole('admin')) {
             // If admin, allowing changing branch (or setting to global/null)
             $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : null;
        }

        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, branch_id = ? WHERE id = ?");
        $stmt->execute([$name, $email, $phone, $branch_id, $id]);

        // Update roles
        $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$id]);
        foreach ($new_roles as $role_id) {
            $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$id, $role_id]);
        }

        $pdo->commit();

        // Save notification preferences
        $selectedChannels = $_POST['notification_channels'] ?? ['email'];
        $notifPrefService->saveUserPreferences($id, $selectedChannels);

        redirectWithAlert("list.php", "User profile updated successfully.", 'success');
    } else {
        redirectWithAlert("edit.php?id=$id", "Please provide both Name and Email.", 'error');
    }
}

$pageDetails = ['title' => 'Edit User'];
require_once '../../templates/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h2>Edit User</h2>
    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin: 10px 0;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>"
                required>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control"
                value="<?php echo htmlspecialchars($user['email']); ?>" required>
        </div>
        <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control"
                value="<?php echo htmlspecialchars($user['phone']); ?>">
        </div>
        
        <?php if (hasRole('admin')): 
            $branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
        ?>
            <div class="form-group">
                <label>Branch (Optional)</label>
                <select name="branch_id" class="form-control">
                    <option value="">-- No Branch (Global/Headquarters) --</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo $branch['id']; ?>" <?php echo $user['branch_id'] == $branch['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color: grey;">Assigning a branch restricts this user's access to that branch's data.</small>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label>Assign Roles</label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 5px;">
                <?php foreach ($all_roles as $role): ?>
                    <?php if (!hasRole('admin') && hasRole('branch_manager') && $role['name'] === 'admin') continue; ?>


                    <label style="font-weight: normal; cursor: pointer;">
                        <input type="checkbox" name="roles[]" value="<?php echo $role['id']; ?>" 
                            <?php echo in_array($role['id'], $active_role_ids) ? 'checked' : ''; ?>>
                        <?php echo ucfirst($role['name']); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Notification Preferences -->
        <div class="form-group">
            <label>Notification Preferences</label>
            <div style="background: #f8fafc; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;">
                <p style="margin: 0 0 10px 0; font-size: 13px; color: #64748b;">Select how this user receives notifications:</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px;">
                    <?php foreach ($availableChannels as $channel): ?>
                        <?php 
                        $isChecked = isset($userPrefs[$channel['type']]) ? $userPrefs[$channel['type']] : $channel['default'];
                        ?>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: white; border: 1px solid #e2e8f0; border-radius: 6px;">
                            <input type="checkbox" name="notification_channels[]" value="<?php echo $channel['type']; ?>"
                                <?php echo $isChecked ? 'checked' : ''; ?>
                                style="width: 16px; height: 16px;">
                            <span style="font-size: 16px;"><?php echo $channel['icon']; ?></span>
                            <span style="font-size: 14px; font-weight: 500;"><?php echo htmlspecialchars($channel['label']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <button type="submit" class="btn">Save Changes</button>
        <a href="list.php" class="btn btn-secondary">Back to List</a>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>