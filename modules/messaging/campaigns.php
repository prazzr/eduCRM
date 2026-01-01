<?php
require_once '../../config.php';
require_once '../../includes/services/MessagingFactory.php';

requireLogin();
requireAdminOrCounselor();

MessagingFactory::init($pdo);

// Handle campaign actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_campaign') {
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
            $gateway = MessagingFactory::create();
            foreach ($recipients as $recipient) {
                $gateway->queue($recipient, $_POST['message'], [
                    'metadata' => ['campaign_id' => $campaignId]
                ]);
            }
        }

        $_SESSION['flash_message'] = 'Campaign created successfully';
        $_SESSION['flash_type'] = 'success';
        header('Location: campaigns.php');
        exit;
    }
}

// Get all campaigns
$stmt = $pdo->query("
    SELECT c.*, u.name as created_by_name
    FROM messaging_campaigns c
    LEFT JOIN users u ON c.created_by = u.id
    ORDER BY c.created_at DESC
");
$campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get templates for dropdown
$stmt = $pdo->query("SELECT id, name FROM messaging_templates WHERE is_active = TRUE");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageDetails = ['title' => 'SMS Campaigns'];
require_once '../../includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">📢 SMS Campaigns</h1>
        <p class="text-slate-600 mt-1">Create and manage bulk messaging campaigns</p>
    </div>
    <button onclick="showCreateModal()" class="btn">+ New Campaign</button>
</div>

<?php renderFlashMessage(); ?>

<!-- Campaigns List -->
<?php if (count($campaigns) > 0): ?>
    <div class="space-y-4">
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
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="font-bold text-slate-800 text-lg">
                            <?php echo htmlspecialchars($campaign['name']); ?>
                        </h3>
                        <p class="text-sm text-slate-600 mt-1">
                            Created by
                            <?php echo htmlspecialchars($campaign['created_by_name']); ?> •
                            <?php echo date('M d, Y H:i', strtotime($campaign['created_at'])); ?>
                        </p>
                    </div>
                    <span class="px-3 py-1 <?php echo $statusColor; ?> text-xs font-medium rounded">
                        <?php echo ucfirst($campaign['status']); ?>
                    </span>
                </div>

                <?php if ($campaign['status'] === 'processing' || $campaign['status'] === 'completed'): ?>
                    <div class="mb-4">
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-slate-600">Progress</span>
                            <span class="font-medium text-slate-800">
                                <?php echo $campaign['sent_count']; ?>/
                                <?php echo $campaign['total_recipients']; ?> (
                                <?php echo $progress; ?>%)
                            </span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="bg-primary-600 h-2 rounded-full transition-all" style="width: <?php echo $progress; ?>%">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div class="text-center p-3 bg-emerald-50 rounded-lg">
                            <div class="text-2xl font-bold text-emerald-700">
                                <?php echo $campaign['delivered_count']; ?>
                            </div>
                            <div class="text-xs text-emerald-600">Delivered</div>
                        </div>
                        <div class="text-center p-3 bg-red-50 rounded-lg">
                            <div class="text-2xl font-bold text-red-700">
                                <?php echo $campaign['failed_count']; ?>
                            </div>
                            <div class="text-xs text-red-600">Failed</div>
                        </div>
                        <div class="text-center p-3 bg-slate-50 rounded-lg">
                            <div class="text-2xl font-bold text-slate-700">$
                                <?php echo number_format($campaign['total_cost'], 2); ?>
                            </div>
                            <div class="text-xs text-slate-600">Total Cost</div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($campaign['scheduled_at'] && $campaign['status'] === 'scheduled'): ?>
                    <div class="mb-4 p-3 bg-blue-50 rounded-lg">
                        <p class="text-sm text-blue-700">
                            📅 Scheduled for:
                            <?php echo date('M d, Y H:i', strtotime($campaign['scheduled_at'])); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <div class="text-sm text-slate-600 mb-4">
                    <strong>Message:</strong>
                    <?php echo htmlspecialchars(substr($campaign['message'], 0, 150)); ?>
                    <?php echo strlen($campaign['message']) > 150 ? '...' : ''; ?>
                </div>

                <div class="flex gap-2">
                    <?php if ($campaign['status'] === 'draft' || $campaign['status'] === 'scheduled'): ?>
                        <button onclick="cancelCampaign(<?php echo $campaign['id']; ?>)"
                            class="btn-secondary px-4 py-2 text-xs rounded-lg">
                            Cancel
                        </button>
                    <?php endif; ?>
                    <a href="campaign_details.php?id=<?php echo $campaign['id']; ?>"
                        class="btn-secondary px-4 py-2 text-xs rounded-lg">
                        View Details
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-12 text-center">
        <div class="text-6xl mb-4">📢</div>
        <h3 class="text-lg font-semibold text-slate-800 mb-2">No Campaigns Yet</h3>
        <p class="text-slate-600 mb-4">Create your first SMS campaign</p>
        <button onclick="showCreateModal()" class="btn inline-block">+ New Campaign</button>
    </div>
<?php endif; ?>

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
        if (!confirm('Are you sure you want to cancel this campaign?')) return;

        fetch('cancel_campaign.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${campaignId}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }
</script>

<?php require_once '../../includes/footer.php'; ?>