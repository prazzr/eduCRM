<?php
require_once '../../config.php';
requireLogin();

$student_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$student_id)
    die("Invalid ID");

$message = '';
$msg_type = 'success'; // or 'error'

// Fetch Student Info - Robust multi-role support
$stmt = $pdo->prepare("
    SELECT u.* 
    FROM users u 
    JOIN user_roles ur ON u.id = ur.user_id 
    JOIN roles r ON ur.role_id = r.id 
    WHERE u.id = ? AND r.name = 'student'
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student)
    die("Student not found.");

// Security: Student can only view their own profile
if (hasRole('student') && !hasRole('admin') && !hasRole('counselor') && $student_id != $_SESSION['user_id']) {
    die("Unauthorized access.");
}

// Fetch Documents
$docs = $pdo->prepare("SELECT * FROM student_documents WHERE student_id = ? ORDER BY uploaded_at DESC");
$docs->execute([$student_id]);
$my_docs = $docs->fetchAll();

// Fetch Ledger Data
$fees_stmt = $pdo->prepare("SELECT sf.*, ft.name as fee_type FROM student_fees sf LEFT JOIN fee_types ft ON sf.fee_type_id = ft.id WHERE sf.student_id = ? ORDER BY sf.created_at DESC");
$fees_stmt->execute([$student_id]);
$all_fees = $fees_stmt->fetchAll();

$fee_types = $pdo->query("SELECT * FROM fee_types")->fetchAll();

// Fetch Cumulative Performance Summary
$perf_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN attendance = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN attendance = 'late' THEN 1 ELSE 0 END) as late_days,
        AVG(class_task_mark) as avg_class_mark,
        AVG(home_task_mark) as avg_home_mark
    FROM daily_performance 
    WHERE student_id = ?
");
$perf_stmt->execute([$student_id]);
$perf_summary = $perf_stmt->fetch();

$attendance_pct = 0;
if ($perf_summary['total_days'] > 0) {
    $attendance_pct = (($perf_summary['present_days'] + ($perf_summary['late_days'] * 0.5)) / $perf_summary['total_days']) * 100;
}

// Fetch Detailed Daily Performance Logs
$logs_stmt = $pdo->prepare("
    SELECT dp.*, dr.roster_date, dr.topic, c.name as class_name
    FROM daily_performance dp
    JOIN daily_rosters dr ON dp.roster_id = dr.id
    JOIN classes c ON dr.class_id = c.id
    WHERE dp.student_id = ?
    ORDER BY dr.roster_date DESC
");
$logs_stmt->execute([$student_id]);
$daily_logs = $logs_stmt->fetchAll();

// Fetch Visa Workflow
$visa_stmt = $pdo->prepare("SELECT * FROM visa_workflows WHERE student_id = ?");
$visa_stmt->execute([$student_id]);
$visa_workflow = $visa_stmt->fetch();

// Success Message from GET
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'enrolled')
        $message = "Student successfully enrolled in the batch.";
    if ($_GET['msg'] == 'score_added')
        $message = "Test score recorded successfully.";
    if ($_GET['msg'] == 'fee_added')
        $message = "New fee assigned to student.";
    if ($_GET['msg'] == 'payment_added')
        $message = "Payment recorded and ledger updated.";
    if ($_GET['msg'] == 'doc_uploaded')
        $message = "Document successfully stored in vault.";
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// Handle New Log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_log'])) {
    $type = $_POST['type'];
    $msg = sanitize($_POST['message']);

    $stmt = $pdo->prepare("INSERT INTO student_logs (student_id, author_id, type, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$student_id, $_SESSION['user_id'], $type, $msg]);
    header("Location: profile.php?id=" . $student_id);
    exit;
}

// Handle Delete Log
if (isset($_GET['delete_log']) && !hasRole('student')) {
    $log_id = (int) $_GET['delete_log'];
    $pdo->prepare("DELETE FROM student_logs WHERE id = ?")->execute([$log_id]);
    header("Location: profile.php?id=" . $student_id);
    exit;
}

