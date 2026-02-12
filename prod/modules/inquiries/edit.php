<?php
/**
 * Edit Inquiry
 * Updates inquiry details with automatic lead scoring and conversion support
 */
require_once '../../app/bootstrap.php';



requireLogin();
requireCRMAccess();

// Validate ID parameter
$id = requireIdParam();

// Load lookup data using cached service
$lookup = \EduCRM\Services\LookupCacheService::getInstance($pdo);
$countries = $lookup->getActiveRecords('countries');
$statuses = $lookup->getAll('inquiry_statuses');

// Get inquiry with joined lookup data
$stmt = $pdo->prepare("SELECT i.*, 
    c.name as country_name, 
    ist.name as status_name,
    pl.name as priority_name
    FROM inquiries i 
    LEFT JOIN countries c ON i.country_id = c.id
    LEFT JOIN inquiry_statuses ist ON i.status_id = ist.id
    LEFT JOIN priority_levels pl ON i.priority_id = pl.id
    WHERE i.id = ?");
$stmt->execute([$id]);
$inq = $stmt->fetch();

if (!$inq) {
    die("Inquiry not found");
}

$leadScoringService = new \EduCRM\Services\LeadScoringService($pdo);

// Handle rescore action
if (isset($_GET['rescore'])) {
    $leadScoringService->updateInquiryScore($id);
    logAction('inquiry_rescore', "Rescored inquiry ID: {$id}");
    header("Location: edit.php?id=$id&rescored=1");
    exit;
}

$message = '';
if (isset($_GET['rescored'])) {
    $message = 'Score recalculated successfully!';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $country_id = !empty($_POST['country_id']) ? (int) $_POST['country_id'] : null;
    $course = sanitize($_POST['intended_course']);
    $status_id = (int) $_POST['status_id'];
    $source = sanitize($_POST['source'] ?? '');
    $source_other = ($source === 'other') ? sanitize($_POST['source_other'] ?? '') : null;

    // Check if status is being changed to 'converted'
    $convertedStatusId = $lookup->getIdByName('inquiry_statuses', 'converted');

    $wasConverted = ($inq['status_id'] == $convertedStatusId);
    $isBeingConverted = ($status_id == $convertedStatusId);
    $conversionMessage = '';
    $conversionError = '';

    // Auto-convert to student if status changed to 'converted' and not already converted
    if ($isBeingConverted && !$wasConverted && !empty($email)) {
        // Check if user with this email already exists
        $checkUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkUser->execute([$email]);

        if ($checkUser->rowCount() == 0) {
            // Create new student user
            try {
                $pdo->beginTransaction();

                // Generate secure password
                $raw_password = generateSecurePassword();
                $password_hash = password_hash($raw_password, PASSWORD_DEFAULT);

                // Insert user with student role
                $userStmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, phone, country_id, education_level_id) VALUES (?, ?, ?, 'student', ?, ?, ?)");
                $userStmt->execute([$name, $email, $password_hash, $phone, $country_id, $inq['education_level_id']]);
                $user_id = $pdo->lastInsertId();

                // Link to student role
                $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'student'");
                $roleStmt->execute();
                $role_id = $roleStmt->fetchColumn();

                $linkStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                $linkStmt->execute([$user_id, $role_id]);

                $pdo->commit();

                // Try to send welcome email
                try {

                    $emailService = new \EduCRM\Services\EmailService();
                    $emailService->sendWelcomeEmail([
                        'name' => $name,
                        'email' => $email
                    ], $raw_password);
                } catch (Exception $e) {
                    // Email sending is optional, don't fail the conversion
                }

                $conversionMessage = " Student account created! Password: $raw_password";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $conversionError = " (Error creating student: " . $e->getMessage() . ")";
            }
        } else {
            $conversionMessage = " (Student already exists)";
        }
    }

    try {
        $branch_sql = "";
        $params = [$name, $email, $phone, $country_id, $course, $status_id, $source, $source_other];

        if (hasRole('admin')) {
            if (isset($_POST['branch_id'])) {
                $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : null;
                $branch_sql = ", branch_id = ?";
                $params[] = $branch_id;
            }
        }
        $params[] = $id;

        $stmt = $pdo->prepare("UPDATE inquiries SET name = ?, email = ?, phone = ?, country_id = ?, intended_course = ?, status_id = ?, source = ?, source_other = ? $branch_sql WHERE id = ?");
        $stmt->execute($params);

        // Phase 1: Auto-rescore after update
        $leadScoringService->updateInquiryScore($id);

        $alertMessage = "Inquiry updated and rescored!" . $conversionMessage . $conversionError;
        redirectWithAlert("list.php", $alertMessage);
    } catch (PDOException $e) {
        redirectWithAlert("edit.php?id=$id", "Update Error: " . $e->getMessage(), "error");
    }
}

