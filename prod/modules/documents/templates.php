<?php
require_once '../../app/bootstrap.php';


requireLogin();
requireBranchManager();

$documentService = new \EduCRM\Services\DocumentService($pdo);

// Handle template creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_template') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO document_templates (name, description, file_path, category, variables, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $variables = json_encode($_POST['variables'] ?? []);

            $result = $stmt->execute([
                $_POST['name'],
                $_POST['description'],
                $_POST['file_path'],
                $_POST['category'],
                $variables,
                $_SESSION['user_id']
            ]);

            if ($result) {
                redirectWithAlert('templates.php', 'Template created successfully', 'success');
            }
        } catch (Exception $e) {
            redirectWithAlert('templates.php', 'Error: ' . $e->getMessage(), 'error');
        }
    }
}

// Get all templates
$stmt = $pdo->query("
    SELECT t.*, u.name as created_by_name
    FROM document_templates t
    LEFT JOIN users u ON t.created_by = u.id
    ORDER BY t.created_at DESC
");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = $documentService->getCategories();

$pageDetails = ['title' => 'Document Templates'];
require_once '../../templates/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">📋 Document Templates</h1>
        <p class="text-slate-600 mt-1">Manage reusable document templates</p>
    </div>
    <button onclick="showCreateModal()" class="btn">+ Create Template</button>
</div>

<?php renderFlashMessage(); ?>

<!-- Templates Grid -->
<?php if (count($templates) > 0): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($templates as $template):
            $variables = json_decode($template['variables'], true) ?? [];
            ?>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="text-4xl">📄</div>
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
                        <?php echo $categories[$template['category']] ?? $template['category']; ?>
                    </span>
                <?php endif; ?>

                <p class="text-sm text-slate-600 mb-4">
                    <?php echo htmlspecialchars($template['description']); ?>
                </p>

                <?php if (count($variables) > 0): ?>
                    <div class="mb-4">
                        <p class="text-xs font-medium text-slate-700 mb-2">Variables:</p>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($variables as $var): ?>
                                <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-xs rounded">{
                                    <?php echo $var; ?>}
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="text-xs text-slate-500 mb-4">
                    Created by
                    <?php echo htmlspecialchars($template['created_by_name']); ?>
                </div>

                <div class="flex gap-2">
                    <button onclick="generateFromTemplate(<?php echo $template['id']; ?>)"
                        class="flex-1 btn-secondary px-3 py-2 text-xs rounded-lg">
                        Generate
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
        <div class="text-6xl mb-4">📋</div>
        <h3 class="text-lg font-semibold text-slate-800 mb-2">No Templates Yet</h3>
        <p class="text-slate-600 mb-4">Create your first document template</p>
        <button onclick="showCreateModal()" class="btn inline-block">+ Create Template</button>
    </div>
<?php endif; ?>

<!-- Create Template Modal -->
<div id="createModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4">
        <div class="p-6 border-b border-slate-200 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800">Create Template</h2>
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
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Template Name *</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        placeholder="e.g., Offer Letter Template">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Description</label>
                    <textarea name="description" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        placeholder="Template description..."></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Category *</label>
                    <select name="category" required class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                        <option value="">Select category...</option>
                        <?php foreach ($categories as $key => $label): ?>
                            <option value="<?php echo $key; ?>">
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">File Path *</label>
                    <input type="text" name="file_path" required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        placeholder="uploads/templates/offer_letter.docx">
                    <p class="text-xs text-slate-500 mt-1">Path to template file on server</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Variables (comma-separated)</label>
                    <input type="text" id="variablesInput" class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                        placeholder="student_name, course, start_date">
                    <p class="text-xs text-slate-500 mt-1">Variables that can be replaced in the template</p>
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

<script>
    function showCreateModal() {
        document.getElementById('createModal').classList.remove('hidden');
    }

    function closeCreateModal() {
        document.getElementById('createModal').classList.add('hidden');
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

    function generateFromTemplate(templateId) {
        window.location.href = `generate.php?template_id=${templateId}`;
    }

    // Convert comma-separated variables to array before submit
    document.querySelector('form').addEventListener('submit', function (e) {
        const variablesInput = document.getElementById('variablesInput').value;
        const variables = variablesInput.split(',').map(v => v.trim()).filter(v => v);

        // Create hidden inputs for each variable
        variables.forEach((v, i) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `variables[${i}]`;
            input.value = v;
            this.appendChild(input);
        });
    });
</script>

<?php require_once '../../templates/footer.php'; ?>