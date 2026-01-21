<?php
/**
 * Automation Logs
 * View execution history for automated notifications
 */

require_once __DIR__ . '/../../app/bootstrap.php';

// Admin only access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    header('Location: /index.php');
    exit;
}

$automationService = new \EduCRM\Services\AutomationService($pdo);

// Filters
$filters = [
    'channel' => $_GET['channel'] ?? '',
    'status' => $_GET['status'] ?? '',
    'trigger_event' => $_GET['trigger'] ?? '',
    'date_from' => $_GET['from'] ?? '',
    'date_to' => $_GET['to'] ?? ''
];

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Get logs with pagination
$logs = $automationService->getLogs($filters, $perPage, $offset);
$totalLogs = $automationService->getLogCount($filters);
$totalPages = ceil($totalLogs / $perPage);

// Get stats
$stats = $automationService->getLogStats();

$triggerEvents = \EduCRM\Services\AutomationService::TRIGGER_EVENTS;

$pageTitle = 'Automation Logs';
include __DIR__ . '/../../templates/header.php';
?>

<div class="p-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Automation Logs</h1>
            <p class="text-slate-600">View execution history and delivery status</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <div class="text-2xl font-bold text-slate-800"><?= number_format($stats['total'] ?? 0) ?></div>
            <div class="text-sm text-slate-500">Total Executions</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-green-200 p-4">
            <div class="text-2xl font-bold text-green-600"><?= number_format($stats['sent'] ?? 0) ?></div>
            <div class="text-sm text-slate-500">Sent</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-red-200 p-4">
            <div class="text-2xl font-bold text-red-600"><?= number_format($stats['failed'] ?? 0) ?></div>
            <div class="text-sm text-slate-500">Failed</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-amber-200 p-4">
            <div class="text-2xl font-bold text-amber-600"><?= number_format($stats['queued'] ?? 0) ?></div>
            <div class="text-sm text-slate-500">Queued</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Channel</label>
                <select name="channel" class="rounded-lg border-slate-300 text-sm">
                    <option value="">All</option>
                    <option value="email" <?= $filters['channel'] === 'email' ? 'selected' : '' ?>>Email</option>
                    <option value="sms" <?= $filters['channel'] === 'sms' ? 'selected' : '' ?>>SMS</option>
                    <option value="whatsapp" <?= $filters['channel'] === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                <select name="status" class="rounded-lg border-slate-300 text-sm">
                    <option value="">All</option>
                    <option value="sent" <?= $filters['status'] === 'sent' ? 'selected' : '' ?>>Sent</option>
                    <option value="failed" <?= $filters['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                    <option value="queued" <?= $filters['status'] === 'queued' ? 'selected' : '' ?>>Queued</option>
                    <option value="skipped" <?= $filters['status'] === 'skipped' ? 'selected' : '' ?>>Skipped</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Trigger</label>
                <select name="trigger" class="rounded-lg border-slate-300 text-sm">
                    <option value="">All</option>
                    <?php foreach ($triggerEvents as $key => $event): ?>
                        <option value="<?= $key ?>" <?= $filters['trigger_event'] === $key ? 'selected' : '' ?>>
                            <?= $event['name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">From</label>
                <input type="date" name="from" value="<?= htmlspecialchars($filters['date_from']) ?>"
                    class="rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">To</label>
                <input type="date" name="to" value="<?= htmlspecialchars($filters['date_to']) ?>"
                    class="rounded-lg border-slate-300 text-sm">
            </div>
            <button type="submit"
                class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition-colors">
                Filter
            </button>
            <?php if (array_filter($filters)): ?>
                <a href="logs.php" class="px-4 py-2 text-slate-600 hover:text-slate-800">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider px-4 py-3">
                            Timestamp</th>
                        <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider px-4 py-3">
                            Trigger</th>
                        <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider px-4 py-3">
                            Channel</th>
                        <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider px-4 py-3">
                            Recipient</th>
                        <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider px-4 py-3">
                            Status</th>
                        <th class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider px-4 py-3">
                            Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-12 text-slate-500">
                                <svg class="w-12 h-12 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p>No logs found matching your criteria.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    <?= date('M j, Y g:i A', strtotime($log['executed_at'])) ?>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <?php
                                    $eventName = $triggerEvents[$log['trigger_event']]['name'] ?? $log['trigger_event'];
                                    ?>
                                    <span class="text-slate-700"><?= htmlspecialchars($eventName) ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 text-xs rounded-full <?=
                                        $log['channel'] === 'email' ? 'bg-blue-100 text-blue-700' :
                                        ($log['channel'] === 'sms' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700')
                                        ?>">
                                        <?= ucfirst($log['channel']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    <?= htmlspecialchars($log['recipient']) ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php
                                    $statusColors = [
                                        'sent' => 'bg-green-100 text-green-700',
                                        'failed' => 'bg-red-100 text-red-700',
                                        'queued' => 'bg-amber-100 text-amber-700',
                                        'skipped' => 'bg-slate-100 text-slate-600'
                                    ];
                                    ?>
                                    <span
                                        class="px-2 py-0.5 text-xs rounded-full <?= $statusColors[$log['status']] ?? 'bg-slate-100 text-slate-600' ?>">
                                        <?= ucfirst($log['status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <?php if ($log['error_message']): ?>
                                        <button onclick="showError('<?= htmlspecialchars(addslashes($log['error_message'])) ?>')"
                                            class="text-red-600 hover:text-red-800 text-xs underline">
                                            View Error
                                        </button>
                                    <?php elseif ($log['status'] === 'sent'): ?>
                                        <span class="text-green-600">✓</span>
                                    <?php else: ?>
                                        <span class="text-slate-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="px-4 py-3 border-t border-slate-200 bg-slate-50 flex justify-between items-center">
                <div class="text-sm text-slate-600">
                    Showing <?= $offset + 1 ?> to <?= min($offset + $perPage, $totalLogs) ?> of
                    <?= number_format($totalLogs) ?> logs
                </div>
                <div class="flex gap-1">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                            class="px-3 py-1 rounded border border-slate-300 text-sm hover:bg-white">
                            Previous
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                            class="px-3 py-1 rounded text-sm <?= $i === $page ? 'bg-teal-600 text-white' : 'border border-slate-300 hover:bg-white' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                            class="px-3 py-1 rounded border border-slate-300 text-sm hover:bg-white">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Error Modal -->
<div id="errorModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center"
    onclick="closeErrorModal()">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4 p-6" onclick="event.stopPropagation()">
        <div class="flex justify-between items-start mb-4">
            <h3 class="text-lg font-semibold text-red-700">Error Details</h3>
            <button onclick="closeErrorModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div id="errorContent"
            class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700 font-mono whitespace-pre-wrap">
        </div>
    </div>
</div>

<script>
    function showError(message) {
        document.getElementById('errorContent').textContent = message;
        document.getElementById('errorModal').classList.remove('hidden');
    }

    function closeErrorModal() {
        document.getElementById('errorModal').classList.add('hidden');
    }
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>