<?php
require_once '../../app/bootstrap.php';
requireLogin();

$class_id = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
$roster_id = isset($_GET['roster_id']) ? (int) $_GET['roster_id'] : 0;

if (!$class_id || !$roster_id) {
    header("Location: classes.php");
    exit;
}

// Fetch Class & Roster details
$stmt = $pdo->prepare("SELECT c.*, co.name as course_name, dr.roster_date, dr.topic 
                       FROM classes c 
                       JOIN courses co ON c.course_id = co.id 
                       JOIN daily_rosters dr ON dr.class_id = c.id
                       WHERE c.id = ? AND dr.id = ?");
$stmt->execute([$class_id, $roster_id]);
$details = $stmt->fetch();

if (!$details)
    die("Roster not found.");

$can_edit = hasRole('admin') || ($details['teacher_id'] == $_SESSION['user_id']);

// Handle Save Performance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_performance']) && $can_edit) {
    try {
        $pdo->beginTransaction();

        $attendances = $_POST['attendance'] ?? [];
        $class_marks = $_POST['class_mark'] ?? [];
        $home_marks = $_POST['home_mark'] ?? [];
        $remarks = $_POST['remarks'] ?? [];

        foreach ($attendances as $std_id => $status) {
            $cm = (float) ($class_marks[$std_id] ?? 0);
            $hm = (float) ($home_marks[$std_id] ?? 0);
            $rem = sanitize($remarks[$std_id] ?? '');

            $stmt = $pdo->prepare("INSERT INTO daily_performance 
                (roster_id, student_id, attendance, class_task_mark, home_task_mark, remarks) 
                VALUES (?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                attendance = VALUES(attendance), 
                class_task_mark = VALUES(class_task_mark), 
                home_task_mark = VALUES(home_task_mark), 
                remarks = VALUES(remarks)");
            $stmt->execute([$roster_id, $std_id, $status, $cm, $hm, $rem]);
        }

        $pdo->commit();
        redirectWithAlert("daily_roster.php?class_id=$class_id&roster_id=$roster_id", "Performance data saved successfully.", 'success');
    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        redirectWithAlert("daily_roster.php?class_id=$class_id&roster_id=$roster_id", "Error saving: " . $e->getMessage(), 'error');
    }
}

// Fetch Students & existing performance
// Fetch Students & existing performance
$sql = "
    SELECT u.id, u.name, dp.attendance, dp.class_task_mark, dp.home_task_mark, dp.remarks
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    LEFT JOIN daily_performance dp ON dp.student_id = u.id AND dp.roster_id = ?
    WHERE e.class_id = ?
";
$params = [$roster_id, $class_id];

// Privacy Filter: Students see ONLY themselves
if (!$can_edit && hasRole('student')) {
    $sql .= " AND u.id = ?";
    $params[] = $_SESSION['user_id'];
}

$sql .= " ORDER BY u.name";

$students_stmt = $pdo->prepare($sql);
$students_stmt->execute($params);
$students = $students_stmt->fetchAll();

$pageDetails = ['title' => 'Daily Roster - ' . date('M d', strtotime($details['roster_date']))];
require_once '../../templates/header.php';
?>

<div class="card">
    <div
        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
        <div>
            <h2 style="margin:0;"><?php echo htmlspecialchars($details['name']); ?> <span
                    style="font-weight:400; font-size:16px;">(<?php echo htmlspecialchars($details['course_name']); ?>)</span>
            </h2>
            <p style="color: #64748b; margin: 5px 0 0 0;">
                <strong>Date:</strong> <?php echo date('l, F d, Y', strtotime($details['roster_date'])); ?>
                <?php if ($details['topic']): ?> | <strong>Topic:</strong>
                    <?php echo htmlspecialchars($details['topic']); ?><?php endif; ?>
            </p>
        </div>
        <a href="classroom.php?class_id=<?php echo $class_id; ?>" class="btn btn-secondary">← Back to Class</a>
    </div>

    <?php if (isset($message)): ?>
        <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="save_performance" value="1">

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc; text-align: left;">
                        <th style="padding: 12px; border-bottom: 2px solid #e2e8f0;">Student Name</th>
                        <th style="padding: 12px; border-bottom: 2px solid #e2e8f0; width: 150px;">
                            Attendance
                            <?php if ($can_edit): ?>
                                <button type="button" onclick="markAllPresent()"
                                    style="display: block; font-size: 9px; padding: 2px 4px; border: 1px solid #ccc; background: #fff; margin-top: 5px; cursor: pointer; border-radius: 3px;">Mark
                                    All Present</button>
                            <?php endif; ?>
                        </th>
                        <th style="padding: 12px; border-bottom: 2px solid #e2e8f0; width: 120px;">Class Task</th>
                        <th style="padding: 12px; border-bottom: 2px solid #e2e8f0; width: 120px;">Home Task</th>
                        <th style="padding: 12px; border-bottom: 2px solid #e2e8f0;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($students) > 0): ?>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                    <strong><?php echo htmlspecialchars($s['name']); ?></strong>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                    <?php if ($can_edit): ?>
                                        <select name="attendance[<?php echo $s['id']; ?>]" class="form-control"
                                            style="font-size: 13px;">
                                            <option value="present" <?php echo ($s['attendance'] == 'present') ? 'selected' : ''; ?>>
                                                Present</option>
                                            <option value="absent" <?php echo ($s['attendance'] == 'absent') ? 'selected' : ''; ?>>
                                                Absent</option>
                                            <option value="late" <?php echo ($s['attendance'] == 'late') ? 'selected' : ''; ?>>Late
                                            </option>
                                        </select>
                                    <?php else: ?>
                                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; background: <?php echo match ($s['attendance']) {
                                            'present' => '#dcfce7; color: #166534',
                                            'absent' => '#fee2e2; color: #991b1b',
                                            'late' => '#fef3c7; color: #92400e',
                                            default => '#f1f5f9; color: #64748b'
                                        }; ?>">
                                            <?php echo ucfirst($s['attendance'] ?: 'Pending'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                    <?php if ($can_edit): ?>
                                        <input type="number" step="0.5" name="class_mark[<?php echo $s['id']; ?>]"
                                            class="form-control" placeholder="Marks"
                                            value="<?php echo htmlspecialchars($s['class_task_mark'] ?? ''); ?>"
                                            style="font-size: 13px;">
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($s['class_task_mark'] ?? '-'); ?>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                    <?php if ($can_edit): ?>
                                        <input type="number" step="0.5" name="home_mark[<?php echo $s['id']; ?>]"
                                            class="form-control" placeholder="Marks"
                                            value="<?php echo htmlspecialchars($s['home_task_mark'] ?? ''); ?>"
                                            style="font-size: 13px;">
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($s['home_task_mark'] ?? '-'); ?>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                    <?php if ($can_edit): ?>
                                        <input type="text" name="remarks[<?php echo $s['id']; ?>]" class="form-control"
                                            placeholder="Opt. notes..." value="<?php echo htmlspecialchars($s['remarks'] ?? ''); ?>"
                                            style="font-size: 13px;">
                                    <?php else: ?>
                                        <span
                                            style="color: #64748b; font-size: 13px; font-style: italic;"><?php echo htmlspecialchars($s['remarks'] ?? ''); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="padding:20px; text-align:center; color:#64748b;">No students enrolled in
                                this class.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($can_edit && count($students) > 0): ?>
            <div style="margin-top: 20px; text-align: right;">
                <button type="submit" class="btn">Save Daily Performance</button>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>

<script>
    function markAllPresent() {
        const selects = document.querySelectorAll('select[name^="attendance"]');
        selects.forEach(s => {
            s.value = 'present';
        });
    }
</script>