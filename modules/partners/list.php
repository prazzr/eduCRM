<?php
require_once '../../config.php';
requireLogin();

// Admin/Counselor only
if (hasRole('student')) {
    header("Location: ../../index.php");
    exit;
}

$message = '';
// Add Partner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_partner'])) {
    $name = sanitize($_POST['name']);
    $country = sanitize($_POST['country']);
    $type = $_POST['type'];
    $website = sanitize($_POST['website']);

    $stmt = $pdo->prepare("INSERT INTO partners (name, country, type, website) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $country, $type, $website]);
    redirectWithAlert("list.php", "Partner added.");
}

$partners = $pdo->query("SELECT * FROM partners ORDER BY name")->fetchAll();

$pageDetails = ['title' => 'Partners Database'];
require_once '../../includes/header.php';
?>

<div class="card">
    <h2>Partner Universities & Agents</h2>

    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin: 15px 0;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php renderFlashMessage(); ?>

    <form method="POST" style="margin-bottom: 30px; background: #f8fafc; padding: 15px; border-radius: 8px;">
        <input type="hidden" name="add_partner" value="1">
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 2fr; gap: 15px; align-items: end;">
            <div class="form-group" style="margin: 0;">
                <label>Institution Name</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. University of Oxford">
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Country</label>
                <input type="text" name="country" class="form-control" required placeholder="UK">
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Type</label>
                <select name="type" class="form-control">
                    <option value="university">University</option>
                    <option value="college">College</option>
                    <option value="agent">Agent</option>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Website</label>
                <input type="text" name="website" class="form-control" placeholder="https://...">
            </div>
        </div>
        <button type="submit" class="btn" style="margin-top: 15px;">Add Partner</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Country</th>
                <th>Website</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($partners as $p): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                    <td><span class="status-badge" style="background: #e2e8f0;"><?php echo ucfirst($p['type']); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($p['country']); ?></td>
                    <td><a href="<?php echo htmlspecialchars($p['website']); ?>"
                            target="_blank"><?php echo htmlspecialchars($p['website']); ?></a></td>
                    <td style="text-align: right;">
                        <div style="display: flex; gap: 5px; justify-content: flex-end;">
                            <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-secondary"
                                style="font-size: 11px; padding: 5px 8px;">Edit</a>
                            <a href="delete.php?id=<?php echo $p['id']; ?>" class="btn"
                                style="font-size: 11px; padding: 5px 8px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;"
                                onclick="return confirm('Delete this partner?')">Delete</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../includes/footer.php'; ?>