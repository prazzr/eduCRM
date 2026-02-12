<?php
require_once '../../app/bootstrap.php';


requireLogin();
requireBranchManager();

\EduCRM\Services\MessagingFactory::init($pdo);

// Branch managers see only their own campaigns
$isBranchManager = hasRole('branch_manager') && !hasRole('admin');

// Handle campaign actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_campaign') {
        try {
            // Create campaign
            $stmt = $pdo->prepare("
                INSERT INTO messaging_campaigns (name, message_type, template_id, message, scheduled_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $_POST['name'],
                $_POST['message_type'],
                $_POST['template_id'] ?? null,
                $_POST['message'],
                $_POST['scheduled_at'] ?? null,
                $_SESSION['user_id']
            ]);

            $campaignId = $pdo->lastInsertId();

            // Add recipients
            $recipients = explode("\n", $_POST['recipients']);
            $recipients = array_filter(array_map('trim', $recipients));

            foreach ($recipients as $recipient) {
                $stmt = $pdo->prepare("
                    INSERT INTO messaging_campaign_recipients (campaign_id, recipient)
                    VALUES (?, ?)
                ");
                $stmt->execute([$campaignId, $recipient]);
            }

            // Update total recipients
            $pdo->prepare("UPDATE messaging_campaigns SET total_recipients = ? WHERE id = ?")
                ->execute([count($recipients), $campaignId]);

            // If not scheduled, start immediately
            if (empty($_POST['scheduled_at'])) {
                $pdo->prepare("UPDATE messaging_campaigns SET status = 'processing' WHERE id = ?")
                    ->execute([$campaignId]);

                // Queue messages
                $gateway = \EduCRM\Services\MessagingFactory::create();
                foreach ($recipients as $recipient) {
                    $gateway->queue($recipient, $_POST['message'], [
                        'metadata' => ['campaign_id' => $campaignId]
                    ]);
                }
            }

            redirectWithAlert('campaigns.php', 'Campaign created successfully', 'success');
        } catch (Exception $e) {
            redirectWithAlert('campaigns.php', 'Unable to create campaign. Please check inputs and try again.', 'error');
        }

    }
}

// Get campaigns (branch managers see only their own)
$campaignSql = "
    SELECT c.*, u.name as created_by_name
    FROM messaging_campaigns c
    LEFT JOIN users u ON c.created_by = u.id
";
$campaignParams = [];
if ($isBranchManager) {
    $campaignSql .= " WHERE c.created_by = ?";
    $campaignParams[] = $_SESSION['user_id'];
}
$campaignSql .= " ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($campaignSql);
$stmt->execute($campaignParams);
$campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get templates for dropdown
$stmt = $pdo->query("SELECT id, name FROM messaging_templates WHERE is_active = TRUE");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageDetails = ['title' => 'SMS Campaigns'];
require_once '../../templates/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">SMS Campaigns</h1>
        <p class="text-slate-500 mt-1 text-sm">Create and manage bulk messaging campaigns</p>
    </div>
    <button onclick="showCreateModal()" class="btn btn-primary">
        <?php echo \EduCRM\Services\NavigationService::getIcon('plus', 16); ?> New Campaign
    </button>
</div>

<?php require_once 'tabs.php'; ?>

<?php renderFlashMessage(); ?>

