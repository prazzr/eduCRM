<?php
require_once '../../config.php';
require_once '../../includes/services/LeadScoringService.php';
requireLogin();

requireAdminOrCounselor();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id)
    die("Invalid ID");

$stmt = $pdo->prepare("SELECT * FROM inquiries WHERE id = ?");
$stmt->execute([$id]);
$inq = $stmt->fetch();

if (!$inq)
    die("Inquiry not found");

$leadScoringService = new LeadScoringService($pdo);

// Handle rescore action
if (isset($_GET['rescore'])) {
    $leadScoringService->updateInquiryScore($id);
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
    $country = sanitize($_POST['intended_country']);
    $course = sanitize($_POST['intended_course']);
    $status = $_POST['status'];

    $stmt = $pdo->prepare("UPDATE inquiries SET name = ?, email = ?, phone = ?, intended_country = ?, intended_course = ?, status = ? WHERE id = ?");
    $stmt->execute([$name, $email, $phone, $country, $course, $status, $id]);

    // Phase 1: Auto-rescore after update
    $leadScoringService->updateInquiryScore($id);

    redirectWithAlert("list.php", "Inquiry updated and rescored!");

    // Refresh
    $inq['name'] = $name;
    $inq['email'] = $email;
    $inq['phone'] = $phone;
    $inq['intended_country'] = $country;
    $inq['intended_course'] = $course;
    $inq['status'] = $status;
}

// Refresh inquiry data to get latest score
$stmt = $pdo->prepare("SELECT * FROM inquiries WHERE id = ?");
$stmt->execute([$id]);
$inq = $stmt->fetch();

$pageDetails = ['title' => 'Edit Inquiry'];
require_once '../../includes/header.php';

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
                    <span
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm font-bold uppercase <?php echo $priorityColors[$inq['priority']]; ?>">
                        <?php echo $priorityIcons[$inq['priority']]; ?>
                        <?php echo $inq['priority']; ?> Priority
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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Intended Country</label>
                    <input type="text" name="intended_country"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        value="<?php echo htmlspecialchars($inq['intended_country']); ?>">
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
                <select name="status"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    <option value="new" <?php echo $inq['status'] == 'new' ? 'selected' : ''; ?>>New</option>
                    <option value="contacted" <?php echo $inq['status'] == 'contacted' ? 'selected' : ''; ?>>Contacted
                    </option>
                    <option value="converted" <?php echo $inq['status'] == 'converted' ? 'selected' : ''; ?>>Converted
                    </option>
                    <option value="closed" <?php echo $inq['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="submit" class="btn">Update Inquiry</button>
                <a href="list.php" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>