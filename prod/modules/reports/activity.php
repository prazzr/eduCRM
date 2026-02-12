<?php
/**
 * User Activity Report
 * Shows activity logs and daily summaries for all users
 */

require_once '../../app/bootstrap.php';


requireLogin();
requireBranchManager();

// Admin or Branch Manager can view activities
$isAdmin = hasRole('admin');
$isBranchManager = hasRole('branch_manager');

$activityService = new \EduCRM\Services\ActivityService($pdo, $_SESSION['branch_id']);

// Date filters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$selectedUser = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$viewDate = $_GET['view_date'] ?? date('Y-m-d');

// Get data based on view
$dailySummaries = $activityService->getAllUsersSummary($viewDate);
$activityStats = $activityService->getActivityStats($startDate, $endDate);
$topUsers = $activityService->getMostActiveUsers($startDate, $endDate, 5);

// Get recent activities
if ($selectedUser) {
    $activities = $activityService->getUserActivity($selectedUser, $startDate, $endDate, 100);
} else {
    $activities = $activityService->getAllActivity($startDate, $endDate, 100);
}

// Get users for filter dropdown
$branchService = new \EduCRM\Services\BranchService($pdo);
$branchFilter = $branchService->getBranchFilter($_SESSION['user_id']);
$users = $pdo->query("SELECT id, name FROM users WHERE 1=1 $branchFilter ORDER BY name")->fetchAll();

$pageDetails = ['title' => 'User Activity Report'];
require_once '../../templates/header.php';
?>