<!-- Campaigns List -->
<div class="card">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-sm">
                    <th class="p-3 font-semibold">Campaign Name</th>
                    <th class="p-3 font-semibold">Status</th>
                    <th class="p-3 font-semibold">Progress</th>
                    <th class="p-3 font-semibold">Stats</th>
                    <th class="p-3 font-semibold">Created</th>
                    <th class="p-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (count($campaigns) > 0): ?>
                    <?php foreach ($campaigns as $campaign):
                        $progress = $campaign['total_recipients'] > 0
                            ? round(($campaign['sent_count'] / $campaign['total_recipients']) * 100)
                            : 0;

                        $statusColors = [
                            'draft' => 'bg-slate-100 text-slate-700',
                            'scheduled' => 'bg-blue-100 text-blue-700',
                            'processing' => 'bg-yellow-100 text-yellow-700',
                            'completed' => 'bg-emerald-100 text-emerald-700',
                            'cancelled' => 'bg-red-100 text-red-700'
                        ];
                        $statusColor = $statusColors[$campaign['status']] ?? 'bg-slate-100 text-slate-700';
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="p-3">
                                <div class="font-medium text-slate-800"><?php echo htmlspecialchars($campaign['name']); ?></div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    <?php echo htmlspecialchars(substr($campaign['message'], 0, 50)) . (strlen($campaign['message']) > 50 ? '...' : ''); ?>
                                </div>
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 <?php echo $statusColor; ?> text-xs font-bold uppercase rounded">
                                    <?php echo $campaign['status']; ?>
                                </span>
                                <?php if ($campaign['scheduled_at'] && $campaign['status'] === 'scheduled'): ?>
                                    <div class="text-xs text-blue-600 mt-1">
                                        <?php echo date('M d, H:i', strtotime($campaign['scheduled_at'])); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 align-middle">
                                <?php if ($campaign['status'] === 'processing' || $campaign['status'] === 'completed'): ?>
                                    <div class="w-24 bg-slate-200 rounded-full h-1.5 mb-1">
                                        <div class="bg-primary-600 h-1.5 rounded-full" style="width: <?php echo $progress; ?>%">
                                        </div>
                                    </div>
                                    <div class="text-xs text-slate-500"><?php echo $progress; ?>%
                                        (<?php echo $campaign['sent_count']; ?>/<?php echo $campaign['total_recipients']; ?>)</div>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-sm">
                                <?php if ($campaign['status'] === 'processing' || $campaign['status'] === 'completed'): ?>
                                    <div class="flex gap-2 text-xs">
                                        <span class="text-emerald-600" title="Delivered">✓
                                            <?php echo $campaign['delivered_count']; ?></span>
                                        <span class="text-red-600" title="Failed">✗ <?php echo $campaign['failed_count']; ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3">
                                <div class="text-sm text-slate-600">
                                    <?php echo htmlspecialchars($campaign['created_by_name']); ?>
                                </div>
                                <div class="text-xs text-slate-400">
                                    <?php echo date('M d, Y', strtotime($campaign['created_at'])); ?>
                                </div>
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex gap-2 justify-end">
                                    <a href="campaign_details.php?id=<?php echo $campaign['id']; ?>" class="action-btn blue"
                                        title="View Details">
                                        <?php echo \EduCRM\Services\NavigationService::getIcon('eye', 16); ?>
                                    </a>
                                    <?php if ($campaign['status'] === 'draft' || $campaign['status'] === 'scheduled'): ?>
                                        <button onclick="cancelCampaign(<?php echo $campaign['id']; ?>)" class="action-btn red"
                                            title="Cancel">
                                            <?php echo \EduCRM\Services\NavigationService::getIcon('x-circle', 16); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="p-8 text-center">
                            <div class="flex flex-col items-center justify-center text-slate-400">
                                <?php echo \EduCRM\Services\NavigationService::getIcon('message-square', 48); ?>
                                <h3 class="mt-2 text-sm font-medium text-slate-900">No Campaigns Yet</h3>
                                <p class="mt-1 text-sm text-slate-500">Create your first SMS campaign to get started.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Campaign Modal -->
<div id="createModal"
    class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 my-8">
        <div class="p-6 border-b border-slate-200 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800">Create SMS Campaign</h2>
            <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="create_campaign">

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Campaign Name *</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        placeholder="Monthly Newsletter">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Message Type *</label>
                    <select name="message_type" required class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        <option value="sms">SMS</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="viber">Viber</option>
                        <option value="push">Push Notification</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Use Template (Optional)</label>
                    <select name="template_id" id="template_select"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg" onchange="loadTemplate()">
                        <option value="">Select template...</option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?php echo $template['id']; ?>">
                                <?php echo htmlspecialchars($template['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Message *</label>
                    <textarea name="message" id="message_content" required rows="5"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        placeholder="Your message here..."></textarea>
                    <p class="text-xs text-slate-500 mt-1" id="char_count">0 characters</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Recipients (one per line) *</label>
                    <textarea name="recipients" required rows="5"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg font-mono text-sm"
                        placeholder="+1234567890&#10;+0987654321"></textarea>
                    <p class="text-xs text-slate-500 mt-1">Enter phone numbers, one per line</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Schedule (Optional)</label>
                    <input type="datetime-local" name="scheduled_at"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <p class="text-xs text-slate-500 mt-1">Leave empty to send immediately</p>
                </div>
            </div>

            <div class="flex gap-3 mt-6 pt-4 border-t border-slate-200">
                <button type="submit" class="btn">Create Campaign</button>
                <button type="button" onclick="closeCreateModal()"
                    class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showCreateModal() {
        document.getElementById('createModal').classList.remove('hidden');
    }

    function closeCreateModal() {
        document.getElementById('createModal').classList.add('hidden');
    }

    function loadTemplate() {
        const templateId = document.getElementById('template_select').value;
        if (!templateId) return;

        fetch(`get_template.php?id=${templateId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('message_content').value = data.content;
                    updateCharCount();
                }
            });
    }

    const messageContent = document.getElementById('message_content');
    if (messageContent) {
        messageContent.addEventListener('input', updateCharCount);
    }

    function updateCharCount() {
        const content = document.getElementById('message_content').value;
        document.getElementById('char_count').textContent = content.length + ' characters';
    }

    function cancelCampaign(campaignId) {
        Modal.show({
            type: 'warning',
            title: 'Cancel Campaign',
            message: 'Are you sure you want to cancel this campaign? This action cannot be undone.',
            confirmText: 'Cancel Campaign',
            onConfirm: () => {
                fetch('cancel_campaign.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${campaignId}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Toast.success('Campaign cancelled successfully');
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            Modal.error(data.message, 'Error');
                        }
                    })
                    .catch(err => Modal.error('Network error: ' + err.message, 'Connection Error'));
            }
        });
    }
</script>

<?php require_once '../../templates/footer.php'; ?>