<?php
require_once '../../config.php';
require_once '../../includes/services/MessagingFactory.php';

requireLogin();
requireAdminOrCounselor();

MessagingFactory::init($pdo);

// Handle template actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_template') {
        $variables = [];
        if (!empty($_POST['variables'])) {
            $variables = array_map('trim', explode(',', $_POST['variables']));
        }

        $stmt = $pdo->prepare("
            INSERT INTO messaging_templates (name, message_type, category, subject, content, variables, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['name'],
            $_POST['message_type'],
            $_POST['category'],
            $_POST['subject'] ?? null,
            $_POST['content'],
            json_encode($variables),
            $_SESSION['user_id']
        ]);

        $_SESSION['flash_message'] = 'Template created successfully';
        $_SESSION['flash_type'] = 'success';
        header('Location: templates.php');
        exit;
    }
}

// Get all templates
$stmt = $pdo->query("
    SELECT t.*, u.name as created_by_name
    FROM messaging_templates t
    LEFT JOIN users u ON t.created_by = u.id
    ORDER BY t.created_at DESC
");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageDetails = ['title' => 'Message Templates'];
require_once '../../includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">📝 Message Templates</h1>
        <p class="text-slate-600 mt-1">Manage reusable message templates</p>
    </div>
    <button onclick="showCreateModal()" class="btn">+ Create Template</button>
</div>

<?php renderFlashMessage(); ?>

<!-- Templates Grid -->
<?php if (count($templates) > 0): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($templates as $template):
            $variables = json_decode($template['variables'], true) ?? [];
            $icons = ['sms' => '📱', 'whatsapp' => '💬', 'viber' => '📞', 'email' => '📧'];
            $icon = $icons[$template['message_type']] ?? '📝';
            ?>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="text-4xl">
                        <?php echo $icon; ?>
                    </div>
                    <span
                        class="px-2 py-1 <?php echo $template['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'; ?> text-xs font-medium rounded">
                        <?php echo $template['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>

                <h3 class="font-bold text-slate-800 mb-2">
                    <?php echo htmlspecialchars($template['name']); ?>
                </h3>

                <?php if ($template['category']): ?>
                    <span class="inline-block px-2 py-0.5 bg-primary-100 text-primary-700 text-xs rounded mb-3">
                        <?php echo ucfirst($template['category']); ?>
                    </span>
                <?php endif; ?>

                <p class="text-sm text-slate-600 mb-4 line-clamp-3">
                    <?php echo htmlspecialchars($template['content']); ?>
                </p>

                <?php if (count($variables) > 0): ?>
                    <div class="mb-4">
                        <p class="text-xs font-medium text-slate-700 mb-2">Variables:</p>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($variables as $var): ?>
                                <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-xs rounded font-mono">{
                                    <?php echo $var; ?>}
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="text-xs text-slate-500 mb-4">
                    Used
                    <?php echo number_format($template['usage_count']); ?> times
                </div>

                <div class="flex gap-2">
                    <button onclick="previewTemplate(<?php echo $template['id']; ?>)"
                        class="flex-1 btn-secondary px-3 py-2 text-xs rounded-lg">
                        Preview
                    </button>
                    <button
                        onclick="toggleTemplate(<?php echo $template['id']; ?>, <?php echo $template['is_active'] ? 'false' : 'true'; ?>)"
                        class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs rounded-lg font-medium">
                        <?php echo $template['is_active'] ? 'Disable' : 'Enable'; ?>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-12 text-center">
        <div class="text-6xl mb-4">📝</div>
        <h3 class="text-lg font-semibold text-slate-800 mb-2">No Templates Yet</h3>
        <p class="text-slate-600 mb-4">Create your first message template</p>
        <button onclick="showCreateModal()" class="btn inline-block">+ Create Template</button>
    </div>
<?php endif; ?>

<!-- Create Template Modal -->
<div id="createModal"
    class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 my-8">
        <div class="p-6 border-b border-slate-200 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800">Create Message Template</h2>
            <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="create_template">

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Template Name *</label>
                        <input type="text" name="name" required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                            placeholder="Appointment Reminder">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Message Type *</label>
                        <select name="message_type" required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                            <option value="sms">SMS</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="viber">Viber</option>
                            <option value="email">Email</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Category</label>
                    <select name="category" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        <option value="">Select category...</option>
                        <option value="appointment">Appointment</option>
                        <option value="task">Task</option>
                        <option value="welcome">Welcome</option>
                        <option value="reminder">Reminder</option>
                        <option value="application">Application</option>
                        <option value="payment">Payment</option>
                        <option value="general">General</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Subject (for Email/WhatsApp)</label>
                    <input type="text" name="subject" class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        placeholder="Optional subject line">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Message Content *</label>
                    <textarea name="content" required rows="5"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        placeholder="Hi {name}, your appointment is on {date} at {time}..."></textarea>
                    <p class="text-xs text-slate-500 mt-1">Use {variable_name} for dynamic content</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Variables (comma-separated)</label>
                    <input type="text" name="variables" class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        placeholder="name, date, time, location">
                    <p class="text-xs text-slate-500 mt-1">List all variables used in the message</p>
                </div>
            </div>

            <div class="flex gap-3 mt-6 pt-4 border-t border-slate-200">
                <button type="submit" class="btn">Create Template</button>
                <button type="button" onclick="closeCreateModal()"
                    class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4">
        <div class="p-6 border-b border-slate-200 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800">Template Preview</h2>
            <button onclick="closePreviewModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>
        <div id="previewContent" class="p-6">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<script>
    function showCreateModal() {
        document.getElementById('createModal').classList.remove('hidden');
    }

    function closeCreateModal() {
        document.getElementById('createModal').classList.add('hidden');
    }

    function previewTemplate(templateId) {
        document.getElementById('previewModal').classList.remove('hidden');
        document.getElementById('previewContent').innerHTML = '<p class="text-center text-slate-500">Loading...</p>';

        fetch(`preview_template.php?id=${templateId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('previewContent').innerHTML = html;
            });
    }

    function closePreviewModal() {
        document.getElementById('previewModal').classList.add('hidden');
    }

    function toggleTemplate(templateId, activate) {
        fetch('toggle_template.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${templateId}&active=${activate}`
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