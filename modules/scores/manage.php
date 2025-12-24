<?php
require_once '../../config.php';
requireLogin();

// Redirect to new Student Management
header("Location: ../students/list.php");
exit;

// Admin/Counselor only

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

    $stmt = $pdo->prepare("INSERT INTO test_scores (student_id, test_type, overall_score, listening, reading, writing, speaking) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$student_id, $type, $overall, $l, $r, $w, $s]);
    $message = "Score added.";
}

$scores = $pdo->query("
    SELECT ts.*, u.name as student_name 
    FROM test_scores ts 
    JOIN users u ON ts.student_id = u.id 
    ORDER BY ts.created_at DESC
")->fetchAll();

$pageDetails = ['title' => 'Test Scores'];
require_once '../../includes/header.php';
?>

<div class="card">
    <h2>Student Test Scores</h2>
    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin: 15px 0;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" style="margin-bottom: 30px; background: #f8fafc; padding: 15px; border-radius: 8px;">
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 15px;">
            <div class="form-group">
                <label>Student</label>
                <select name="student_id" class="form-control" required>
                    <option value="">Select Student...</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Test Type</label>
                <select name="test_type" class="form-control">
                    <option value="IELTS">IELTS</option>
                    <option value="PTE">PTE</option>
                    <option value="SAT">SAT</option>
                </select>
            </div>
            <div class="form-group">
                <label>Overall Score</label>
                <input type="number" step="0.1" name="overall" class="form-control" required placeholder="e.g. 7.5">
            </div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px;">
            <input type="number" step="0.1" name="listening" class="form-control" placeholder="Listen">
            <input type="number" step="0.1" name="reading" class="form-control" placeholder="Read">
            <input type="number" step="0.1" name="writing" class="form-control" placeholder="Write">
            <input type="number" step="0.1" name="speaking" class="form-control" placeholder="Speak">
        </div>
        <button type="submit" class="btn" style="margin-top: 15px;">Record Score</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Test</th>
                <th>Overall</th>
                <th>Breakdown (L/R/W/S)</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($scores as $sc): ?>
                <tr>
                    <td><?php echo htmlspecialchars($sc['student_name']); ?></td>
                    <td><strong><?php echo $sc['test_type']; ?></strong></td>
                    <td><span
                            style="font-weight: bold; font-size: 16px; color: var(--primary-color);"><?php echo $sc['overall_score']; ?></span>
                    </td>
                    <td><?php echo $sc['listening'] . ' / ' . $sc['reading'] . ' / ' . $sc['writing'] . ' / ' . $sc['speaking']; ?>
                    </td>
                    <td style="text-align: right;">
                        <a href="score_edit.php?id=<?php echo $sc['id']; ?>" class="btn btn-secondary"
                            style="font-size: 11px; padding: 5px 8px;">Edit</a>
                        <a href="score_delete.php?id=<?php echo $sc['id']; ?>" class="btn"
                            style="font-size: 11px; padding: 5px 8px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;"
                            onclick="return confirm('Delete this score record?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../includes/footer.php'; ?>