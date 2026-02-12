<?php
require_once '../../app/bootstrap.php';


requireLogin();
requireAnalyticsAccess();

$reportService = new \EduCRM\Services\ReportService($pdo);

// Get parameters
$reportType = $_GET['type'] ?? null;
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Generate report data
$reportData = null;
if ($reportType) {
    switch ($reportType) {
        case 'inquiry':
            $reportData = $reportService->getInquiryReport($startDate, $endDate);
            break;
        case 'student':
            $reportData = $reportService->getStudentReport($startDate, $endDate);
            break;
        case 'financial':
            $reportData = $reportService->getFinancialReport($startDate, $endDate);
            break;
        case 'counselor':
            $reportData = $reportService->getCounselorPerformanceReport($startDate, $endDate);
            break;
    }
}

// Handle export
if (isset($_GET['export']) && $reportData) {
    $format = $_GET['export'];
    $filename = $reportType . '_report_' . date('Y-m-d');

    try {
        switch ($format) {
            case 'csv':
                $filepath = $reportService->exportToCSV($reportData, $filename);
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
                readfile($filepath);
                unlink($filepath);
                exit;

            case 'excel':
                $filepath = $reportService->exportToExcel($reportData, $filename);
                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
                readfile($filepath);
                unlink($filepath);
                exit;

            case 'pdf':
                $filepath = $reportService->exportToPDF($reportData, $filename, ucwords($reportType) . ' Report');
                header('Content-Type: text/html');
                readfile($filepath);
                unlink($filepath);
                exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pageDetails = ['title' => 'Report Builder'];
require_once '../../templates/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">📊 Custom Report Builder</h1>
    <p class="text-slate-600 mt-1">Generate and export custom reports</p>
</div>

<!-- Report Builder Form -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6">
    <h3 class="text-lg font-bold text-slate-800 mb-4">Select Report Template</h3>

    <form method="GET" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Report Type -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Report Type</label>
                <select name="type" required class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                    <option value="">Select report...</option>
                    <option value="inquiry" <?php echo $reportType === 'inquiry' ? 'selected' : ''; ?>>Inquiry Report
                    </option>
                    <option value="student" <?php echo $reportType === 'student' ? 'selected' : ''; ?>>Student Report
                    </option>
                    <option value="financial" <?php echo $reportType === 'financial' ? 'selected' : ''; ?>>Financial
                        Report</option>
                    <option value="counselor" <?php echo $reportType === 'counselor' ? 'selected' : ''; ?>>Counselor
                        Performance</option>
                </select>
            </div>

            <!-- Start Date -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Start Date</label>
                <input type="date" name="start_date" value="<?php echo $startDate; ?>"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg">
            </div>

            <!-- End Date -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">End Date</label>
                <input type="date" name="end_date" value="<?php echo $endDate; ?>"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg">
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary px-6 py-2 rounded-lg">
                🔍 Generate Report
            </button>

            <?php if ($reportData): ?>
                <a href="?type=<?php echo $reportType; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&export=csv"
                    class="btn-secondary px-6 py-2 rounded-lg">
                    📥 Export CSV
                </a>
                <a href="?type=<?php echo $reportType; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&export=excel"
                    class="btn-secondary px-6 py-2 rounded-lg">
                    📊 Export Excel
                </a>
                <a href="?type=<?php echo $reportType; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&export=pdf"
                    class="btn-secondary px-6 py-2 rounded-lg" target="_blank">
                    📄 Export PDF
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Report Preview -->
<?php if ($reportData): ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-slate-800">
                <?php echo ucwords($reportType); ?> Report Preview
            </h3>
            <span class="text-sm text-slate-600">
                <?php echo count($reportData); ?> records found
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b-2 border-slate-200">
                        <?php if (count($reportData) > 0): ?>
                            <?php foreach (array_keys($reportData[0]) as $header): ?>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">
                                    <?php echo ucwords(str_replace('_', ' ', $header)); ?>
                                </th>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($reportData, 0, 50) as $row): ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <?php foreach ($row as $value): ?>
                                <td class="py-3 px-4 text-sm text-slate-600">
                                    <?php echo htmlspecialchars($value ?? '-'); ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (count($reportData) > 50): ?>
                <div class="mt-4 text-center text-sm text-slate-600">
                    Showing first 50 of <?php echo count($reportData); ?> records. Export to see all data.
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php elseif ($reportType): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-center">
        <p class="text-amber-800">No data found for the selected criteria.</p>
    </div>
<?php endif; ?>

<!-- Report Templates Info -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h4 class="font-bold text-blue-900 mb-2">📞 Inquiry Report</h4>
        <p class="text-sm text-blue-700">All inquiries with status and contact details</p>
    </div>

    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4">
        <h4 class="font-bold text-emerald-900 mb-2">🎓 Student Report</h4>
        <p class="text-sm text-emerald-700">Student records and enrollment data</p>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
        <h4 class="font-bold text-amber-900 mb-2">💰 Financial Report</h4>
        <p class="text-sm text-amber-700">Payment transactions and financial summary</p>
    </div>

    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
        <h4 class="font-bold text-purple-900 mb-2">🏆 Counselor Performance</h4>
        <p class="text-sm text-purple-700">Performance metrics by counselor</p>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>