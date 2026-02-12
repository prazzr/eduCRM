<?php
/**
 * PDF Report Generation Endpoint
 * Generates and streams reports for download/print
 */
require_once '../../app/bootstrap.php';
requireLogin();
requireAnalyticsAccess();

$reportType = $_GET['type'] ?? 'counselor';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$action = $_GET['action'] ?? 'download'; // download or print

// Validate dates
if (!strtotime($startDate) || !strtotime($endDate)) {
    redirectWithAlert('dashboard.php', 'Invalid date range.', 'error');
}

$pdfService = new \EduCRM\Services\PdfReportService($pdo);

try {
    switch ($reportType) {
        case 'counselor':
            $filename = $pdfService->generateCounselorReport($startDate . ' 00:00:00', $endDate . ' 23:59:59');
            $title = 'Counselor Performance Report';
            break;

        case 'financial':
            $filename = $pdfService->generateFinancialReport($startDate . ' 00:00:00', $endDate . ' 23:59:59');
            $title = 'Financial Summary Report';
            break;

        case 'pipeline':
            $filename = $pdfService->generatePipelineReport($startDate . ' 00:00:00', $endDate . ' 23:59:59');
            $title = 'Inquiry Pipeline Report';
            break;

        default:
            throw new \Exception('Invalid report type');
    }

    $filepath = __DIR__ . '/../../storage/exports/' . $filename;

    if (!file_exists($filepath)) {
        throw new \Exception('Failed to generate report file');
    }

    // Read the HTML content
    $html = file_get_contents($filepath);

    if ($action === 'print') {
        // Stream for browser print
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        echo '<script>window.onload = function() { window.print(); }</script>';
    } else {
        // Suggest download (browser will offer print-to-PDF)
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $title . '.html"');
        echo $html;
        echo '
        <div style="position: fixed; bottom: 20px; right: 20px; background: #0d9488; color: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-family: Arial, sans-serif; z-index: 9999;">
            <strong>💡 Tip:</strong> Press <kbd style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 4px;">Ctrl+P</kbd> to save as PDF
            <button onclick="this.parentElement.remove()" style="margin-left: 15px; background: none; border: none; color: white; cursor: pointer; font-size: 16px;">×</button>
        </div>';
    }

    // Clean up file after serving
    // unlink($filepath); // Uncomment to auto-delete after serving

} catch (\Exception $e) {
    error_log('PDF Report Error: ' . $e->getMessage());
    redirectWithAlert('dashboard.php', 'Failed to generate report: ' . $e->getMessage(), 'error');
}
