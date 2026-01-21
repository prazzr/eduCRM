<?php
require_once '../../app/bootstrap.php';
requireLogin();

$student_id = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;

// Security: Students can only view their own
if (hasRole('student') && $student_id != $_SESSION['user_id']) {
    die("Unauthorized access.");
}
if (!$student_id)
    die("Invalid Student ID");

// Fetch Student
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();
if (!$student)
    die("Student not found");

$isAdmin = hasRole('admin') || hasRole('accountant');
$message = '';

// 1. Add Fee (Invoice)
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fee'])) {
    $fee_type_id = $_POST['fee_type_id'];
    $amount = (float) $_POST['amount'];
    $desc = sanitize($_POST['description']);
    $due_date = $_POST['due_date'];

    if ($amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO student_fees (student_id, fee_type_id, description, amount, due_date, status) VALUES (?, ?, ?, ?, ?, 'unpaid')");
        $stmt->execute([$student_id, $fee_type_id, $desc, $amount, $due_date]);
        redirectWithAlert("student_ledger.php?student_id=$student_id", "Fee assigned successfully.", "success");
    } else {
        redirectWithAlert("student_ledger.php?student_id=$student_id", "Amount must be greater than zero.", "error");
    }
}

// 2. Record Payment
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $fee_id = $_POST['fee_id'];
    $amount = (float) $_POST['amount'];
    $method = $_POST['method'];
    $remarks = sanitize($_POST['remarks']);

    if ($amount > 0) {
        // Calculate remaining due for this fee
        $sum = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE student_fee_id = ?");
        $sum->execute([$fee_id]);
        $total_paid_already = $sum->fetchColumn() ?: 0;

        $fee_info = $pdo->prepare("SELECT amount FROM student_fees WHERE id = ?");
        $fee_info->execute([$fee_id]);
        $fee_total = $fee_info->fetchColumn();

        $limit = $fee_total - $total_paid_already;

        if ($amount > $limit) {
            redirectWithAlert("student_ledger.php?student_id=$student_id", "Error: Payment amount ($$amount) exceeds the remaining balance ($$limit).", "danger");
        } else {
            $pdo->beginTransaction();

            // Insert Payment
            $stmt = $pdo->prepare("INSERT INTO payments (student_fee_id, amount, payment_method, remarks) VALUES (?, ?, ?, ?)");
            $stmt->execute([$fee_id, $amount, $method, $remarks]);

            $total_paid_new = $total_paid_already + $amount;

            $new_status = 'partial';
            if ($total_paid_new >= $fee_total)
                $new_status = 'paid';

            $upd = $pdo->prepare("UPDATE student_fees SET status = ? WHERE id = ?");
            $upd->execute([$new_status, $fee_id]);

            $pdo->commit();
            redirectWithAlert("student_ledger.php?student_id=$student_id", "Payment recorded successfully.", "success");
        }
    }
}

// 3. Delete Fee (Added for CRUD completeness)
if ($isAdmin && isset($_GET['delete_fee'])) {
    $fid = (int) $_GET['delete_fee'];
    $stmt = $pdo->prepare("DELETE FROM student_fees WHERE id = ? AND status = 'unpaid'");
    $stmt->execute([$fid]);
    $stmt->execute([$fid]);
    redirectWithAlert("student_ledger.php?student_id=" . $student_id, "Fee deleted successfully.", "danger");
}

