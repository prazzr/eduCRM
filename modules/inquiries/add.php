<?php
/**
 * Add Inquiry
 * Creates new student inquiries with automatic lead scoring
 */
require_once '../../app/bootstrap.php';



requireLogin();
requireCRMAccess();

$message = '';
$error = '';

// Load lookup data using cached service (replaces direct queries)
$lookup = \EduCRM\Services\LookupCacheService::getInstance($pdo);
$countries = $lookup->getActiveRecords('countries');
$educationLevels = $lookup->getAll('education_levels');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!csrf_verify()) {
        redirectWithAlert("add.php", "Security validation failed. Please refresh and try again.", "error");
    } else {
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $country_id = !empty($_POST['country_id']) ? (int) $_POST['country_id'] : null;
        $course = sanitize($_POST['course']);
        $education_level_id = !empty($_POST['education_level_id']) ? (int) $_POST['education_level_id'] : null;
        $source = sanitize($_POST['source'] ?? '');
        $source_other = ($source === 'other') ? sanitize($_POST['source_other'] ?? '') : null;
        $assigned_to = $_SESSION['user_id'];

        if ($name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO inquiries (name, email, phone, country_id, intended_course, education_level_id, source, source_other, assigned_to, status_id, priority_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 3)");
                $stmt->execute([$name, $email, $phone, $country_id, $course, $education_level_id, $source, $source_other, $assigned_to]);

                // Auto-score the new inquiry
                $inquiryId = $pdo->lastInsertId();
                $leadScoringService = new \EduCRM\Services\LeadScoringService($pdo);
                $leadScoringService->updateInquiryScore($inquiryId);

                // Log the action
                logAction('inquiry_create', "Created inquiry ID: {$inquiryId}");

                redirectWithAlert("list.php", "Inquiry added and scored successfully!", 'success');
            } catch (PDOException $e) {
                redirectWithAlert("add.php", "Unable to create inquiry. Please check your inputs.", 'error');
            }
        } else {
            redirectWithAlert("add.php", "Name is required.", 'error');
        }
    }
}

$pageDetails = ['title' => 'Add Inquiry'];
require_once '../../templates/header.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <h2>Add New Student Inquiry</h2>

    <?php if ($message): ?>
        <div style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin: 15px 0;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; margin: 15px 0;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?php csrf_field(); ?>
        <div class="form-group">
            <label>Student Name *</label>
            <input type="text" name="name" class="form-control" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>Intended Country</label>
                <select name="country_id" class="form-control">
                    <option value="">Select Country</option>
                    <?php foreach ($countries as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Intended Course</label>
                <select name="course" class="form-control">
                    <option value="">Select Course</option>
                    <option value="IELTS">IELTS</option>
                    <option value="PTE">PTE</option>
                    <option value="SAT">SAT</option>
                    <option value="Study Abroad">Study Abroad Only</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Current Education Level</label>
            <select name="education_level_id" class="form-control">
                <option value="">Select Education Level</option>
                <?php foreach ($educationLevels as $el): ?>
                    <option value="<?php echo $el['id']; ?>"><?php echo htmlspecialchars($el['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Source</label>
            <select name="source" id="source" class="form-control" onchange="toggleSourceOther()">
                <option value="">Select Source</option>
                <option value="walk_in">Walk In</option>
                <option value="referred">Referred</option>
                <option value="social_media_post">Social Media Post</option>
                <option value="social_media_ad">Social Media Ad</option>
                <option value="sms_campaign">SMS Campaign</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div class="form-group" id="source_other_container" style="display: none;">
            <label>Please Specify (Optional)</label>
            <input type="text" name="source_other" id="source_other" class="form-control"
                placeholder="Enter source details...">
        </div>

        <script>
            function toggleSourceOther() {
                var sourceSelect = document.getElementById('source');
                var otherContainer = document.getElementById('source_other_container');
                if (sourceSelect.value === 'other') {
                    otherContainer.style.display = 'block';
                } else {
                    otherContainer.style.display = 'none';
                    document.getElementById('source_other').value = '';
                }
            }
        </script>

        <button type="submit" class="btn">Save Inquiry</button>
        <a href="list.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>