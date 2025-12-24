<?php
require_once '../../config.php';
requireLogin();

// Admin/Counselor only
if (hasRole('student')) {
    header("Location: ../../index.php");
    exit;
}

$message = '';

// 1. Add Application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_app'])) {
    $student_id = $_POST['student_id'];
    $uni = sanitize($_POST['university_name']);
    $course = sanitize($_POST['course_name']);
    $country = sanitize($_POST['country']);

    $stmt = $pdo->prepare("INSERT INTO university_applications (student_id, university_name, course_name, country) VALUES (?, ?, ?, ?)");
    $stmt->execute([$student_id, $uni, $course, $country]);
    $message = "Application tracked.";
}

// 2. Update Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $app_id = $_POST['app_id'];
    $status = $_POST['status'];
    $notes = sanitize($_POST['notes']);

    $stmt = $pdo->prepare("UPDATE university_applications SET status = ?, notes = ? WHERE id = ?");
    $stmt->execute([$status, $notes, $app_id]);
    $message = "Status updated.";
}

// Fetch Students (Multi-role support)
$students = $pdo->query("
    SELECT DISTINCT u.id, u.name 
    FROM users u 
    JOIN user_roles ur ON u.id = ur.user_id 
    JOIN roles r ON ur.role_id = r.id 
    WHERE r.name = 'student' 
    ORDER BY u.name
")->fetchAll();

// Fetch Applications
$apps = $pdo->query("
    SELECT ua.*, u.name as student_name 
    FROM university_applications ua 
    JOIN users u ON ua.student_id = u.id 
    ORDER BY ua.updated_at DESC
")->fetchAll();

$pageDetails = ['title' => 'Application Tracker'];
require_once '../../includes/header.php';
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>University Application Tracker</h2>
        <button onclick="document.getElementById('new-app-form').style.display='block'" class="btn">New
            Application</button>
    </div>

    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin-bottom: 20px;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Add Form (Hidden by default) -->
    <div id="new-app-form" class="card" style="background: #f8fafc; display: none; margin-bottom: 20px;">
        <h4>Track New Application</h4>
        <form method="POST">
            <input type="hidden" name="add_app" value="1">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
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
                    <label>University Name</label>
                    <input type="text" name="university_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Course</label>
                    <input type="text" name="course_name" class="form-control">
                </div>
                <div class="form-group">
                    <label>Country</label>
                    <select name="country" class="form-control">
                        <option value="USA">USA</option>
                        <option value="UK">UK</option>
                        <option value="Australia">Australia</option>
                        <option value="Canada">Canada</option>
                        <option value="Europe">Europe</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn">Track Application</button>
            <button type="button" onclick="document.getElementById('new-app-form').style.display='none'"
                class="btn btn-secondary">Cancel</button>
        </form>
    </div>

    <!-- Application List -->
    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>University / Country</th>
                <th>Course</th>
                <th>Status</th>
                <th>Last Update</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($apps as $a): ?>
                <tr>
                    <td><?php echo htmlspecialchars($a['student_name']); ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($a['university_name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($a['country']); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($a['course_name']); ?></td>
                    <td>
                        <?php
                        $colors = [
                            'applied' => '#e0f2fe',
                            'offer_received' => '#fef3c7',
                            'offer_accepted' => '#dcfce7',
                            'visa_lodged' => '#fae8ff',
                            'visa_granted' => '#16a34a',
                            'rejected' => '#fee2e2'
                        ];
                        $bg = $colors[$a['status']] ?? '#f1f5f9';
                        $txt = ($a['status'] == 'visa_granted') ? 'white' : 'black';
                        ?>
                        <span class="status-badge" style="background: <?php echo $bg; ?>; color: <?php echo $txt; ?>;">
                            <?php echo ucwords(str_replace('_', ' ', $a['status'])); ?>
                        </span>
                    </td>
                    <td><?php echo date('M d', strtotime($a['updated_at'])); ?></td>
                    <td>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <button
                                onclick="editStatus(<?php echo $a['id']; ?>, '<?php echo $a['status']; ?>', '<?php echo addslashes($a['notes']); ?>')"
                                class="btn btn-secondary" style="padding: 5px 10px; font-size: 11px;">Update</button>
                            <a href="../documents/list.php?student_id=<?php echo $a['student_id']; ?>" class="btn"
                                style="padding: 5px 10px; font-size: 11px;">Docs</a>
                            <a href="delete.php?id=<?php echo $a['id']; ?>" class="btn"
                                style="padding: 5px 10px; font-size: 11px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;"
                                onclick="return confirm('Delete this application record?')">Delete</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Update Request Modal (Simple JS implementation overlay) -->
<div id="update-modal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div class="card" style="width: 400px; padding: 20px;">
        <h3>Update Application Status</h3>
        <form method="POST">
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" name="app_id" id="modal_app_id">

            <div class="form-group">
                <label>Status</label>
                <select name="status" id="modal_status" class="form-control">
                    <option value="applied">Applied</option>
                    <option value="offer_received">Offer Received</option>
                    <option value="offer_accepted">Offer Accepted</option>
                    <option value="visa_lodged">Visa Lodged</option>
                    <option value="visa_granted">Visa Granted</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="modal_notes" class="form-control"></textarea>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn">Update</button>
                <button type="button" onclick="document.getElementById('update-modal').style.display='none'"
                    class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function editStatus(id, status, notes) {
        document.getElementById('update-modal').style.display = 'flex';
        document.getElementById('modal_app_id').value = id;
        document.getElementById('modal_status').value = status;
        document.getElementById('modal_notes').value = notes;
    }
</script>

<?php require_once '../../includes/footer.php'; ?>