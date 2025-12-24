<?php
require_once '../../config.php';
requireLogin();

$class_id = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
if (!$class_id) {
    header("Location: classes.php");
    exit;
}

// Fetch Class Details
$stmt = $pdo->prepare("SELECT c.*, co.name as course_name, u.name as teacher_name FROM classes c JOIN courses co ON c.course_id = co.id LEFT JOIN users u ON c.teacher_id = u.id WHERE c.id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch();

if (!$class)
    die("Class not found.");

// Check access (Admin, Assigned Teacher, or Enrolled Student)
$can_edit = hasRole('admin') || ($class['teacher_id'] == $_SESSION['user_id']);
$is_student = hasRole('student');

// Strict Enrollment Check for Students
if ($is_student && !hasRole('admin')) {
    $check_enrollment = $pdo->prepare("SELECT id FROM enrollments WHERE class_id = ? AND student_id = ?");
    $check_enrollment->execute([$class_id, $_SESSION['user_id']]);
    if (!$check_enrollment->fetch()) {
        die("You are not enrolled in this class.");
    }
}

// Handle "Today's Roster" Quick Link
if (isset($_GET['today_roster']) && $can_edit) {
    $today = date('Y-m-d');
    // Check if roster for today already exists
    $chk = $pdo->prepare("SELECT id FROM daily_rosters WHERE class_id = ? AND roster_date = ?");
    $chk->execute([$class_id, $today]);
    $existing = $chk->fetch();

    if ($existing) {
        header("Location: daily_roster.php?class_id=$class_id&roster_id=" . $existing['id']);
        exit;
    } else {
        // Auto-create for today
        $stmt = $pdo->prepare("INSERT INTO daily_rosters (class_id, teacher_id, roster_date, topic) VALUES (?, ?, ?, ?)");
        $stmt->execute([$class_id, $_SESSION['user_id'], $today, 'Daily Session']);
        $new_id = $pdo->lastInsertId();
        header("Location: daily_roster.php?class_id=$class_id&roster_id=$new_id");
        exit;
    }
}

// Handle Logic
$message = '';

// Delete Material
if (isset($_GET['delete_material']) && $can_edit) {
    $mid = (int) $_GET['delete_material'];
    $pdo->prepare("DELETE FROM class_materials WHERE id = ?")->execute([$mid]);
    header("Location: classroom.php?class_id=" . $class_id);
    exit;
}

// Unenroll Student
if (isset($_GET['unenroll']) && $can_edit) {
    $sid = (int) $_GET['unenroll'];
    $pdo->prepare("DELETE FROM enrollments WHERE class_id = ? AND student_id = ?")->execute([$class_id, $sid]);
    header("Location: classroom.php?class_id=" . $class_id);
    exit;
}

// 1. Add Material (Teacher/Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_material']) && $can_edit) {
    // File Upload (Simplified - in real app, check types/size)
    $file_path = '';
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $upload_dir = '../../uploads/materials/';
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);
        $file_name = time() . '_' . basename($_FILES['file']['name']);
        if (move_uploaded_file($_FILES['file']['tmp_name'], $upload_dir . $file_name)) {
            $file_path = 'uploads/materials/' . $file_name;
        }
    }

    $title = sanitize($_POST['title']);
    $desc = sanitize($_POST['description']);
    $type = $_POST['type'];
    $due_date = $_POST['due_date'] ? $_POST['due_date'] : NULL;

    $stmt = $pdo->prepare("INSERT INTO class_materials (class_id, teacher_id, title, description, file_path, type, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$class_id, $_SESSION['user_id'], $title, $desc, $file_path, $type, $due_date]);
    $message = "Material added.";
}

// 2. Enroll Student (Teacher/Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_student']) && $can_edit) {
    $student_id = $_POST['student_id'];
    // Check if valid student
    // Check if valid student (Multi-role support)
    $chk = $pdo->prepare("
        SELECT u.id 
        FROM users u 
        JOIN user_roles ur ON u.id = ur.user_id 
        JOIN roles r ON ur.role_id = r.id 
        WHERE u.id = ? AND r.name = 'student'
    ");
    $chk->execute([$student_id]);
    if ($chk->fetch()) {
        // Enforce Unique
        $exist = $pdo->prepare("SELECT id FROM enrollments WHERE class_id = ? AND student_id = ?");
        $exist->execute([$class_id, $student_id]);
        if (!$exist->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO enrollments (class_id, student_id) VALUES (?, ?)");
            $stmt->execute([$class_id, $student_id]);
            $message = "Student enrolled.";
        } else {
            $message = "Student already enrolled.";
        }
    }
}

