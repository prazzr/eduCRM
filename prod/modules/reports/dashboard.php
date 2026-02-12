<?php
require_once '../../app/bootstrap.php';


requireLogin();
requireAnalyticsAccess();

$pageDetails = ['title' => 'Reports & Analytics'];
require_once '../../templates/header.php';

$reportingService = new \EduCRM\Services\ReportingService($pdo, $_SESSION['branch_id']);

// Get date range from query params or default to last 30 days
$range = $_GET['range'] ?? '30';
$customStart = $_GET['start_date'] ?? null;
$customEnd = $_GET['end_date'] ?? null;

// Calculate date range
if ($range === 'custom' && $customStart && $customEnd) {
    $startDate = $customStart . ' 00:00:00';
    $endDate = $customEnd . ' 23:59:59';
    $rangeLabel = date('M d', strtotime($customStart)) . ' - ' . date('M d, Y', strtotime($customEnd));
} else {
    $days = (int) $range;
    $startDate = date('Y-m-d 00:00:00', strtotime("-$days days"));
    $endDate = date('Y-m-d 23:59:59');
    $rangeLabel = "Last $days Days";
}

// Fetch all analytics data
$taskStats = $reportingService->getTaskCompletionRate($startDate, $endDate);
$appointmentStats = $reportingService->getAppointmentMetrics($startDate, $endDate);
$leadFunnel = $reportingService->getLeadConversionFunnel($startDate, $endDate);
$priorityDist = $reportingService->getPriorityDistribution($startDate, $endDate);
$counselorPerf = $reportingService->getCounselorPerformance($startDate, $endDate);
$avgCompletionTime = $reportingService->getAverageCompletionTime($startDate, $endDate);
$studentStats = $reportingService->getStudentEnrollmentStats($startDate, $endDate);
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-slate-800 mb-2">📊 Reports & Analytics</h1>
    <p class="text-slate-600">Comprehensive insights into your CRM performance</p>
</div>

<!-- Date Range Selector -->
<div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <span class="text-sm font-medium text-slate-700">Date Range:</span>
            <div class="flex gap-2">
                <a href="?range=7"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?php echo $range === '7' ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">
                    Last 7 Days
                </a>
                <a href="?range=30"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?php echo $range === '30' ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">
                    Last 30 Days
                </a>
                <a href="?range=90"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?php echo $range === '90' ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'; ?>">
                    Last 90 Days
                </a>
            </div>

            <form method="GET" class="flex gap-2 items-center ml-4">
                <input type="date" name="start_date" value="<?php echo $customStart ?? ''; ?>"
                    class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm">
                <span class="text-slate-500">to</span>
                <input type="date" name="end_date" value="<?php echo $customEnd ?? ''; ?>"
                    class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm">
                <input type="hidden" name="range" value="custom">
                <button type="submit" class="btn-secondary px-3 py-1.5 text-sm rounded-lg">Apply</button>
            </form>
        </div>

        <div class="flex gap-2">
            <!-- PDF Reports Dropdown -->
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="btn-primary px-4 py-2 text-sm rounded-lg flex items-center gap-2">
                    📄 Download PDF
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div x-show="open" @click.away="open = false" x-transition
                    class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-slate-200 z-10">
                    <a href="generate_pdf.php?type=counselor&start_date=<?php echo date('Y-m-d', strtotime($startDate)); ?>&end_date=<?php echo date('Y-m-d', strtotime($endDate)); ?>"
                        class="block px-4 py-3 hover:bg-slate-50 text-sm text-slate-700 border-b border-slate-100">
                        📊 Counselor Performance
                    </a>
                    <a href="generate_pdf.php?type=financial&start_date=<?php echo date('Y-m-d', strtotime($startDate)); ?>&end_date=<?php echo date('Y-m-d', strtotime($endDate)); ?>"
                        class="block px-4 py-3 hover:bg-slate-50 text-sm text-slate-700 border-b border-slate-100">
                        💰 Financial Summary
                    </a>
                    <a href="generate_pdf.php?type=pipeline&start_date=<?php echo date('Y-m-d', strtotime($startDate)); ?>&end_date=<?php echo date('Y-m-d', strtotime($endDate)); ?>"
                        class="block px-4 py-3 hover:bg-slate-50 text-sm text-slate-700">
                        📈 Inquiry Pipeline
                    </a>
                </div>
            </div>
            <button onclick="exportToCSV()" class="btn-secondary px-4 py-2 text-sm rounded-lg">📥 Export CSV</button>
        </div>
    </div>
