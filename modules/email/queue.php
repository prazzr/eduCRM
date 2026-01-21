<?php
/**
 * Email Queue Management
 * View, manage, and monitor email queue status
 */

require_once '../../app/bootstrap.php';


requireLogin();
requireAdminCounselorOrBranchManager();

$pageDetails = ['title' => 'Email Queue'];

// Initialize email service
$emailService = new \EduCRM\Services\EmailNotificationService($pdo);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $emailId = (int) ($_POST['email_id'] ?? 0);

    switch ($action) {
        case 'retry':
            if ($emailId) {
                $stmt = $pdo->prepare("UPDATE email_queue SET status = 'pending', attempts = 0, error_message = NULL WHERE id = ?");
                if ($stmt->execute([$emailId])) {
                    redirectWithAlert('queue.php', 'Email queued for retry', 'success');
                }
            }
            break;

        case 'delete':
            if ($emailId) {
                $stmt = $pdo->prepare("DELETE FROM email_queue WHERE id = ?");
                if ($stmt->execute([$emailId])) {
                    redirectWithAlert('queue.php', 'Email removed from queue', 'success');
                }
            }
            break;

        case 'process_queue':
            $result = $emailService->processQueue(20);
            redirectWithAlert('queue.php', "Queue processed: {$result['sent']} sent, {$result['failed']} failed", 'success');
            break;

        case 'clear_sent':
            $stmt = $pdo->prepare("DELETE FROM email_queue WHERE status = 'sent' AND sent_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute();
            $count = $stmt->rowCount();
            redirectWithAlert('queue.php', "Cleared $count old sent emails", 'success');
            break;
    }
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$where = [];
$params = [];

if ($statusFilter) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $where[] = "(recipient_email LIKE ? OR subject LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM email_queue $whereClause");
$countStmt->execute($params);
$totalEmails = $countStmt->fetchColumn();
$totalPages = ceil($totalEmails / $perPage);

// Get emails
$sql = "SELECT * FROM email_queue $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM email_queue
")->fetch(PDO::FETCH_ASSOC);

require_once '../../templates/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Email Queue</h1>
        <p class="text-slate-500 mt-1 text-sm">Monitor and manage outgoing email notifications</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="compose.php" class="btn btn-primary">
            <?php echo \EduCRM\Services\NavigationService::getIcon('plus', 16); ?> Compose
        </a>
        <form method="POST" class="inline">
            <input type="hidden" name="action" value="process_queue">
            <button type="submit" class="btn btn-success">
                <?php echo \EduCRM\Services\NavigationService::getIcon('play', 16); ?> Process Queue
            </button>
        </form>
        <a href="templates.php" class="btn btn-secondary">
            <?php echo \EduCRM\Services\NavigationService::getIcon('file-text', 16); ?> Templates
        </a>
        <a href="settings.php" class="btn btn-secondary">
            <?php echo \EduCRM\Services\NavigationService::getIcon('settings', 16); ?> Settings
        </a>
    </div>
</div>

<?php renderFlashMessage(); ?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
    <div class="card p-5">
        <div class="flex items-center">
            <div class="p-3 rounded-lg bg-slate-100">
                <?php echo \EduCRM\Services\NavigationService::getIcon('mail', 24); ?>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-slate-500">Total Emails</p>
                <p class="text-2xl font-bold text-slate-800"><?php echo number_format($stats['total'] ?? 0); ?></p>
            </div>
        </div>
    </div>

    <div class="card p-5">
        <div class="flex items-center">
            <div class="p-3 rounded-lg bg-amber-100">
                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-slate-500">Pending</p>
                <p class="text-2xl font-bold text-amber-600"><?php echo number_format($stats['pending'] ?? 0); ?></p>
            </div>
        </div>
    </div>

    <div class="card p-5">
        <div class="flex items-center">
            <div class="p-3 rounded-lg bg-emerald-100">
                <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-slate-500">Sent</p>
                <p class="text-2xl font-bold text-emerald-600"><?php echo number_format($stats['sent'] ?? 0); ?></p>
            </div>
        </div>
    </div>

    <div class="card p-5">
        <div class="flex items-center">
            <div class="p-3 rounded-lg bg-red-100">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-slate-500">Failed</p>
                <p class="text-2xl font-bold text-red-600"><?php echo number_format($stats['failed'] ?? 0); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3">
        <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>"
            placeholder="Search by email or subject..."
            class="flex-1 min-w-[200px] px-3 py-2 border border-slate-300 rounded-lg text-sm">

        <select name="status" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">All Status</option>
            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="sent" <?php echo $statusFilter === 'sent' ? 'selected' : ''; ?>>Sent</option>
            <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
        </select>

        <button type="submit" class="btn btn-secondary">Apply Filters</button>
        <?php if ($statusFilter || $searchQuery): ?>
            <a href="queue.php" class="px-4 py-2 text-sm text-slate-500 hover:text-slate-700">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Email Queue Table -->
