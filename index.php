<?php
require_once 'config.php';
requireLogin();

$pageDetails = ['title' => 'Dashboard'];
require_once 'includes/header.php';
?>

<!-- Welcome Banner -->
<div class="welcome-banner">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h2>
    <p>You are logged in as <strong><?php echo ucfirst($_SESSION['role']); ?></strong>. Here's your overview for today.
    </p>
</div>

<div class="dashboard-grid">
    <?php if (hasRole('admin') || hasRole('counselor')): ?>
        <div class="stat-card">
            <h3>New Inquiries</h3>
            <div class="value">
                <?php
                $count = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE status='new'")->fetchColumn();
                echo $count;
                ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (hasRole('admin') || hasRole('teacher')): ?>
        <div class="stat-card">
            <h3>Active Classes</h3>
            <div class="value">
                <?php
                if (hasRole('admin')) {
                    echo $pdo->query("SELECT COUNT(*) FROM classes WHERE status='active'")->fetchColumn();
                } else {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE teacher_id = ? AND status='active'");
                    $stmt->execute([$_SESSION['user_id']]);
                    echo $stmt->fetchColumn();
                }
                ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (hasRole('student')): ?>
        <div class="stat-card">
            <h3>My Classes</h3>
            <div class="value">
                <?php
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                echo $stmt->fetchColumn();
                ?>
            </div>
        </div>
        <div class="stat-card" style="background: #f0fdf4; border-color: #86efac;">
            <h3>Visa Status</h3>
            <div class="value" style="font-size: 18px; color: #166534; padding-top: 10px;">
                <?php
                $v_stmt = $pdo->prepare("SELECT current_stage FROM visa_workflows WHERE student_id = ?");
                $v_stmt->execute([$_SESSION['user_id']]);
                $v_stage = $v_stmt->fetchColumn();
                echo $v_stage ?: 'Not Started';
                ?>
            </div>
        </div>
        <div class="stat-card" style="background: #fff1f2; border-color: #fca5a5;">
            <h3>Dues Balance</h3>
            <div class="value" style="font-size: 22px; color: #9f1239; padding-top: 5px;">
                <?php
                $total_bill = $pdo->prepare("SELECT SUM(amount) FROM student_fees WHERE student_id = ?");
                $total_bill->execute([$_SESSION['user_id']]);
                $billed = $total_bill->fetchColumn() ?: 0;

                $total_paid = $pdo->prepare("SELECT SUM(p.amount) FROM payments p JOIN student_fees sf ON p.student_fee_id = sf.id WHERE sf.student_id = ?");
                $total_paid->execute([$_SESSION['user_id']]);
                $paid = $total_paid->fetchColumn() ?: 0;

                echo '$' . number_format($billed - $paid, 2);
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="card">
    <h3>Quick Actions</h3>
    <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
        <?php if (hasRole('admin') || hasRole('counselor')): ?>
            <a href="modules/inquiries/add.php" class="btn">New Inquiry</a>
            <a href="modules/partners/list.php" class="btn btn-secondary">Manage Partners</a>
        <?php endif; ?>

        <?php if (hasRole('admin')): ?>
            <a href="modules/lms/courses.php" class="btn btn-secondary">Manage Courses</a>
            <a href="modules/users/list.php" class="btn btn-secondary">Manage Users</a>
        <?php endif; ?>

        <?php if (hasRole('admin') || hasRole('teacher')): ?>
            <a href="modules/lms/classes.php" class="btn btn-secondary">My Classes</a>
        <?php endif; ?>
    </div>
</div>

