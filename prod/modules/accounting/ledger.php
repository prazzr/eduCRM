<?php
require_once '../../app/bootstrap.php';
requireLogin();

// If Student, redirect to their own ledger
if (hasRole('student')) {
    header("Location: student_ledger.php?student_id=" . $_SESSION['user_id']);
    exit;
}

requireRoles(['admin', 'accountant']);

// Fetch Students with balance summary (optional advanced query)
// For now, simple list
$branchService = new \EduCRM\Services\BranchService($pdo);
$branchFilter = $branchService->getBranchFilter($_SESSION['user_id'], 'u');

$students = $pdo->query("
    SELECT u.* 
    FROM users u 
    JOIN user_roles ur ON u.id = ur.user_id 
    JOIN roles r ON ur.role_id = r.id 
    WHERE r.name = 'student' 
    $branchFilter
    ORDER BY u.name
")->fetchAll();

$pageDetails = ['title' => 'Financial Ledgers'];
require_once '../../templates/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Student Financial Ledgers</h2>
    <div style="display: flex; gap: 10px;">
        <a href="dashboard.php" class="btn btn-secondary">📊 Financial Dashboard</a>
        <a href="fee_types.php" class="btn btn-primary">Manage Fee Structure</a>
    </div>
</div>

<div class="card" style="padding: 0;">
    <div style="padding: 15px; border-bottom: 1px solid #eee;">
        <input type="text" id="studentSearch" class="form-control" placeholder="Search by Student Name..."
            style="max-width: 300px;">
    </div>
    <table id="ledgerTable">
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['name']); ?></td>
                    <td><?php echo htmlspecialchars($s['email']); ?></td>
                    <td><?php echo htmlspecialchars($s['phone']); ?></td>
                    <td>
                        <a href="student_ledger.php?student_id=<?php echo $s['id']; ?>" class="btn">View Ledger</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    document.getElementById('studentSearch').addEventListener('keyup', function () {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('#ledgerTable tbody tr');

        rows.forEach(row => {
            let name = row.cells[0].textContent.toLowerCase();
            if (name.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
</script>

<?php require_once '../../templates/footer.php'; ?>