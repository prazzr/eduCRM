<?php
/**
 * Edit Email Template
 * Allows admins to customize email notification templates
 */

require_once '../../app/bootstrap.php';

requireLogin();
requireAdmin();

$templateId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Get template
$stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
$stmt->execute([$templateId]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    redirectWithAlert('templates.php', 'Template not found.', 'error');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body_html = $_POST['body_html'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($subject) || empty($body_html)) {
        redirectWithAlert("edit_template.php?id={$templateId}", 'Subject and body are required.', 'error');
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE email_templates 
            SET name = ?, description = ?, subject = ?, body_html = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $subject, $body_html, $is_active, $templateId]);

        redirectWithAlert('templates.php', 'Template updated successfully!', 'success');
    } catch (Exception $e) {
        redirectWithAlert("edit_template.php?id={$templateId}", 'Error saving template: ' . $e->getMessage(), 'error');
    }
}

$pageDetails = ['title' => 'Edit Template - ' . htmlspecialchars($template['name'])];
require_once '../../templates/header.php';

// Parse variables from JSON
$variables = json_decode($template['variables'] ?? '[]', true) ?: [];
?>

<div class="page-header">
    <div>
        <div class="flex items-center gap-2 mb-1">
            <a href="templates.php" class="text-slate-400 hover:text-slate-600">
                <?php echo \EduCRM\Services\NavigationService::getIcon('arrow-left', 18); ?>
            </a>
            <h1 class="page-title">Edit Template</h1>
        </div>
        <p class="text-slate-500 text-sm">
            <?php echo htmlspecialchars($template['template_key']); ?>
        </p>
    </div>
    <div class="flex items-center gap-3">
        <button type="button" onclick="previewTemplate()" class="btn btn-secondary">
            <?php echo \EduCRM\Services\NavigationService::getIcon('eye', 16); ?> Preview
        </button>
    </div>
</div>

<?php renderFlashMessage(); ?>

<form method="POST" id="templateForm">
    <div class="grid grid-cols-12 gap-6">
        <!-- Main Editor -->
        <div class="col-span-8">
            <div class="card">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                    <h2 class="text-lg font-semibold text-slate-800">Template Content</h2>
                </div>
                <div class="p-6 space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Template Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($template['name']); ?>"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Description</label>
                        <input type="text" name="description"
                            value="<?php echo htmlspecialchars($template['description'] ?? ''); ?>"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Subject Line <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="subject" required
                            value="<?php echo htmlspecialchars($template['subject']); ?>"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary font-mono text-sm">
                        <p class="mt-1 text-xs text-slate-500">You can use variables like {name} in the subject</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            HTML Body <span class="text-red-500">*</span>
                        </label>
                        <textarea name="body_html" id="body_html" required rows="20"
                            class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary font-mono text-sm"><?php echo htmlspecialchars($template['body_html']); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-span-4 space-y-6">
            <!-- Variables -->
            <div class="card">
                <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
                    <h3 class="font-semibold text-slate-800">Available Variables</h3>
                </div>
                <div class="p-4">
                    <p class="text-xs text-slate-500 mb-3">Click to insert into body</p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($variables as $var): ?>
                            <button type="button" onclick="insertVariable('<?php echo $var; ?>')"
                                class="px-2 py-1 text-xs font-mono bg-primary-50 text-primary-700 rounded hover:bg-primary-100 transition-colors">
                                {
                                <?php echo $var; ?>}
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Settings -->
            <div class="card">
                <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
                    <h3 class="font-semibold text-slate-800">Settings</h3>
                </div>
                <div class="p-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" <?php echo $template['is_active'] ? 'checked' : ''; ?>
                        class="h-4 w-4 text-primary focus:ring-primary border-slate-300 rounded">
                        <span class="ml-3 text-sm text-slate-700">Template is active</span>
                    </label>
                    <p class="mt-2 text-xs text-slate-500">Inactive templates will use the default system template.</p>
                </div>
            </div>

            <!-- Info -->
            <div class="card">
                <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
                    <h3 class="font-semibold text-slate-800">Template Info</h3>
                </div>
                <div class="p-4 text-sm space-y-2">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Key:</span>
                        <span class="font-mono text-slate-700">
                            <?php echo htmlspecialchars($template['template_key']); ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Created:</span>
                        <span class="text-slate-700">
                            <?php echo date('M j, Y', strtotime($template['created_at'])); ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Updated:</span>
                        <span class="text-slate-700">
                            <?php echo date('M j, Y H:i', strtotime($template['updated_at'])); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-col gap-3">
                <button type="submit" class="btn btn-primary w-full">
                    <?php echo \EduCRM\Services\NavigationService::getIcon('save', 16); ?> Save Changes
                </button>
                <a href="templates.php" class="btn btn-secondary w-full text-center">Cancel</a>
            </div>
        </div>
    </div>
