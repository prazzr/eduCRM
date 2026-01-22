<?php
/**
 * Edit Student
 * Updates student profile information
 */
require_once '../../app/bootstrap.php';


requireLogin();
requireAdminCounselorOrBranchManager();

// Validate ID parameter
$id = requireIdParam();

// Load lookup data using cached service
$lookup = \EduCRM\Services\LookupCacheService::getInstance($pdo);
$countries = $lookup->getAll('countries');
$education_levels = $lookup->getAll('education_levels');

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
        // Update using FK columns
        $upd = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, country_id = ?, education_level_id = ?, passport_number = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute([$name, $email, $phone, $country_id, $education_level_id, $passport, $id]);

        // Log the action
        logAction('student_update', "Updated student ID: {$id}");

        redirectWithAlert("list.php", "Student profile updated successfully.", 'success');
    } catch (PDOException $e) {
        redirectWithAlert("edit.php?id=$id", "Unable to update profile. Please try again.", 'error');
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

        <button type="submit" class="btn">Update Records</button>
        <a href="profile.php?id=<?php echo $id; ?>" class="btn btn-secondary">Back to Profile</a>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>