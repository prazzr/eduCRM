<?php
require_once '../../app/bootstrap.php';


requireLogin();

$branchId = $_SESSION['branch_id'] ?? null;
// If user is admin, they might want to see all data, or select a branch. 
// For now, if they are admin, they see all (branchId = null). 
// If they are branch manager, they see their branch.
// logic: if hasRole('admin') then branchId = null (unless we add a selector later).
// But wait, login.php sets branch_id for everyone?
// Let's check logic:
// If hasRole('admin'), we might want to allow them to see everything.
// If I pass $branchId from session, and admin has a branch_id set (e.g. main branch), they will only see that branch.
// Usually admins should see GLOBAL data by default.
if (hasRole('admin')) {
    $branchId = null; // Admin sees all
}
// Optionally allow admin to filter by branch via GET param if we implement that later.

$analytics = new \EduCRM\Services\AnalyticsService($pdo, $branchId);

// Get date range from query params
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of month
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today
$period = $_GET['period'] ?? 'month';

// Get analytics data
$realTimeMetrics = $analytics->getRealTimeMetrics();
$conversionFunnel = $analytics->getConversionFunnel($startDate, $endDate);
$counselorPerformance = $analytics->getCounselorPerformance($period);
$revenueForecast = $analytics->getRevenueForecast(3);
$goalProgress = $analytics->getGoalProgress();
$trendData = $analytics->getTrendData('inquiries', 30);

$pageDetails = ['title' => 'Analytics Dashboard'];
require_once '../../templates/header.php';
?>

<!-- Chart.js loaded via header.php -->

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">📊 Analytics Dashboard</h1>
            <p class="text-slate-600 mt-1">Business intelligence and performance metrics</p>
        </div>

        <!-- Date Range Selector -->
        <div class="flex gap-3">
            <select id="periodSelector" class="px-4 py-2 border border-slate-300 rounded-lg" onchange="changePeriod()">
                <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>This Week</option>
                <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>This Month</option>
                <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>This Year</option>
            </select>
            <button onclick="exportDashboard()" class="btn-secondary px-4 py-2 rounded-lg">
                📥 Export PDF
            </button>
        </div>
    </div>
</div>

<!-- Real-Time KPI Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <!-- Today's Inquiries -->
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-blue-100 text-sm font-medium">Today's Inquiries</span>
            <span class="text-3xl">📞</span>
        </div>
        <div class="text-3xl font-bold mb-1">
            <?php echo number_format($realTimeMetrics['today_inquiries']); ?>
        </div>
        <div class="text-blue-100 text-sm">
            <?php echo $realTimeMetrics['new_inquiries']; ?> new, uncontacted
        </div>
    </div>

    <!-- Today's Enrollments -->
    <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-emerald-100 text-sm font-medium">Today's Enrollments</span>
            <span class="text-3xl">🎓</span>
        </div>
        <div class="text-3xl font-bold mb-1">
            <?php echo number_format($realTimeMetrics['today_enrollments']); ?>
        </div>
        <div class="text-emerald-100 text-sm">
            <?php echo $realTimeMetrics['today_applications']; ?> applications submitted
        </div>
    </div>

    <!-- Today's Revenue -->
    <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-amber-100 text-sm font-medium">Today's Revenue</span>
            <span class="text-3xl">💰</span>
        </div>
        <div class="text-3xl font-bold mb-1">$
            <?php echo number_format($realTimeMetrics['today_revenue']); ?>
        </div>
        <div class="text-amber-100 text-sm">
            From
            <?php echo $realTimeMetrics['today_enrollments']; ?> enrollments
        </div>
    </div>

    <!-- Pending Tasks -->
    <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-purple-100 text-sm font-medium">Pending Tasks</span>
            <span class="text-3xl">✓</span>
        </div>
        <div class="text-3xl font-bold mb-1">
            <?php echo number_format($realTimeMetrics['pending_tasks']); ?>
        </div>
        <div class="text-purple-100 text-sm">
            <?php echo $realTimeMetrics['overdue_tasks']; ?> overdue
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Conversion Funnel -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">🔄 Conversion Funnel</h3>
        <canvas id="funnelChart" height="300"></canvas>
        <div class="mt-4 grid grid-cols-4 gap-2 text-center text-sm">
            <?php foreach ($conversionFunnel['stages'] as $stage): ?>
                <div>
                    <div class="font-bold text-slate-800">
                        <?php echo $stage['count']; ?>
                    </div>
                    <div class="text-slate-600 text-xs">
                        <?php echo $stage['name']; ?>
                    </div>
                    <div class="text-primary-600 text-xs font-medium">
                        <?php echo $stage['rate']; ?>%
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Revenue Trend -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">📈 Revenue Trend (30 Days)</h3>
        <canvas id="revenueTrendChart" height="300"></canvas>
        <div class="mt-4 text-center">
            <span class="text-sm text-slate-600">Forecast Growth Rate: </span>
            <span class="text-lg font-bold text-emerald-600">
                <?php echo $revenueForecast['growth_rate']; ?>
            </span>
        </div>
    </div>