// 3. Submit Assignment (Student)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment']) && $is_student) {
    $material_id = $_POST['material_id'];

    $file_path = '';
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == 0) {
        $upload_dir = '../../uploads/submissions/';
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);
        $file_name = time() . '_u' . $_SESSION['user_id'] . '_' . basename($_FILES['submission_file']['name']);
        if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $upload_dir . $file_name)) {
            $file_path = 'uploads/submissions/' . $file_name;
        }
    }

    $comments = sanitize($_POST['comments']);

    // Check if already submitted (Fixed logic)
    $subExist = $pdo->prepare("SELECT id FROM submissions WHERE material_id = ? AND student_id = ?");
    $subExist->execute([$material_id, $_SESSION['user_id']]);
    if ($subExist->fetch()) {
        $message = "You have already submitted this task.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO submissions (material_id, student_id, file_path, comments) VALUES (?, ?, ?, ?)");
        $stmt->execute([$material_id, $_SESSION['user_id'], $file_path, $comments]);
        $message = "Task submitted successfully!";
    }
}

// 4. Handle Create New Daily Roster
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_roster']) && $can_edit) {
    $date = $_POST['roster_date'];
    $topic = sanitize($_POST['topic']);

    $stmt = $pdo->prepare("INSERT INTO daily_rosters (class_id, teacher_id, roster_date, topic) VALUES (?, ?, ?, ?)");
    $stmt->execute([$class_id, $_SESSION['user_id'], $date, $topic]);
    $new_roster_id = $pdo->lastInsertId();

    header("Location: daily_roster.php?class_id=$class_id&roster_id=$new_roster_id");
    exit;
}


// Fetch Materials
$materials_stmt = $pdo->prepare("SELECT * FROM class_materials WHERE class_id = ? ORDER BY created_at DESC");
$materials_stmt->execute([$class_id]);
$all_materials = $materials_stmt->fetchAll();

// Fetch Daily Rosters
$rosters_stmt = $pdo->prepare("SELECT * FROM daily_rosters WHERE class_id = ? ORDER BY roster_date DESC");
$rosters_stmt->execute([$class_id]);
$all_rosters = $rosters_stmt->fetchAll();

