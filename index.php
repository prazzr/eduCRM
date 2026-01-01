<?php
require_once 'config.php';
require_once 'includes/services/DashboardService.php';
requireLogin();

$pageDetails = ['title' => 'Dashboard'];
require_once 'includes/header.php';

// Initialize Service
$dashboardService = new DashboardService($pdo, $_SESSION['user_id'], $_SESSION['role']);

// Fetch Data
$newInquiries = $dashboardService->getNewInquiriesCount();
$activeClasses = $dashboardService->getActiveClassesCount();
$studentClasses = $dashboardService->getStudentClassesCount();
$visaStage = $dashboardService->getVisaStage();
$duesBalance = $dashboardService->getDuesBalance();
$teacherClasses = $dashboardService->getTeacherClasses();
$studentMaterials = $dashboardService->getStudentRecentMaterials();
$financials = $dashboardService->getFinancialOverview();

// Analytics Data
$inquiryStats = $dashboardService->getInquiryStats();
$visaStats = $dashboardService->getVisaPipelineStats();
$funnelStats = $dashboardService->getFunnelStats();

// Student Specific Analytics
$attendanceStats = $dashboardService->getStudentAttendanceStats();
$performanceStats = $dashboardService->getStudentPerformanceStats();

// Phase 1 Enhancements - New Metrics
$pendingTasks = $dashboardService->getPendingTasksCount();
$upcomingAppointments = $dashboardService->getUpcomingAppointmentsCount();
$overdueFees = $dashboardService->getOverdueFeesSummary();
$recentApplications = $dashboardService->getRecentApplicationsCount();
$activeVisaProcesses = $dashboardService->getActiveVisaProcessesCount();
$leadPriorityStats = $dashboardService->getLeadPriorityStats();
$recentTasks = $dashboardService->getRecentTasks(5);
$upcomingAppointmentsList = $dashboardService->getUpcomingAppointments(5);
?>