<div class="card mb-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-slate-800">📊 User Activity Report</h2>
    </div>

    <!-- Filters -->
    <form method="GET" class="bg-slate-50 p-4 rounded-lg mb-6">
        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12 md:col-span-2">
                <label class="block text-sm font-medium text-slate-600 mb-1">Start Date</label>
                <input type="date" name="start_date" value="<?php echo $startDate; ?>"
                    class="form-control w-full text-sm">
            </div>
            <div class="col-span-12 md:col-span-2">
                <label class="block text-sm font-medium text-slate-600 mb-1">End Date</label>
                <input type="date" name="end_date" value="<?php echo $endDate; ?>" class="form-control w-full text-sm">
            </div>
            <div class="col-span-12 md:col-span-5">
                <label class="block text-sm font-medium text-slate-600 mb-1">User Filter</label>
                <input type="hidden" name="user_id" id="userIdValue" value="<?php echo $selectedUser; ?>">
                <div style="position: relative;">
                    <input type="text" id="userSearch" class="form-control w-full"
                        placeholder="🔍 Search user by name..." autocomplete="off" value="<?php
                        if ($selectedUser) {
                            foreach ($users as $u) {
                                if ($u['id'] == $selectedUser) {
                                    echo htmlspecialchars($u['name']);
                                    break;
                                }
                            }
                        }
                        ?>">
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const userData = <?php echo json_encode(array_map(function ($u) {
                            return ['id' => $u['id'], 'name' => $u['name']];
                        }, $users)); ?>;

                        // Add "All Users" option at the beginning
                        userData.unshift({ id: '', name: 'All Users' });

                        new SearchableDropdown({
                            inputId: 'userSearch',
                            hiddenInputId: 'userIdValue',
                            data: userData,
                            displayField: 'name'
                        });
                    });
                </script>
            </div>
            <div class="col-span-12 md:col-span-3 flex items-end">
                <button type="submit" class="btn w-full">Apply Filters</button>
            </div>
        </div>
    </form>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
            <h4 class="text-blue-700 text-sm font-medium mb-1">Total Actions</h4>
            <div class="text-2xl font-bold text-blue-800">
                <?php echo array_sum(array_column($activityStats, 'count')); ?>
            </div>
        </div>
        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
            <h4 class="text-green-700 text-sm font-medium mb-1">Active Users</h4>
            <div class="text-2xl font-bold text-green-800">
                <?php echo count($topUsers); ?>
            </div>
        </div>
        <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
            <h4 class="text-purple-700 text-sm font-medium mb-1">Most Common Action</h4>
            <div class="text-lg font-bold text-purple-800">
                <?php echo !empty($activityStats) ? \EduCRM\Services\ActivityService::getActionLabel($activityStats[0]['action']) : 'N/A'; ?>
            </div>
        </div>
        <div class="bg-amber-50 p-4 rounded-lg border border-amber-200">
            <h4 class="text-amber-700 text-sm font-medium mb-1">Top Performer</h4>
            <div class="text-lg font-bold text-amber-800">
                <?php echo !empty($topUsers) ? htmlspecialchars($topUsers[0]['user_name'] ?? 'N/A') : 'N/A'; ?>
            </div>
        </div>
    </div>

    <!-- Daily Summary Section -->
    <h3 class="text-lg font-semibold text-slate-800 mb-4">
        Daily Summary -
        <input type="date" name="view_date" value="<?php echo $viewDate; ?>"
            onchange="window.location.href='?view_date='+this.value+'&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>'"
            class="inline px-2 py-1 border rounded">
    </h3>

    <?php if (empty($dailySummaries)): ?>
        <p class="text-slate-500 text-center py-8">No activity recorded for this date.</p>
    <?php else: ?>
        <div class="overflow-x-auto mb-6">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50 text-slate-600 text-sm">
                        <th class="p-3 text-left font-semibold">User</th>
                        <th class="p-3 text-center font-semibold">Total Actions</th>
                        <th class="p-3 text-center font-semibold">Inquiries</th>
                        <th class="p-3 text-center font-semibold">Conversions</th>
                        <th class="p-3 text-center font-semibold">Tasks Done</th>
                        <th class="p-3 text-center font-semibold">Appointments</th>
                        <th class="p-3 text-center font-semibold">First Active</th>
                        <th class="p-3 text-center font-semibold">Last Active</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($dailySummaries as $summary): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="p-3 font-medium text-slate-800">
                                <?php echo htmlspecialchars($summary['user_name']); ?>
                            </td>
                            <td class="p-3 text-center">
                                <span class="inline-block px-2 py-1 bg-blue-100 text-blue-700 rounded font-bold">
                                    <?php echo $summary['total_actions']; ?>
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                <?php echo $summary['inquiries_added']; ?>
                            </td>
                            <td class="p-3 text-center">
                                <?php if ($summary['inquiries_converted'] > 0): ?>
                                    <span class="text-green-600 font-bold">
                                        <?php echo $summary['inquiries_converted']; ?>
                                    </span>
                                <?php else: ?>
                                    0
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-center">
                                <?php echo $summary['tasks_completed']; ?>
                            </td>
                            <td class="p-3 text-center">
                                <?php echo $summary['appointments_created']; ?>
                            </td>
                            <td class="p-3 text-center text-sm text-slate-500">
                                <?php echo date('H:i', strtotime($summary['first_activity'])); ?>
                            </td>
                            <td class="p-3 text-center text-sm text-slate-500">
                                <?php echo date('H:i', strtotime($summary['last_activity'])); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Activity Log -->
    <h3 class="text-lg font-semibold text-slate-800 mb-4">Recent Activity Log</h3>

    <?php if (empty($activities)): ?>
        <p class="text-slate-500 text-center py-8">No activities found for the selected criteria.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50 text-slate-600 text-sm">
                        <th class="p-3 text-left font-semibold">Time</th>
                        <th class="p-3 text-left font-semibold">User</th>
                        <th class="p-3 text-left font-semibold">Action</th>
                        <th class="p-3 text-left font-semibold">Entity</th>
                        <th class="p-3 text-left font-semibold">Details</th>
                        <th class="p-3 text-left font-semibold">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php foreach ($activities as $activity): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="p-3 text-slate-500">
                                <?php echo date('M d, H:i', strtotime($activity['created_at'])); ?>
                            </td>
                            <td class="p-3 font-medium text-slate-800">
                                <?php echo htmlspecialchars($activity['user_name'] ?? 'Unknown'); ?>
                            </td>
                            <td class="p-3">
                                <span class="inline-block px-2 py-1 bg-slate-100 text-slate-700 rounded text-xs">
                                    <?php echo \EduCRM\Services\ActivityService::getActionLabel($activity['action']); ?>
                                </span>
                            </td>
                            <td class="p-3 text-slate-600">
                                <?php if ($activity['entity_type']): ?>
                                    <?php echo ucfirst($activity['entity_type']); ?> #
                                    <?php echo $activity['entity_id']; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-slate-500 max-w-xs truncate">
                                <?php echo htmlspecialchars($activity['details'] ?? '-'); ?>
                            </td>
                            <td class="p-3 text-slate-400 text-xs">
                                <?php echo htmlspecialchars($activity['ip_address']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../templates/footer.php'; ?>