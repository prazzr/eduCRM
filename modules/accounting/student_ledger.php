<?php
require_once '../../config.php';
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

    $stmt = $pdo->prepare("INSERT INTO student_fees (student_id, fee_type_id, description, amount, due_date, status) VALUES (?, ?, ?, ?, ?, 'unpaid')");
    $stmt->execute([$student_id, $fee_type_id, $desc, $amount, $due_date]);
    $message = "Fee assigned successfully.";
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
            $message = "Error: Payment amount ($$amount) exceeds the remaining balance ($$limit).";
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
            $message = "Payment recorded successfully.";
        }
    }
}

// 3. Delete Fee (Added for CRUD completeness)
if ($isAdmin && isset($_GET['delete_fee'])) {
    $fid = (int) $_GET['delete_fee'];
    $stmt = $pdo->prepare("DELETE FROM student_fees WHERE id = ? AND status = 'unpaid'");
    $stmt->execute([$fid]);
    header("Location: student_ledger.php?student_id=" . $student_id);
    exit;
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
require_once '../../includes/header.php';
?>

<div class="card">
    <div style="border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
        <h2>Financial Ledger: <?php echo htmlspecialchars($student['name']); ?></h2>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
    </div>

    <?php if ($message): ?>
        <?php $isError = strpos($message, 'Error') !== false; ?>
        <div
            style="background: <?php echo $isError ? '#fee2e2' : '#dcfce7'; ?>; color: <?php echo $isError ? '#991b1b' : '#166534'; ?>; padding: 10px; border-radius: 6px; margin-bottom: 20px;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <!-- Admin Actions -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
            <div class="card" style="background: #f8fafc; border: 1px dashed #cbd5e1; margin-bottom: 0;">
                <h4>Assign Fee (Invoice)</h4>
                <form method="POST">
                    <input type="hidden" name="add_fee" value="1">
                    <div class="form-group">
                        <label>Fee Type</label>
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
                    <button type="submit" class="btn">Assign Fee</button>
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
                    <button type="submit" class="btn btn-success" style="background-color: var(--success-color);">Record
                        Payment</button>
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
            <?php foreach ($all_fees as $f): ?>
                <?php
                // Get Payments for this fee
                $pays = $pdo->prepare("SELECT * FROM payments WHERE student_fee_id = ?");
                $pays->execute([$f['id']]);
                $payments = $pays->fetchAll();

                $paid_amount = 0;
                foreach ($payments as $p)
                    $paid_amount += $p['amount'];
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
                            <?php if ($f['status'] === 'unpaid'): ?>
                                <a href="?student_id=<?php echo $student_id; ?>&delete_fee=<?php echo $f['id']; ?>"
                                    onclick="return confirm('Delete this invoice?')"
                                    style="color: #ef4444; font-size: 12px;">Delete</a>
                            <?php else: ?>
                                <small style="color: #94a3b8;">Locked</small>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../includes/footer.php'; ?>