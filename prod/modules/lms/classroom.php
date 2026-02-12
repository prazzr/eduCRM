<?php
require_once '../../app/bootstrap.php';

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
$can_edit = hasRole('admin') || hasRole('branch_manager') || ($class['teacher_id'] == $_SESSION['user_id']);
$is_student = hasRole('student');

// Strict Enrollment Check for Students
if ($is_student && !hasRole('admin') && !hasRole('branch_manager')) {
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
    redirectWithAlert("classroom.php?class_id=$class_id", "Material added successfully.", 'success');
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
            $enrollment_id = $pdo->lastInsertId();

            // Send enrollment notification email
            try {
                $emailService = new \EduCRM\Services\EmailNotificationService($pdo);
                $emailService->sendEnrollmentNotification($enrollment_id);
            } catch (Exception $e) {
                error_log("Failed to send enrollment email: " . $e->getMessage());
            }

            redirectWithAlert("classroom.php?class_id=$class_id", "Student enrolled successfully! Notification sent.", 'success');
        } else {
            redirectWithAlert("classroom.php?class_id=$class_id", "Student already enrolled.", 'warning');
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
        redirectWithAlert("classroom.php?class_id=$class_id", "You have already submitted this task.", 'warning');
    } else {
        $stmt = $pdo->prepare("INSERT INTO submissions (material_id, student_id, file_path, comments) VALUES (?, ?, ?, ?)");
        $stmt->execute([$material_id, $_SESSION['user_id'], $file_path, $comments]);
        redirectWithAlert("classroom.php?class_id=$class_id", "Task submitted successfully!", 'success');
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

// Fetch All Students for Enrollment Dropdown (EXCLUDE already enrolled)
$all_students = [];
if ($can_edit) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.name, u.email 
        FROM users u 
        JOIN user_roles ur ON u.id = ur.user_id 
        JOIN roles r ON ur.role_id = r.id 
        WHERE r.name = 'student' 
        AND u.id NOT IN (
            SELECT student_id FROM enrollments WHERE class_id = ?
        )
        ORDER BY u.name
    ");
    $stmt->execute([$class_id]);
    $all_students = $stmt->fetchAll();
}

