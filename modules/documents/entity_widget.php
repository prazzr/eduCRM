<?php
/**
 * Entity Documents Widget
 * Displays and manages documents for an entity (inquiry/student/application)
 * Include this file in entity view/edit pages
 * 
 * Required variables:
 * - $entityType: 'inquiry', 'student', or 'application'
 * - $entityId: ID of the entity
 * - $pdo: Database connection
 */

if (!isset($entityType) || !isset($entityId)) {
    return;
}

require_once __DIR__ . '/../../app/services/DocumentService.php';

$documentService = new \EduCRM\Services\DocumentService($pdo);
$entityDocuments = $documentService->getEntityDocuments($entityType, $entityId);
$categories = $documentService->getCategories();
?>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-bold text-slate-800">📁 Documents</h3>
        <a href="../documents/upload.php?entity_type=<?php echo $entityType; ?>&entity_id=<?php echo $entityId; ?>"
            class="btn-secondary px-3 py-2 text-sm rounded-lg">
            + Upload
        </a>
    </div>

    <?php if (count($entityDocuments) > 0): ?>
        <div class="space-y-3">
            <?php foreach ($entityDocuments as $doc):
                $fileIcons = [
                    'pdf' => '📄',
                    'doc' => '📝',
                    'docx' => '📝',
                    'jpg' => '🖼️',
                    'jpeg' => '🖼️',
                    'png' => '🖼️',
                    'xls' => '📊',
                    'xlsx' => '📊'
                ];
                $icon = $fileIcons[$doc['file_type']] ?? '📎';
                $sizeMB = round($doc['file_size'] / 1048576, 2);
                ?>
                <div class="border border-slate-200 rounded-lg p-4 hover:bg-slate-50 transition-colors">
                    <div class="flex items-start gap-3">
                        <div class="text-3xl">
                            <?php echo $icon; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-semibold text-slate-800 truncate">
                                <?php echo htmlspecialchars($doc['file_name']); ?>
                            </h4>

                            <div class="flex items-center gap-2 mt-1">
                                <?php if ($doc['category']): ?>
                                    <span class="px-2 py-0.5 bg-primary-100 text-primary-700 text-xs rounded">
                                        <?php echo $categories[$doc['category']] ?? $doc['category']; ?>
                                    </span>
                                <?php endif; ?>
                                <span class="text-xs text-slate-500">
                                    <?php echo $sizeMB; ?> MB
                                </span>
                                <span class="text-xs text-slate-500">•</span>
                                <span class="text-xs text-slate-500">
                                    <?php echo date('M d, Y', strtotime($doc['created_at'])); ?>
                                </span>
                            </div>

                            <?php if ($doc['description']): ?>
                                <p class="text-sm text-slate-600 mt-2">
                                    <?php echo htmlspecialchars($doc['description']); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="flex gap-2">
                            <a href="../documents/download.php?id=<?php echo $doc['id']; ?>"
                                class="btn-secondary px-3 py-1.5 text-xs rounded" title="Download">
                                ⬇️
                            </a>
                            <?php if (hasRole('admin') || $doc['uploaded_by'] == $_SESSION['user_id']): ?>
                                <button onclick="deleteEntityDocument(<?php echo $doc['id']; ?>)"
                                    class="px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 text-xs rounded font-medium"
                                    title="Delete">
                                    🗑️
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-8">
            <div class="text-4xl mb-2">📁</div>
            <p class="text-slate-500 text-sm mb-3">No documents uploaded yet</p>
            <a href="../documents/upload.php?entity_type=<?php echo $entityType; ?>&entity_id=<?php echo $entityId; ?>"
                class="btn-secondary px-4 py-2 text-sm rounded-lg inline-block">
                Upload First Document
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
    function deleteEntityDocument(documentId) {
        if (!confirm('Are you sure you want to delete this document?')) {
            return;
        }

        fetch('../documents/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${documentId}`
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