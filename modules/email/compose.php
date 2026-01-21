<?php
/**
 * Compose Email
 * Send custom emails to users, students, or inquiries
 */

require_once '../../app/bootstrap.php';


requireLogin();
requireAdminCounselorOrBranchManager();

$pageDetails = ['title' => 'Compose Email'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipientType = $_POST['recipient_type'] ?? '';
    $recipientId = (int) ($_POST['recipient_id'] ?? 0);
    $customEmail = filter_var($_POST['custom_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $customName = trim($_POST['custom_name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $scheduleAt = $_POST['schedule_at'] ?? null;

    $recipientEmail = '';
    $recipientName = '';

    // Determine recipient
    if ($recipientType === 'custom' && $customEmail) {
        $recipientEmail = $customEmail;
        $recipientName = $customName ?: 'Recipient';
    } elseif ($recipientType === 'user' && $recipientId) {
        $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->execute([$recipientId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $recipientEmail = $user['email'];
            $recipientName = $user['name'];
        }
    } elseif ($recipientType === 'inquiry' && $recipientId) {
        $stmt = $pdo->prepare("SELECT name, email FROM inquiries WHERE id = ?");
        $stmt->execute([$recipientId]);
        $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($inquiry) {
            $recipientEmail = $inquiry['email'];
            $recipientName = $inquiry['name'];
        }
    } elseif ($recipientType === 'student' && $recipientId) {
        $stmt = $pdo->prepare("SELECT s.name, u.email FROM students s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ?");
        $stmt->execute([$recipientId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($student && $student['email']) {
            $recipientEmail = $student['email'];
            $recipientName = $student['name'];
        }
    }

    if (!$recipientEmail) {
        redirectWithAlert('compose.php', 'Invalid recipient or no email address found.', 'error');
    } elseif (!$subject || !$body) {
        redirectWithAlert('compose.php', 'Subject and body are required.', 'error');
    } else {
        $emailService = new \EduCRM\Services\EmailNotificationService($pdo);

        // Wrap body in HTML template
        $htmlBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='padding: 20px;'>
                    " . nl2br(htmlspecialchars($body)) . "
                </div>
                <p style='color: #64748b; font-size: 12px; margin-top: 30px; padding: 20px; border-top: 1px solid #e2e8f0;'>
                    Sent from EduCRM by " . htmlspecialchars($_SESSION['user_name'] ?? 'Admin') . "
                </p>
            </div>
        ";

        $scheduled = !empty($scheduleAt) ? date('Y-m-d H:i:s', strtotime($scheduleAt)) : null;

        if ($emailService->queueEmail($recipientEmail, $recipientName, $subject, $htmlBody, 'custom', $scheduled)) {
            $msg = 'Email ' . ($scheduled ? 'scheduled' : 'queued') . ' successfully!';
            redirectWithAlert('queue.php', $msg, 'success');
        } else {
            redirectWithAlert('compose.php', 'Failed to queue email.', 'error');
        }
    }
}

// Get users for dropdown
$users = $pdo->query("SELECT id, name, email FROM users WHERE email IS NOT NULL AND email != '' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

require_once '../../templates/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Compose Email</h1>
        <p class="text-slate-500 mt-1 text-sm">Send a custom email to users, students, or inquiries</p>
    </div>
    <a href="queue.php" class="btn btn-secondary">
        <?php echo \EduCRM\Services\NavigationService::getIcon('arrow-left', 16); ?> Back to Queue
    </a>
</div>

<?php renderFlashMessage(); ?>

<form method="POST" class="space-y-6" x-data="{ recipientType: 'custom' }">
    <!-- Recipient -->
    <div class="card">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h2 class="text-lg font-semibold text-slate-800">Recipient</h2>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Recipient Type</label>
                <select name="recipient_type" x-model="recipientType"
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    <option value="custom">Custom Email Address</option>
                    <option value="user">System User</option>
                    <option value="inquiry">Inquiry</option>
                    <option value="student">Student</option>
                </select>
            </div>

            <!-- Custom Email -->
            <div x-show="recipientType === 'custom'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Email Address</label>
                    <input type="email" name="custom_email" placeholder="recipient@example.com"
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Name (optional)</label>
                    <input type="text" name="custom_name" placeholder="Recipient Name"
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
            </div>

            <!-- User Dropdown -->
            <div x-show="recipientType === 'user'">
                <label class="block text-sm font-medium text-slate-700 mb-2">Select User</label>
                <select name="recipient_id"
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    <option value="">-- Select User --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Inquiry Search -->
            <div x-show="recipientType === 'inquiry'">
                <label class="block text-sm font-medium text-slate-700 mb-2">Inquiry ID</label>
                <input type="number" name="recipient_id" placeholder="Enter inquiry ID"
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                <p class="mt-1 text-xs text-slate-500">Enter the inquiry ID from the inquiries list</p>
            </div>

            <!-- Student Search -->
            <div x-show="recipientType === 'student'">
                <label class="block text-sm font-medium text-slate-700 mb-2">Student ID</label>
                <input type="number" name="recipient_id" placeholder="Enter student ID"
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                <p class="mt-1 text-xs text-slate-500">Enter the student ID from the students list</p>
            </div>
        </div>
    </div>

    <!-- Email Content -->
    <div class="card">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h2 class="text-lg font-semibold text-slate-800">Email Content</h2>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Subject <span
                        class="text-red-500">*</span></label>
                <input type="text" name="subject" required placeholder="Enter email subject"
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Message <span
                        class="text-red-500">*</span></label>
                <textarea name="body" rows="10" required placeholder="Write your email message here..."
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"></textarea>
                <p class="mt-1 text-xs text-slate-500">Plain text will be converted to HTML automatically</p>
            </div>
        </div>
    </div>

    <!-- Schedule -->
    <div class="card">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h2 class="text-lg font-semibold text-slate-800">Schedule (Optional)</h2>
        </div>
        <div class="p-6">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Send At</label>
                <input type="datetime-local" name="schedule_at"
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                <p class="mt-1 text-xs text-slate-500">Leave empty to send immediately (via queue)</p>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex justify-end gap-4">
        <a href="queue.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <?php echo \EduCRM\Services\NavigationService::getIcon('mail', 16); ?> Send Email
        </button>
    </div>
</form>

<?php require_once '../../templates/footer.php'; ?>