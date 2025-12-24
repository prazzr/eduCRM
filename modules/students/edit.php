<?php
require_once '../../config.php';
requireLogin();

requireAdminOrCounselor();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student)
    die("Student not found");

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $country = sanitize($_POST['country']);
    $education = sanitize($_POST['education_level']);
    $passport = sanitize($_POST['passport_number']);

    $upd = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, country = ?, education_level = ?, passport_number = ? WHERE id = ?");
    $upd->execute([$name, $email, $phone, $country, $education, $passport, $id]);
    redirectWithAlert("list.php", "Student details updated!");

    // Refresh
    $student['name'] = $name;
    $student['email'] = $email;
    $student['phone'] = $phone;
    $student['country'] = $country;
    $student['education_level'] = $education;
    $student['passport_number'] = $passport;
}

$pageDetails = ['title' => 'Edit Student'];
require_once '../../includes/header.php';
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
                <input type="text" name="country" class="form-control"
                    value="<?php echo htmlspecialchars($student['country']); ?>">
            </div>
            <div class="form-group">
                <label>Education Level</label>
                <select name="education_level" class="form-control">
                    <option value="High School" <?php echo $student['education_level'] == 'High School' ? 'selected' : ''; ?>>High School</option>
                    <option value="Bachelor" <?php echo $student['education_level'] == 'Bachelor' ? 'selected' : ''; ?>>
                        Bachelor</option>
                    <option value="Master" <?php echo $student['education_level'] == 'Master' ? 'selected' : ''; ?>>Master
                    </option>
                    <option value="PhD" <?php echo $student['education_level'] == 'PhD' ? 'selected' : ''; ?>>PhD</option>
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

<?php require_once '../../includes/footer.php'; ?>