// Refresh inquiry data to get latest score
$stmt = $pdo->prepare("SELECT * FROM inquiries WHERE id = ?");
$stmt->execute([$id]);
$inq = $stmt->fetch();

$pageDetails = ['title' => 'Edit Inquiry'];
require_once '../../templates/header.php';

$priorityColors = [
    'hot' => 'bg-red-100 text-red-700 border-red-200',
    'warm' => 'bg-orange-100 text-orange-700 border-orange-200',
    'cold' => 'bg-blue-100 text-blue-700 border-blue-200'
];
$priorityIcons = [
    'hot' => '🔥',
    'warm' => '☀️',
    'cold' => '❄️'
];
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-slate-800">Edit Inquiry</h1>
        <a href="list.php" class="btn-secondary px-4 py-2 rounded-lg font-medium">← Back to List</a>
    </div>

    <?php if ($message): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-6">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Phase 1: Lead Score Display -->
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-sm font-medium text-slate-600 mb-2">Lead Score & Priority</h3>
                <div class="flex items-center gap-4">
                    <div class="flex items-baseline gap-2">
                        <span class="text-4xl font-bold text-slate-800"><?php echo $inq['score']; ?></span>
                        <span class="text-lg text-slate-500">/100</span>
                    </div>
                    <?php $displayPriority = $inq['priority_name'] ?? $inq['priority'] ?? 'cold'; ?>
                    <span
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm font-bold uppercase <?php echo $priorityColors[$displayPriority] ?? $priorityColors['cold']; ?>">
                        <?php echo $priorityIcons[$displayPriority] ?? '❄️'; ?>
                        <?php echo $displayPriority; ?> Priority
                    </span>
                </div>
            </div>
            <a href="edit.php?id=<?php echo $id; ?>&rescore=1" class="btn-secondary px-4 py-2 rounded-lg font-medium">
                🔄 Recalculate Score
            </a>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-800 mb-4">Inquiry Details</h2>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Name *</label>
                <input type="text" name="name"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    value="<?php echo htmlspecialchars($inq['name']); ?>" required>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email *</label>
                    <input type="email" name="email"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        value="<?php echo htmlspecialchars($inq['email']); ?>" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                    <input type="text" name="phone"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        value="<?php echo htmlspecialchars($inq['phone']); ?>">
                </div>
            </div>

            <?php if (hasRole('admin')):
                $branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
                ?>
                <div class="form-group mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Branch</label>
                    <select name="branch_id"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">-- Global / Head Office --</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo ($inq['branch_id'] == $b['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Intended Country</label>
                    <select name="country_id"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Select Country</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $inq['country_id'] == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Affects lead score calculation</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Intended Course</label>
                    <input type="text" name="intended_course"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        value="<?php echo htmlspecialchars($inq['intended_course']); ?>">
                    <p class="text-xs text-slate-500 mt-1">Affects lead score calculation</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                <select name="status_id"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $inq['status_id'] == $s['id'] ? 'selected' : ''; ?>><?php echo ucfirst($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php
            $sourceOptions = [
                '' => 'Select Source',
                'walk_in' => 'Walk In',
                'referred' => 'Referred',
                'social_media_post' => 'Social Media Post',
                'social_media_ad' => 'Social Media Ad',
                'sms_campaign' => 'SMS Campaign',
                'other' => 'Other'
            ];
            ?>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Source</label>
                <select name="source" id="source"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    onchange="toggleSourceOther()">
                    <?php foreach ($sourceOptions as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($inq['source'] ?? '') == $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="source_other_container"
                style="<?php echo ($inq['source'] ?? '') === 'other' ? '' : 'display: none;'; ?>">
                <label class="block text-sm font-medium text-slate-700 mb-1">Please Specify (Optional)</label>
                <input type="text" name="source_other" id="source_other"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    value="<?php echo htmlspecialchars($inq['source_other'] ?? ''); ?>"
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

            <div class="flex gap-3 pt-4">
                <button type="submit" class="btn">Update Inquiry</button>
                <a href="list.php" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>