<?php if (hasRole('teacher')): ?>
    <div class="card" style="margin-top: 20px;">
        <h3>My Assigned Classes</h3>
        <p style="color: #64748b; font-size: 14px; margin-bottom: 15px;">Click "Today's Roster" to start attendance and
            daily task grading.</p>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc; text-align: left;">
                        <th style="padding: 12px; border-bottom: 2px solid #e2e8f0;">Class Name</th>
                        <th style="padding: 12px; border-bottom: 2px solid #e2e8f0;">Course</th>
                        <th style="padding: 12px; border-bottom: 2px solid #e2e8f0; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $my_classes = $pdo->prepare("
                        SELECT c.*, co.name as course_name 
                        FROM classes c 
                        JOIN courses co ON c.course_id = co.id 
                        WHERE c.teacher_id = ? AND c.status = 'active'
                    ");
                    $my_classes->execute([$_SESSION['user_id']]);
                    $rows = $my_classes->fetchAll();
                    ?>
                    <?php if (count($rows) > 0): ?>
                        <?php foreach ($rows as $cl): ?>
                            <tr>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                    <strong><?php echo htmlspecialchars($cl['name']); ?></strong>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                    <?php echo htmlspecialchars($cl['course_name']); ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: right;">
                                    <a href="modules/lms/classroom.php?class_id=<?php echo $cl['id']; ?>&today_roster=1" class="btn"
                                        style="padding: 6px 12px; font-size: 12px;">Today's Roster</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="padding: 20px; text-align: center; color: #64748b;">No active classes
                                assigned to you.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if (hasRole('student')): ?>
    <div class="card" style="margin-top: 20px;">
        <h3>Recent Class Materials & Assignments</h3>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc; text-align: left;">
                        <th style="padding: 12px; border-bottom: 2px solid #e2e8f0;">Title</th>
                        <th style="padding: 12px; border-bottom: 2px solid #e2e8f0;">Class</th>
                        <th style="padding: 12px; border-bottom: 2px solid #e2e8f0;">Type</th>
                        <th style="padding: 12px; border-bottom: 2px solid #e2e8f0; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT cm.*, c.name as class_name, s.grade, s.submitted_at
                        FROM class_materials cm
                        JOIN enrollments e ON cm.class_id = e.class_id
                        JOIN classes c ON cm.class_id = c.id
                        LEFT JOIN submissions s ON s.material_id = cm.id AND s.student_id = e.student_id
                        WHERE e.student_id = ?
                        ORDER BY cm.created_at DESC
                        LIMIT 3
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $materials = $stmt->fetchAll();
                    ?>
                    <?php if (count($materials) > 0): ?>
                        <?php foreach ($materials as $m): ?>
                            <tr>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                    <strong><?php echo htmlspecialchars($m['title']); ?></strong>
                                    <br><small
                                        style="color: #64748b;"><?php echo date('M d', strtotime($m['created_at'])); ?></small>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                    <?php echo htmlspecialchars($m['class_name']); ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;">
                                    <span
                                        style="font-size: 10px; text-transform: uppercase; background: #e2e8f0; padding: 2px 6px; border-radius: 4px;"><?php echo $m['type']; ?></span>
                                    <br>
                                    <?php if ($m['type'] !== 'notice' && $m['type'] !== 'reading'): ?>
                                        <?php if ($m['grade'] !== null): ?>
                                            <small style="color: #059669; font-weight: bold;">Grade: <?php echo $m['grade']; ?></small>
                                        <?php elseif ($m['submitted_at']): ?>
                                            <small style="color: #0284c7;">Submitted</small>
                                        <?php else: ?>
                                            <small style="color: #ef4444;">Pending</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: right;">
                                    <a href="modules/lms/classroom.php?class_id=<?php echo $m['class_id']; ?>"
                                        class="btn btn-secondary" style="padding: 6px 12px; font-size: 11px;">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="padding: 20px; text-align: center; color: #64748b;">No materials posted yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Role-Specific Analytics -->