</div>

<!-- Overview Metric Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <!-- Task Completion Rate -->
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-blue-100 text-sm font-medium">Task Completion</span>
            <svg class="w-8 h-8 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <div class="text-4xl font-bold mb-1">
            <?php echo $taskStats['completion_rate']; ?>%
        </div>
        <div class="text-blue-100 text-sm">
            <?php echo $taskStats['completed']; ?> of
            <?php echo $taskStats['total']; ?> tasks completed
        </div>
    </div>

    <!-- Appointment Attendance -->
    <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-emerald-100 text-sm font-medium">Attendance Rate</span>
            <svg class="w-8 h-8 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
        </div>
        <div class="text-4xl font-bold mb-1">
            <?php echo $appointmentStats['attendance_rate']; ?>%
        </div>
        <div class="text-emerald-100 text-sm">
            <?php echo $appointmentStats['completed']; ?> of
            <?php echo $appointmentStats['total']; ?> appointments
        </div>
    </div>

    <!-- Lead Conversion -->
    <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-purple-100 text-sm font-medium">Conversion Rate</span>
            <svg class="w-8 h-8 text-purple-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
            </svg>
        </div>
        <div class="text-4xl font-bold mb-1">
            <?php echo $leadFunnel['conversion_rate']; ?>%
        </div>
        <div class="text-purple-100 text-sm">
            <?php echo $leadFunnel['converted']; ?> of
            <?php echo $leadFunnel['total']; ?> leads converted
        </div>
    </div>

    <!-- Avg Completion Time -->
    <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-orange-100 text-sm font-medium">Avg Task Time</span>
            <svg class="w-8 h-8 text-orange-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <div class="text-4xl font-bold mb-1">
            <?php echo $avgCompletionTime; ?>
        </div>
        <div class="text-orange-100 text-sm">days to complete</div>
    </div>

    <!-- New Students -->
    <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-indigo-100 text-sm font-medium">New Students</span>
            <svg class="w-8 h-8 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                </path>
            </svg>
        </div>
        <div class="text-4xl font-bold mb-1">
            <?php echo $studentStats['new_students']; ?>
        </div>
        <div class="text-indigo-100 text-sm">enrolled in period</div>
    </div>
</div>

<!-- Charts Row 1 -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Task Completion Trend -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Task Completion Trend</h3>
        <div class="relative h-[250px]">
            <canvas id="taskTrendChart"></canvas>
        </div>
    </div>

    <!-- Appointment Status Distribution -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Appointment Status</h3>
        <div class="relative h-[250px]">
            <canvas id="appointmentStatusChart"></canvas>
        </div>
    </div>
</div>

<!-- Charts Row 2 -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Lead Conversion Funnel -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Lead Conversion Funnel</h3>
        <div class="relative h-[250px]">
            <canvas id="conversionFunnelChart"></canvas>
        </div>
    </div>

    <!-- Priority Distribution -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Lead Priority Distribution</h3>
        <div class="relative h-[250px]">
            <canvas id="priorityDistChart"></canvas>
        </div>
    </div>
</div>