// Fetch Ledger Data
$fees = $pdo->prepare("
    SELECT sf.*, ft.name as fee_type 
    FROM student_fees sf 
    LEFT JOIN fee_types ft ON sf.fee_type_id = ft.id 
    WHERE sf.student_id = ? 
    ORDER BY sf.created_at DESC
");
$fees->execute([$student_id]);
$all_fees = $fees->fetchAll();

// Fetch Fee Types for dropdown
$fee_types = $pdo->query("SELECT * FROM fee_types")->fetchAll();

$pageDetails = ['title' => 'Student Ledger'];
require_once '../../templates/header.php';
?>

<div class="card">
    <div style="border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
        <h2>Financial Ledger: <?php echo htmlspecialchars($student['name']); ?></h2>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
    </div>

    <?php renderFlashMessage(); ?>

    <?php if ($isAdmin): ?>
        <!-- Admin Actions -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
            <div class="card" style="background: #f8fafc; border: 1px dashed #cbd5e1; margin-bottom: 0;">
                <h4>Assign Fee (Invoice)</h4>
                <form method="POST">
                    <input type="hidden" name="add_fee" value="1">
                    <div class="form-group">
                        <div class="flex justify-between items-center mb-1">
                            <label>Fee Type</label>
                            <a href="fee_types.php" class="text-xs text-blue-600 hover:text-blue-800">Manage Types</a>
                        </div>
                        <select name="fee_type_id" class="form-control">
                            <?php foreach ($fee_types as $ft): ?>
                                <option value="<?php echo $ft['id']; ?>"><?php echo htmlspecialchars($ft['name']); ?>
                                    ($<?php echo $ft['default_amount']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="number" step="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description (Optional)</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary">Assign Fee</button>
                </form>
            </div>

            <div class="card" style="background: #f0fdf4; border: 1px dashed #86efac; margin-bottom: 0;">
                <h4>Record Payment</h4>
                <form method="POST">
                    <input type="hidden" name="record_payment" value="1">
                    <div class="form-group">
                        <label>Existing Invoice</label>
                        <select name="fee_id" class="form-control" required>
                            <option value="">Select Invoice...</option>
                            <?php foreach ($all_fees as $f): ?>
                                <?php if ($f['status'] !== 'paid'): ?>
                                    <option value="<?php echo $f['id']; ?>">
                                        #<?php echo $f['id']; ?> - <?php echo $f['fee_type']; ?> ($<?php echo $f['amount']; ?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payment Amount</label>
                        <input type="number" step="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Method</label>
                        <select name="method" class="form-control">
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Card">Card</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <input type="text" name="remarks" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <h3>Transaction History</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Due Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Payments</th>
                <?php if ($isAdmin): ?>
                    <th>Actions</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php $total_outstanding = 0; ?>
            <?php foreach ($all_fees as $f): ?>
                <?php
                $pays = $pdo->prepare("SELECT * FROM payments WHERE student_fee_id = ?");
                $pays->execute([$f['id']]);
                $payments = $pays->fetchAll();

                $paid_amount = 0;
                foreach ($payments as $p)
                    $paid_amount += $p['amount'];

                $balance = $f['amount'] - $paid_amount;
                if ($f['status'] !== 'paid') {
                    $total_outstanding += $balance;
                }
                ?>
                <tr>
                    <td><?php echo date('Y-m-d', strtotime($f['created_at'])); ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($f['fee_type']); ?></strong>
                        <?php if ($f['description'])
                            echo "<br><small>" . htmlspecialchars($f['description']) . "</small>"; ?>
                    </td>
                    <td><?php echo $f['due_date']; ?></td>
                    <td>$<?php echo number_format($f['amount'], 2); ?></td>
                    <td>
                        <span class="status-badge"
                            style="background: <?php echo $f['status'] == 'paid' ? '#dcfce7' : ($f['status'] == 'partial' ? '#fef3c7' : '#fee2e2'); ?>; color: <?php echo $f['status'] == 'paid' ? '#16a34a' : ($f['status'] == 'partial' ? '#d97706' : '#991b1b'); ?>;">
                            <?php echo ucfirst($f['status']); ?>
                        </span>
                        <?php if ($f['status'] !== 'paid'): ?>
                            <br><small>Due: $<?php echo number_format($f['amount'] - $paid_amount, 2); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (count($payments) > 0): ?>
                            <ul style="padding-left: 15px; margin: 0; font-size: 13px;">
                                <?php foreach ($payments as $p): ?>
                                    <li>$<?php echo $p['amount']; ?> (<?php echo $p['payment_method']; ?>) on
                                        <?php echo date('m/d', strtotime($p['transaction_date'])); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <?php if ($isAdmin): ?>
                        <td>
                            <a href="invoice.php?fee_id=<?php echo $f['id']; ?>" target="_blank"
                                style="color: #4f46e5; font-size: 12px; margin-right: 8px;" title="View Invoice">📄 Invoice</a>
                            <?php if ($f['status'] === 'unpaid'): ?>
                                <a href="#" onclick="confirmDelete(<?php echo $f['id']; ?>)"
                                    style="color: #ef4444; font-size: 12px;">Delete</a>
                            <?php else: ?>
                                <small style="color: #94a3b8;">Locked</small>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background-color: #f8fafc; border-top: 2px solid #e2e8f0;">
                <td colspan="4" style="text-align: right; padding: 15px; font-weight: bold; color: #475569;">
                    Total Remaining Balance:
                </td>
                <td colspan="<?php echo $isAdmin ? 3 : 2; ?>"
                    style="padding: 15px; font-weight: bold; color: #dc2626; font-size: 1.1em;">
                    $<?php echo number_format($total_outstanding, 2); ?>
                </td>
            </tr>
        </tfoot>
    </table>
</div>

<?php require_once '../../templates/footer.php'; ?>

<script>
    function confirmDelete(id) {
        Modal.show({
            type: 'error',
            title: 'Delete Invoice?',
            message: 'Are you sure you want to delete this invoice/fee? This is only possible if it is unpaid.',
            confirmText: 'Yes, Delete It',
            onConfirm: function () {
                window.location.href = '?student_id=<?php echo $student_id; ?>&delete_fee=' + id;
            }
        });
    }
</script>