// Handle Add Score
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_score']) && !hasRole('student')) {
    try {
        $type = $_POST['test_type'];
        $overall = $_POST['overall'];
        $l = $_POST['listening'] ?: 0;
        $r = $_POST['reading'] ?: 0;
        $w = $_POST['writing'] ?: 0;
        $s = $_POST['speaking'] ?: 0;

        $stmt = $pdo->prepare("INSERT INTO test_scores (student_id, test_type, overall_score, listening, reading, writing, speaking) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $type, $overall, $l, $r, $w, $s]);
        header("Location: profile.php?id=" . $student_id . "&msg=score_added");
        exit;
    } catch (PDOException $e) {
        $message = "Score Error: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// Handle Enroll Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_class']) && !hasRole('student')) {
    try {
        $class_id = (int) $_POST['class_id'];
        if (!$class_id)
            throw new Exception("Please select a class.");

        // Check if already enrolled
        $exist = $pdo->prepare("SELECT id FROM enrollments WHERE class_id = ? AND student_id = ?");
        $exist->execute([$class_id, $student_id]);
        if ($exist->fetch()) {
            throw new Exception("Student is already enrolled in this class.");
        }

        $pdo->prepare("INSERT INTO enrollments (class_id, student_id) VALUES (?, ?)")->execute([$class_id, $student_id]);
        header("Location: profile.php?id=" . $student_id . "&msg=enrolled");
        exit;
    } catch (Exception $e) {
        $message = "Enrollment Error: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// Handle Unenroll
if (isset($_GET['unenroll']) && !hasRole('student')) {
    $cid = (int) $_GET['unenroll'];
    $pdo->prepare("DELETE FROM enrollments WHERE class_id = ? AND student_id = ?")->execute([$cid, $student_id]);
    header("Location: profile.php?id=" . $student_id);
    exit;
}

// Handle Delete Score
if (isset($_GET['delete_score']) && !hasRole('student')) {
    $sid = (int) $_GET['delete_score'];
    $pdo->prepare("DELETE FROM test_scores WHERE id = ? AND student_id = ?")->execute([$sid, $student_id]);
    header("Location: profile.php?id=$student_id&tab=classes");
    exit;
}

// --- NEW CONSOLIDATED LOGIC ---

// 1. Handle Fee Assignment (Ledger)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_fee']) && hasRole('admin')) {
    try {
        $ft_id = $_POST['fee_type_id'];
        $amt = (float) $_POST['amount'];
        $desc = sanitize($_POST['description']);
        $due = $_POST['due_date'];
        $pdo->prepare("INSERT INTO student_fees (student_id, fee_type_id, description, amount, due_date, status) VALUES (?, ?, ?, ?, ?, 'unpaid')")
            ->execute([$student_id, $ft_id, $desc, $amt, $due]);
        header("Location: profile.php?id=$student_id&tab=ledger&msg=fee_added");
        exit;
    } catch (Exception $e) {
        $message = "Fee Error: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// 2. Handle Payment Recording (Ledger)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment']) && hasRole('admin')) {
    try {
        $fee_id = $_POST['fee_id'];
        $amt = (float) $_POST['amount'];
        $method = $_POST['method'];
        $remarks = sanitize($_POST['remarks']);

        // Validation logic
        $fee_info = $pdo->prepare("SELECT amount FROM student_fees WHERE id = ?");
        $fee_info->execute([$fee_id]);
        $total_fee = $fee_info->fetchColumn();

        $paid_stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE student_fee_id = ?");
        $paid_stmt->execute([$fee_id]);
        $already_paid = $paid_stmt->fetchColumn() ?: 0;

        if ($amt > ($total_fee - $already_paid))
            throw new Exception("Payment exceeds balance.");

        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO payments (student_fee_id, amount, payment_method, remarks) VALUES (?, ?, ?, ?)")->execute([$fee_id, $amt, $method, $remarks]);
        $new_paid = $already_paid + $amt;
        $status = ($new_paid >= $total_fee) ? 'paid' : 'partial';
        $pdo->prepare("UPDATE student_fees SET status = ? WHERE id = ?")->execute([$status, $fee_id]);
        $pdo->commit();
        header("Location: profile.php?id=$student_id&tab=ledger&msg=payment_added");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Payment Error: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// 3. Handle Document Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['doc'])) {
    try {
        $title = sanitize($_POST['doc_title']);
        if ($_FILES['doc']['error'] !== 0)
            throw new Exception("Upload error.");

        $ext = strtolower(pathinfo($_FILES['doc']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'docx']))
            throw new Exception("Invalid file type.");

        if (!is_dir(SECURE_UPLOAD_DIR)) {
            mkdir(SECURE_UPLOAD_DIR, 0777, true);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['doc']['tmp_name']);

        $allowed_mimes = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'
        ];

        if (!array_key_exists($mime, $allowed_mimes)) {
            throw new Exception("Invalid file content (MIME mismatch).");
        }

        $fname = time() . '_' . basename($_FILES['doc']['name']);
        $target_file = SECURE_UPLOAD_DIR . $fname;

        if (move_uploaded_file($_FILES['doc']['tmp_name'], $target_file)) {
            // Auto-Resize if Image
            if (in_array($mime, ['image/jpeg', 'image/png'])) {
                list($width, $height) = getimagesize($target_file);
                if ($width > 1200) {
                    $new_width = 1200;
                    $new_height = ($height / $width) * 1200;
                    $thumb = imagecreatetruecolor($new_width, $new_height);
                    if ($mime == 'image/jpeg')
                        $source = imagecreatefromjpeg($target_file);
                    else
                        $source = imagecreatefrompng($target_file);

                    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

                    if ($mime == 'image/jpeg')
                        imagejpeg($thumb, $target_file, 85);
                    else
                        imagepng($thumb, $target_file, 8);

                    imagedestroy($thumb);
                    imagedestroy($source);
                }
            }

            // 1. Centralized Attachment
            $stmtAtt = $pdo->prepare("INSERT INTO attachments (user_id, file_path, file_name, file_mime, file_size) VALUES (?, ?, ?, ?, ?)");
            $stmtAtt->execute([$_SESSION['user_id'], 'secure_uploads/' . $fname, $fname, $mime, filesize($target_file)]);
            $attachment_id = $pdo->lastInsertId();

            // 2. Link to Student Documents (Legacy Table Wrapper)
            // Note: We use the proxy link now instead of direct path
            $proxy_link = "download.php?id=" . $attachment_id;
            $pdo->prepare("INSERT INTO student_documents (student_id, title, file_path) VALUES (?, ?, ?)")->execute([$student_id, $title, $proxy_link]);

            logAction('document_upload', "User " . $_SESSION['user_id'] . " uploaded $fname for Student $student_id");
            header("Location: profile.php?id=$student_id&tab=vault&msg=doc_uploaded");
            exit;
        }
    } catch (Exception $e) {
        $message = "Vault Error: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// 4. Handle Delete Fee / Doc
if (isset($_GET['delete_fee']) && hasRole('admin')) {
    $pdo->prepare("DELETE FROM student_fees WHERE id = ? AND student_id = ? AND status='unpaid'")->execute([$_GET['delete_fee'], $student_id]);
    header("Location: profile.php?id=$student_id&tab=ledger");
    exit;
}
if (isset($_GET['delete_doc']) && !hasRole('student')) {
    $pdo->prepare("DELETE FROM student_documents WHERE id = ? AND student_id = ?")->execute([$_GET['delete_doc'], $student_id]);
    header("Location: profile.php?id=$student_id&tab=vault");
    exit;
}

// Fetch Test Scores
$stmt_scores = $pdo->prepare("SELECT * FROM test_scores WHERE student_id = ? ORDER BY created_at DESC");
$stmt_scores->execute([$student_id]);
$my_scores = $stmt_scores->fetchAll();

// Fetch Logs
$logs = $pdo->prepare("
    SELECT sl.*, u.name as author_name 
    FROM student_logs sl 
    JOIN users u ON sl.author_id = u.id 
    WHERE sl.student_id = ? 
    ORDER BY sl.created_at DESC
");
$logs->execute([$student_id]);
$timelines = $logs->fetchAll();

// Fetch Available Classes (for enrollment)
$all_classes = $pdo->query("
    SELECT c.id, c.name as class_name, co.name as course_name 
    FROM classes c 
    JOIN courses co ON c.course_id = co.id 
    WHERE c.status = 'active' 
    ORDER BY co.name, c.name
")->fetchAll();

// Fetch Enrollment Info (Classes) with individual performance stats
$classes = $pdo->prepare("
    SELECT 
        c.id as class_id, 
        c.name as class_name, 
        co.name as course_name, 
        e.enrolled_at,
        COUNT(dp.id) as total_days,
        SUM(CASE WHEN dp.attendance = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN dp.attendance = 'late' THEN 1 ELSE 0 END) as late_days,
        AVG(dp.class_task_mark) as avg_class_mark,
        AVG(dp.home_task_mark) as avg_home_mark
    FROM enrollments e 
    JOIN classes c ON e.class_id = c.id 
    JOIN courses co ON c.course_id = co.id
    LEFT JOIN daily_rosters dr ON dr.class_id = c.id
    LEFT JOIN daily_performance dp ON dp.roster_id = dr.id AND dp.student_id = e.student_id
    WHERE e.student_id = ?
    GROUP BY c.id, co.name, e.enrolled_at
    ORDER BY e.enrolled_at DESC
");
$classes->execute([$student_id]);
$my_classes = $classes->fetchAll();

// Fetch Documents
$docs = $pdo->prepare("SELECT * FROM student_documents WHERE student_id = ?");
$docs->execute([$student_id]);
$my_docs = $docs->fetchAll();

$pageDetails = ['title' => 'Student Profile'];
require_once '../../includes/header.php';
?>

<?php if ($message): ?>
    <div
        style="background: <?php echo $msg_type === 'error' ? '#fee2e2' : '#dcfce7'; ?>; 
                color: <?php echo $msg_type === 'error' ? '#991b1b' : '#166534'; ?>; 
                padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid <?php echo $msg_type === 'error' ? '#fecaca' : '#bbf7d0'; ?>;">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<!-- Tab Navigation -->
<div
    style="display: flex; gap: 5px; margin-bottom: 25px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; overflow-x: auto;">
    <?php
    $tabs = [
        'profile' => '👤 Profile',
        'ledger' => '💰 Ledger',
        'vault' => '📁 Vault',
        'classes' => '🎓 Classes',
        'visa' => '✈️ Visa',
        'logs' => '📝 Logs'
    ];
    foreach ($tabs as $t_key => $t_name):
        $active = ($tab === $t_key);
        ?>
        <a href="?id=<?php echo $student_id; ?>&tab=<?php echo $t_key; ?>" style="text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 14px;
                  background: <?php echo $active ? 'var(--primary-color)' : 'transparent'; ?>;
                  color: <?php echo $active ? '#fff' : '#64748b'; ?>;
                  transition: all 0.2s;">
            <?php echo $t_name; ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Tab Content -->
<?php if ($tab === 'profile'): ?>
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Primary Information</h3>
            <a href="edit.php?id=<?php echo $student_id; ?>" class="btn btn-secondary"
                style="font-size: 11px; padding: 5px 12px;">Edit Profile</a>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($student['name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($student['phone']); ?></p>
            </div>
            <div>
                <p><strong>Country:</strong> <?php echo htmlspecialchars($student['country'] ?: 'N/A'); ?></p>
                <p><strong>Level:</strong> <?php echo htmlspecialchars($student['education_level']); ?></p>
                <p><strong>Passport:</strong> <?php echo htmlspecialchars($student['passport_number'] ?: 'N/A'); ?></p>
            </div>
        </div>

        <?php if (!hasRole('student')): ?>
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; display: flex; gap: 10px;">
                <a href="../users/reset_password.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">Direct Password
                    Reset</a>
                <a href="../users/send_reset_email.php?id=<?php echo $student_id; ?>" class="btn btn-secondary"
                    style="background: #e0f2fe; color: #0369a1; border-color: #7dd3fc;">Email Reset Link</a>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'ledger'): ?>
    <?php if (hasRole('admin')): ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
            <div class="card" style="margin-bottom:0; background: #f8fafc; border: 1px dashed #cbd5e1;">
                <h4>Assign New Fee</h4>
                <form method="POST">
                    <input type="hidden" name="assign_fee" value="1">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom:10px;">
                        <select name="fee_type_id" class="form-control" required>
                            <?php foreach ($fee_types as $ft): ?>
                                <option value="<?php echo $ft['id']; ?>"><?php echo $ft['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="Amount" required>
                    </div>
                    <input type="text" name="description" class="form-control" placeholder="Extra Note"
                        style="margin-bottom:10px;">
                    <input type="date" name="due_date" class="form-control" style="margin-bottom:10px;">
                    <button type="submit" class="btn">Assign Fee</button>
                </form>
            </div>
            <div class="card" style="margin-bottom:0; background: #f0fdf4; border: 1px dashed #86efac;">
                <h4>Record Payment</h4>
                <form method="POST">
                    <input type="hidden" name="record_payment" value="1">
                    <select name="fee_id" class="form-control" style="margin-bottom:10px;" required>
                        <option value="">Select Unpaid Invoice...</option>
                        <?php foreach ($all_fees as $f):
                            if ($f['status'] != 'paid'): ?>
                                <option value="<?php echo $f['id']; ?>">#<?php echo $f['id']; ?> - <?php echo $f['fee_type']; ?>
                                    ($<?php echo $f['amount']; ?>)</option>
                            <?php endif; endforeach; ?>
                    </select>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom:10px;">
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="Paid Amt" required>
                        <select name="method" class="form-control">
                            <option value="Cash">Cash</option>
                            <option value="Bank">Bank</option>
                        </select>
                    </div>
                    <button type="submit" class="btn" style="background: #16a34a;">Submit Payment</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3>Financial History</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Item</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_fees as $f): ?>
                    <tr>
                        <td><?php echo date('Y-m-d', strtotime($f['created_at'])); ?></td>
                        <td><strong><?php echo $f['fee_type']; ?></strong><br><small><?php echo htmlspecialchars($f['description']); ?></small>
                        </td>
                        <td>$<?php echo number_format($f['amount'], 2); ?></td>
                        <td>
                            <span class="status-badge"
                                style="background: <?php echo $f['status'] == 'paid' ? '#dcfce7' : '#fee2e2'; ?>;">
                                <?php echo ucfirst($f['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($f['status'] == 'unpaid' && hasRole('admin')): ?>
                                <a href="?id=<?php echo $student_id; ?>&tab=ledger&delete_fee=<?php echo $f['id']; ?>"
                                    style="color:red; font-size:12px;" onclick="return confirm('Delete?')">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($tab === 'vault'): ?>
    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
        <div class="card" style="background: #eff6ff; border: 1px dashed #bfdbfe;">
            <h4>Upload New File</h4>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="upload_doc" value="1">
                <div class="form-group">
                    <label>File Title</label>
                    <input type="text" name="doc_title" class="form-control" placeholder="e.g. Passport" required>
                </div>
                <div class="form-group">
                    <label>Choose File</label>
                    <input type="file" name="doc" class="form-control" required>
                </div>
                <button type="submit" class="btn">Upload to Vault</button>
            </form>
        </div>
        <div class="card">
            <h3>Stored Documents</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 15px;">
                <?php foreach ($my_docs as $d): ?>
                    <div style="border:1px solid #e2e8f0; padding:10px; border-radius:8px; text-align:center;">
                        <div style="font-size:30px;">📄</div>
                        <div style="font-size:12px; font-weight:bold; margin:5px 0;">
                            <?php echo htmlspecialchars($d['title']); ?>
                        </div>
                        <div style="display:flex; justify-content:center; gap:10px; margin-top:5px;">
                            <a href="<?php echo BASE_URL . $d['file_path']; ?>" target="_blank" style="font-size:11px;">View</a>
                            <?php if (!hasRole('student')): ?>
                                <a href="?id=<?php echo $student_id; ?>&tab=vault&delete_doc=<?php echo $d['id']; ?>"
                                    style="color:red; font-size:11px;" onclick="return confirm('Delete?')">Del</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'classes'): ?>
    <!-- Global Performance Summary (All Classes) -->
    <h3 style="margin: 0 0 15px 0; color: #64748b; font-size: 16px;">Overall Performance (All Classes)</h3>
    <div class="card"
        style="background: #f8fafc; margin-bottom: 20px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; text-align: center;">
        <div style="border-right: 1px solid #e2e8f0;">
            <div style="font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Attendance
            </div>
            <div style="font-size: 24px; font-weight: bold; color: var(--primary-color);">
                <?php echo round($attendance_pct, 1); ?>%
            </div>
            <div style="font-size: 11px; color: #94a3b8;">
                <?php echo $perf_summary['present_days']; ?>/<?php echo $perf_summary['total_days']; ?> Days
            </div>
        </div>
        <div style="border-right: 1px solid #e2e8f0;">
            <div style="font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Avg Class Task
            </div>
            <div style="font-size: 24px; font-weight: bold; color: #0f172a;">
                <?php echo round($perf_summary['avg_class_mark'] ?: 0, 1); ?>
            </div>
            <div style="font-size: 11px; color: #94a3b8;">Rating Score</div>
        </div>
        <div>
            <div style="font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Avg Home Task
            </div>
            <div style="font-size: 24px; font-weight: bold; color: #0f172a;">
                <?php echo round($perf_summary['avg_home_mark'] ?: 0, 1); ?>
            </div>
            <div style="font-size: 11px; color: #94a3b8;">Rating Score</div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div>
            <div class="card">
                <h4>Enroll in Batch</h4>
                <form method="POST">
                    <input type="hidden" name="enroll_class" value="1">
                    <select name="class_id" class="form-control" style="margin-bottom:10px;">
                        <option value="">Select Batch...</option>
                        <?php foreach ($all_classes as $ac): ?>
                            <option value="<?php echo $ac['id']; ?>">
                                <?php echo htmlspecialchars($ac['course_name'] . ' - ' . $ac['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn">Enroll Student</button>
                </form>
            </div>
            <div class="card">
                <h4>Active Batches</h4>
                <?php if (count($my_classes) > 0): ?>
                    <?php foreach ($my_classes as $mc):
                        $class_attn = 0;
                        if ($mc['total_days'] > 0) {
                            $class_attn = (($mc['present_days'] + ($mc['late_days'] * 0.5)) / $mc['total_days']) * 100;
                        }
                        ?>
                        <div
                            style="margin-bottom:12px; padding:12px; background:#f8fafc; border: 1px solid #e2e8f0; border-radius:8px;">
                            <div style="display:flex; justify-content:space-between; align-items: flex-start; margin-bottom: 8px;">
                                <span>
                                    <strong
                                        style="color: #64748b; font-size: 11px; text-transform: uppercase;"><?php echo htmlspecialchars($mc['course_name']); ?></strong><br>
                                    <a href="enrollment_details.php?student_id=<?php echo $student_id; ?>&class_id=<?php echo $mc['class_id']; ?>"
                                        style="color: var(--primary-color); text-decoration: none; font-weight: bold; font-size: 15px;">
                                        <?php echo htmlspecialchars($mc['class_name']); ?>
                                    </a>
                                </span>
                                <a href="?id=<?php echo $student_id; ?>&tab=classes&unenroll=<?php echo $mc['class_id']; ?>"
                                    style="color:#cbd5e1; text-decoration:none; font-size: 20px; line-height: 1;"
                                    onclick="return confirm('Remove student from this class?')">×</a>
                            </div>

                            <div
                                style="display: flex; gap: 15px; font-size: 12px; color: #475569; background: #fff; padding: 8px; border-radius: 6px;">
                                <div><span style="color: #94a3b8;">Attn:</span> <strong
                                        style="color: <?php echo $class_attn >= 75 ? '#16a34a' : '#ef4444'; ?>;"><?php echo round($class_attn, 1); ?>%</strong>
                                </div>
                                <div><span style="color: #94a3b8;">Class:</span>
                                    <strong><?php echo $mc['avg_class_mark'] ? round($mc['avg_class_mark'], 1) : '-'; ?></strong>
                                </div>
                                <div><span style="color: #94a3b8;">Home:</span>
                                    <strong><?php echo $mc['avg_home_mark'] ? round($mc['avg_home_mark'], 1) : '-'; ?></strong>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #666; font-size: 13px;">No active classes.</p>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <div class="card">
                <h4>Record Test Score</h4>
                <form method="POST">
                    <input type="hidden" name="add_score" value="1">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom:10px;">
                        <select name="test_type" class="form-control">
                            <option value="IELTS">IELTS</option>
                            <option value="PTE">PTE</option>
                            <option value="SAT">SAT</option>
                        </select>
                        <input type="number" step="0.1" name="overall" class="form-control" placeholder="Overall Score"
                            required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px; margin-bottom: 10px;">
                        <input type="number" step="0.1" name="listening" class="form-control" placeholder="L">
                        <input type="number" step="0.1" name="reading" class="form-control" placeholder="R">
                        <input type="number" step="0.1" name="writing" class="form-control" placeholder="W">
                        <input type="number" step="0.1" name="speaking" class="form-control" placeholder="S">
                    </div>
                    <button type="submit" class="btn">Save Score</button>
                </form>
            </div>
            <div class="card">
                <h4>Score History</h4>
                <?php if (count($my_scores) > 0): ?>
                    <?php foreach ($my_scores as $ms): ?>
                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid #eee; padding:5px 0;">
                            <span>
                                <strong><?php echo htmlspecialchars($ms['test_type']); ?>:
                                    <?php echo htmlspecialchars($ms['overall_score']); ?></strong>
                                <br><small
                                    style="color: #666;"><?php echo $ms['listening'] . '/' . $ms['reading'] . '/' . $ms['writing'] . '/' . $ms['speaking']; ?></small>
                            </span>
                            <a href="?id=<?php echo $student_id; ?>&tab=classes&delete_score=<?php echo $ms['id']; ?>"
                                style="color:red;" onclick="return confirm('Delete score?')">×</a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #666; font-size: 13px;">No scores recorded.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Daily Performance Log Section -->
    <div class="card" style="margin-top: 25px;">
        <h3>Daily Performance Log</h3>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #f8fafc; text-align: left;">
                        <th style="padding: 10px; border-bottom: 2px solid #e2e8f0;">Date</th>
                        <th style="padding: 10px; border-bottom: 2px solid #e2e8f0;">Class / Topic</th>
                        <th style="padding: 10px; border-bottom: 2px solid #e2e8f0; text-align: center;">Attendance</th>
                        <th style="padding: 10px; border-bottom: 2px solid #e2e8f0; text-align: center;">Class Task</th>
                        <th style="padding: 10px; border-bottom: 2px solid #e2e8f0; text-align: center;">Home Task</th>
                        <th style="padding: 10px; border-bottom: 2px solid #e2e8f0;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($daily_logs) > 0): ?>
                        <?php foreach ($daily_logs as $log): ?>
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0; white-space: nowrap;">
                                    <?php echo date('M d, Y', strtotime($log['roster_date'])); ?>
                                </td>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">
                                    <strong><?php echo htmlspecialchars($log['class_name']); ?></strong><br>
                                    <small
                                        style="color:#64748b; font-size: 11px;"><?php echo htmlspecialchars($log['topic'] ?: 'No topic'); ?></small>
                                </td>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: center;">
                                    <?php
                                    $att_colors = ['present' => '#dcfce7', 'absent' => '#fee2e2', 'late' => '#fef3c7'];
                                    $bg = $att_colors[$log['attendance']] ?? '#f1f5f9';
                                    ?>
                                    <span
                                        style="background: <?php echo $bg; ?>; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; text-transform: uppercase;">
                                        <?php echo $log['attendance']; ?>
                                    </span>
                                </td>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: center; font-weight: bold;">
                                    <?php echo $log['class_task_mark']; ?>
                                </td>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: center; font-weight: bold;">
                                    <?php echo $log['home_task_mark']; ?>
                                </td>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0; font-size: 12px; color: #64748b;">
                                    <?php echo htmlspecialchars($log['remarks']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="padding:20px; text-align:center; color:#666;">No daily performance records
                                found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tab === 'visa'): ?>
    <div style="max-width: 800px; margin: 0 auto;">
        <div class="card" style="border-left: 5px solid var(--primary-color);">
            <h3 style="margin-top:0;">Visa Application Progress</h3>
            <?php if ($visa_workflow): ?>
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px; margin-top: 20px;">
                    <div>
                        <div style="margin-bottom: 20px;">
                            <label
                                style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: bold;">Destination
                                Country</label>
                            <div style="font-size: 20px; font-weight: bold; color: #0f172a;">
                                <?php echo htmlspecialchars($visa_workflow['country']); ?>
                            </div>
                        </div>
                        <div>
                            <label
                                style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: bold;">Current
                                Stage</label>
                            <div style="margin-top: 5px;">
                                <span class="status-badge"
                                    style="background: var(--primary-color); color: #fff; font-size: 16px; padding: 5px 15px;">
                                    <?php echo htmlspecialchars($visa_workflow['current_stage']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: bold;">Counselor
                            Notes & Checklist</label>
                        <div
                            style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 5px; min-height: 100px; white-space: pre-wrap;">
                            <?php echo $visa_workflow['notes'] ?: 'No notes provided yet.'; ?>
                        </div>
                    </div>
                </div>

                <!-- Progress Tracker Visual -->
                <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee;">
                    <div style="display: flex; justify-content: space-between; position: relative;">
                        <?php
                        $stages = ['Doc Collection', 'Submission', 'Interview', 'Approved'];
                        $current_idx = array_search($visa_workflow['current_stage'], $stages);
                        if ($visa_workflow['current_stage'] == 'Rejected')
                            $current_idx = -1;

                        foreach ($stages as $idx => $s):
                            $is_done = ($current_idx !== false && $idx <= $current_idx);
                            $is_active = ($current_idx !== false && $idx == $current_idx);
                            ?>
                            <div style="flex: 1; text-align: center; position: relative; z-index: 1;">
                                <div
                                    style="width: 30px; height: 30px; background: <?php echo $is_done ? 'var(--primary-color)' : '#e2e8f0'; ?>; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-weight: bold;">
                                    <?php echo $is_done ? '✓' : ($idx + 1); ?>
                                </div>
                                <div
                                    style="font-size: 12px; font-weight: <?php echo $is_active ? 'bold' : 'normal'; ?>; color: <?php echo $is_active ? '#0f172a' : '#64748b'; ?>;">
                                    <?php echo $s; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <!-- Connecting Line -->
                        <div
                            style="position: absolute; top: 15px; left: 10%; right: 10%; height: 2px; background: #e2e8f0; z-index: 0;">
                        </div>
                        <div
                            style="position: absolute; top: 15px; left: 10%; width: <?php echo $current_idx !== false ? ($current_idx / (count($stages) - 1) * 80) : 0; ?>%; height: 2px; background: var(--primary-color); z-index: 0; transition: width 0.5s;">
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #64748b;">
                    <div style="font-size: 48px; margin-bottom: 20px;">ℹ️</div>
                    <p>No visa application has been started for this student yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($tab === 'logs'): ?>
    <div class="card" style="background:#fffbeb; border:1px dashed #fcd34d;">
        <h4>Add Communication Log</h4>
        <form method="POST">
            <input type="hidden" name="add_log" value="1">
            <div style="display:flex; gap:10px;">
                <select name="type" class="form-control" style="width:120px;">
                    <option value="call">📞 Call</option>
                    <option value="email">📧 Email</option>
                    <option value="note">📝 Note</option>
                    <option value="meeting">👥 Meeting</option>
                </select>
                <input type="text" name="message" class="form-control" placeholder="Details..." required>
                <button type="submit" class="btn">Log Activity</button>
            </div>
        </form>
    </div>
    <h3 style="margin-top: 25px;">Timeline</h3>
    <?php if (count($timelines) > 0): ?>
        <?php foreach ($timelines as $log): ?>
            <div class="card" style="margin-bottom:10px; border-left: 4px solid var(--primary-color);">
                <div style="display:flex; justify-content:space-between;">
                    <strong>
                        <?php
                        $icons = ['call' => '📞', 'email' => '📧', 'meeting' => '👥', 'note' => '📝'];
                        echo $icons[$log['type']] ?? '•';
                        ?>
                        <?php echo ucfirst($log['type']); ?>
                    </strong>
                    <small>
                        <?php echo date('M d, H:i', strtotime($log['created_at'])); ?> by
                        <?php echo htmlspecialchars($log['author_name']); ?>
                        <?php if (!hasRole('student')): ?>
                            | <a href="?id=<?php echo $student_id; ?>&tab=logs&delete_log=<?php echo $log['id']; ?>"
                                onclick="return confirm('Delete log?')" style="color: #ef4444;">Delete</a>
                        <?php endif; ?>
                    </small>
                </div>
                <p style="margin:5px 0;"><?php echo nl2br(htmlspecialchars($log['message'])); ?></p>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="color: #666; font-size: 13px;">No logs recorded for this student.</p>
    <?php endif; ?>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>