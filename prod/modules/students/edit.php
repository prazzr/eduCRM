<?php
/**
 * Edit Student
 * Updates student profile information
 */
require_once '../../app/bootstrap.php';


requireLogin();
requireCRMAccess();

// Validate ID parameter
$id = requireIdParam();

// Load lookup data using cached service
$lookup = \EduCRM\Services\LookupCacheService::getInstance($pdo);
$countries = $lookup->getAll('countries');
$education_levels = $lookup->getAll('education_levels');

// Get available notification channels and user's preferences
$notifPrefService = new \EduCRM\Services\NotificationPreferenceService($pdo);
$availableChannels = $notifPrefService->getAvailableChannels();
$userPrefs = $notifPrefService->getUserPreferences($id);

// Fetch student with FK JOINs
$stmt = $pdo->prepare("
    SELECT u.*, 
           c.name as country_name, 
           el.name as education_level_name
    FROM users u
    LEFT JOIN countries c ON u.country_id = c.id
    LEFT JOIN education_levels el ON u.education_level_id = el.id
    WHERE u.id = ?
");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    die("Student not found");
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $country_id = !empty($_POST['country_id']) ? (int) $_POST['country_id'] : null;
    $education_level_id = !empty($_POST['education_level_id']) ? (int) $_POST['education_level_id'] : null;
    $passport = sanitize($_POST['passport_number']);

    try {
        $pdo->beginTransaction();

        // Update using FK columns
        $branch_sql = "";
        $params = [$name, $email, $phone, $country_id, $education_level_id, $passport];

        if (hasRole('admin')) {
             if (isset($_POST['branch_id'])) {
                 $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : null;
                 $branch_sql = ", branch_id = ?";
                 $params[] = $branch_id;
             }
        }
        $params[] = $id;

        $upd = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, country_id = ?, education_level_id = ?, passport_number = ? $branch_sql, updated_at = NOW() WHERE id = ?");
        $upd->execute($params);

        // Log the action
        logAction('student_update', "Updated student ID: {$id}");

        // Save notification preferences
        $selectedChannels = $_POST['notification_channels'] ?? [];

        // DEBUG LOGGING
        file_put_contents(__DIR__ . '/debug_edit.log', date('Y-m-d H:i:s') . " - ID: " . $id . " - POST Channels: " . json_encode($selectedChannels) . "\n", FILE_APPEND);

        $notifPrefService->saveUserPreferences($id, $selectedChannels);

        $pdo->commit();

        // Set flash message for helper function
        $_SESSION['flash_msg'] = ['message' => "Student profile updated successfully.", 'type' => 'success'];

        // Robust redirect
        if (!headers_sent()) {
            header("Location: list.php");
            exit;
        } else {
            echo '<script>window.location.href = "list.php";</script>';
            exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMsg = "Unable to update profile: " . $e->getMessage();
        redirectWithAlert("edit.php?id=$id", $errorMsg, 'error');
    }
}

$pageDetails = ['title' => 'Edit Student'];
require_once '../../templates/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h2>Edit Student: <?php echo htmlspecialchars($student['name']); ?></h2>

    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" class="form-control"
                value="<?php echo htmlspecialchars($student['name']); ?>" required>
        </div>
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" class="form-control"
                value="<?php echo htmlspecialchars($student['email']); ?>" required>
        </div>
        <div class="form-group">
            <label>Phone Number</label>
            <input type="text" name="phone" class="form-control"
                value="<?php echo htmlspecialchars($student['phone']); ?>" required>
        </div>

        <?php if (hasRole('admin')): 
            $branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
        ?>
            <div class="form-group">
                <label>Branch</label>
                <select name="branch_id" class="form-control">
                    <option value="">-- Global / Head Office --</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo ($student['branch_id'] == $b['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['name']); ?>
                        </option>
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
                        <option value="<?php echo $c['id']; ?>" <?php echo ($student['country_id'] == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Education Level</label>
                <select name="education_level_id" class="form-control">
                    <option value="">-- Select Level --</option>
                    <?php foreach ($education_levels as $el): ?>
                        <option value="<?php echo $el['id']; ?>" <?php echo ($student['education_level_id'] == $el['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($el['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Passport Number</label>
            <input type="text" name="passport_number" class="form-control"
                value="<?php echo htmlspecialchars($student['passport_number']); ?>">
        </div>

        <!-- Notification Preferences -->
        <div class="form-group">
            <label>Notification Preferences</label>
            <div style="background: #f8fafc; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;">
                <p style="margin: 0 0 10px 0; font-size: 13px; color: #64748b;">Select how this student receives
                    notifications:</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px;">
                    <?php foreach ($availableChannels as $channel): ?>
                        <?php
                        $isChecked = isset($userPrefs[$channel['type']]) ? $userPrefs[$channel['type']] : $channel['default'];
                        ?>
                        <label
                            style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: white; border: 1px solid #e2e8f0; border-radius: 6px;">
                            <input type="checkbox" name="notification_channels[]" value="<?php echo $channel['type']; ?>"
                                <?php echo $isChecked ? 'checked' : ''; ?> style="width: 16px; height: 16px;">
                            <span style="font-size: 16px;"><?php echo $channel['icon']; ?></span>
                            <span
                                style="font-size: 14px; font-weight: 500;"><?php echo htmlspecialchars($channel['label']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <button type="submit" class="btn">Update Records</button>
        <a href="profile.php?id=<?php echo $id; ?>" class="btn btn-secondary">Back to Profile</a>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>