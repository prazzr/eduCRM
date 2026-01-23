<?php
/**
 * Financial Dashboard
 * Visual overview of revenue, collections, and outstanding balances
 */
require_once '../../app/bootstrap.php';
requireLogin();
requireAdminCounselorOrBranchManager();

$pageDetails = ['title' => 'Financial Dashboard'];
require_once '../../templates/header.php';

$financeService = new \EduCRM\Services\FinancialReportService($pdo);

// Get all dashboard data
$overview = $financeService->getOverview();
$trend = $financeService->getRevenueTrend(6);
$methods = $financeService->getPaymentMethodBreakdown();
$feeTypes = $financeService->getFeeTypeBreakdown();
$defaulters = $financeService->getTopDefaulters(5);
$recentTx = $financeService->getRecentTransactions(5);
$projected = $financeService->getProjectedCollections();

// Currency helper
function formatCurrency($amount)
{
    return 'Rs. ' . number_format($amount, 2);
}
?>

<div class="page-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="page-title">Financial Dashboard</h1>
        <p class="text-slate-500 text-sm">Overview of revenue, collections, and outstanding balances</p>
    </div>
    <div class="flex gap-3">
        <a href="ledger.php" class="btn btn-secondary">
            <?php echo \EduCRM\Services\NavigationService::getIcon('users', 16); ?> Student Ledgers
        </a>
        <a href="fee_types.php" class="btn btn-secondary">
            <?php echo \EduCRM\Services\NavigationService::getIcon('settings', 16); ?> Fee Types
        </a>
    </div>
</div>

<?php renderFlashMessage(); ?>

<!-- KPI Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Revenue -->
    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-green-100 text-sm font-medium">Total Revenue</span>
            <svg class="w-6 h-6 text-green-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
        </div>
        <div class="text-3xl font-bold mb-1">
            <?php echo formatCurrency($overview['total_revenue']); ?>
        </div>
        <div class="text-green-100 text-sm flex items-center gap-1">
            <?php if ($overview['month_change'] >= 0): ?>
                <span class="text-green-200">↑
                    <?php echo abs($overview['month_change']); ?>%
                </span>
            <?php else: ?>
                <span class="text-red-200">↓
                    <?php echo abs($overview['month_change']); ?>%
                </span>
            <?php endif; ?>
            vs last month
        </div>
    </div>

    <!-- Outstanding -->
    <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-orange-100 text-sm font-medium">Outstanding</span>
            <svg class="w-6 h-6 text-orange-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <div class="text-3xl font-bold mb-1">
            <?php echo formatCurrency($overview['outstanding']); ?>
        </div>
        <div class="text-orange-100 text-sm">
            <?php echo $overview['overdue_students']; ?> students overdue
        </div>
    </div>

    <!-- Collection Rate -->
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-blue-100 text-sm font-medium">Collection Rate</span>
            <svg class="w-6 h-6 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
        </div>
        <div class="text-3xl font-bold mb-1">
            <?php echo number_format($overview['collection_rate'], 1); ?>%
        </div>
        <div class="text-blue-100 text-sm">
            of invoiced amount collected
        </div>
    </div>

    <!-- This Month -->
    <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-indigo-100 text-sm font-medium">This Month</span>
            <svg class="w-6 h-6 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
        </div>
        <div class="text-3xl font-bold mb-1">
            <?php echo formatCurrency($overview['this_month']); ?>
        </div>
        <div class="text-indigo-100 text-sm">
            Last month:
            <?php echo formatCurrency($overview['last_month']); ?>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Revenue Trend Chart -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800">Revenue Trend</h2>
        </div>
        <div class="p-6">
            <canvas id="revenueTrendChart" height="250"></canvas>
        </div>
    </div>

    <!-- Fee Type Breakdown -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800">Revenue by Fee Type</h2>
        </div>
        <div class="p-6">
            <canvas id="feeTypeChart" height="250"></canvas>
        </div>
    </div>
</div>

