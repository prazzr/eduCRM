<?php
require_once '../../config.php';
requireLogin();

requireAdminOrCounselor();

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
        $error = "Please assign at least one role.";
    } else {
        // Security: Counselors can only create Students
        if (!hasRole('admin')) {
            // Fetch student role ID
            $student_role = array_filter($all_roles, fn($r) => $r['name'] === 'student');
            $student_role_id = $student_role ? reset($student_role)['id'] : 0;
            
            foreach ($selected_role_ids as $rid) {
                if ($rid != $student_role_id) {
                    die("Unauthorized: Counselors can only create Student accounts.");
                }
            }
        }

        // Check if email exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
            $error = "A user with this email already exists.";
        } else {
            try {
                $pdo->beginTransaction();



                // Insert User
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, phone) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $password_hash, $phone]);
                $user_id = $pdo->lastInsertId();

                // Insert User Roles
                $linkStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                foreach ($selected_role_ids as $rid) {
                    $linkStmt->execute([$user_id, $rid]);
                }

                $pdo->commit();
                redirectWithAlert("list.php", "User created successfully with roles: " . count($selected_role_ids) . ". <br>Generated Password: <strong>$password</strong> (Copy this now)");
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

$pageDetails = ['title' => 'Add New User'];
require_once '../../includes/header.php';
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
                        // Visibility Check: Counselors only see Student option
                        if (!hasRole('admin') && $role['name'] !== 'student') continue;
                        ?>
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="roles[]" value="<?php echo $role['id']; ?>" 
                                <?php echo ($role['name'] === 'student' && !hasRole('admin')) ? 'checked onclick="return false;"' : ''; ?>>
                            <?php echo ucfirst($role['name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (!hasRole('admin')): ?>
                    <small style="color: grey;">(Restricted: Counselors can only add Students)</small>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn">Create User</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>