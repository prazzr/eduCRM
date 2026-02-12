<?php
require_once '../../app/bootstrap.php';
requireLogin();

if (hasRole('student')) {
    die("Unauthorized");
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

$stmt = $pdo->prepare("SELECT * FROM student_documents WHERE id = ?");
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc)
    die("Document not found");

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);

    if ($title) {
        $stmt = $pdo->prepare("UPDATE student_documents SET title = ? WHERE id = ?");
        $stmt->execute([$title, $id]);
        redirectWithAlert("list.php?student_id=" . $doc['student_id'], "Document title updated successfully.", 'success');
    } else {
        redirectWithAlert("edit.php?id=$id", "Please provide a document title.", 'error');
    }
}

$pageDetails = ['title' => 'Edit Document'];
require_once '../../templates/header.php';
?>

<div class="card" style="max-width: 500px; margin: 0 auto;">
    <h2>Edit Document Title</h2>
    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin: 10px 0;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Current Title</label>
            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($doc['title']); ?>"
                required>
        </div>
        <button type="submit" class="btn">Update Title</button>
        <a href="list.php?student_id=<?php echo $doc['student_id']; ?>" class="btn btn-secondary">Back</a>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>