<?php
require_once '../../config.php';
requireLogin();

if (hasRole('student')) {
    die("Unauthorized");
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

$stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
$stmt->execute([$id]);
$partner = $stmt->fetch();

if (!$partner)
    die("Partner not found");

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $country = sanitize($_POST['country']);
    $type = $_POST['type'];
    $website = sanitize($_POST['website']);

    if ($name) {
        $stmt = $pdo->prepare("UPDATE partners SET name = ?, country = ?, type = ?, website = ? WHERE id = ?");
        $stmt->execute([$name, $country, $type, $website, $id]);
        redirectWithAlert("list.php", "Partner updated!");

        $partner['name'] = $name;
        $partner['country'] = $country;
        $partner['type'] = $type;
        $partner['website'] = $website;
    }
}

$pageDetails = ['title' => 'Edit Partner'];
require_once '../../includes/header.php';
?>

<div class="card" style="max-width: 500px; margin: 0 auto;">
    <h2>Edit Partner</h2>
    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin: 10px 0;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Institution Name</label>
            <input type="text" name="name" class="form-control"
                value="<?php echo htmlspecialchars($partner['name']); ?>" required>
        </div>
        <div class="form-group">
            <label>Country</label>
            <input type="text" name="country" class="form-control"
                value="<?php echo htmlspecialchars($partner['country']); ?>" required>
        </div>
        <div class="form-group">
            <label>Type</label>
            <select name="type" class="form-control">
                <option value="university" <?php echo $partner['type'] == 'university' ? 'selected' : ''; ?>>University
                </option>
                <option value="college" <?php echo $partner['type'] == 'college' ? 'selected' : ''; ?>>College</option>
                <option value="agent" <?php echo $partner['type'] == 'agent' ? 'selected' : ''; ?>>Agent</option>
            </select>
        </div>
        <div class="form-group">
            <label>Website</label>
            <input type="text" name="website" class="form-control"
                value="<?php echo htmlspecialchars($partner['website']); ?>">
        </div>
        <button type="submit" class="btn">Update Partner</button>
        <a href="list.php" class="btn btn-secondary">Back</a>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>