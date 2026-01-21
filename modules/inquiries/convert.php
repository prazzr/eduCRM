<?php
require_once '../../app/bootstrap.php';
requireLogin();

requireAdminCounselorOrBranchManager();
$inquiry_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$inquiry_id) {
    header("Location: list.php");
    exit;
}

$error = '';
$stmt = $pdo->prepare("SELECT i.*, c.name as country_name, el.name as education_level_name 
    FROM inquiries i 
    LEFT JOIN countries c ON i.country_id = c.id
    LEFT JOIN education_levels el ON i.education_level_id = el.id
    WHERE i.id = ?");
$stmt->execute([$inquiry_id]);
$inquiry = $stmt->fetch();

if (!$inquiry) {
    die("Inquiry not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Convert to User
    $raw_password = generateSecurePassword();
    $password_hash = password_hash($raw_password, PASSWORD_DEFAULT);
    $email = $inquiry['email'];
    $name = $inquiry['name'];
    $phone = $inquiry['phone'];
    $country_id = $inquiry['country_id'];
    $education_level_id = $inquiry['education_level_id'];

    // Check if email already exists
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->rowCount() > 0) {
        redirectWithAlert("convert.php?id=$inquiry_id", "User with this email already exists.", "error");
    } else {
        try {
            $pdo->beginTransaction();

            // Insert User with FK columns
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, phone, country_id, education_level_id) VALUES (?, ?, ?, 'student', ?, ?, ?)");
            $stmt->execute([$name, $email, $password_hash, $phone, $country_id, $education_level_id]);
            $user_id = $pdo->lastInsertId();

            // Link to Student Role (Multi-role support)
            $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'student'");
            $roleStmt->execute();
            $role_id = $roleStmt->fetchColumn();

            $linkStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            $linkStmt->execute([$user_id, $role_id]);

            // Update Inquiry Status using FK
            $update = $pdo->prepare("UPDATE inquiries SET status_id = (SELECT id FROM inquiry_statuses WHERE name = 'converted') WHERE id = ?");
            $update->execute([$inquiry_id]);

            $pdo->commit();

            // Send Welcome Email

            $emailService = new \EduCRM\Services\EmailService();
            $emailService->sendWelcomeEmail([
                'name' => $name,
                'email' => $email
            ], $raw_password);

            // PRG: Redirect to new student profile with password in flash message
            $success_msg = "Successfully converted! Generated Password: <strong>$raw_password</strong> (Please copy this now)";
            redirectWithAlert("../students/profile.php?id=$user_id", $success_msg, "success");

        } catch (PDOException $e) {
            $pdo->rollBack();
            redirectWithAlert("convert.php?id=$inquiry_id", "Error converting user: " . $e->getMessage(), "error");
        }
    }
}

$pageDetails = ['title' => 'Convert to Student'];
require_once '../../templates/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h2>Convert Inquiry to Student</h2>
    <p>This will create a student user account for <strong><?php echo htmlspecialchars($inquiry['name']); ?></strong>.
    </p>

    <?php if (isset($success_msg)): ?>
        <div
            style="background: #dcfce7; color: #166534; padding: 20px; border-radius: 6px; margin: 15px 0; border: 2px solid #16a34a;">
            <?php echo $success_msg; ?>
            <div style="margin-top: 20px;">
                <a href="list.php" class="btn">Back to Inquiry List</a>
            </div>
        </div>
    <?php else: ?>
        <?php if ($error): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; margin: 15px 0;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <p><strong>Security Note:</strong> A random secure password will be generated for the student. Please ensure you
                share it with them.</p>
            <button type="submit" class="btn">Confirm Conversion</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div>

<?php require_once '../../templates/footer.php'; ?>