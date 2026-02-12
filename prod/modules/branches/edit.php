<?php
/**
 * Branch Management - Add/Edit Branch
 */

require_once '../../app/bootstrap.php';

requireLogin();

if (!hasRole('admin')) {
    die("Access denied. Admin only.");
}

$branchService = new \EduCRM\Services\BranchService($pdo);
$isEdit = isset($_GET['id']);
$branchId = $isEdit ? (int) $_GET['id'] : 0;
$branch = $isEdit ? $branchService->getBranch($branchId) : null;

if ($isEdit && !$branch) {
    die("Branch not found.");
}

$message = '';
$error = '';

// Get users for manager dropdown (Must have 'branch_manager' role)
$users = $pdo->query("
    SELECT u.id, u.name 
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    WHERE r.name = 'branch_manager'
    ORDER BY u.name
")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => sanitize($_POST['name']),
        'code' => strtoupper(sanitize($_POST['code'])),
        'address' => sanitize($_POST['address']),
        'city' => sanitize($_POST['city']),
        'phone' => sanitize($_POST['phone']),
        'email' => sanitize($_POST['email']),
        'manager_id' => $_POST['manager_id'] ?: null,
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];

    if (empty($data['name'])) {
        redirectWithAlert("edit.php" . ($isEdit ? "?id=$branchId" : ""), "Branch name is required.", "error");
    } else {
        try {
            if ($isEdit) {
                $branchService->updateBranch($branchId, $data);
                $branch = $branchService->getBranch($branchId);
                redirectWithAlert("edit.php?id=$branchId", "Branch updated successfully!", "warning");
            } else {
                $newId = $branchService->createBranch($data);
                redirectWithAlert("list.php", "Branch created successfully!", "success");
            }
        } catch (PDOException $e) {
            redirectWithAlert("edit.php" . ($isEdit ? "?id=$branchId" : ""), "Error: " . $e->getMessage(), "error");
        }
    }
}

$pageDetails = ['title' => ($isEdit ? 'Edit' : 'Add') . ' Branch'];
require_once '../../templates/header.php';
?>

<div class="card" style="max-width: 700px; margin: 0 auto;">
    <h2 class="text-xl font-bold text-slate-800 mb-6">
        <?php echo $isEdit ? 'Edit Branch' : 'Add New Branch'; ?>
    </h2>

    <?php renderFlashMessage(); ?>

    <form method="POST">
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="form-group">
                <label>Branch Name *</label>
                <input type="text" name="name" class="form-control" required
                    value="<?php echo htmlspecialchars($branch['name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Branch Code</label>
                <input type="text" name="code" class="form-control" maxlength="10" placeholder="Auto-generated if empty"
                    value="<?php echo htmlspecialchars($branch['code'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-group mb-4">
            <label>Address</label>
            <textarea name="address" class="form-control"
                rows="2"><?php echo htmlspecialchars($branch['address'] ?? ''); ?></textarea>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="form-group">
                <label>City</label>
                <input type="text" name="city" class="form-control"
                    value="<?php echo htmlspecialchars($branch['city'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control"
                    value="<?php echo htmlspecialchars($branch['phone'] ?? ''); ?>">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control"
                    value="<?php echo htmlspecialchars($branch['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Branch Manager</label>
                <select name="manager_id" class="form-control">
                    <option value="">-- Select Manager --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo ($branch['manager_id'] ?? '') == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if ($isEdit && !$branch['is_headquarters']): ?>
            <div class="form-group mb-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" <?php echo ($branch['is_active'] ?? 1) ? 'checked' : ''; ?>>
                    <span>Branch is Active</span>
                </label>
            </div>
        <?php endif; ?>

        <div class="flex gap-2">
            <button type="submit" class="btn">
                <?php echo $isEdit ? 'Update Branch' : 'Create Branch'; ?>
            </button>
            <a href="list.php" class="btn-secondary px-4 py-2 rounded">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../templates/footer.php'; ?>