<?php
namespace EduCRM\Services;

/**
 * PDF Report Service
 * Generates printable PDF reports using Dompdf or native HTML-to-PDF
 */
class PdfReportService
{
    private $pdo;
    private $companyName = 'EduCRM';

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Generate Counselor Performance Report
     */
    public function generateCounselorReport(string $startDate, string $endDate): string
    {
        // Get counselor performance data
        $counselorData = $this->getCounselorPerformance($startDate, $endDate);
        $taskStats = $this->getTaskStats($startDate, $endDate);

        $html = $this->getReportHeader('Counselor Performance Report', $startDate, $endDate);

        $html .= '<h2 style="color: #1e293b; margin-top: 30px;">Summary</h2>';
        $html .= '<table class="summary-table">
            <tr><td><strong>Total Tasks Completed</strong></td><td>' . $taskStats['completed'] . '</td></tr>
            <tr><td><strong>Total Appointments</strong></td><td>' . $taskStats['appointments'] . '</td></tr>
            <tr><td><strong>Inquiry Conversions</strong></td><td>' . $taskStats['conversions'] . '</td></tr>
        </table>';

        $html .= '<h2 style="color: #1e293b; margin-top: 30px;">Individual Performance</h2>';
        $html .= '<table class="data-table">
            <thead>
                <tr>
                    <th>Counselor</th>
                    <th>Tasks Completed</th>
                    <th>Appointments</th>
                    <th>Conversions</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($counselorData as $c) {
            $html .= '<tr>
                <td>' . htmlspecialchars($c['name']) . '</td>
                <td>' . $c['tasks_completed'] . '</td>
                <td>' . $c['appointments'] . '</td>
                <td>' . $c['conversions'] . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';
        $html .= $this->getReportFooter();

        return $this->generatePdf($html, 'counselor_report');
    }

    /**
     * Generate Financial Summary Report
     */
    public function generateFinancialReport(string $startDate, string $endDate): string
    {
        $financeService = new FinancialReportService($this->pdo);
        $overview = $financeService->getOverview($startDate, $endDate);
        $feeTypes = $financeService->getFeeTypeBreakdown();
        $defaulters = $financeService->getTopDefaulters(20);

        $html = $this->getReportHeader('Financial Summary Report', $startDate, $endDate);

        $html .= '<h2 style="color: #1e293b; margin-top: 30px;">Financial Overview</h2>';
        $html .= '<div style="display: flex; gap: 20px; margin-bottom: 20px;">
            <div class="kpi-box" style="background: #dcfce7; border-color: #10b981; flex: 1;">
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-value">Rs. ' . number_format($overview['total_revenue'], 2) . '</div>
            </div>
            <div class="kpi-box" style="background: #fef3c7; border-color: #f59e0b; flex: 1;">
                <div class="kpi-label">Outstanding</div>
                <div class="kpi-value">Rs. ' . number_format($overview['outstanding'], 2) . '</div>
            </div>
            <div class="kpi-box" style="background: #dbeafe; border-color: #3b82f6; flex: 1;">
                <div class="kpi-label">Collection Rate</div>
                <div class="kpi-value">' . number_format($overview['collection_rate'], 1) . '%</div>
            </div>
        </div>';

        if (!empty($feeTypes)) {
            $html .= '<h2 style="color: #1e293b; margin-top: 30px;">Revenue by Fee Type</h2>';
            $html .= '<table class="data-table">
                <thead>
                    <tr>
                        <th>Fee Type</th>
                        <th>Invoices</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($feeTypes as $ft) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($ft['fee_type']) . '</td>
                    <td>' . $ft['invoice_count'] . '</td>
                    <td>Rs. ' . number_format($ft['total_amount'], 2) . '</td>
                </tr>';
            }

            $html .= '</tbody></table>';
        }

        if (!empty($defaulters)) {
            $html .= '<h2 style="color: #1e293b; margin-top: 30px; page-break-before: auto;">Outstanding Dues by Student</h2>';
            $html .= '<table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Outstanding</th>
                        <th>Oldest Due</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($defaulters as $d) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($d['name']) . '</td>
                    <td>' . htmlspecialchars($d['email']) . '</td>
                    <td style="color: #dc2626; font-weight: bold;">Rs. ' . number_format($d['outstanding'], 2) . '</td>
                    <td>' . ($d['oldest_due'] ? date('M j, Y', strtotime($d['oldest_due'])) : '-') . '</td>
                </tr>';
            }

            $html .= '</tbody></table>';
        }

        $html .= $this->getReportFooter();