<div class="dashboard-grid" style="grid-template-columns: 1fr 1fr;">
    <?php if (hasRole('admin') || hasRole('counselor')): ?>
        <div class="card">
            <h3>Visa Pipeline</h3>
            <canvas id="visaChart"></canvas>
        </div>
    <?php endif; ?>

    <?php if (hasRole('admin')): ?>
        <div class="card">
            <h3>Financial Overview</h3>
            <div style="font-size: 14px; margin-top: 10px;">
                <?php
                $total_due = $pdo->query("SELECT SUM(amount) FROM student_fees WHERE status != 'paid'")->fetchColumn() ?: 0;
                $total_paid = $pdo->query("SELECT SUM(amount) FROM payments")->fetchColumn() ?: 0;
                ?>
                <p>Total Revenue: <strong>$<?php echo number_format($total_paid, 2); ?></strong></p>
                <p>Outstanding Dues: <strong
                        style="color: #991b1b;">$<?php echo number_format($total_due - $total_paid, 2); ?></strong></p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Attendance Summary Widget (Student Only) -->
<?php if (hasRole('student')): ?>
    <div class="card">
        <h3>Current Month Attendance</h3>
        <div class="attendance-summary">
            <?php
            // Fetch attendance stats for current month
            $month_start = date('Y-m-01');
            $month_end = date('Y-m-t');

            $att_stmt = $pdo->prepare("
                    SELECT attendance, COUNT(*) as count 
                    FROM daily_performance dp
                    JOIN daily_rosters dr ON dp.roster_id = dr.id
                    WHERE dp.student_id = ? 
                    AND dr.roster_date BETWEEN ? AND ?
                    GROUP BY attendance
                ");
            $att_stmt->execute([$_SESSION['user_id'], $month_start, $month_end]);
            $att_data = $att_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $present = $att_data['present'] ?? 0;
            $late = $att_data['late'] ?? 0;
            $absent = $att_data['absent'] ?? 0;
            $total_days = $present + $late + $absent;

            // Calculate percentages avoiding division by zero
            $p_pct = $total_days > 0 ? ($present / $total_days) * 100 : 0;
            $l_pct = $total_days > 0 ? ($late / $total_days) * 100 : 0;
            $a_pct = $total_days > 0 ? ($absent / $total_days) * 100 : 0;

            // Task Completion Logic
            $task_stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT cm.id) 
            FROM class_materials cm
            JOIN enrollments e ON cm.class_id = e.class_id
            WHERE e.student_id = ? AND cm.type = 'assignment'
        ");
            $task_stmt->execute([$_SESSION['user_id']]);
            $total_tasks = $task_stmt->fetchColumn() ?: 0;

            $sub_stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT s.material_id)
            FROM submissions s
            JOIN class_materials cm ON s.material_id = cm.id
            WHERE s.student_id = ? AND cm.type = 'assignment'
        ");
            $sub_stmt->execute([$_SESSION['user_id']]);
            $completed_tasks = $sub_stmt->fetchColumn() ?: 0;

            $pending_tasks = max(0, $total_tasks - $completed_tasks);
            ?>

            <div class="dashboard-grid" style="grid-template-columns: 1fr 1fr;">
                <div class="card">
                    <h3>Current Month Attendance</h3>
                    <div class="attendance-summary" style="display: flex; justify-content: center;">
                        <canvas id="attendanceChart" style="max-height: 250px;"></canvas>
                    </div>
                </div>

                <div class="card">
                    <h3>Task Completion Ratio</h3>
                    <div class="attendance-summary" style="display: flex; justify-content: center;">
                        <canvas id="taskChart" style="max-height: 250px;"></canvas>
                    </div>
                    <div style="text-align: center; margin-top: 10px; font-size: 14px; color: #64748b;">
                        <?php echo $completed_tasks; ?> Completed / <?php echo $total_tasks; ?> Total
                    </div>
                </div>
            </div>

            <!-- Hide raw visuals but keep calculation for Chart -->
            <script>
                // Data passed from PHP
                const attData = {
                    present: <?php echo $present; ?>,
                    late: <?php echo $late; ?>,
                    absent: <?php echo $absent; ?>
                };

                const taskData = {
                    completed: <?php echo $completed_tasks; ?>,
                    pending: <?php echo $pending_tasks; ?>
                };
            </script>
        <?php endif; ?>

        <?php if (!hasRole('student')): ?>
            <div class="card">
                <h3>Inquiry Status Overview</h3>
                <canvas id="inquiryChart" style="max-height: 250px;"></canvas>
            </div>
            <div class="card">
                <h3>Conversion Funnel</h3>
                <canvas id="funnelChart" style="max-height: 250px;"></canvas>
            </div>
        <?php endif; ?>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <?php
    // Fetch Data for Charts (Admin/Counselor Only)
    if (hasRole('admin') || hasRole('counselor')) {
        // 1. Inquiry Statuses
        $stats = $pdo->query("SELECT status, COUNT(*) as count FROM inquiries GROUP BY status")->fetchAll();
        $labels = [];
        $data = [];
        foreach ($stats as $s) {
            $labels[] = ucfirst($s['status']);
            $data[] = $s['count'];
        }

        // 2. Visa Stages
        $v_stats = $pdo->query("SELECT current_stage, COUNT(*) as count FROM visa_workflows GROUP BY current_stage")->fetchAll();
        $v_labels = [];
        $v_data = [];
        foreach ($v_stats as $vs) {
            $v_labels[] = $vs['current_stage'];
            $v_data[] = $vs['count'];
        }

        // 3. Simple Funnel
        $total_inq = $pdo->query("SELECT COUNT(*) FROM inquiries")->fetchColumn();
        $converted = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE status='converted'")->fetchColumn();
    }
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Common Chart Config
            const commonOptions = {
                responsive: true,
                maintainAspectRatio: false
            };

            // Student Attendance Chart
            const attChartCtx = document.getElementById('attendanceChart');
            if (attChartCtx && typeof attData !== 'undefined') {
                new Chart(attChartCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Present', 'Late', 'Absent'],
                        datasets: [{
                            data: [attData.present, attData.late, attData.absent],
                            backgroundColor: ['#10b981', '#f59e0b', '#ef4444'] // Green, Orange, Red
                        }]
                    },
                    options: commonOptions
                });
            }

            // Student Task Chart
            const taskChartCtx = document.getElementById('taskChart');
            if (taskChartCtx && typeof taskData !== 'undefined') {
                new Chart(taskChartCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Completed', 'Pending'],
                        datasets: [{
                            data: [taskData.completed, taskData.pending],
                            backgroundColor: ['#3b82f6', '#e2e8f0'] // Blue, Gray
                        }]
                    },
                    options: commonOptions
                });
            }

            // Admin/Counselor Charts
            const inqChartCtx = document.getElementById('inquiryChart');
            if (inqChartCtx) {
                new Chart(inqChartCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo isset($labels) ? json_encode($labels) : '[]'; ?>,
                        datasets: [{
                            data: <?php echo isset($data) ? json_encode($data) : '[]'; ?>,
                            backgroundColor: ['#e0f2fe', '#fef3c7', '#dcfce7', '#f1f5f9']
                        }]
                    },
                    options: commonOptions
                });
            }

            const visaChartCtx = document.getElementById('visaChart');
            if (visaChartCtx) {
                new Chart(visaChartCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo isset($v_labels) ? json_encode($v_labels) : '[]'; ?>,
                        datasets: [{
                            label: 'Students',
                            data: <?php echo isset($v_data) ? json_encode($v_data) : '[]'; ?>,
                            backgroundColor: '#0369a1'
                        }]
                    },
                    options: commonOptions
                });
            }

            const funnelChartCtx = document.getElementById('funnelChart');
            if (funnelChartCtx) {
                new Chart(funnelChartCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Total Inquiries', 'Converted Students'],
                        datasets: [{
                            label: 'Count',
                            data: [<?php echo isset($total_inq) ? $total_inq : 0; ?>, <?php echo isset($converted) ? $converted : 0; ?>],
                            backgroundColor: ['#64748b', '#16a34a']
                        }]
                    },
                    options: { ...commonOptions, scales: { y: { beginAtZero: true } } }
                });
            }
        });
    </script>


    <?php require_once 'includes/footer.php'; ?>