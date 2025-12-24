<?php
require_once '../../config.php';
requireLogin();

requireAdminOrCounselor();

$student_id = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;
if (!$student_id)
    die("Invalid Student ID");

// Fetch Student & Current Workflow
$stmt = $pdo->prepare("SELECT u.*, vw.id as workflow_id, vw.country as visa_country, vw.current_stage, vw.notes 
                      FROM users u 
                      LEFT JOIN visa_workflows vw ON u.id = vw.student_id 
                      WHERE u.id = ?");
$stmt->execute([$student_id]);
$data = $stmt->fetch();

if (!$data)
    die("Student not found.");

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $country = sanitize($_POST['country']);
    $stage = $_POST['stage'];
    $notes = sanitize($_POST['notes']);

    if ($data['workflow_id']) {
        // Update existing
        $upd = $pdo->prepare("UPDATE visa_workflows SET country = ?, current_stage = ?, notes = ? WHERE student_id = ?");
        $upd->execute([$country, $stage, $notes, $student_id]);
    } else {
        // Create new
        $ins = $pdo->prepare("INSERT INTO visa_workflows (student_id, country, current_stage, notes) VALUES (?, ?, ?, ?)");
        $ins->execute([$student_id, $country, $stage, $notes]);
    }

    // Add a log entry for the student
    $log = $pdo->prepare("INSERT INTO student_logs (student_id, author_id, type, message) VALUES (?, ?, 'note', ?)");
    $msg = "Visa status updated for $country: $stage. Notes: $notes";
    $log->execute([$student_id, $_SESSION['user_id'], $msg]);

    redirectWithAlert("list.php", "Visa status updated successfully.");
}

$pageDetails = ['title' => 'Update Visa Status'];
require_once '../../includes/header.php';
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <h2>Update Visa Status for <?php echo htmlspecialchars($data['name']); ?></h2>

    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin-bottom: 20px;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Destination Country</label>
            <input type="text" name="country" class="form-control"
                value="<?php echo htmlspecialchars($data['visa_country'] ?: $data['country']); ?>" required>
        </div>

        <div class="form-group">
            <label>Current Stage</label>
            <select name="stage" class="form-control">
                <?php
                $stages = ['Doc Collection', 'Submission', 'Interview', 'Approved', 'Rejected'];
                foreach ($stages as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo ($data['current_stage'] == $s) ? 'selected' : ''; ?>>
                        <?php echo $s; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Notes / Checklist Status</label>
            <textarea name="notes" class="form-control"
                rows="4"><?php echo htmlspecialchars($data['notes']); ?></textarea>
        </div>

        <button type="submit" class="btn">Update Workflow</button>
        <a href="list.php" class="btn btn-secondary">Back to List</a>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>