<div class="card">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-sm">
                    <th class="p-3 font-semibold">Recipient</th>
                    <th class="p-3 font-semibold">Subject</th>
                    <th class="p-3 font-semibold">Template</th>
                    <th class="p-3 font-semibold">Status</th>
                    <th class="p-3 font-semibold">Attempts</th>
                    <th class="p-3 font-semibold">Created</th>
                    <th class="p-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($emails)): ?>
                    <tr>
                        <td colspan="7" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center text-slate-400">
                                <?php echo \EduCRM\Services\NavigationService::getIcon('mail', 48); ?>
                                <h3 class="mt-2 text-sm font-medium text-slate-900">No Emails in Queue</h3>
                                <p class="mt-1 text-sm text-slate-500">Compose a new email or wait for automated
                                    notifications.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($emails as $email): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="p-3">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-9 w-9">
                                        <div
                                            class="h-9 w-9 rounded-full bg-primary-50 text-primary-700 flex items-center justify-center">
                                            <span class="font-medium text-xs">
                                                <?php echo strtoupper(substr($email['recipient_name'] ?? $email['recipient_email'], 0, 2)); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-slate-800">
                                            <?php echo htmlspecialchars($email['recipient_name'] ?? 'Unknown'); ?>
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            <?php echo htmlspecialchars($email['recipient_email']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3">
                                <div class="text-sm text-slate-700 max-w-xs truncate"
                                    title="<?php echo htmlspecialchars($email['subject']); ?>">
                                    <?php echo htmlspecialchars($email['subject']); ?>
                                </div>
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-1 bg-slate-100 text-slate-600 text-xs font-medium rounded">
                                    <?php echo htmlspecialchars($email['template'] ?? 'custom'); ?>
                                </span>
                            </td>
                            <td class="p-3">
                                <?php
                                $statusClasses = [
                                    'pending' => 'bg-amber-100 text-amber-700',
                                    'sent' => 'bg-emerald-100 text-emerald-700',
                                    'failed' => 'bg-red-100 text-red-700'
                                ];
                                $statusClass = $statusClasses[$email['status']] ?? 'bg-slate-100 text-slate-600';
                                ?>
                                <span class="px-2 py-1 <?php echo $statusClass; ?> text-xs font-medium rounded">
                                    <?php echo ucfirst($email['status']); ?>
                                </span>
                                <?php if ($email['error_message']): ?>
                                    <div class="text-xs text-red-500 mt-1 max-w-[150px] truncate"
                                        title="<?php echo htmlspecialchars($email['error_message']); ?>">
                                        <?php echo htmlspecialchars($email['error_message']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-3">
                                <span class="text-sm text-slate-600"><?php echo $email['attempts']; ?> / 3</span>
                            </td>
                            <td class="p-3">
                                <div class="text-sm text-slate-700">
                                    <?php echo date('M d, Y', strtotime($email['created_at'])); ?></div>
                                <div class="text-xs text-slate-500">
                                    <?php echo date('h:i A', strtotime($email['created_at'])); ?></div>
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex gap-1 justify-end">
                                    <button onclick="viewEmail(<?php echo $email['id']; ?>)" class="action-btn slate"
                                        title="View">
                                        <?php echo \EduCRM\Services\NavigationService::getIcon('eye', 16); ?>
                                    </button>
                                    <?php if ($email['status'] === 'failed'): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="retry">
                                            <input type="hidden" name="email_id" value="<?php echo $email['id']; ?>">
                                            <button type="submit" class="action-btn green" title="Retry">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                                    </path>
                                                </svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" class="inline"
                                        onsubmit="return confirm('Delete this email from queue?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="email_id" value="<?php echo $email['id']; ?>">
                                        <button type="submit" class="action-btn red" title="Delete">
                                            <?php echo \EduCRM\Services\NavigationService::getIcon('trash', 16); ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="px-4 py-3 flex items-center justify-between border-t border-slate-200">
            <div class="text-sm text-slate-600">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalEmails); ?> of
                <?php echo $totalEmails; ?> results
            </div>
            <div class="flex gap-1">
                <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($searchQuery); ?>"
                        class="px-3 py-1 text-sm rounded <?php echo $i === $page ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="mt-4 flex justify-end">
    <form method="POST" onsubmit="return confirm('Clear all sent emails older than 7 days?');">
        <input type="hidden" name="action" value="clear_sent">
        <button type="submit" class="btn btn-secondary btn-sm">
            <?php echo \EduCRM\Services\NavigationService::getIcon('trash', 14); ?> Clear Old Sent Emails
        </button>
    </form>
</div>

<!-- Email Preview Modal -->
<div id="emailModal"
    class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 my-8">
        <div class="p-6 border-b border-slate-200 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800">Email Preview</h2>
            <button onclick="closeEmailModal()" class="text-slate-400 hover:text-slate-600">
                <?php echo \EduCRM\Services\NavigationService::getIcon('x', 24); ?>
            </button>
        </div>
        <div id="emailContent" class="p-6 max-h-[70vh] overflow-y-auto">
            <!-- Email content will be loaded here -->
        </div>
    </div>
</div>

<script>
    function viewEmail(emailId) {
        fetch('view_email.php?id=' + emailId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('emailContent').innerHTML = `
                    <div class="space-y-3">
                        <div class="flex">
                            <span class="font-medium text-slate-500 w-24">To:</span>
                            <span class="text-slate-800">${data.email.recipient_name} &lt;${data.email.recipient_email}&gt;</span>
                        </div>
                        <div class="flex">
                            <span class="font-medium text-slate-500 w-24">Subject:</span>
                            <span class="text-slate-800">${data.email.subject}</span>
                        </div>
                        <div class="flex">
                            <span class="font-medium text-slate-500 w-24">Status:</span>
                            <span class="text-slate-800">${data.email.status}</span>
                        </div>
                        <div class="flex">
                            <span class="font-medium text-slate-500 w-24">Created:</span>
                            <span class="text-slate-800">${data.email.created_at}</span>
                        </div>
                        <hr class="my-4 border-slate-200">
                        <div class="border border-slate-200 rounded-lg p-4 bg-slate-50">
                            ${data.email.body}
                        </div>
                    </div>
                `;
                    document.getElementById('emailModal').classList.remove('hidden');
                }
            });
    }

    function closeEmailModal() {
        document.getElementById('emailModal').classList.add('hidden');
    }

    document.getElementById('emailModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeEmailModal();
        }
    });
</script>

<?php require_once '../../templates/footer.php'; ?>