// Fetch Enrolled Students (Roster)
$enrolled_students = $pdo->prepare("
    SELECT u.id, u.name, u.email, e.enrolled_at 
    FROM enrollments e 
    JOIN users u ON e.student_id = u.id 
    WHERE e.class_id = ?
");
$enrolled_students->execute([$class_id]);
$roster = $enrolled_students->fetchAll();

// Fetch All Students for Enrollment Dropdown
$all_students = [];
if ($can_edit) {
    $all_students = $pdo->query("
        SELECT DISTINCT u.id, u.name, u.email 
        FROM users u 
        JOIN user_roles ur ON u.id = ur.user_id 
        JOIN roles r ON ur.role_id = r.id 
        WHERE r.name = 'student' 
        ORDER BY u.name
    ")->fetchAll();
}

$pageDetails = ['title' => $class['course_name'] . ' - Classroom'];
require_once '../../includes/header.php';
?>

<div class="card">
    <div style="border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
        <h2><?php echo htmlspecialchars($class['name']); ?> <span
                style="font-weight: 400; font-size: 16px; color: #666;">(<?php echo htmlspecialchars($class['course_name']); ?>)</span>
        </h2>
        <p><strong>Teacher:</strong> <?php echo htmlspecialchars($class['teacher_name']); ?> | <strong>Status:</strong>
            <?php echo ucfirst($class['status']); ?></p>
    </div>

    <?php if ($message): ?>
        <div style="background: #e0f2fe; color: #0284c7; padding: 10px; border-radius: 6px; margin-bottom: 20px;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">

        <!-- Left Column: Streams / Materials -->
        <div>
            <h3>Class Stream</h3>

            <?php if ($can_edit): ?>
                <div class="card" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
                    <h4>Post Material / Assignment</h4>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="add_material" value="1">
                        <div class="form-group">
                            <input type="text" name="title" class="form-control" placeholder="Title" required>
                        </div>
                        <div class="form-group">
                            <textarea name="description" class="form-control"
                                placeholder="Description/Instructions"></textarea>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <input type="file" name="file" class="form-control">
                            <select name="type" class="form-control">
                                <option value="notice">Notice / Announcement</option>
                                <option value="reading">Reading Material</option>
                                <option value="assignment">Assignment</option>
                                <option value="class_task">Class Task</option>
                                <option value="home_task">Home Task</option>
                            </select>
                            <input type="date" name="due_date" class="form-control" title="Due Date">
                        </div>
                        <button type="submit" class="btn" style="margin-top: 10px;">Post</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="materials-list">
                <?php foreach ($all_materials as $m): ?>
                    <div class="card" style="border-left: 4px solid var(--primary-color);">
                        <div style="display: flex; justify-content: space-between;">
                            <h4>
                                <span
                                    style="font-size: 11px; text-transform: uppercase; background: #e2e8f0; padding: 2px 6px; border-radius: 4px;"><?php echo $m['type']; ?></span>
                                <?php echo htmlspecialchars($m['title']); ?>
                            </h4>
                            <div style="text-align: right;">
                                <small
                                    style="display: block;"><?php echo date('M d', strtotime($m['created_at'])); ?></small>
                                <?php if ($can_edit): ?>
                                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                        <?php if (in_array($m['type'], ['assignment', 'class_task', 'home_task'])): ?>
                                            <a href="review_submissions.php?material_id=<?php echo $m['id']; ?>"
                                                style="color: var(--primary-color); font-size: 11px; font-weight: bold;">Review
                                                Submissions</a>
                                        <?php endif; ?>
                                        <a href="?class_id=<?php echo $class_id; ?>&delete_material=<?php echo $m['id']; ?>"
                                            onclick="return confirm('Delete this post?')"
                                            style="color: #ef4444; font-size: 11px;">Delete</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p style="margin: 10px 0;"><?php echo nl2br(htmlspecialchars($m['description'])); ?></p>

                        <?php if ($m['file_path']): ?>
                            <p><a href="<?php echo BASE_URL . $m['file_path']; ?>" target="_blank"
                                    style="text-decoration: underline;">Download Attachment</a></p>
                        <?php endif; ?>

                        <?php if (in_array($m['type'], ['assignment', 'class_task', 'home_task'])): ?>
                            <div style="background: #f1f5f9; padding: 10px; margin-top: 10px; border-radius: 6px;">
                                <div
                                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                    <strong>Task Submission</strong>
                                    <?php if ($m['due_date']): ?>
                                        <small style="color: #64748b;">Due:
                                            <?php echo date('M d, H:i', strtotime($m['due_date'])); ?></small>
                                    <?php endif; ?>
                                </div>

                                <?php if ($is_student): ?>
                                    <!-- Check if already submitted -->
                                    <?php
                                    $subCheck = $pdo->prepare("SELECT * FROM submissions WHERE material_id = ? AND student_id = ?");
                                    $subCheck->execute([$m['id'], $_SESSION['user_id']]);
                                    $mySub = $subCheck->fetch();
                                    ?>
                                    <?php if ($mySub): ?>
                                        <div
                                            style="background: #ecfdf5; border: 1px solid #a7f3d0; padding: 10px; border-radius: 6px; margin-top: 10px;">
                                            <p style="color: #065f46; margin: 0; font-weight: bold; font-size: 13px;">
                                                ✓ Submitted on <?php echo date('M d, Y', strtotime($mySub['submitted_at'])); ?>
                                            </p>
                                            <?php if ($mySub['grade'] !== null): ?>
                                                <p style="margin: 5px 0 0 0; font-size: 14px; color: #065f46;">
                                                    <strong>Awarded Mark:</strong> <span
                                                        style="background: #059669; color: #fff; padding: 2px 8px; border-radius: 4px;"><?php echo $mySub['grade']; ?></span>
                                                </p>
                                            <?php else: ?>
                                                <p style="margin: 5px 0 0 0; font-size: 11px; color: #666; font-style: italic;">Awaiting
                                                    review by teacher...</p>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <form method="POST" enctype="multipart/form-data" style="margin-top: 10px;">
                                            <input type="hidden" name="submit_assignment" value="1">
                                            <input type="hidden" name="material_id" value="<?php echo $m['id']; ?>">
                                            <div style="margin-bottom: 8px;">
                                                <label
                                                    style="display: block; font-size: 11px; color: #64748b; margin-bottom: 3px;">Upload
                                                    Answer (Photo or PDF)</label>
                                                <input type="file" name="submission_file" class="form-control" style="font-size: 11px;"
                                                    required>
                                            </div>
                                            <input type="text" name="comments" placeholder="Add a comment..."
                                                style="width: 100%; padding: 6px; font-size: 12px; border-radius: 4px; border: 1px solid #ddd; margin-bottom: 8px;">
                                            <button type="submit" class="btn btn-secondary"
                                                style="padding: 5px 12px; font-size: 11px; width: 100%;">Submit Work</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right Column: People & Roster -->
        <div>
            <!-- Daily Roster Section -->
            <div style="margin-bottom: 30px;">
                <h3 style="display: flex; justify-content: space-between; align-items: center;">
                    Daily Roster
                    <?php if ($can_edit): ?>
                        <button onclick="document.getElementById('new-roster-form').style.display='block'" class="btn"
                            style="font-size: 10px; padding: 5px 10px;">+ New</button>
                    <?php endif; ?>
                </h3>

                <?php if ($can_edit): ?>
                    <div id="new-roster-form" class="card"
                        style="display: none; background: #fffbeb; border: 1px dashed #fbbf24; margin-bottom: 15px;">
                        <h4 style="margin-top: 0;">Start Daily Roster</h4>
                        <form method="POST">
                            <input type="hidden" name="create_roster" value="1">
                            <input type="date" name="roster_date" class="form-control" value="<?php echo date('Y-m-d'); ?>"
                                required style="margin-bottom: 10px;">
                            <input type="text" name="topic" class="form-control" placeholder="Topic for today..."
                                style="margin-bottom: 10px;">
                            <div style="display: flex; gap: 5px;">
                                <button type="submit" class="btn">Create & Entry</button>
                                <button type="button"
                                    onclick="document.getElementById('new-roster-form').style.display='none'"
                                    class="btn btn-secondary">Cancel</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <div style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($all_rosters as $r): ?>
                        <div class="card" style="padding: 10px; margin-bottom: 8px; border-left: 3px solid #fbbf24;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <strong
                                        style="font-size: 14px;"><?php echo date('M d, Y', strtotime($r['roster_date'])); ?></strong>
                                    <div style="font-size: 12px; color: #64748b;">
                                        <?php echo htmlspecialchars($r['topic'] ?: 'No Topic'); ?>
                                    </div>
                                </div>
                                <a href="daily_roster.php?class_id=<?php echo $class_id; ?>&roster_id=<?php echo $r['id']; ?>"
                                    class="btn btn-secondary" style="font-size: 10px; padding: 4px 8px;">
                                    <?php echo $can_edit ? 'Entry Data' : 'View Data'; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <h3>Class Roster</h3>

            <?php if ($can_edit): ?>
                <div style="margin-bottom: 20px;">
                    <form method="POST">
                        <input type="hidden" name="enroll_student" value="1">
                        <select name="student_id" class="form-control" style="margin-bottom: 5px;" required>
                            <option value="">Enroll Student...</option>
                            <?php foreach ($all_students as $std): ?>
                                <option value="<?php echo $std['id']; ?>"><?php echo htmlspecialchars($std['name']); ?>
                                    (<?php echo $std['email']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-secondary"
                            style="width: 100%; font-size: 12px;">Enroll</button>
                    </form>
                </div>
            <?php endif; ?>

            <ul style="list-style: none;">
                <?php foreach ($roster as $r): ?>
                    <li
                        style="padding: 10px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px;">
                        <div
                            style="width: 30px; height: 30px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #64748b;">
                            <?php echo strtoupper(substr($r['name'], 0, 1)); ?>
                        </div>
                        <div>
                            <div style="font-weight: 500; font-size: 14px;">
                                <a href="../students/enrollment_details.php?student_id=<?php echo $r['id']; ?>&class_id=<?php echo $class_id; ?>"
                                    style="color: inherit; text-decoration: none; border-bottom: 1px dotted #ccc;"
                                    title="View Performance Calendar">
                                    <?php echo htmlspecialchars($r['name']); ?>
                                </a>
                            </div>
                            <div style="font-size: 11px; color: #94a3b8;"><?php echo htmlspecialchars($r['email']); ?></div>
                        </div>
                        <?php if ($can_edit): ?>
                            <a href="?class_id=<?php echo $class_id; ?>&unenroll=<?php echo $r['id']; ?>"
                                onclick="return confirm('Remove student from class?')"
                                style="margin-left: auto; color: #ef4444; font-size: 18px; text-decoration: none;"
                                title="Unenroll">×</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>