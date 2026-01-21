<?php
require_once '../../app/bootstrap.php';


requireLogin();
requireAdminCounselorOrBranchManager();

$documentService = new \EduCRM\Services\DocumentService($pdo);

// Get filter parameters
$entityType = $_GET['entity_type'] ?? null;
$entityId = $_GET['entity_id'] ?? null;
$category = $_GET['category'] ?? null;
$search = $_GET['search'] ?? null;

// Get documents
if ($entityType && $entityId) {
    $documents = $documentService->getEntityDocuments($entityType, $entityId, $category);
} else {
    // Get all documents (admin view)
    $sql = "SELECT d.*, u.name as uploaded_by_name FROM documents d LEFT JOIN users u ON d.uploaded_by = u.id WHERE 1=1";
    $params = [];

    if ($category) {
        $sql .= " AND d.category = ?";
        $params[] = $category;
    }

    if ($search) {
        $sql .= " AND (d.file_name LIKE ? OR d.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY d.created_at DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$categories = $documentService->getCategories();

$pageDetails = ['title' => 'Document Management'];
require_once '../../templates/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">📁 Document Management</h1>
        <p class="text-slate-600 mt-1">Manage all uploaded documents</p>
    </div>
    <a href="upload.php<?php echo $entityId ? "?entity_type=$entityType&entity_id=$entityId" : ''; ?>" class="btn">
        + Upload Document
    </a>
</div>

<?php renderFlashMessage(); ?>

<!-- Filters -->
<div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6">
    <form method="GET" class="flex flex-wrap gap-3">
        <?php if ($entityType && $entityId): ?>
            <input type="hidden" name="entity_type" value="<?php echo $entityType; ?>">
            <input type="hidden" name="entity_id" value="<?php echo $entityId; ?>">
        <?php endif; ?>

        <select name="category" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">All Categories</option>
            <?php foreach ($categories as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo $category === $key ? 'selected' : ''; ?>>
                    <?php echo $label; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="text" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>"
            placeholder="Search documents..."
            class="px-3 py-2 border border-slate-300 rounded-lg text-sm flex-1 min-w-[200px]">

        <button type="submit" class="btn-secondary px-4 py-2 rounded-lg text-sm">Apply Filters</button>
        <a href="manage.php<?php echo $entityId ? "?entity_type=$entityType&entity_id=$entityId" : ''; ?>" class="px-4
            py-2 text-sm text-slate-600 hover:text-slate-800">Clear</a>
    </form>
</div>

<!-- Documents Grid -->
<?php if (count($documents) > 0): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php foreach ($documents as $doc):
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
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                <div class="p-6">
                    <!-- File Icon & Type -->
                    <div class="flex items-start justify-between mb-4">
                        <div class="text-5xl">
                            <?php echo $icon; ?>
                        </div>
                        <span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-medium rounded uppercase">
                            <?php echo $doc['file_type']; ?>
                        </span>
                    </div>

                    <!-- File Name -->
                    <h3 class="font-semibold text-slate-800 mb-2 truncate"
                        title="<?php echo htmlspecialchars($doc['file_name']); ?>">
                        <?php echo htmlspecialchars($doc['file_name']); ?>
                    </h3>

                    <!-- Category -->
                    <?php if ($doc['category']): ?>
                        <span class="inline-block px-2 py-0.5 bg-primary-100 text-primary-700 text-xs rounded mb-3">
                            <?php echo $categories[$doc['category']] ?? $doc['category']; ?>
                        </span>
                    <?php endif; ?>

                    <!-- Description -->
                    <?php if ($doc['description']): ?>
                        <p class="text-sm text-slate-600 mb-3 line-clamp-2">
                            <?php echo htmlspecialchars($doc['description']); ?>
                        </p>
                    <?php endif; ?>

                    <!-- Meta Info -->
                    <div class="text-xs text-slate-500 space-y-1 mb-4">
                        <div>📦 Size:
                            <?php echo $sizeMB; ?> MB
                        </div>
                        <div>📅
                            <?php echo date('M d, Y', strtotime($doc['created_at'])); ?>
                        </div>
                        <div>👤
                            <?php echo htmlspecialchars($doc['uploaded_by_name']); ?>
                        </div>
                        <div>⬇️ Downloads:
                            <?php echo $doc['download_count']; ?>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <a href="download.php?id=<?php echo $doc['id']; ?>"
                            class="flex-1 btn-secondary px-3 py-2 text-xs rounded-lg text-center">
                            Download
                        </a>
                        <button onclick="showVersionHistory(<?php echo $doc['id']; ?>)"
                            class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs rounded-lg font-medium"
                            title="Version History">
                            📋
                        </button>
                        <?php if (hasRole('admin') || $doc['uploaded_by'] == $_SESSION['user_id']): ?>
                            <button onclick="deleteDocument(<?php echo $doc['id']; ?>)"
                                class="px-3 py-2 bg-red-100 hover:bg-red-200 text-red-700 text-xs rounded-lg font-medium"
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
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-12 text-center">
        <div class="text-6xl mb-4">📁</div>
        <h3 class="text-lg font-semibold text-slate-800 mb-2">No Documents Found</h3>
        <p class="text-slate-600 mb-4">Upload your first document to get started</p>
        <a href="upload.php<?php echo $entityId ? "?entity_type=$entityType&entity_id=$entityId" : ''; ?>" class="btn
        inline-block">
            + Upload Document
        </a>
    </div>
<?php endif; ?>

<!-- Version History Modal -->
<div id="versionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[80vh] overflow-hidden">
        <div class="p-6 border-b border-slate-200 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800">Version History</h2>
            <button onclick="closeVersionModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>
        <div id="versionContent" class="p-6 overflow-y-auto max-h-[60vh]">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<script>
    function showVersionHistory(documentId) {
        document.getElementById('versionModal').classList.remove('hidden');
        document.getElementById('versionContent').innerHTML = '<p class="text-center text-slate-500">Loading...</p>';

        fetch(`get_versions.php?id=${documentId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '';
                    if (data.versions.length > 0) {
                        html = '<div class="space-y-3">';
                        data.versions.forEach(version => {
                            html += `
                            <div class="border border-slate-200 rounded-lg p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <span class="font-semibold text-slate-800">Version ${version.version_number}</span>
                                        <p class="text-sm text-slate-600 mt-1">${version.change_notes || 'No notes'}</p>
                                    </div>
                                    <a href="download.php?id=${documentId}&version=${version.version_number}" class="btn-secondary px-3 py-1 text-xs rounded">
                                        Download
                                    </a>
                                </div>
                                <div class="text-xs text-slate-500">
                                    ${new Date(version.created_at).toLocaleString()} • ${version.uploaded_by_name}
                                </div>
                            </div>
                        `;
                        });
                        html += '</div>';
                    } else {
                        html = '<p class="text-center text-slate-500">No version history available</p>';
                    }
                    document.getElementById('versionContent').innerHTML = html;
                }
            })
            .catch(error => {
                document.getElementById('versionContent').innerHTML = '<p class="text-center text-red-500">Error loading versions</p>';
            });
    }

    function closeVersionModal() {
        document.getElementById('versionModal').classList.add('hidden');
    }

    function deleteDocument(documentId) {
        Modal.show({
            type: 'error',
            title: 'Delete Document?',
            message: 'Are you sure you want to delete this document? This action cannot be undone.',
            confirmText: 'Yes, Delete It',
            onConfirm: function () {
                fetch('delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${documentId}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // For consistency, we could reload which will show flash if we set it, 
                            // but here we just reload. Ideally we'd use a toast, but alert is acceptable for AJAX response for now, 
                            // or better, simple reload. 
                            // Let's use a simple reload to refresh the grid.
                            window.location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    });
            }
        });
    }
</script>

<?php require_once '../../templates/footer.php'; ?>