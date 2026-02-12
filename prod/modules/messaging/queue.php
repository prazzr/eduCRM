<?php
require_once '../../app/bootstrap.php';


requireLogin();
requireBranchManager();

\EduCRM\Services\MessagingFactory::init($pdo);

// Get queue statistics
$stats = $pdo->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM messaging_queue
    GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);

$statusCounts = [];
foreach ($stats as $stat) {
    $statusCounts[$stat['status']] = $stat['count'];
}

// Get recent messages
$stmt = $pdo->query("
    SELECT q.*, g.name as gateway_name
    FROM messaging_queue q
    LEFT JOIN messaging_gateways g ON q.gateway_id = g.id
    ORDER BY q.created_at DESC
    LIMIT 100
");
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageDetails = ['title' => 'Message Queue'];
require_once '../../templates/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">📬 Message Queue</h1>
    <p class="text-slate-600 mt-1">Monitor and manage queued messages</p>
</div>

<?php require_once 'tabs.php'; ?>

<!-- Statistics -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <div class="text-2xl font-bold text-yellow-600">
            <?php echo $statusCounts['pending'] ?? 0; ?>
        </div>
        <div class="text-xs text-slate-600 mt-1">Pending</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <div class="text-2xl font-bold text-blue-600">
            <?php echo $statusCounts['processing'] ?? 0; ?>
        </div>
        <div class="text-xs text-slate-600 mt-1">Processing</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <div class="text-2xl font-bold text-emerald-600">
            <?php echo $statusCounts['sent'] ?? 0; ?>
        </div>
        <div class="text-xs text-slate-600 mt-1">Sent</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <div class="text-2xl font-bold text-green-600">
            <?php echo $statusCounts['delivered'] ?? 0; ?>
        </div>
        <div class="text-xs text-slate-600 mt-1">Delivered</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <div class="text-2xl font-bold text-red-600">
            <?php echo $statusCounts['failed'] ?? 0; ?>
        </div>
        <div class="text-xs text-slate-600 mt-1">Failed</div>
    </div>
</div>

<!-- Messages Table -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-4 border-b border-slate-200 flex justify-between items-center">
        <h3 class="font-bold text-slate-800">Recent Messages</h3>
        <button onclick="processQueue()" class="btn-secondary px-4 py-2 text-sm rounded-lg">
            Process Queue Now
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase">Recipient</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase">Message</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase">Gateway</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase">Created</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-700 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php foreach ($messages as $msg):
                    $statusColors = [
                        'pending' => 'bg-yellow-100 text-yellow-700',
                        'processing' => 'bg-blue-100 text-blue-700',
                        'sent' => 'bg-emerald-100 text-emerald-700',
                        'delivered' => 'bg-green-100 text-green-700',
                        'failed' => 'bg-red-100 text-red-700',
                        'cancelled' => 'bg-slate-100 text-slate-700'
                    ];
                    $statusColor = $statusColors[$msg['status']] ?? 'bg-slate-100 text-slate-700';
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm text-slate-600">#
                            <?php echo $msg['id']; ?>
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-slate-800">
                            <?php echo htmlspecialchars($msg['recipient']); ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600 max-w-xs truncate">
                            <?php echo htmlspecialchars(substr($msg['message'], 0, 50)); ?>...
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            <?php echo htmlspecialchars($msg['gateway_name'] ?? 'Auto'); ?>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 <?php echo $statusColor; ?> text-xs font-medium rounded">
                                <?php echo ucfirst($msg['status']); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            <?php echo date('M d, H:i', strtotime($msg['created_at'])); ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <?php if ($msg['status'] === 'failed'): ?>
                                <button onclick="retryMessage(<?php echo $msg['id']; ?>)"
                                    class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                                    Retry
                                </button>
                            <?php elseif ($msg['status'] === 'pending'): ?>
                                <button onclick="cancelMessage(<?php echo $msg['id']; ?>)"
                                    class="text-red-600 hover:text-red-700 text-sm font-medium">
                                    Cancel
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function processQueue() {
        Modal.confirm('Process pending messages now?', () => {
            fetch('process_queue.php', {
                method: 'POST'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Modal.success(`Processed: ${data.sent} sent, ${data.failed} failed`, 'Queue Processed');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        Modal.error(data.message, 'Error');
                    }
                })
                .catch(err => Modal.error('Network error: ' + err.message, 'Connection Error'));
        });
    }

    function retryMessage(messageId) {
        fetch('retry_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${messageId}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Toast.success('Message queued for retry');
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    Modal.error(data.message, 'Error');
                }
            })
            .catch(err => Modal.error('Network error: ' + err.message, 'Connection Error'));
    }

    function cancelMessage(messageId) {
        Modal.confirm('Cancel this message?', () => {
            fetch('cancel_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${messageId}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Toast.success('Message cancelled');
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        Modal.error(data.message, 'Error');
                    }
                })
                .catch(err => Modal.error('Network error: ' + err.message, 'Connection Error'));
        });
    }
</script>

<?php require_once '../../templates/footer.php'; ?>