<?php
/**
 * Generate Document from Template
 * Replaces variables and creates new document
 */

require_once '../../app/bootstrap.php';


requireLogin();

$documentService = new \EduCRM\Services\DocumentService($pdo);

$templateId = $_GET['template_id'] ?? null;
$entityType = $_GET['entity_type'] ?? null;
$entityId = $_GET['entity_id'] ?? null;

if (!$templateId) {
    die('Template ID required');
}

// Get template
$stmt = $pdo->prepare("SELECT * FROM document_templates WHERE id = ?");
$stmt->execute([$templateId]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    die('Template not found');
}

$variables = json_decode($template['variables'], true) ?? [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get variable values from form
        $variableValues = [];
        foreach ($variables as $var) {
            $variableValues[$var] = $_POST[$var] ?? '';
        }

        // Read template file
        if (!file_exists($template['file_path'])) {
            throw new Exception('Template file not found');
        }

        $content = file_get_contents($template['file_path']);

        // Replace variables
        foreach ($variableValues as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }

        // Generate new filename
        $extension = pathinfo($template['file_path'], PATHINFO_EXTENSION);
        $newFilename = 'generated_' . time() . '.' . $extension;
        $directory = 'uploads/documents/' . ($entityType ?? 'general') . 's/' . ($entityId ?? 'templates') . '/';

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $newFilePath = $directory . $newFilename;
        file_put_contents($newFilePath, $content);

        // Create document record
        $stmt = $pdo->prepare("
            INSERT INTO documents (
                entity_type, entity_id, file_name, file_path,
                file_type, file_size, uploaded_by, category, description
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $entityType ?? 'general',
            $entityId,
            $template['name'] . ' - ' . date('Y-m-d'),
            $newFilePath,
            $extension,
            filesize($newFilePath),
            $_SESSION['user_id'],
            $template['category'],
            'Generated from template: ' . $template['name']
        ]);

        $_SESSION['flash_message'] = 'Document generated successfully';
        $_SESSION['flash_type'] = 'success';

        if ($entityId && $entityType) {
            header("Location: ../documents/manage.php?entity_type=$entityType&entity_id=$entityId");
        } else {
            header("Location: manage.php");
        }
        exit;

    } catch (Exception $e) {
        redirectWithAlert("generate.php?template_id=$templateId" . ($entityType ? "&entity_type=$entityType" : "") . ($entityId ? "&entity_id=$entityId" : ""), 'Error: ' . $e->getMessage(), 'error');
    }
}

$pageDetails = ['title' => 'Generate Document'];
require_once '../../templates/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Generate Document</h1>
        <p class="text-slate-600 mt-1">Fill in the template variables</p>
    </div>

    <?php renderFlashMessage(); ?>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <div class="mb-6">
            <h3 class="font-bold text-slate-800 mb-2">Template:
                <?php echo htmlspecialchars($template['name']); ?>
            </h3>
            <p class="text-sm text-slate-600">
                <?php echo htmlspecialchars($template['description']); ?>
            </p>
        </div>

        <form method="POST">
            <div class="space-y-4">
                <?php foreach ($variables as $var): ?>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            <?php echo ucwords(str_replace('_', ' ', $var)); ?> *
                        </label>
                        <input type="text" name="<?php echo $var; ?>" required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg"
                            placeholder="Enter <?php echo str_replace('_', ' ', $var); ?>">
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="flex gap-3 mt-6 pt-4 border-t border-slate-200">
                <button type="submit" class="btn">Generate Document</button>
                <a href="templates.php" class="btn-secondary px-4 py-2 rounded-lg font-medium">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>