</form>

<!-- Preview Modal -->
<div id="previewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border border-slate-200 w-full max-w-3xl shadow-lg rounded-xl bg-white">
        <div class="flex justify-between items-center border-b border-slate-200 pb-3">
            <h3 class="text-lg font-semibold text-slate-800">Template Preview</h3>
            <button onclick="closePreviewModal()" class="text-slate-400 hover:text-slate-600">
                <?php echo \EduCRM\Services\NavigationService::getIcon('x', 24); ?>
            </button>
        </div>
        <div class="mt-4">
            <div class="mb-4 p-3 bg-slate-50 rounded-lg">
                <span class="text-xs text-slate-500 uppercase tracking-wider">Subject:</span>
                <p id="previewSubject" class="font-medium text-slate-800 mt-1"></p>
            </div>
            <div id="previewContent"
                class="max-h-[60vh] overflow-y-auto border border-slate-200 rounded-lg p-4 bg-white">
                <!-- Preview content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
    function insertVariable(varName) {
        const textarea = document.getElementById('body_html');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        const variable = '{' + varName + '}';

        textarea.value = text.substring(0, start) + variable + text.substring(end);
        textarea.focus();
        textarea.setSelectionRange(start + variable.length, start + variable.length);
    }

    function previewTemplate() {
        const subject = document.querySelector('input[name="subject"]').value;
        const body = document.getElementById('body_html').value;

        // Replace variables with sample values
        const sampleData = {
            name: 'John Doe',
            email: 'john@example.com',
            password: 'temp123',
            login_url: '#',
            task_title: 'Sample Task',
            task_description: 'This is a sample task description.',
            priority: 'HIGH',
            due_date: 'January 15, 2026',
            task_url: '#',
            days_overdue: '3',
            appointment_title: 'Counseling Session',
            client_name: 'Jane Smith',
            counselor_name: 'Dr. Williams',
            appointment_date: 'January 10, 2026 at 2:00 PM',
            location: 'Room 101',
            meeting_link: 'https://meet.example.com/abc',
            appointment_url: '#',
            application_title: 'Student Visa - USA',
            old_stage: 'Document Collection',
            new_stage: 'Application Filed',
            updated_at: 'January 8, 2026 1:30 PM',
            workflow_url: '#',
            document_name: 'Passport Copy',
            status: 'Verified',
            status_color: '#10b981',
            remarks: '',
            documents_url: '#',
            course_name: 'IELTS Preparation',
            start_date: 'January 20, 2026',
            instructor: 'Prof. Smith',
            schedule: 'Mon, Wed, Fri 10:00 AM',
            course_url: '#',
            changes: '<ul><li>Phone: +1234567890 → +0987654321</li></ul>',
            profile_url: '#'
        };

        let previewSubject = subject;
        let previewBody = body;

        for (const [key, value] of Object.entries(sampleData)) {
            const regex = new RegExp('\\{' + key + '\\}', 'g');
            previewSubject = previewSubject.replace(regex, value);
            previewBody = previewBody.replace(regex, value);
        }

        document.getElementById('previewSubject').textContent = previewSubject;
        document.getElementById('previewContent').innerHTML = previewBody;
        document.getElementById('previewModal').classList.remove('hidden');
    }

    function closePreviewModal() {
        document.getElementById('previewModal').classList.add('hidden');
    }

    document.getElementById('previewModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closePreviewModal();
        }
    });
</script>

<?php require_once '../../templates/footer.php'; ?>