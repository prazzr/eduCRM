<?php
require_once '../../app/bootstrap.php';
requireLogin();

$student_id = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;
$is_student = hasRole('student');

// If student, can only view own documents
if ($is_student) {
    $student_id = $_SESSION['user_id'];
}
// If not student and no ID, redirect
if (!$is_student && !$student_id) {
    header("Location: ../../index.php"); // Or list of students
    exit;
}

// Fetch Student Name
$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student_info = $stmt->fetch();

$message = '';
$error = '';

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['doc'])) {
    $title = sanitize($_POST['title']);

    if (isset($_FILES['doc']) && $_FILES['doc']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
        $ext = strtolower(pathinfo($_FILES['doc']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $upload_dir = '../../uploads/documents/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . basename($_FILES['doc']['name']);

            if (move_uploaded_file($_FILES['doc']['tmp_name'], $upload_dir . $filename)) {
                $path = 'uploads/documents/' . $filename;
                $stmt = $pdo->prepare("INSERT INTO student_documents (student_id, title, file_path) VALUES (?, ?, ?)");
                $stmt->execute([$student_id, $title, $path]);
                redirectWithAlert("list.php?student_id=$student_id", "Document uploaded successfully.", "success");
            } else {
                redirectWithAlert("list.php?student_id=$student_id", "Upload failed.", "error");
            }
        } else {
            redirectWithAlert("list.php?student_id=$student_id", "Invalid file type. Only PDF, Images, and Docs allowed.", "error");
        }
    } else {
        redirectWithAlert("list.php?student_id=$student_id", "Please select a file.", "error");
    }
}

// Handle Delete (Admin/Counselor only)
if (isset($_GET['delete']) && !hasRole('student')) {
    $doc_id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM student_documents WHERE id = ?");
    redirectWithAlert("list.php?student_id=" . $student_id, "Document deleted successfully.", "success");
}

// Fetch Documents
$docs = $pdo->prepare("SELECT * FROM student_documents WHERE student_id = ? ORDER BY uploaded_at DESC");
$docs->execute([$student_id]);
$all_docs = $docs->fetchAll();

$pageDetails = ['title' => 'Document Vault'];
require_once '../../templates/header.php';
?>

<div class="card">
    <div style="border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
        <h2>Document Vault:
            <?php echo htmlspecialchars($student_info['name']); ?>
        </h2>
        <?php if (!$is_student): ?>
            <a href="../../modules/inquiries/list.php" class="btn btn-secondary" style="font-size: 12px;">&laquo; Back to
                Inquiries/List</a>
        <?php endif; ?>
    </div>

    <?php renderFlashMessage(); ?>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">

        <!-- Upload Form -->
        <div class="card" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
            <h4>Upload Document</h4>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Document Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Passport Front" required>
                </div>
                <div class="form-group">
                    <label>File</label>
                    <input type="file" name="doc" class="form-control" required>
                    <small>PDF, JPG, PNG, DOCX</small>
                </div>
                <button type="submit" class="btn">Upload</button>
            </form>
        </div>

        <!-- Document List -->
        <div>
            <h3>Stored Documents</h3>
            <?php if (count($all_docs) > 0): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                    <?php foreach ($all_docs as $d): ?>
                        <div class="card"
                            style="padding: 15px; text-align: center; border: 1px solid #e2e8f0; box-shadow: none;">
                            <div style="font-size: 40px; color: #64748b;">
                                <?php
                                $ext = pathinfo($d['file_path'], PATHINFO_EXTENSION);
                                if (in_array($ext, ['jpg', 'png', 'jpeg']))
                                    echo '🖼️';
                                elseif ($ext == 'pdf')
                                    echo '📄';
                                else
                                    echo '📁';
                                ?>
                            </div>
                            <div style="font-weight: 600; font-size: 14px; margin: 10px 0;">
                                <?php echo htmlspecialchars($d['title']); ?>
                            </div>
                            <a href="<?php echo BASE_URL . $d['file_path']; ?>" target="_blank" class="btn btn-secondary"
                                style="padding: 5px 10px; font-size: 11px;">View</a>

                            <?php if (!hasRole('student')): ?>
                                <div style="display: flex; gap: 10px; justify-content: center; margin-top: 5px;">
                                    <a href="edit.php?id=<?php echo $d['id']; ?>"
                                        style="font-size: 11px; color: var(--primary-color);">Edit</a>
                                    <a href="#" onclick="confirmDelete(<?php echo $d['id']; ?>)"
                                        style="color: red; font-size: 11px;">Delete</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No documents uploaded yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>

<script>
    function confirmDelete(id) {
        Modal.show({
            type: 'error',
            title: 'Delete Document?',
            message: 'Are you sure you want to delete this document? This action cannot be undone.',
            confirmText: 'Yes, Delete It',
            onConfirm: function () {
                window.location.href = '?student_id=<?php echo $student_id; ?>&delete=' + id;
            }
        });
    }
</script>