<!-- Projected Collections & Payment Methods -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Projected Collections -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800">Expected Collections</h2>
        </div>
        <div class="p-4">
            <?php if (count($projected) > 0): ?>
                <?php foreach ($projected as $proj): ?>
                    <div class="flex items-center justify-between py-3 border-b border-slate-100 last:border-0">
                        <div>
                            <span class="font-medium text-slate-800">
                                <?php echo $proj['period']; ?>
                            </span>
                            <span class="text-sm text-slate-500 ml-2">(
                                <?php echo $proj['invoice_count']; ?> invoices)
                            </span>
                        </div>
                        <span
                            class="font-semibold <?php echo $proj['period'] === 'Overdue' ? 'text-red-600' : 'text-slate-700'; ?>">
                            <?php echo formatCurrency($proj['amount']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-slate-500 py-4">No pending invoices</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800">Payment Methods</h2>
            <p class="text-xs text-slate-500">Last 30 days</p>
        </div>
        <div class="p-6">
            <canvas id="paymentMethodChart" height="200"></canvas>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-slate-800">Recent Payments</h2>
            <a href="payments.php" class="text-sm text-primary-600 hover:text-primary-700">View All →</a>
        </div>
        <div class="divide-y divide-slate-100">
            <?php if (count($recentTx) > 0): ?>
                <?php foreach ($recentTx as $tx): ?>
                    <div class="px-4 py-3 flex items-center justify-between">
                        <div>
                            <span class="font-medium text-slate-800 text-sm">
                                <?php echo htmlspecialchars($tx['student_name']); ?>
                            </span>
                            <p class="text-xs text-slate-500">
                                <?php echo date('M j, g:i A', strtotime($tx['created_at'])); ?>
                            </p>
                        </div>
                        <span class="font-semibold text-green-600">+
                            <?php echo formatCurrency($tx['amount']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-slate-500 py-4">No recent transactions</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Top Defaulters -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm">
    <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
        <div>
            <h2 class="text-lg font-semibold text-slate-800">Students with Outstanding Dues</h2>
            <p class="text-xs text-slate-500">Sorted by highest outstanding amount</p>
        </div>
        <a href="ledger.php?filter=overdue" class="btn btn-secondary text-sm">
            View All Defaulters
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Student</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Outstanding</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Oldest Due</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php if (count($defaulters) > 0): ?>
                    <?php foreach ($defaulters as $d): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">
                                <span class="font-medium text-slate-800">
                                    <?php echo htmlspecialchars($d['name']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <div>
                                    <?php echo htmlspecialchars($d['email']); ?>
                                </div>
                                <div>
                                    <?php echo htmlspecialchars($d['phone'] ?? ''); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-semibold text-red-600">
                                    <?php echo formatCurrency($d['outstanding']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <?php if ($d['oldest_due']): ?>
                                    <span class="text-red-600">
                                        <?php echo date('M j, Y', strtotime($d['oldest_due'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <a href="student_ledger.php?id=<?php echo $d['id']; ?>"
                                    class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                                    View Ledger →
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-500">
                            🎉 No outstanding dues! All payments are up to date.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Revenue Trend Chart
    new Chart(document.getElementById('revenueTrendChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($trend, 'label')); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_map('floatval', array_column($trend, 'revenue'))); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.4
        }]
    },
    options: {
        responsive: true,
            plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                    ticks: { callback: value => 'Rs. ' + value.toLocaleString() }
            }
        }
    }
});

    // Fee Type Breakdown Chart
    new Chart(document.getElementById('feeTypeChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($feeTypes, 'fee_type')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_map('floatval', array_column($feeTypes, 'total_amount'))); ?>,
                    backgroundColor: ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ef4444', '#6366f1']
        }]
    },
    options: {
        responsive: true,
            plugins: {
            legend: { position: 'bottom' }
        }
    }
});

    // Payment Methods Chart
    new Chart(document.getElementById('paymentMethodChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($methods, 'method')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_map('floatval', array_column($methods, 'total'))); ?>,
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6']
        }]
    },
    options: {
        responsive: true,
            plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                    ticks: { callback: value => 'Rs. ' + value.toLocaleString() }
            }
        }
    }
});
</script>

<?php require_once '../../templates/footer.php'; ?>