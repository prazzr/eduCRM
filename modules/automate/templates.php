<?php
/**
 * Automation Templates Management
 * WYSIWYG editor for creating and editing notification templates
 */

require_once __DIR__ . '/../../app/bootstrap.php';

// Admin only access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    header('Location: /index.php');
    exit;
}

$automationService = new \EduCRM\Services\AutomationService($pdo);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\EduCRM\CSRFHelper::validateToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            switch ($action) {
                case 'create':
                    $automationService->createTemplate([
                        'name' => $_POST['name'],
                        'template_key' => $_POST['template_key'],
                        'channel' => $_POST['channel'],
                        'subject' => $_POST['subject'] ?: null,
                        'body_html' => $_POST['body_html'] ?: null,
                        'body_text' => $_POST['body_text'],
                        'variables' => array_filter(array_map('trim', explode(',', $_POST['variables']))),
                        'is_system' => false,
                        'is_active' => isset($_POST['is_active']),
                        'created_by' => $_SESSION['user']['id']
                    ]);
                    $message = 'Template created successfully!';
                    $messageType = 'success';
                    break;

                case 'update':
                    $id = (int) $_POST['id'];
                    $automationService->updateTemplate($id, [
                        'name' => $_POST['name'],
                        'subject' => $_POST['subject'] ?: null,
                        'body_html' => $_POST['body_html'] ?: null,
                        'body_text' => $_POST['body_text'],
                        'variables' => array_filter(array_map('trim', explode(',', $_POST['variables']))),
                        'is_active' => isset($_POST['is_active'])
                    ]);
                    $message = 'Template updated successfully!';
                    $messageType = 'success';
                    break;

                case 'delete':
                    $id = (int) $_POST['id'];
                    if ($automationService->deleteTemplate($id)) {
                        $message = 'Template deleted successfully!';
                        $messageType = 'danger';
                    } else {
                        $message = 'Cannot delete system templates.';
                        $messageType = 'error';
                    }
                    break;

                case 'toggle':
                    $id = (int) $_POST['id'];
                    $automationService->toggleTemplate($id);
                    $message = 'Template status updated!';
                    $messageType = 'success';
                    break;
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get filters
$channel = $_GET['channel'] ?? '';
$search = $_GET['search'] ?? '';

// Get templates
$templates = $automationService->getTemplates([
    'channel' => $channel ?: null,
    'search' => $search ?: null
]);

// Get editing template if requested
$editTemplate = null;
if (isset($_GET['edit'])) {
    $editTemplate = $automationService->getTemplate((int) $_GET['edit']);
}

$pageTitle = 'Automation Templates';
include __DIR__ . '/../../templates/header.php';
?>

<!-- TinyMCE CDN -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<div class="p-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Automation Templates</h1>
            <p class="text-slate-600">Manage email, SMS, and WhatsApp notification templates</p>
        </div>
        <button onclick="openCreateModal()"
            class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition-colors flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            New Template
        </button>
    </div>

    <?php if ($message): ?>
        <?php
        $msgClasses = [
            'success' => 'bg-green-50 border-green-200 text-green-700',
            'error' => 'bg-red-50 border-red-200 text-red-700',
            'danger' => 'bg-red-50 border-red-200 text-red-700',
            'warning' => 'bg-orange-50 border-orange-200 text-orange-700',
            'info' => 'bg-blue-50 border-blue-200 text-blue-700'
        ];
        $msgClass = $msgClasses[$messageType] ?? $msgClasses['success'];
        ?>
        <div class="mb-6 p-4 rounded-lg border <?= $msgClass ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Channel</label>
                <select name="channel" class="rounded-lg border-slate-300 text-sm">
                    <option value="">All Channels</option>
                    <option value="email" <?= $channel === 'email' ? 'selected' : '' ?>>Email</option>
                    <option value="sms" <?= $channel === 'sms' ? 'selected' : '' ?>>SMS</option>
                    <option value="whatsapp" <?= $channel === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-slate-700 mb-1">Search</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                    class="w-full rounded-lg border-slate-300 text-sm" placeholder="Search templates...">
            </div>
            <button type="submit"
                class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition-colors">
                Filter
            </button>
            <?php if ($channel || $search): ?>
                <a href="templates.php" class="px-4 py-2 text-slate-600 hover:text-slate-800">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Templates Grid -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($templates as $template): ?>
            <div
                class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-shadow">
                <div class="p-4 border-b border-slate-100">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="font-semibold text-slate-800"><?= htmlspecialchars($template['name']) ?></h3>
                            <p class="text-xs text-slate-500 font-mono"><?= htmlspecialchars($template['template_key']) ?>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php if ($template['is_system']): ?>
                                <span class="px-2 py-0.5 text-xs bg-purple-100 text-purple-700 rounded-full">System</span>
                            <?php endif; ?>
                            <span class="px-2 py-0.5 text-xs rounded-full <?=
                                $template['channel'] === 'email' ? 'bg-blue-100 text-blue-700' :
                                ($template['channel'] === 'sms' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700')
                                ?>">
                                <?= ucfirst($template['channel']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    <?php if ($template['channel'] === 'email' && $template['subject']): ?>
                        <p class="text-sm text-slate-600 mb-2">
                            <span class="font-medium">Subject:</span>
                            <?= htmlspecialchars(substr($template['subject'], 0, 50)) ?>
                            <?= strlen($template['subject']) > 50 ? '...' : '' ?>
                        </p>
                    <?php endif; ?>
                    <p class="text-sm text-slate-500 line-clamp-2">
                        <?= htmlspecialchars(strip_tags(substr($template['body_html'] ?: $template['body_text'], 0, 100))) ?>...
                    </p>

                    <?php
                    $variables = json_decode($template['variables'], true) ?: [];
                    if ($variables):
                        ?>
                        <div class="mt-3 flex flex-wrap gap-1">
                            <?php foreach (array_slice($variables, 0, 4) as $var): ?>
                                <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded">{<?= $var ?>}</span>
                            <?php endforeach; ?>
                            <?php if (count($variables) > 4): ?>
                                <span class="text-xs text-slate-400">+<?= count($variables) - 4 ?> more</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="px-4 py-3 bg-slate-50 flex justify-between items-center">
                    <form method="POST" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= \EduCRM\CSRFHelper::generateToken() ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $template['id'] ?>">
                        <button type="submit"
                            class="flex items-center gap-2 text-sm <?= $template['is_active'] ? 'text-green-600' : 'text-slate-400' ?>">
                            <?php if ($template['is_active']): ?>
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                                </svg>
                                Active
                            <?php else: ?>
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />
                                </svg>
                                Inactive
                            <?php endif; ?>
                        </button>
                    </form>
                    <div class="flex gap-2">
                        <button onclick="editTemplate(<?= $template['id'] ?>)"
                            class="p-2 text-slate-600 hover:text-teal-600 hover:bg-teal-50 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                        <button onclick="previewTemplate(<?= $template['id'] ?>)"
                            class="p-2 text-slate-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                        <?php if (!$template['is_system']): ?>
                            <button
                                onclick="deleteTemplate(<?= $template['id'] ?>, '<?= htmlspecialchars(addslashes($template['name'])) ?>')"
                                class="p-2 text-slate-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($templates)): ?>
            <div class="col-span-full text-center py-12 text-slate-500">
                <svg class="w-12 h-12 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p>No templates found. Create your first template to get started.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="templateModal" class="fixed inset-0 bg-black/50 z-50 hidden" onclick="closeModal(event)">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto"
            onclick="event.stopPropagation()">
            <form id="templateForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?= \EduCRM\CSRFHelper::generateToken() ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="templateId" value="">

                <div class="p-6 border-b border-slate-200">
                    <h2 id="modalTitle" class="text-xl font-bold text-slate-800">New Template</h2>
                </div>

                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Template Name *</label>
                            <input type="text" name="name" id="inputName" required
                                class="w-full rounded-lg border-slate-300" placeholder="Welcome Email">
                        </div>
                        <div id="keyField">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Template Key *</label>
                            <input type="text" name="template_key" id="inputKey" required
                                class="w-full rounded-lg border-slate-300 font-mono text-sm" placeholder="welcome_email"
                                pattern="[a-z0-9_]+" title="Only lowercase letters, numbers, and underscores">
                            <p class="text-xs text-slate-500 mt-1">Unique identifier (lowercase, no spaces)</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div id="channelField">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Channel *</label>
                            <select name="channel" id="inputChannel" required onchange="toggleChannelFields()"
                                class="w-full rounded-lg border-slate-300">
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                                <option value="whatsapp">WhatsApp</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Variables</label>
                            <input type="text" name="variables" id="inputVariables"
                                class="w-full rounded-lg border-slate-300 text-sm" placeholder="name, email, password">
                            <p class="text-xs text-slate-500 mt-1">Comma-separated. Use as {variable_name}</p>
                        </div>
                    </div>

                    <div id="subjectField">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Subject Line</label>
                        <input type="text" name="subject" id="inputSubject" class="w-full rounded-lg border-slate-300"
                            placeholder="Welcome to EduCRM - {name}">
                    </div>

                    <div id="htmlField">
                        <label class="block text-sm font-medium text-slate-700 mb-1">HTML Body</label>
                        <textarea name="body_html" id="inputBodyHtml" rows="12"
                            class="w-full rounded-lg border-slate-300"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Plain Text Body *</label>
                        <textarea name="body_text" id="inputBodyText" required rows="4"
                            class="w-full rounded-lg border-slate-300 font-mono text-sm"
                            placeholder="Plain text version for SMS/WhatsApp or email fallback"></textarea>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" id="inputActive" checked
                            class="rounded border-slate-300 text-teal-600">
                        <label for="inputActive" class="text-sm text-slate-700">Active (template can be used in
                            workflows)</label>
                    </div>
                </div>

                <div class="p-6 border-t border-slate-200 flex justify-end gap-3">
                    <button type="button" onclick="closeModal()"
                        class="px-4 py-2 text-slate-700 hover:bg-slate-100 rounded-lg transition-colors">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition-colors">Save
                        Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="fixed inset-0 bg-black/50 z-50 hidden" onclick="closePreviewModal()">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto"
            onclick="event.stopPropagation()">
            <div class="p-6 border-b border-slate-200 flex justify-between items-center">
                <h2 class="text-xl font-bold text-slate-800">Template Preview</h2>
                <button onclick="closePreviewModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div id="previewContent" class="p-6">
                <!-- Preview content injected here -->
            </div>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= \EduCRM\CSRFHelper::generateToken() ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId" value="">
</form>

<script>
    let editor = null;

    // Templates data for JS
    const templatesData = <?= json_encode(array_column($templates, null, 'id')) ?>;

    // Initialize TinyMCE
    function initTinyMCE() {
        tinymce.init({
            selector: '#inputBodyHtml',
            height: 300,
            plugins: 'link image code table lists',
            toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code',
            menubar: false,
            content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
            setup: function (ed) {
                editor = ed;
            }
        });
    }

    // Open create modal
    function openCreateModal() {
        document.getElementById('modalTitle').textContent = 'New Template';
        document.getElementById('formAction').value = 'create';
        document.getElementById('templateId').value = '';
        document.getElementById('templateForm').reset();
        document.getElementById('keyField').classList.remove('hidden');
        document.getElementById('channelField').classList.remove('hidden');
        document.getElementById('inputActive').checked = true;

        if (editor) {
            editor.setContent('');
        }

        toggleChannelFields();
        document.getElementById('templateModal').classList.remove('hidden');

        // Initialize TinyMCE if not done
        if (!editor) {
            setTimeout(initTinyMCE, 100);
        }
    }

    // Edit template
    function editTemplate(id) {
        const template = templatesData[id];
        if (!template) return;

        document.getElementById('modalTitle').textContent = 'Edit Template';
        document.getElementById('formAction').value = 'update';
        document.getElementById('templateId').value = id;
        document.getElementById('inputName').value = template.name;
        document.getElementById('inputKey').value = template.template_key;
        document.getElementById('inputChannel').value = template.channel;
        document.getElementById('inputSubject').value = template.subject || '';
        document.getElementById('inputBodyText').value = template.body_text || '';
        document.getElementById('inputVariables').value = (JSON.parse(template.variables) || []).join(', ');
        document.getElementById('inputActive').checked = template.is_active == 1;

        // Hide key and channel fields for editing (can't change)
        document.getElementById('keyField').classList.add('hidden');
        document.getElementById('channelField').classList.add('hidden');

        toggleChannelFields();
        document.getElementById('templateModal').classList.remove('hidden');

        // Initialize or update TinyMCE
        if (editor) {
            editor.setContent(template.body_html || '');
        } else {
            document.getElementById('inputBodyHtml').value = template.body_html || '';
            setTimeout(() => {
                initTinyMCE();
                setTimeout(() => {
                    if (editor) editor.setContent(template.body_html || '');
                }, 300);
            }, 100);
        }
    }

    // Toggle fields based on channel
    function toggleChannelFields() {
        const channel = document.getElementById('inputChannel').value;
        const isEmail = channel === 'email';

        document.getElementById('subjectField').classList.toggle('hidden', !isEmail);
        document.getElementById('htmlField').classList.toggle('hidden', !isEmail);
    }

    // Close modal
    function closeModal(event) {
        if (event && event.target !== event.currentTarget) return;
        document.getElementById('templateModal').classList.add('hidden');
    }

    // Preview template
    function previewTemplate(id) {
        const template = templatesData[id];
        if (!template) return;

        let html = '';

        if (template.channel === 'email') {
            html = `
            <div class="mb-4">
                <span class="text-sm font-medium text-slate-500">Subject:</span>
                <p class="text-slate-800">${template.subject || '(no subject)'}</p>
            </div>
            <div class="border rounded-lg p-4 bg-white">
                ${template.body_html || '<p class="text-slate-500 italic">No HTML content</p>'}
            </div>
        `;
        } else {
            html = `
            <div class="bg-slate-100 rounded-lg p-4 font-mono text-sm whitespace-pre-wrap">${template.body_text || '(no content)'}</div>
        `;
        }

        document.getElementById('previewContent').innerHTML = html;
        document.getElementById('previewModal').classList.remove('hidden');
    }

    function closePreviewModal() {
        document.getElementById('previewModal').classList.add('hidden');
    }

    // Delete template
    function deleteTemplate(id, name) {
        if (confirm(`Are you sure you want to delete "${name}"?`)) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteForm').submit();
        }
    }

    // Update TinyMCE content before submit
    document.getElementById('templateForm').addEventListener('submit', function () {
        if (editor) {
            document.getElementById('inputBodyHtml').value = editor.getContent();
        }
    });
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>