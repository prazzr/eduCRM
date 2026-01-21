<?php
require_once 'app/bootstrap.php';
// require_once 'app/services/DashboardService.php'; -- Autoloaded
requireLogin();

$pageDetails = ['title' => 'Dashboard'];
require_once 'templates/header.php';

// Initialize Service
$dashboardService = new \EduCRM\Services\DashboardService($pdo, $_SESSION['user_id'], $_SESSION['role'] ?? 'guest');

// Fetch Data
$newInquiries = $dashboardService->getNewInquiriesCount();
$activeClasses = $dashboardService->getActiveClassesCount();
$studentClasses = $dashboardService->getStudentClassesCount();
$visaStage = $dashboardService->getVisaStage();
$duesBalance = $dashboardService->getDuesBalance();
$teacherClasses = $dashboardService->getTeacherClasses();
$studentMaterials = $dashboardService->getStudentRecentMaterials();
$financials = $dashboardService->getFinancialOverview();

// Analytics
$inquiryStats = $dashboardService->getInquiryStats();
$visaStats = $dashboardService->getVisaPipelineStats();
$funnelStats = $dashboardService->getFunnelStats();
$attendanceStats = $dashboardService->getStudentAttendanceStats();
$performanceStats = $dashboardService->getStudentPerformanceStats();

// Metrics
$pendingTasks = $dashboardService->getPendingTasksCount();
$upcomingAppointments = $dashboardService->getUpcomingAppointmentsCount();
$activeVisaProcesses = $dashboardService->getActiveVisaProcessesCount();
$leadPriorityStats = $dashboardService->getLeadPriorityStats();
$recentTasks = $dashboardService->getRecentTasks(5);
$upcomingAppointmentsList = $dashboardService->getUpcomingAppointments(5);
?>

<!-- Welcome -->
<div class="welcome-banner">
    <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
    <p>Here's what's happening with your consultancy today.</p>
</div>

<?php if (hasRole('admin') || hasRole('counselor') || hasRole('branch_manager')): ?>
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card red">
            <div class="stat-header">
                <div class="stat-icon"><?php echo \EduCRM\Services\NavigationService::getIcon('fire', 18); ?></div>
            </div>
            <div class="stat-value"><?php echo $leadPriorityStats['hot'] ?? 0; ?></div>
            <div class="stat-label">Hot Leads</div>
        </div>

        <div class="stat-card orange">
            <div class="stat-header">
                <div class="stat-icon"><?php echo \EduCRM\Services\NavigationService::getIcon('trending-up', 18); ?></div>
            </div>
            <div class="stat-value"><?php echo $leadPriorityStats['warm'] ?? 0; ?></div>
            <div class="stat-label">Warm Leads</div>
        </div>

        <div class="stat-card blue">
            <div class="stat-header">
                <div class="stat-icon"><?php echo \EduCRM\Services\NavigationService::getIcon('inbox', 18); ?></div>
            </div>
            <div class="stat-value"><?php echo $newInquiries; ?></div>
            <div class="stat-label">New Inquiries</div>
        </div>

        <div class="stat-card amber">
            <div class="stat-header">
                <div class="stat-icon"><?php echo \EduCRM\Services\NavigationService::getIcon('check-square', 18); ?></div>
            </div>
            <div class="stat-value"><?php echo $pendingTasks; ?></div>
            <div class="stat-label">Pending Tasks</div>
        </div>

        <div class="stat-card green">
            <div class="stat-header">
                <div class="stat-icon"><?php echo \EduCRM\Services\NavigationService::getIcon('calendar', 18); ?></div>
            </div>
            <div class="stat-value"><?php echo $upcomingAppointments; ?></div>
            <div class="stat-label">Appointments</div>
        </div>

        <div class="stat-card violet">
            <div class="stat-header">
                <div class="stat-icon"><?php echo \EduCRM\Services\NavigationService::getIcon('plane', 18); ?></div>
            </div>
            <div class="stat-value"><?php echo $activeVisaProcesses; ?></div>
            <div class="stat-label">Visa Processing</div>
        </div>
    </div>
<?php endif; ?>

<?php if (hasRole('student')): ?>
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-header">
                <div class="stat-icon"><?php echo \EduCRM\Services\NavigationService::getIcon('book-open', 18); ?></div>
            </div>
            <div class="stat-value"><?php echo $studentClasses; ?></div>
            <div class="stat-label">My Classes</div>
        </div>
        <div class="stat-card green">
            <div class="stat-header">
                <div class="stat-icon"><?php echo \EduCRM\Services\NavigationService::getIcon('plane', 18); ?></div>
            </div>
            <div class="stat-value" style="font-size: 18px;"><?php echo $visaStage; ?></div>
            <div class="stat-label">Visa Status</div>
        </div>
        <div class="stat-card red">
            <div class="stat-header">
                <div class="stat-icon"><?php echo \EduCRM\Services\NavigationService::getIcon('credit-card', 18); ?></div>
            </div>
            <div class="stat-value">$<?php echo number_format($duesBalance, 0); ?></div>
            <div class="stat-label">Balance Due</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-header">
                <div class="stat-icon"><?php echo \EduCRM\Services\NavigationService::getIcon('check-square', 18); ?></div>
            </div>
            <div class="stat-value"><?php echo $pendingTasks; ?></div>
            <div class="stat-label">Pending Tasks</div>
        </div>
    </div>
