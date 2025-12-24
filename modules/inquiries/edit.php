<?php
require_once '../../config.php';
requireLogin();

requireAdminOrCounselor();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

$stmt = $pdo->prepare("SELECT * FROM inquiries WHERE id = ?");
$stmt->execute([$id]);
$inq = $stmt->fetch();

if (!$inq)
    die("Inquiry not found");

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $country = sanitize($_POST['intended_country']);
    $course = sanitize($_POST['intended_course']);
    $status = $_POST['status'];

    $stmt = $pdo->prepare("UPDATE inquiries SET name = ?, email = ?, phone = ?, intended_country = ?, intended_course = ?, status = ? WHERE id = ?");
    $stmt->execute([$name, $email, $phone, $country, $course, $status, $id]);
    redirectWithAlert("list.php", "Inquiry updated!");

    // Refresh
    $inq['name'] = $name;
    $inq['email'] = $email;
    $inq['phone'] = $phone;
    $inq['intended_country'] = $country;
    $inq['intended_course'] = $course;
    $inq['status'] = $status;
}

$pageDetails = ['title' => 'Edit Inquiry'];
require_once '../../includes/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h2>Edit Inquiry</h2>
    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin: 10px 0;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($inq['name']); ?>"
                required>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($inq['email']); ?>"
                required>
        </div>
        <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($inq['phone']); ?>">
        </div>
        <div class="form-group">
            <label>Intended Country</label>
            <input type="text" name="intended_country" class="form-control"
                value="<?php echo htmlspecialchars($inq['intended_country']); ?>">
        </div>
        <div class="form-group">
            <label>Intended Course</label>
            <input type="text" name="intended_course" class="form-control"
                value="<?php echo htmlspecialchars($inq['intended_course']); ?>">
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control">
                <option value="new" <?php echo $inq['status'] == 'new' ? 'selected' : ''; ?>>New</option>
                <option value="follow-up" <?php echo $inq['status'] == 'follow-up' ? 'selected' : ''; ?>>Follow-up
                </option>
                <option value="converted" <?php echo $inq['status'] == 'converted' ? 'selected' : ''; ?>>Converted
                </option>
                <option value="closed" <?php echo $inq['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
            </select>
        </div>
        <button type="submit" class="btn">Update Inquiry</button>
        <a href="list.php" class="btn btn-secondary">Back</a>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>