<!-- Welcome Banner -->
<div
    class="bg-gradient-to-r from-primary-600 to-indigo-600 text-white p-8 rounded-2xl shadow-lg shadow-primary-500/20 mb-8">
    <h2 class="text-3xl font-bold mb-2">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h2>
    <p class="text-primary-100 text-lg">You are logged in as <strong
            class="text-white"><?php echo ucfirst($_SESSION['role']); ?></strong>. Here's your overview for today.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <?php if (hasRole('admin') || hasRole('counselor')): ?>
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
            <h3 class="text-slate-500 text-sm font-medium uppercase tracking-wider mb-1">New Inquiries</h3>
            <div class="text-3xl font-bold text-slate-800"><?php echo $newInquiries; ?></div>
        </div>
    <?php endif; ?>

    <?php if (hasRole('admin') || hasRole('teacher')): ?>
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
            <h3 class="text-slate-500 text-sm font-medium uppercase tracking-wider mb-1">Active Classes</h3>
            <div class="text-3xl font-bold text-slate-800"><?php echo $activeClasses; ?></div>
        </div>
    <?php endif; ?>

    <!-- Phase 1: Pending Tasks -->
    <div class="bg-amber-50 p-6 rounded-xl border border-amber-200 shadow-sm hover:shadow-md transition-shadow">
        <h3 class="text-amber-700 text-sm font-medium uppercase tracking-wider mb-1">Pending Tasks</h3>
        <div class="text-3xl font-bold text-amber-800"><?php echo $pendingTasks; ?></div>
        <a href="modules/tasks/list.php" class="text-xs text-amber-600 hover:underline mt-2 inline-block">View All →</a>
    </div>

    <?php if (hasRole('admin') || hasRole('counselor')): ?>
        <!-- Phase 1: Upcoming Appointments -->
        <div class="bg-sky-50 p-6 rounded-xl border border-sky-200 shadow-sm hover:shadow-md transition-shadow">
            <h3 class="text-sky-700 text-sm font-medium uppercase tracking-wider mb-1">Upcoming Appointments</h3>
            <div class="text-3xl font-bold text-sky-800"><?php echo $upcomingAppointments; ?></div>
            <a href="modules/appointments/list.php" class="text-xs text-sky-600 hover:underline mt-2 inline-block">View All
                →</a>
        </div>
    <?php endif; ?>

    <?php if (hasRole('student')): ?>
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-slate-500 text-sm font-medium uppercase tracking-wider mb-1">My Classes</h3>
            <div class="text-3xl font-bold text-slate-800"><?php echo $studentClasses; ?></div>
        </div>
        <div class="bg-emerald-50 p-6 rounded-xl border border-emerald-200 shadow-sm">
            <h3 class="text-emerald-700 text-sm font-medium uppercase tracking-wider mb-1">Visa Status</h3>
            <div class="text-2xl font-bold text-emerald-800">
                <?php echo $visaStage; ?>
            </div>
        </div>
        <div class="bg-rose-50 p-6 rounded-xl border border-rose-200 shadow-sm">
            <h3 class="text-rose-700 text-sm font-medium uppercase tracking-wider mb-1">Dues Balance</h3>
            <div class="text-3xl font-bold text-rose-800">
                $<?php echo number_format($duesBalance, 2); ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm mb-8">
    <h3 class="text-lg font-semibold text-slate-800 mb-4">Quick Actions</h3>
    <div class="flex flex-wrap gap-3">
        <?php if (hasRole('admin') || hasRole('counselor')): ?>
            <a href="modules/inquiries/add.php" class="btn">+ New Inquiry</a>
            <a href="modules/appointments/add.php" class="btn">+ New Appointment</a>
            <a href="modules/partners/list.php" class="btn-secondary px-4 py-2 rounded-lg font-medium">Manage Partners</a>
        <?php endif; ?>

        <a href="modules/tasks/add.php" class="btn">+ New Task</a>

        <?php if (hasRole('admin')): ?>
            <a href="modules/lms/courses.php" class="btn-secondary px-4 py-2 rounded-lg font-medium">Manage Courses</a>
            <a href="modules/users/list.php" class="btn-secondary px-4 py-2 rounded-lg font-medium">Manage Users</a>
        <?php endif; ?>

        <?php if (hasRole('admin') || hasRole('teacher')): ?>
            <a href="modules/lms/classes.php" class="btn-secondary px-4 py-2 rounded-lg font-medium">My Classes</a>
        <?php endif; ?>
    </div>
</div>