<?php endif; ?>

<!-- Two Column Layout -->
<div
    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 20px; margin-bottom: 20px;">
    <!-- Tasks -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?php echo \EduCRM\Services\NavigationService::getIcon('check-square', 18); ?>
                Pending Tasks
            </h3>
            <a href="modules/tasks/list.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <?php if (count($recentTasks) > 0): ?>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($recentTasks as $task): ?>
                    <?php
                    $colors = ['urgent' => 'badge-hot', 'high' => 'badge-warm', 'medium' => 'badge-cold', 'low' => 'badge-cold'];
                    $isOverdue = $task['due_date'] && strtotime($task['due_date']) < time();
                    ?>
                    <div style="padding: 12px; background: var(--bg); border-radius: var(--radius-md);">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 4px;">
                            <a href="modules/tasks/edit.php?id=<?php echo $task['id']; ?>"
                                style="font-weight: 500; color: var(--text); text-decoration: none; font-size: 13px;">
                                <?php echo htmlspecialchars($task['title']); ?>
                            </a>
                            <span
                                class="badge <?php echo $colors[$task['priority']] ?? 'badge-cold'; ?>"><?php echo ucfirst($task['priority']); ?></span>
                        </div>
                        <?php if ($task['due_date']): ?>
                            <div
                                style="font-size: 12px; color: <?php echo $isOverdue ? 'var(--accent-red)' : 'var(--text-muted)'; ?>;">
                                Due: <?php echo date('M d', strtotime($task['due_date'])); ?>
                                <?php if ($isOverdue): ?><strong>(Overdue)</strong><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 32px; color: var(--text-muted);">
                <p>No pending tasks</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if (hasRole('admin') || hasRole('counselor') || hasRole('branch_manager')): ?>
        <!-- Appointments -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <?php echo \EduCRM\Services\NavigationService::getIcon('calendar', 18); ?>
                    Upcoming Appointments
                </h3>
                <a href="modules/appointments/list.php" class="btn btn-ghost btn-sm">View All</a>
            </div>
            <?php if (count($upcomingAppointmentsList) > 0): ?>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <?php foreach ($upcomingAppointmentsList as $apt): ?>
                        <?php $isToday = date('Y-m-d', strtotime($apt['appointment_date'])) === date('Y-m-d'); ?>
                        <div
                            style="padding: 12px; background: <?php echo $isToday ? 'var(--accent-blue-light)' : 'var(--bg)'; ?>; border-radius: var(--radius-md);">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 4px;">
                                <a href="modules/appointments/edit.php?id=<?php echo $apt['id']; ?>"
                                    style="font-weight: 500; color: var(--text); text-decoration: none; font-size: 13px;">
                                    <?php echo htmlspecialchars($apt['title']); ?>
                                </a>
                                <?php if ($isToday): ?>
                                    <span class="badge" style="background: var(--accent-blue); color: white;">Today</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 12px; color: var(--text-muted);">
                                <?php echo date('M d \a\t h:i A', strtotime($apt['appointment_date'])); ?>
                                <?php if ($apt['client_name']): ?> ·
                                    <?php echo htmlspecialchars($apt['client_name']); ?>             <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 32px; color: var(--text-muted);">
                    <p>No upcoming appointments</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (hasRole('teacher')): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?php echo \EduCRM\Services\NavigationService::getIcon('book-open', 18); ?>
                My Assigned Classes
            </h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Class Name</th>
                    <th>Course</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($teacherClasses) > 0): ?>
                    <?php foreach ($teacherClasses as $cl): ?>
                        <tr>
                            <td style="font-weight: 500;"><?php echo htmlspecialchars($cl['name']); ?></td>
                            <td><?php echo htmlspecialchars($cl['course_name']); ?></td>
                            <td style="text-align: right;">
                                <a href="modules/lms/classroom.php?class_id=<?php echo $cl['id']; ?>&today_roster=1"
                                    class="btn btn-primary btn-sm">Today's Roster</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 32px; color: var(--text-muted);">No active classes
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Charts Section -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 20px;">
    <?php if (hasRole('admin') || hasRole('counselor') || hasRole('branch_manager')): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <?php echo \EduCRM\Services\NavigationService::getIcon('bar-chart-2', 18); ?>
                    Visa Pipeline
                </h3>
            </div>
            <div style="height: 240px;"><canvas id="visaChart"></canvas></div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <?php echo \EduCRM\Services\NavigationService::getIcon('activity', 18); ?>
                    Inquiry Status
                </h3>
            </div>
            <div style="height: 240px;"><canvas id="inquiryChart"></canvas></div>
        </div>
    <?php endif; ?>

    <?php if (hasRole('admin') || hasRole('branch_manager')): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <?php echo \EduCRM\Services\NavigationService::getIcon('credit-card', 18); ?>
                    Financial Overview
                </h3>
            </div>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: var(--accent-green-light); border-radius: var(--radius-md);">
                    <span style="color: var(--text-secondary); font-size: 13px;">Revenue</span>
                    <span
                        style="font-size: 20px; font-weight: 700; color: var(--accent-green);">$<?php echo number_format($financials['revenue'] ?? 0, 2); ?></span>
                </div>
                <div
                    style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: var(--accent-red-light); border-radius: var(--radius-md);">
                    <span style="color: var(--text-secondary); font-size: 13px;">Outstanding</span>
                    <span
                        style="font-size: 20px; font-weight: 700; color: var(--accent-red);">$<?php echo number_format($financials['outstanding'] ?? 0, 2); ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (hasRole('student')): ?>
    <?php
    $present = $attendanceStats['present'] ?? 0;
    $late = $attendanceStats['late'] ?? 0;
    $absent = $attendanceStats['absent'] ?? 0;
    $avg_class = $performanceStats['avg_class'];
    $avg_home = $performanceStats['avg_home'];
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php echo \EduCRM\Services\NavigationService::getIcon('activity', 18); ?> Attendance
                </h3>
            </div>
            <div style="height: 200px; display: flex; justify-content: center;"><canvas id="attendanceChart"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php echo \EduCRM\Services\NavigationService::getIcon('bar-chart-2', 18); ?>
                    Performance</h3>
            </div>
            <div style="height: 160px; display: flex; justify-content: center;"><canvas id="perfChart"></canvas></div>
            <div style="display: flex; justify-content: center; gap: 32px; margin-top: 12px;">
                <div style="text-align: center;">
                    <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Class</div>
                    <div style="font-size: 20px; font-weight: 700; color: var(--accent-blue);"><?php echo $avg_class; ?>%
                    </div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Home</div>
                    <div style="font-size: 20px; font-weight: 700; color: var(--accent-violet);"><?php echo $avg_home; ?>%
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        const attData = { present: <?php echo $present; ?>, late: <?php echo $late; ?>, absent: <?php echo $absent; ?> };
        const perfData = { class: <?php echo $avg_class; ?>, home: <?php echo $avg_home; ?> };
    </script>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const opts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true } } } };

        const attCtx = document.getElementById('attendanceChart');
        if (attCtx && typeof attData !== 'undefined') {
            new Chart(attCtx, { type: 'doughnut', data: { labels: ['Present', 'Late', 'Absent'], datasets: [{ data: [attData.present, attData.late, attData.absent], backgroundColor: ['#16a34a', '#d97706', '#dc2626'], borderWidth: 0 }] }, options: opts });
        }

        const perfCtx = document.getElementById('perfChart');
        if (perfCtx && typeof perfData !== 'undefined') {
            new Chart(perfCtx, { type: 'bar', data: { labels: ['Class', 'Home'], datasets: [{ label: '%', data: [perfData.class, perfData.home], backgroundColor: ['#2563eb', '#7c3aed'], borderRadius: 6 }] }, options: { ...opts, scales: { y: { beginAtZero: true, max: 100 } } } });
        }

        const inqCtx = document.getElementById('inquiryChart');
        if (inqCtx) {
            new Chart(inqCtx, { type: 'doughnut', data: { labels: <?php echo json_encode($inquiryStats['labels'] ?? []); ?>, datasets: [{ data: <?php echo json_encode($inquiryStats['data'] ?? []); ?>, backgroundColor: ['#2563eb', '#d97706', '#16a34a', '#94a3b8'], borderWidth: 0 }] }, options: opts });
        }

        const visaCtx = document.getElementById('visaChart');
        if (visaCtx) {
            new Chart(visaCtx, { type: 'bar', data: { labels: <?php echo json_encode($visaStats['labels'] ?? []); ?>, datasets: [{ label: 'Students', data: <?php echo json_encode($visaStats['data'] ?? []); ?>, backgroundColor: '#0f766e', borderRadius: 6 }] }, options: { ...opts, scales: { y: { beginAtZero: true } } } });
        }
    });
</script>

<?php require_once 'templates/footer.php'; ?>