<!-- Counselor Performance Table -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
    <div class="p-6 border-b border-slate-200">
        <h3 class="text-lg font-bold text-slate-800">Counselor Performance</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-sm">
                    <th class="p-3 font-semibold">Counselor</th>
                    <th class="p-3 font-semibold text-center">Tasks</th>
                    <th class="p-3 font-semibold text-center">Task Rate</th>
                    <th class="p-3 font-semibold text-center">Appointments</th>
                    <th class="p-3 font-semibold text-center">Appt Rate</th>
                    <th class="p-3 font-semibold text-center">Inquiries</th>
                    <th class="p-3 font-semibold text-center">Conv. Rate</th>
                    <th class="p-3 font-semibold text-center">Avg Score</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (count($counselorPerf) > 0):
                    foreach ($counselorPerf as $counselor): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="p-3 font-medium text-slate-800">
                                <?php echo htmlspecialchars($counselor['name']); ?>
                            </td>
                            <td class="p-3 text-center text-slate-600">
                                <?php echo $counselor['completed_tasks']; ?>/
                                <?php echo $counselor['total_tasks']; ?>
                            </td>
                            <td class="p-3 text-center">
                                <span
                                    class="inline-block px-2 py-0.5 rounded text-xs font-bold <?php echo $counselor['task_completion_rate'] >= 75 ? 'bg-emerald-100 text-emerald-700' : ($counselor['task_completion_rate'] >= 50 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                                    <?php echo $counselor['task_completion_rate']; ?>%
                                </span>
                            </td>
                            <td class="p-3 text-center text-slate-600">
                                <?php echo $counselor['completed_appointments']; ?>/
                                <?php echo $counselor['total_appointments']; ?>
                            </td>
                            <td class="p-3 text-center">
                                <span
                                    class="inline-block px-2 py-0.5 rounded text-xs font-bold <?php echo $counselor['appointment_completion_rate'] >= 75 ? 'bg-emerald-100 text-emerald-700' : ($counselor['appointment_completion_rate'] >= 50 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                                    <?php echo $counselor['appointment_completion_rate']; ?>%
                                </span>
                            </td>
                            <td class="p-3 text-center text-slate-600">
                                <?php echo $counselor['converted_inquiries']; ?>/
                                <?php echo $counselor['total_inquiries']; ?>
                            </td>
                            <td class="p-3 text-center">
                                <span
                                    class="inline-block px-2 py-0.5 rounded text-xs font-bold <?php echo $counselor['conversion_rate'] >= 25 ? 'bg-emerald-100 text-emerald-700' : ($counselor['conversion_rate'] >= 15 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                                    <?php echo $counselor['conversion_rate']; ?>%
                                </span>
                            </td>
                            <td class="p-3 text-center font-semibold text-slate-800">
                                <?php echo $counselor['avg_lead_score']; ?>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="8" class="p-6 text-center text-slate-500">No counselor data available for this period
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart.js loaded via header.php -->

<script>
    // Task Completion Trend Chart
    const taskTrendCtx = document.getElementById('taskTrendChart').getContext('2d');
    new Chart(taskTrendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($taskStats['trend_data'], 'date')); ?>,
            datasets: [{
                label: 'Tasks Completed',
                data: <?php echo json_encode(array_column($taskStats['trend_data'], 'count')); ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Appointment Status Chart
    const appointmentStatusCtx = document.getElementById('appointmentStatusChart').getContext('2d');
    new Chart(appointmentStatusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'Scheduled', 'Cancelled', 'No Show'],
            datasets: [{
                data: [
                    <?php echo $appointmentStats['completed']; ?>,
                    <?php echo $appointmentStats['scheduled']; ?>,
                    <?php echo $appointmentStats['cancelled']; ?>,
                    <?php echo $appointmentStats['no_show']; ?>
                ],
                backgroundColor: ['#10b981', '#3b82f6', '#ef4444', '#f59e0b']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Conversion Funnel Chart
    const conversionFunnelCtx = document.getElementById('conversionFunnelChart').getContext('2d');
    new Chart(conversionFunnelCtx, {
        type: 'bar',
        data: {
            labels: ['New', 'Contacted', 'Converted', 'Closed'],
            datasets: [{
                label: 'Inquiries',
                data: [
                    <?php echo $leadFunnel['new']; ?>,
                    <?php echo $leadFunnel['contacted']; ?>,
                    <?php echo $leadFunnel['converted']; ?>,
                    <?php echo $leadFunnel['closed']; ?>
                ],
                backgroundColor: ['#3b82f6', '#8b5cf6', '#10b981', '#64748b']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Priority Distribution Chart
    const priorityDistCtx = document.getElementById('priorityDistChart').getContext('2d');
    new Chart(priorityDistCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($priorityDist, 'priority')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($priorityDist, 'count')); ?>,
                backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Export to CSV
    function exportToCSV() {
        const counselorData = <?php echo json_encode($counselorPerf); ?>;
        let csv = 'Counselor,Tasks Completed,Task Rate,Appointments Completed,Appt Rate,Inquiries Converted,Conv Rate,Avg Score\n';

        counselorData.forEach(c => {
            csv += `"${c.name}",${c.completed_tasks}/${c.total_tasks},${c.task_completion_rate}%,${c.completed_appointments}/${c.total_appointments},${c.appointment_completion_rate}%,${c.converted_inquiries}/${c.total_inquiries},${c.conversion_rate}%,${c.avg_lead_score}\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'counselor_performance_<?php echo date('Y-m-d'); ?>.csv';
        a.click();
    }
</script>

<?php require_once '../../templates/footer.php'; ?>