</div>

<!-- Counselor Performance & Goals -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Counselor Leaderboard -->
    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">🏆 Counselor Performance</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-slate-200">
                        <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Rank</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Counselor</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-slate-700">Inquiries</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-slate-700">Conversions</th>
                        <th class="text-center py-3 px-4 text-sm font-semibold text-slate-700">Rate</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-slate-700">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rank = 1;
                    foreach ($counselorPerformance as $counselor):
                        $medalClass = match ($rank) {
                            1 => 'text-yellow-500',
                            2 => 'text-slate-400',
                            3 => 'text-amber-600',
                            default => 'text-slate-300'
                        };
                        ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="py-3 px-4">
                                <span class="<?php echo $medalClass; ?> text-xl">
                                    <?php echo $rank <= 3 ? '🏅' : $rank; ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 font-medium text-slate-800">
                                <?php echo htmlspecialchars($counselor['counselor_name']); ?>
                            </td>
                            <td class="py-3 px-4 text-center text-slate-600">
                                <?php echo $counselor['total_inquiries']; ?>
                            </td>
                            <td class="py-3 px-4 text-center text-slate-600">
                                <?php echo $counselor['total_conversions']; ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded text-sm font-medium">
                                    <?php echo number_format($counselor['conversion_rate'], 1); ?>%
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-semibold text-slate-800">
                                $
                                <?php echo number_format($counselor['total_revenue']); ?>
                            </td>
                        </tr>
                        <?php
                        $rank++;
                    endforeach;
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Goal Progress -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">🎯 Goal Progress</h3>
        <div class="space-y-4">
            <?php foreach ($goalProgress as $goal):
                $percentage = min(100, $goal['progress_percentage']);
                $barColor = $percentage >= 100 ? 'bg-emerald-500' : ($percentage >= 75 ? 'bg-blue-500' : 'bg-amber-500');
                ?>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-slate-700">
                            <?php echo htmlspecialchars($goal['goal_name']); ?>
                        </span>
                        <span class="text-sm font-bold text-slate-800">
                            <?php echo number_format($percentage, 1); ?>%
                        </span>
                    </div>
                    <div class="w-full bg-slate-200 rounded-full h-2">
                        <div class="<?php echo $barColor; ?> h-2 rounded-full transition-all"
                            style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <div class="flex justify-between items-center mt-1">
                        <span class="text-xs text-slate-500">
                            <?php echo number_format($goal['current_value']); ?> /
                            <?php echo number_format($goal['target_value']); ?>
                        </span>
                        <span class="text-xs text-slate-500">
                            <?php echo $goal['days_remaining']; ?> days left
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    // Conversion Funnel Chart
    const funnelCtx = document.getElementById('funnelChart').getContext('2d');
    new Chart(funnelCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($conversionFunnel['stages'], 'name')); ?>,
            datasets: [{
                label: 'Count',
                data: <?php echo json_encode(array_column($conversionFunnel['stages'], 'count')); ?>,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(239, 68, 68, 0.8)'
                ],
                borderColor: [
                    'rgb(59, 130, 246)',
                    'rgb(16, 185, 129)',
                    'rgb(245, 158, 11)',
                    'rgb(239, 68, 68)'
                ],
                borderWidth: 2
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

    // Revenue Trend Chart
    const revenueTrendCtx = document.getElementById('revenueTrendChart').getContext('2d');
    const trendData = <?php echo json_encode($trendData); ?>;
    new Chart(revenueTrendCtx, {
        type: 'line',
        data: {
            labels: trendData.map(d => d.date),
            datasets: [{
                label: 'Inquiries',
                data: trendData.map(d => d.value),
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4
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

    function changePeriod() {
        const period = document.getElementById('periodSelector').value;
        window.location.href = `?period=${period}`;
    }

    function exportDashboard() {
        window.print();
    }

    // Auto-refresh every 30 seconds
    setTimeout(() => {
        location.reload();
    }, 30000);
</script>

<?php require_once '../../templates/footer.php'; ?>