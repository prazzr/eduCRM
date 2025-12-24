<?php
require_once '../../config.php';
requireLogin();

// Only Admin/Counselor
requireAdminOrCounselor();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $country = sanitize($_POST['country']);
    $course = sanitize($_POST['course']);
    $edu_level = sanitize($_POST['edu_level']);
    $assigned_to = $_SESSION['user_id'];

    if ($name) {
        try {
            $stmt = $pdo->prepare("INSERT INTO inquiries (name, email, phone, intended_country, intended_course, education_level, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $country, $course, $edu_level, $assigned_to]);
            redirectWithAlert("list.php", "Inquiry added successfully!");
        } catch (PDOException $e) {
            $error = "Error adding inquiry: " . $e->getMessage();
        }
    } else {
        $error = "Name is required.";
    }
}

$pageDetails = ['title' => 'Add Inquiry'];
require_once '../../includes/header.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <h2>Add New Student Inquiry</h2>

    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin: 15px 0;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; margin: 15px 0;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Student Name *</label>
            <input type="text" name="name" class="form-control" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>Intended Country</label>
                <select name="country" class="form-control">
                    <option value="">Select Country</option>
                    <option value="USA">USA</option>
                    <option value="UK">UK</option>
                    <option value="Australia">Australia</option>
                    <option value="Canada">Canada</option>
                    <option value="Europe">Europe</option>
                </select>
            </div>
            <div class="form-group">
                <label>Intended Course</label>
                <select name="course" class="form-control">
                    <option value="">Select Course</option>
                    <option value="IELTS">IELTS</option>
                    <option value="PTE">PTE</option>
                    <option value="SAT">SAT</option>
                    <option value="Study Abroad">Study Abroad Only</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Current Education Level</label>
            <input type="text" name="edu_level" class="form-control" placeholder="e.g. +2 Completed, Bachelor Running">
        </div>

        <button type="submit" class="btn">Save Inquiry</button>
        <a href="list.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>