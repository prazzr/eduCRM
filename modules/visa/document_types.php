<?php
/**
 * Document Types Management
 * Admin interface to add/edit/delete document types for visa checklist
 */

require_once __DIR__ . '/../../app/bootstrap.php';
requireLogin();
requireAdminCounselorOrBranchManager();

$page_title = 'Document Types';
$current_module = 'visa';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        $code = sanitize($_POST['code'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $is_required = isset($_POST['is_required']) ? 1 : 0;
        $display_order = (int) ($_POST['display_order'] ?? 0);

        // Generate code from name if not provided
        if (empty($code)) {
            $code = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $name));
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO document_types (name, code, description, is_required_default, display_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $code, $description, $is_required, $display_order]);
            redirectWithAlert('document_types.php', 'Document type added successfully!', 'success');
        } catch (PDOException $e) {
            redirectWithAlert('document_types.php', 'Failed to add document type: ' . ($e->getCode() == 23000 ? 'Code already exists' : $e->getMessage()), 'error');
        }
    } elseif ($action === 'edit') {
        $id = (int) $_POST['id'];
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $is_required = isset($_POST['is_required']) ? 1 : 0;
        $display_order = (int) ($_POST['display_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE document_types SET name = ?, description = ?, is_required_default = ?, display_order = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$name, $description, $is_required, $display_order, $is_active, $id]);
        redirectWithAlert('document_types.php', 'Document type updated!', 'warning');
    } elseif ($action === 'delete') {
        $id = (int) $_POST['id'];

        // Check if any documents use this type
        $check = $pdo->prepare("SELECT COUNT(*) FROM student_documents WHERE document_type_id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            redirectWithAlert('document_types.php', 'Cannot delete: Documents of this type exist. Deactivate instead.', 'error');
        } else {
            $stmt = $pdo->prepare("DELETE FROM document_types WHERE id = ?");
            $stmt->execute([$id]);
            redirectWithAlert('document_types.php', 'Document type deleted!', 'danger');
        }
    }
}

// Fetch all document types
$types = $pdo->query("SELECT * FROM document_types ORDER BY display_order ASC, name ASC")->fetchAll();

require_once '../../templates/header.php';
?>

<style>
    .doc-types-table {
        width: 100%;
        border-collapse: collapse;
    }

    .doc-types-table th,
    .doc-types-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
    }

    .doc-types-table th {
        background: var(--surface);
        font-weight: 600;
    }

    .badge-required {
        background: #22c55e;
        color: white;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 12px;
    }

    .badge-optional {
        background: #6b7280;
        color: white;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 12px;
    }

    .badge-inactive {
        background: #ef4444;
        color: white;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 12px;
    }

    .form-card {
        background: var(--card-bg);
        padding: 24px;
        border-radius: 12px;
        margin-bottom: 24px;
        border: 1px solid var(--border);
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
    }

    .form-group {
        margin-bottom: 16px;
    }

    .form-group label {
        display: block;
        margin-bottom: 4px;
        font-weight: 500;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--border);
        border-radius: 6px;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 60px;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-row {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .btn-sm {
        padding: 4px 12px;
        font-size: 13px;
        border-radius: 4px;
        cursor: pointer;
        border: none;
    }

    .btn-edit {
        background: #3b82f6;
        color: white;
    }

    .btn-delete {
        background: #ef4444;
        color: white;
    }

    .code-text {
        font-family: monospace;
        background: #f3f4f6;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 12px;
    }
</style>

<div class="container">
    <div class="page-header">
        <h1>
            <?= htmlspecialchars($page_title) ?>
        </h1>
        <p>Manage document types for visa checklist. These will appear in the visa workflow update form.</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php renderFlashMessage(); ?>

    <!-- Add New Document Type Form -->
    <div class="form-card">
        <h3 style="margin-bottom: 16px;">➕ Add New Document Type</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" required placeholder="e.g., Visa Application Form">
                </div>
                <div class="form-group">
                    <label>Code (auto-generated if blank)</label>
                    <input type="text" name="code" placeholder="e.g., visa_form">
                </div>
                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" value="<?= count($types) + 1 ?>">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_required" checked>
                        Required by default
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Brief description of what this document is..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Add Document Type</button>
        </form>
    </div>

    <!-- Document Types List -->
    <div class="form-card">
        <h3 style="margin-bottom: 16px;">📋 Document Types (
            <?= count($types) ?>)
        </h3>
        <table class="doc-types-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Description</th>
                    <th>Default</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($types as $type): ?>
                    <tr>
                        <td>
                            <?= $type['display_order'] ?>
                        </td>
                        <td><strong>
                                <?= htmlspecialchars($type['name']) ?>
                            </strong></td>
                        <td><span class="code-text">
                                <?= htmlspecialchars($type['code']) ?>
                            </span></td>
                        <td>
                            <?= htmlspecialchars($type['description'] ?? '') ?>
                        </td>
                        <td>
                            <?php if ($type['is_required_default']): ?>
                                <span class="badge-required">Required</span>
                            <?php else: ?>
                                <span class="badge-optional">Optional</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($type['is_active']): ?>
                                <span class="badge-required">Active</span>
                            <?php else: ?>
                                <span class="badge-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-row">
                                <button type="button" class="btn-sm btn-edit"
                                    onclick="editType(<?= htmlspecialchars(json_encode($type)) ?>)">Edit</button>
                                <button type="button" class="btn-sm btn-delete"
                                    onclick="confirmDelete(<?= $type['id'] ?>)">Delete</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div
        style="background:var(--card-bg, white); padding:24px; border-radius:12px; width:100%; max-width:500px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); border: 1px solid var(--border); position: relative;">
        <button onclick="closeEditModal()"
            style="position: absolute; top: 16px; right: 16px; border: none; background: none; font-size: 20px; color: #64748b; cursor: pointer;">&times;</button>
        <h3 style="margin-top:0; margin-bottom:16px;">Edit Document Type</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" id="edit_name" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="edit_description"></textarea>
            </div>
            <div class="form-group">
                <label>Display Order</label>
                <input type="number" name="display_order" id="edit_order">
            </div>
            <div style="display: flex; gap: 24px; margin-bottom: 16px;">
                <label class="checkbox-label" style="cursor: pointer;">
                    <input type="checkbox" name="is_required" id="edit_required">
                    Required by default
                </label>
                <label class="checkbox-label" style="cursor: pointer;">
                    <input type="checkbox" name="is_active" id="edit_active">
                    Active
                </label>
            </div>
            <div class="btn-row">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>



<script>
    // Edit Modal Logic
    function editType(type) {
        document.getElementById('edit_id').value = type.id;
        document.getElementById('edit_name').value = type.name;
        document.getElementById('edit_description').value = type.description || '';
        document.getElementById('edit_order').value = type.display_order;
        document.getElementById('edit_required').checked = type.is_required_default == 1;
        document.getElementById('edit_active').checked = type.is_active == 1;
        document.getElementById('editModal').style.display = 'flex';
    }
    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }


    // Hidden Delete Form (for Modal callback)
    const deleteForm = document.createElement('form');
    deleteForm.method = 'POST';
    deleteForm.style.display = 'none';
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete';
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    deleteForm.appendChild(actionInput);
    deleteForm.appendChild(idInput);
    document.body.appendChild(deleteForm);

    // Global Modal Delete Logic
    function confirmDelete(id) {
        Modal.confirm('Are you sure you want to delete this document type? This action cannot be undone.', function () {
            idInput.value = id;
            deleteForm.submit();
        }, function () {
            // Cancelled
        });

        // Override default title/type to match dangerous action
        const title = document.getElementById('modalTitle');
        const icon = document.getElementById('modalIcon');
        // We know Modal.confirm sets type='confirm' (blue). We want 'error' (red).
        // Since Modal.confirm is a shortcut, we can call Modal.show directly or just accept standard confirm.
        // User asked for "standardize Delete Confirmation ... in Red".
        // Let's call Modal.show directly for precise control.
        Modal.show({
            type: 'error',
            title: 'Delete Document Type?',
            message: 'Are you sure you want to delete this document type? This process cannot be undone.',
            confirmText: 'Yes, Delete It',
            onConfirm: function () {
                idInput.value = id;
                deleteForm.submit();
            }
        });
    }

    // Close modal when clicking outside
    document.getElementById('editModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeEditModal();
        }
    });
</script>

<?php require_once '../../templates/footer.php'; ?>