<?php if (hasRole('teacher')): ?>
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm mb-8">
        <h3 class="text-lg font-semibold text-slate-800 mb-2">My Assigned Classes</h3>
        <p class="text-slate-500 text-sm mb-4">Click "Today's Roster" to start attendance and daily task grading.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-sm">
                        <th class="p-3 font-semibold">Class Name</th>
                        <th class="p-3 font-semibold">Course</th>
                        <th class="p-3 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (count($teacherClasses) > 0): ?>
                        <?php foreach ($teacherClasses as $cl): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="p-3">
                                    <strong class="text-slate-800"><?php echo htmlspecialchars($cl['name']); ?></strong>
                                </td>
                                <td class="p-3 text-slate-600">
                                    <?php echo htmlspecialchars($cl['course_name']); ?>
                                </td>
                                <td class="p-3 text-right">
                                    <a href="modules/lms/classroom.php?class_id=<?php echo $cl['id']; ?>&today_roster=1"
                                        class="btn text-xs px-3 py-1.5">Today's Roster</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="p-6 text-center text-slate-500">No active classes assigned to you.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if (hasRole('student')): ?>
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm mb-8">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Recent Class Materials & Assignments</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-sm">
                        <th class="p-3 font-semibold">Title</th>
                        <th class="p-3 font-semibold">Class</th>
                        <th class="p-3 font-semibold">Type</th>
                        <th class="p-3 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (count($studentMaterials) > 0): ?>
                        <?php foreach ($studentMaterials as $m): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="p-3">
                                    <strong class="text-slate-800 block"><?php echo htmlspecialchars($m['title']); ?></strong>
                                    <span
                                        class="text-xs text-slate-400"><?php echo date('M d', strtotime($m['created_at'])); ?></span>
                                </td>
                                <td class="p-3 text-slate-600">
                                    <?php echo htmlspecialchars($m['class_name']); ?>
                                </td>
                                <td class="p-3">
                                    <span
                                        class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-600 tracking-wide"><?php echo $m['type']; ?></span>
                                    <div class="mt-1">
                                        <?php if ($m['type'] !== 'notice' && $m['type'] !== 'reading'): ?>
                                            <?php if ($m['grade'] !== null): ?>
                                                <span class="text-emerald-600 text-xs font-bold">Grade: <?php echo $m['grade']; ?></span>
                                            <?php elseif ($m['submitted_at']): ?>
                                                <span class="text-sky-600 text-xs font-medium">Submitted</span>
                                            <?php else: ?>
                                                <span class="text-red-500 text-xs font-medium">Pending</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="p-3 text-right">
                                    <a href="modules/lms/classroom.php?class_id=<?php echo $m['class_id']; ?>"
                                        class="btn-secondary px-3 py-1.5 text-xs rounded">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="p-6 text-center text-slate-500">No materials posted yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Phase 1: Task & Appointment Widgets -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <!-- Pending Tasks Widget -->
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-slate-800">My Pending Tasks</h3>
            <a href="modules/tasks/list.php" class="text-sm text-primary-600 hover:underline">View All</a>
        </div>
        <?php if (count($recentTasks) > 0): ?>
            <div class="space-y-3">
                <?php foreach ($recentTasks as $task): ?>
                    <?php
                    $priorityColors = [
                        'urgent' => 'bg-red-100 text-red-700',
                        'high' => 'bg-orange-100 text-orange-700',
                        'medium' => 'bg-yellow-100 text-yellow-700',
                        'low' => 'bg-blue-100 text-blue-700'
                    ];
                    $isOverdue = $task['due_date'] && strtotime($task['due_date']) < time();
                    ?>
                    <div class="p-3 bg-slate-50 rounded-lg hover:bg-slate-100 transition-colors">
                        <div class="flex justify-between items-start mb-1">
                            <a href="modules/tasks/edit.php?id=<?php echo $task['id']; ?>"
                                class="font-medium text-slate-800 hover:text-primary-600 flex-1">
                                <?php echo htmlspecialchars($task['title']); ?>
                            </a>
                            <span
                                class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase <?php echo $priorityColors[$task['priority']]; ?> ml-2">
                                <?php echo $task['priority']; ?>
                            </span>
                        </div>
                        <?php if ($task['due_date']): ?>
                            <div class="text-xs <?php echo $isOverdue ? 'text-red-600 font-semibold' : 'text-slate-500'; ?>">
                                Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                <?php if ($isOverdue): ?> (Overdue!)<?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-slate-500 text-sm text-center py-8">No pending tasks. You're all caught up! 🎉</p>
        <?php endif; ?>
    </div>

    <?php if (hasRole('admin') || hasRole('counselor')): ?>
        <!-- Upcoming Appointments Widget -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-slate-800">Upcoming Appointments</h3>
                <a href="modules/appointments/list.php" class="text-sm text-primary-600 hover:underline">View All</a>
            </div>
            <?php if (count($upcomingAppointmentsList) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($upcomingAppointmentsList as $apt): ?>
                        <?php
                        $isToday = date('Y-m-d', strtotime($apt['appointment_date'])) === date('Y-m-d');
                        ?>
                        <div
                            class="p-3 <?php echo $isToday ? 'bg-blue-50 border border-blue-200' : 'bg-slate-50'; ?> rounded-lg hover:bg-slate-100 transition-colors">
                            <div class="flex justify-between items-start mb-1">
                                <a href="modules/appointments/edit.php?id=<?php echo $apt['id']; ?>"
                                    class="font-medium text-slate-800 hover:text-primary-600 flex-1">
                                    <?php echo htmlspecialchars($apt['title']); ?>
                                </a>
                                <?php if ($isToday): ?>
                                    <span
                                        class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-600 text-white ml-2">
                                        TODAY
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-slate-600">
                                <?php echo date('M d, Y \a\t h:i A', strtotime($apt['appointment_date'])); ?>
                            </div>
                            <?php if ($apt['client_name']): ?>
                                <div class="text-xs text-slate-500 mt-1">
                                    With: <?php echo htmlspecialchars($apt['client_name']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-slate-500 text-sm text-center py-8">No upcoming appointments scheduled.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Role-Specific Analytics -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <?php if (hasRole('admin') || hasRole('counselor')): ?>
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Visa Pipeline</h3>
            <div class="h-64">
                <canvas id="visaChart"></canvas>
            </div>
        </div>
    <?php endif; ?>

    <?php if (hasRole('admin')): ?>
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Financial Overview</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center p-4 bg-slate-50 rounded-lg">
                    <span class="text-slate-600">Total Revenue</span>
                    <span
                        class="text-xl font-bold text-slate-900">$<?php echo number_format($financials['revenue'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center p-4 bg-red-50 rounded-lg border border-red-100">
                    <span class="text-red-700">Outstanding Dues</span>
                    <span
                        class="text-xl font-bold text-red-700">$<?php echo number_format($financials['outstanding'] ?? 0, 2); ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Attendance Summary Widget (Student Only) -->
<?php if (hasRole('student')): ?>
    <?php
    $present = $attendanceStats['present'] ?? 0;
    $late = $attendanceStats['late'] ?? 0;
    $absent = $attendanceStats['absent'] ?? 0;

    $avg_class = $performanceStats['avg_class'];
    $avg_home = $performanceStats['avg_home'];
    ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Current Month Attendance</h3>
            <div class="h-64 flex justify-center">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Average Performance</h3>
            <div class="h-56 flex justify-center mb-4">
                <canvas id="perfChart"></canvas>
            </div>
            <div class="flex justify-center gap-8 text-sm text-slate-600">
                <div class="text-center">
                    <div class="text-xs text-slate-400 uppercase tracking-wider">Class Task</div>
                    <div class="text-lg font-bold text-slate-800"><?php echo $avg_class; ?>%</div>
                </div>
                <div class="text-center">
                    <div class="text-xs text-slate-400 uppercase tracking-wider">Home Task</div>
                    <div class="text-lg font-bold text-slate-800"><?php echo $avg_home; ?>%</div>
                </div>
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

        const perfData = {
            class: <?php echo $avg_class; ?>,
            home: <?php echo $avg_home; ?>
        };
    </script>
<?php endif; ?>

<?php if (hasRole('admin') || hasRole('counselor')): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Inquiry Status Overview</h3>
            <div class="h-64">
                <canvas id="inquiryChart"></canvas>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Conversion Funnel</h3>
            <div class="h-64">
                <canvas id="funnelChart"></canvas>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Common Chart Config
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false
        };

        // Student Charts
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

        const perfChartCtx = document.getElementById('perfChart');
        if (perfChartCtx && typeof perfData !== 'undefined') {
            new Chart(perfChartCtx, {
                type: 'bar',
                data: {
                    labels: ['Class Task', 'Home Task'],
                    datasets: [{
                        label: 'Average Marks',
                        data: [perfData.class, perfData.home],
                        backgroundColor: ['#3b82f6', '#8b5cf6'], // Blue, Purple
                        borderRadius: 6
                    }]
                },
                options: {
                    ...commonOptions,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }

        // Admin/Counselor Charts
        const inqChartCtx = document.getElementById('inquiryChart');
        if (inqChartCtx) {
            new Chart(inqChartCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($inquiryStats['labels'] ?? []); ?>,
                    datasets: [{
                        data: <?php echo json_encode($inquiryStats['data'] ?? []); ?>,
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
                    labels: <?php echo json_encode($visaStats['labels'] ?? []); ?>,
                    datasets: [{
                        label: 'Students',
                        data: <?php echo json_encode($visaStats['data'] ?? []); ?>,
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
                        data: [<?php echo $funnelStats['total'] ?? 0; ?>, <?php echo $funnelStats['converted'] ?? 0; ?>],
                        backgroundColor: ['#64748b', '#16a34a']
                    }]
                },
                options: { ...commonOptions, scales: { y: { beginAtZero: true } } }
            });
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>