        return $this->generatePdf($html, 'financial_report');
    }

    /**
     * Generate Inquiry Pipeline Report
     */
    public function generatePipelineReport(string $startDate, string $endDate): string
    {
        $pipelineData = $this->getPipelineStats($startDate, $endDate);
        $sourceData = $this->getSourceStats($startDate, $endDate);

        $html = $this->getReportHeader('Inquiry Pipeline Report', $startDate, $endDate);

        $html .= '<h2 style="color: #1e293b; margin-top: 30px;">Pipeline Summary</h2>';
        $html .= '<table class="data-table">
            <thead>
                <tr>
                    <th>Stage</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>';

        $total = array_sum(array_column($pipelineData, 'count'));
        foreach ($pipelineData as $stage) {
            $percentage = $total > 0 ? ($stage['count'] / $total) * 100 : 0;
            $html .= '<tr>
                <td>' . htmlspecialchars($stage['status']) . '</td>
                <td>' . $stage['count'] . '</td>
                <td>' . number_format($percentage, 1) . '%</td>
            </tr>';
        }

        $html .= '</tbody></table>';

        if (!empty($sourceData)) {
            $html .= '<h2 style="color: #1e293b; margin-top: 30px;">Lead Sources</h2>';
            $html .= '<table class="data-table">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($sourceData as $source) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($source['source'] ?: 'Unknown') . '</td>
                    <td>' . $source['count'] . '</td>
                </tr>';
            }

            $html .= '</tbody></table>';
        }

        $html .= $this->getReportFooter();

        return $this->generatePdf($html, 'pipeline_report');
    }

    /**
     * Get counselor performance data
     */
    private function getCounselorPerformance(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.name,
                (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'completed' 
                 AND updated_at BETWEEN ? AND ?) as tasks_completed,
                (SELECT COUNT(*) FROM appointments WHERE counselor_id = u.id 
                 AND appointment_date BETWEEN ? AND ?) as appointments,
                (SELECT COUNT(*) FROM inquiries WHERE assigned_to = u.id AND status = 'converted' 
                 AND updated_at BETWEEN ? AND ?) as conversions
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE r.name IN ('admin', 'counselor')
            ORDER BY u.name
        ");
        $stmt->execute([$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get task statistics
     */
    private function getTaskStats(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM tasks WHERE status = 'completed' AND updated_at BETWEEN ? AND ?) as completed,
                (SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ?) as appointments,
                (SELECT COUNT(*) FROM inquiries WHERE status = 'converted' AND updated_at BETWEEN ? AND ?) as conversions
        ");
        $stmt->execute([$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get pipeline stats
     */
    private function getPipelineStats(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT status, COUNT(*) as count
            FROM inquiries
            WHERE created_at BETWEEN ? AND ?
            GROUP BY status
            ORDER BY FIELD(status, 'new', 'contacted', 'qualified', 'proposal', 'converted', 'lost')
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get source stats
     */
    private function getSourceStats(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT source, COUNT(*) as count
            FROM inquiries
            WHERE created_at BETWEEN ? AND ?
            GROUP BY source
            ORDER BY count DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Generate report header HTML
     */
    private function getReportHeader(string $title, string $startDate, string $endDate): string
    {
        $dateRange = date('M j, Y', strtotime($startDate)) . ' - ' . date('M j, Y', strtotime($endDate));
        $generatedAt = date('F j, Y g:i A');

        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1e293b; margin: 40px; }
                .header { border-bottom: 2px solid #0d9488; padding-bottom: 15px; margin-bottom: 20px; }
                .header h1 { color: #0d9488; margin: 0 0 5px 0; font-size: 24px; }
                .header .meta { color: #64748b; font-size: 11px; }
                .data-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                .data-table th { background: #f1f5f9; padding: 10px; text-align: left; border-bottom: 2px solid #e2e8f0; font-size: 11px; }
                .data-table td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
                .data-table tr:nth-child(even) { background: #f8fafc; }
                .summary-table { width: 50%; margin: 15px 0; }
                .summary-table td { padding: 8px; border-bottom: 1px solid #e2e8f0; }
                .kpi-box { padding: 15px; border-radius: 8px; border-left: 4px solid; text-align: center; }
                .kpi-label { font-size: 11px; color: #64748b; }
                .kpi-value { font-size: 18px; font-weight: bold; margin-top: 5px; }
                .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 10px; text-align: center; }
                @page { margin: 20mm; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>' . htmlspecialchars($title) . '</h1>
                <div class="meta">
                    <strong>Period:</strong> ' . $dateRange . ' &nbsp;|&nbsp; 
                    <strong>Generated:</strong> ' . $generatedAt . '
                </div>
            </div>';
    }

    /**
     * Generate report footer HTML
     */
    private function getReportFooter(): string
    {
        return '<div class="footer">
                <p>' . $this->companyName . ' &copy; ' . date('Y') . ' | Confidential Report</p>
            </div>
        </body>
        </html>';
    }

    /**
     * Generate PDF from HTML
     * Uses browser print or Dompdf if available
     */
    private function generatePdf(string $html, string $prefix): string
    {
        // Generate filename
        $filename = $prefix . '_' . date('Y-m-d_His') . '.html';
        $filepath = __DIR__ . '/../../storage/exports/' . $filename;

        // Ensure directory exists
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Save HTML file (can be printed to PDF from browser)
        file_put_contents($filepath, $html);

        return $filename;
    }

    /**
     * Stream HTML for browser PDF print
     */
    public function streamForPrint(string $html): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        echo '<script>window.print();</script>';
    }
}
