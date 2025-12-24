<?php
require_once '../../config.php';
requireLogin();

if (hasRole('student')) {
    die("Unauthorized");
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

$stmt = $pdo->prepare("SELECT * FROM test_scores WHERE id = ?");
$stmt->execute([$id]);
$score = $stmt->fetch();

if (!$score)
    die("Score record not found");

$students = $pdo->query("SELECT id, name FROM users WHERE role='student' ORDER BY name")->fetchAll();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'];
    $type = $_POST['test_type'];
    $overall = $_POST['overall'];
    $l = $_POST['listening'];
    $r = $_POST['reading'];
    $w = $_POST['writing'];
    $s = $_POST['speaking'];

    $stmt = $pdo->prepare("UPDATE test_scores SET student_id = ?, test_type = ?, overall_score = ?, listening = ?, reading = ?, writing = ?, speaking = ? WHERE id = ?");
    $stmt->execute([$student_id, $type, $overall, $l, $r, $w, $s, $id]);
    $message = "Score updated!";

    // Refresh
    $score['student_id'] = $student_id;
    $score['test_type'] = $type;
    $score['overall_score'] = $overall;
    $score['listening'] = $l;
    $score['reading'] = $r;
    $score['writing'] = $w;
    $score['speaking'] = $s;
}

$pageDetails = ['title' => 'Edit Test Score'];
require_once '../../includes/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h2>Edit Test Score</h2>
    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin: 15px 0;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Student</label>
            <select name="student_id" class="form-control" required>
                <?php foreach ($students as $st): ?>
                    <option value="<?php echo $st['id']; ?>" <?php echo $st['id'] == $score['student_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($st['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Test Type</label>
            <select name="test_type" class="form-control">
                <option value="IELTS" <?php echo $score['test_type'] == 'IELTS' ? 'selected' : ''; ?>>IELTS</option>
                <option value="PTE" <?php echo $score['test_type'] == 'PTE' ? 'selected' : ''; ?>>PTE</option>
                <option value="SAT" <?php echo $score['test_type'] == 'SAT' ? 'selected' : ''; ?>>SAT</option>
            </select>
        </div>
        <div class="form-group">
            <label>Overall Score</label>
            <input type="number" step="0.1" name="overall" class="form-control"
                value="<?php echo $score['overall_score']; ?>" required>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px;">
            <div class="form-group">
                <label>Listening</label>
                <input type="number" step="0.1" name="listening" class="form-control"
                    value="<?php echo $score['listening']; ?>">
            </div>
            <div class="form-group">
                <label>Reading</label>
                <input type="number" step="0.1" name="reading" class="form-control"
                    value="<?php echo $score['reading']; ?>">
            </div>
            <div class="form-group">
                <label>Writing</label>
                <input type="number" step="0.1" name="writing" class="form-control"
                    value="<?php echo $score['writing']; ?>">
            </div>
            <div class="form-group">
                <label>Speaking</label>
                <input type="number" step="0.1" name="speaking" class="form-control"
                    value="<?php echo $score['speaking']; ?>">
            </div>
        </div>
        <button type="submit" class="btn">Update Score</button>
        <a href="manage.php" class="btn btn-secondary">Back</a>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>