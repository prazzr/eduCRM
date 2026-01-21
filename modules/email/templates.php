<?php
/**
 * Email Templates Management
 * View and manage email notification templates
 */

require_once '../../app/bootstrap.php';

requireLogin();
requireAdmin();

$pageDetails = ['title' => 'Email Templates'];

// Load templates from database
$templates = [];
try {
    $stmt = $pdo->query("SELECT * FROM email_templates ORDER BY name ASC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist - show empty state
    $templates = [];
}

require_once '../../templates/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Email Templates</h1>
        <p class="text-slate-500 mt-1 text-sm">Customize email notification templates</p>
    </div>
    <div class="flex gap-3">
        <a href="add_template.php" class="btn btn-primary">
            <?php echo \EduCRM\Services\NavigationService::getIcon('plus', 16); ?> Create Template
        </a>
        <a href="queue.php" class="btn btn-secondary">
            <?php echo \EduCRM\Services\NavigationService::getIcon('arrow-left', 16); ?> Back to Queue
        </a>
    </div>
</div>

<?php renderFlashMessage(); ?>

<?php if (empty($templates)): ?>
    <div class="card p-12 text-center">
        <div class="flex flex-col items-center justify-center text-slate-400">
            <?php echo \EduCRM\Services\NavigationService::getIcon('mail', 48); ?>
            <p class="mt-4 text-lg font-medium text-slate-500">No templates found</p>
            <p class="mt-1 text-sm text-slate-400">Run the email_templates migration to seed default templates.</p>
        </div>
    </div>
<?php else: ?>
    <!-- Templates Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php foreach ($templates as $template):
            $variables = json_decode($template['variables'] ?? '[]', true) ?: [];
            ?>
            <div class="card hover:shadow-md transition-shadow <?php echo !$template['is_active'] ? 'opacity-60' : ''; ?>">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-800">
                                <?php echo htmlspecialchars($template['name']); ?>
                            </h3>
                            <?php if (!$template['is_active']): ?>
                                <span class="text-xs text-amber-600 font-medium">Inactive</span>
                            <?php endif; ?>
                        </div>
                        <span
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800">
                            <?php echo htmlspecialchars($template['template_key']); ?>
                        </span>
                    </div>
                </div>
                <div class="p-6">
                    <p class="text-sm text-slate-600 mb-4">
                        <?php echo htmlspecialchars($template['description'] ?? 'No description'); ?>
                    </p>

                    <div class="mb-4">
                        <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Subject</span>
                        <p class="text-sm text-slate-700 mt-1 font-mono bg-slate-50 px-2 py-1 rounded">
                            <?php echo htmlspecialchars($template['subject']); ?>
                        </p>
                    </div>

                    <div>
                        <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Available Variables</span>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <?php foreach ($variables as $var): ?>
                                <span
                                    class="inline-flex items-center px-2 py-1 rounded text-xs font-mono bg-slate-100 text-slate-700">
                                    {<?php echo htmlspecialchars($var); ?>}
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
                    <span class="text-xs text-slate-400">
                        Updated: <?php echo date('M j, Y', strtotime($template['updated_at'])); ?>
                    </span>
                    <div class="flex gap-2">
                        <button onclick="previewTemplate(<?php echo $template['id']; ?>)"
                            class="text-sm text-slate-600 hover:text-slate-800 font-medium">
                            Preview
                        </button>
                        <a href="add_template.php?id=<?php echo $template['id']; ?>"
                            class="text-sm text-primary hover:text-primary-hover font-medium">
                            Edit →
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

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
    // Store templates for preview
    const templates = <?php echo json_encode(array_column($templates, null, 'id')); ?>;

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

    function previewTemplate(templateId) {
        const template = templates[templateId];
        if (!template) return;

        let previewSubject = template.subject;
        let previewBody = template.body_html;

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