$pageDetails = ['title' => $class['course_name'] . ' - Classroom'];
require_once '../../templates/header.php';
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

            <?php if (!$is_student): ?>
                <h3>Class Roster</h3>

                <?php if ($can_edit): ?>
                    <div style="margin-bottom: 20px; position: relative;">
                        <form method="POST" id="enrollForm">
                            <input type="hidden" name="enroll_student" value="1">
                            <input type="hidden" name="student_id" id="selectedStudentId" value="">

                            <div style="position: relative;">
                                <input type="text" id="studentSearch" class="form-control"
                                    placeholder="🔍 Search student by name or email..." autocomplete="off"
                                    style="margin-bottom: 5px;">

                                <div id="studentDropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; 
                                           background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; 
                                           max-height: 250px; overflow-y: auto; z-index: 1000; 
                                           box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-secondary" id="enrollBtn" style="width: 100%; font-size: 12px;"
                                disabled>Enroll</button>
                        </form>
                    </div>

                    <script>
                        (function () {
                            const students = <?php echo json_encode($all_students); ?>;
                            const searchInput = document.getElementById('studentSearch');
                            const dropdown = document.getElementById('studentDropdown');
                            const hiddenInput = document.getElementById('selectedStudentId');
                            const enrollBtn = document.getElementById('enrollBtn');
                            let selectedIndex = -1;

                            function renderDropdown(filtered) {
                                if (filtered.length === 0) {
                                    dropdown.innerHTML = '<div style="padding: 12px; color: #64748b; text-align: center;">No students found</div>';
                                } else {
                                    dropdown.innerHTML = filtered.map((s, i) => `
                                    <div class="student-option" data-id="${s.id}" data-index="${i}"
                                        style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9;
                                               display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #6366f1, #8b5cf6); 
                                                    border-radius: 50%; display: flex; align-items: center; justify-content: center; 
                                                    color: #fff; font-weight: bold; font-size: 12px;">
                                            ${s.name.charAt(0).toUpperCase()}
                                        </div>
                                        <div>
                                            <div style="font-weight: 500; color: #1e293b;">${s.name}</div>
                                            <div style="font-size: 11px; color: #64748b;">${s.email}</div>
                                        </div>
                                    </div>
                                `).join('');
                                }
                                dropdown.style.display = 'block';
                                selectedIndex = -1;
                            }

                            function selectStudent(id, name) {
                                hiddenInput.value = id;
                                searchInput.value = name;
                                dropdown.style.display = 'none';
                                enrollBtn.disabled = false;
                                enrollBtn.style.background = '#6366f1';
                                enrollBtn.style.color = '#fff';
                            }

                            function highlightOption(index) {
                                const options = dropdown.querySelectorAll('.student-option');
                                options.forEach((opt, i) => {
                                    opt.style.background = i === index ? '#f1f5f9' : '#fff';
                                });
                            }

                            searchInput.addEventListener('input', function () {
                                const query = this.value.toLowerCase().trim();
                                hiddenInput.value = '';
                                enrollBtn.disabled = true;
                                enrollBtn.style.background = '';
                                enrollBtn.style.color = '';

                                if (query.length < 1) {
                                    dropdown.style.display = 'none';
                                    return;
                                }

                                const filtered = students.filter(s =>
                                    s.name.toLowerCase().includes(query) ||
                                    s.email.toLowerCase().includes(query)
                                ).slice(0, 10); // Limit to 10 results

                                renderDropdown(filtered);
                            });

                            searchInput.addEventListener('focus', function () {
                                if (this.value.length >= 1 && !hiddenInput.value) {
                                    const query = this.value.toLowerCase().trim();
                                    const filtered = students.filter(s =>
                                        s.name.toLowerCase().includes(query) ||
                                        s.email.toLowerCase().includes(query)
                                    ).slice(0, 10);
                                    renderDropdown(filtered);
                                }
                            });

                            searchInput.addEventListener('keydown', function (e) {
                                const options = dropdown.querySelectorAll('.student-option');
                                if (options.length === 0) return;

                                if (e.key === 'ArrowDown') {
                                    e.preventDefault();
                                    selectedIndex = Math.min(selectedIndex + 1, options.length - 1);
                                    highlightOption(selectedIndex);
                                } else if (e.key === 'ArrowUp') {
                                    e.preventDefault();
                                    selectedIndex = Math.max(selectedIndex - 1, 0);
                                    highlightOption(selectedIndex);
                                } else if (e.key === 'Enter' && selectedIndex >= 0) {
                                    e.preventDefault();
                                    const opt = options[selectedIndex];
                                    selectStudent(opt.dataset.id, opt.querySelector('div > div').textContent);
                                } else if (e.key === 'Escape') {
                                    dropdown.style.display = 'none';
                                }
                            });

                            dropdown.addEventListener('click', function (e) {
                                const option = e.target.closest('.student-option');
                                if (option) {
                                    selectStudent(option.dataset.id, option.querySelector('div > div').textContent);
                                }
                            });

                            dropdown.addEventListener('mouseover', function (e) {
                                const option = e.target.closest('.student-option');
                                if (option) {
                                    selectedIndex = parseInt(option.dataset.index);
                                    highlightOption(selectedIndex);
                                }
                            });

                            document.addEventListener('click', function (e) {
                                if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                                    dropdown.style.display = 'none';
                                }
                            });

                            document.getElementById('enrollForm').addEventListener('submit', function (e) {
                                if (!hiddenInput.value) {
                                    e.preventDefault();
                                    alert('Please select a student from the list');
                                }
                            });
                        })();
                    </script>
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
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>