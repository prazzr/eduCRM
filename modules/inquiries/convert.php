<?php
require_once '../../config.php';
requireLogin();

requireAdminOrCounselor();
$inquiry_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$inquiry_id) {
    header("Location: list.php");
    exit;
}

$error = '';
$stmt = $pdo->prepare("SELECT * FROM inquiries WHERE id = ?");
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
    $country = $inquiry['intended_country'];
    $education = $inquiry['education_level'];

    // Check if email already exists
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->rowCount() > 0) {
        $error = "User with this email already exists.";
    } else {
        try {
            $pdo->beginTransaction();

            // Insert User
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, phone, country, education_level) VALUES (?, ?, ?, 'student', ?, ?, ?)");
            $stmt->execute([$name, $email, $password_hash, $phone, $country, $education]);
            $user_id = $pdo->lastInsertId();

            // Link to Student Role (Multi-role support)
            $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'student'");
            $roleStmt->execute();
            $role_id = $roleStmt->fetchColumn();

            $linkStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            $linkStmt->execute([$user_id, $role_id]);

            // Update Inquiry Status
            $update = $pdo->prepare("UPDATE inquiries SET status = 'converted' WHERE id = ?");
            $update->execute([$inquiry_id]);

            $pdo->commit();

            // Send Welcome Email
            require_once '../../includes/services/EmailService.php';
            $emailService = new EmailService();
            $emailService->sendWelcomeEmail([
                'name' => $name,
                'email' => $email
            ], $raw_password);

            // Show password once before redirect
            $success_msg = "Successfully converted! Generated Password: <strong>$raw_password</strong> (Please copy this now)";
            $success_msg .= "<br><br><a href='../students/profile.php?id=$user_id' class='btn btn-secondary'>View Student Profile &raquo;</a>";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error converting user: " . $e->getMessage();
        }
    }
}

$pageDetails = ['title' => 'Convert to Student'];
require_once '../../includes/header.php';
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

<?php require_once '../../includes/footer.php'; ?>