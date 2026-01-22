<?php
require_once '../../app/bootstrap.php';
requireLogin();

$material_id = isset($_GET['material_id']) ? (int) $_GET['material_id'] : 0;
if (!$material_id) {
    die("Invalid Material ID.");
}

// Fetch Material & Class Details
$stmt = $pdo->prepare("
    SELECT cm.*, c.id as class_id, c.name as class_name, co.name as course_name, c.teacher_id
    FROM class_materials cm
    JOIN classes c ON cm.class_id = c.id
    JOIN courses co ON c.course_id = co.id
    WHERE cm.id = ?
");
$stmt->execute([$material_id]);
$material = $stmt->fetch();

if (!$material)
    die("Material not found.");

$class_id = $material['class_id'];
$can_edit = hasRole('admin') || ($material['teacher_id'] == $_SESSION['user_id']);

if (!$can_edit) {
    die("Access denied.");
}

$message = '';
// Handle Grading & Sync
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $roster_id = (int) $_POST['roster_id'];
    $grades = $_POST['grades'] ?? []; // student_id => mark

    if (!$roster_id) {
        redirectWithAlert("review_submissions.php?material_id=$material_id", "Please select a Daily Session date to sync these marks.", 'error');
    } else {
        try {
            $pdo->beginTransaction();

            foreach ($grades as $student_id => $mark) {
                if ($mark === '')
                    continue;
                $mark = (float) $mark;

                // 1. Update Submission Grade
                $updSub = $pdo->prepare("UPDATE submissions SET grade = ? WHERE material_id = ? AND student_id = ?");
                $updSub->execute([$mark, $material_id, $student_id]);

                // 2. Sync to Daily Performance
                $col = ($material['type'] === 'home_task') ? 'home_task_mark' : 'class_task_mark';

                // Check if performance record exists for this student/roster
                $chkPerf = $pdo->prepare("SELECT id FROM daily_performance WHERE roster_id = ? AND student_id = ?");
                $chkPerf->execute([$roster_id, $student_id]);
                $perf = $chkPerf->fetch();

                if ($perf) {
                    $updPerf = $pdo->prepare("UPDATE daily_performance SET $col = ? WHERE id = ?");
                    $updPerf->execute([$mark, $perf['id']]);
                } else {
                    $insPerf = $pdo->prepare("INSERT INTO daily_performance (roster_id, student_id, $col) VALUES (?, ?, ?)");
                    $insPerf->execute([$roster_id, $student_id, $mark]);
                }
            }

            $pdo->commit();
            redirectWithAlert("review_submissions.php?material_id=$material_id", "Grades saved and synced to Daily Journal!", 'success');
        } catch (Exception $e) {
            $pdo->rollBack();
            redirectWithAlert("review_submissions.php?material_id=$material_id", "Unable to save grades. Please try again.", 'error');
        }
    }
}

// Fetch all enrolled students and their submissions
$students_stmt = $pdo->prepare("
    SELECT u.id, u.name, s.file_path, s.submitted_at, s.grade, s.comments
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    LEFT JOIN submissions s ON s.material_id = ? AND s.student_id = u.id
    WHERE e.class_id = ?
    ORDER BY u.name
");
$students_stmt->execute([$material_id, $class_id]);
$students = $students_stmt->fetchAll();

// Fetch available rosters for sync
$rosters = $pdo->prepare("SELECT id, roster_date, topic FROM daily_rosters WHERE class_id = ? ORDER BY roster_date DESC");
$rosters->execute([$class_id]);
$daily_rosters = $rosters->fetchAll();

$pageDetails = ['title' => 'Review: ' . $material['title']];
require_once '../../templates/header.php';
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2 style="margin: 0;"><?php echo htmlspecialchars($material['title']); ?></h2>
            <p style="color: #64748b; margin: 5px 0 0 0;">
                Class: <strong><?php echo htmlspecialchars($material['class_name']); ?></strong> |
                Type: <span
                    style="text-transform: uppercase; font-weight: bold; font-size: 12px;"><?php echo $material['type']; ?></span>
            </p>
        </div>
        <a href="classroom.php?class_id=<?php echo $class_id; ?>" class="btn btn-secondary">← Back to Classroom</a>
    </div>

    <?php renderFlashMessage(); ?>

    <form method="POST">
        <div class="card" style="background: #f8fafc; border: 1px solid #e2e8f0; margin-bottom: 20px;">
            <div style="display: flex; gap: 20px; align-items: center;">
                <div style="flex: 1;">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">Sync Grades to Physical
                        Session:</label>
                    <select name="roster_id" class="form-control" required>
                        <option value="">-- Select Roster Date --</option>
                        <?php foreach ($daily_rosters as $r): ?>
                            <option value="<?php echo $r['id']; ?>">
                                <?php echo date('M d, Y', strtotime($r['roster_date'])); ?> -
                                <?php echo htmlspecialchars($r['topic']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #64748b;">This will automatically update the student's marks in the selected
                        session journal.</small>
                </div>
                <button type="submit" name="save_grades" class="btn">Save & Sync All Marks</button>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Submision</th>
                    <th>Comments</th>
                    <th style="width: 150px;">Mark / Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $s): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($s['name']); ?></strong>
                            <?php if ($s['submitted_at']): ?>
                                <br><small style="color: #059669;">Submitted:
                                    <?php echo date('M d, H:i', strtotime($s['submitted_at'])); ?></small>
                            <?php else: ?>
                                <br><small style="color: #ef4444;">No submission yet</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($s['file_path']): ?>
                                <a href="<?php echo BASE_URL . $s['file_path']; ?>" target="_blank" class="btn btn-secondary"
                                    style="padding: 4px 8px; font-size: 11px;">View File</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?php echo nl2br(htmlspecialchars($s['comments'] ?? '')); ?></small>
                        </td>
                        <td>
                            <input type="number" step="0.01" name="grades[<?php echo $s['id']; ?>]"
                                value="<?php echo $s['grade']; ?>" class="form-control" placeholder="0.00"
                                style="padding: 5px;">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>