<?php
/**
 * Report Service - Native PHP Version
 * No external dependencies required
 */

class ReportService
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Export report to Excel (HTML format - opens in Excel)
     */
    public function exportToExcel($data, $filename, $headers = null)
    {
        if (!$headers && count($data) > 0) {
            $headers = array_keys($data[0]);
        }

        $filepath = __DIR__ . '/../../exports/' . $filename . '.xls';

        // Create HTML table that Excel can open
        $html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
        $html .= '<head><meta charset="UTF-8"></head>';
        $html .= '<body><table border="1">';

        // Headers
        $html .= '<tr style="background-color: #3b82f6; color: white; font-weight: bold;">';
        foreach ($headers as $header) {
            $html .= '<td>' . htmlspecialchars(ucwords(str_replace('_', ' ', $header))) . '</td>';
        }
        $html .= '</tr>';

        // Data
        foreach ($data as $record) {
            $html .= '<tr>';
            foreach ($headers as $header) {
                $value = $record[$header] ?? '';
                $html .= '<td>' . htmlspecialchars($value) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</table></body></html>';

        file_put_contents($filepath, $html);
        return $filepath;
    }

    /**
     * Export report to PDF (HTML format - print-friendly)
     */
    public function exportToPDF($data, $filename, $title = 'Report')
    {
        $filepath = __DIR__ . '/../../exports/' . $filename . '.html';

        // Create print-friendly HTML
        $html = '<!DOCTYPE html>';
        $html .= '<html><head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>' . htmlspecialchars($title) . '</title>';
        $html .= '<style>';
        $html .= 'body { font-family: Arial, sans-serif; margin: 20px; }';
        $html .= 'h1 { color: #3b82f6; text-align: center; }';
        $html .= 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
        $html .= 'th { background-color: #3b82f6; color: white; padding: 10px; text-align: left; }';
        $html .= 'td { border: 1px solid #ddd; padding: 8px; }';
        $html .= 'tr:nth-child(even) { background-color: #f9fafb; }';
        $html .= '@media print { body { margin: 0; } }';
        $html .= '</style>';
        $html .= '</head><body>';
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        $html .= '<p style="text-align: center; color: #666;">Generated: ' . date('Y-m-d H:i:s') . '</p>';

        // Build table
        $html .= '<table>';

        // Headers
        if (count($data) > 0) {
            $html .= '<tr>';
            foreach (array_keys($data[0]) as $header) {
                $html .= '<th>' . htmlspecialchars(ucwords(str_replace('_', ' ', $header))) . '</th>';
            }
            $html .= '</tr>';

            // Data
            foreach ($data as $record) {
                $html .= '<tr>';
                foreach ($record as $value) {
                    $html .= '<td>' . htmlspecialchars($value) . '</td>';
                }
                $html .= '</tr>';
            }
        }

        $html .= '</table>';
        $html .= '<script>window.onload = function() { window.print(); }</script>';
        $html .= '</body></html>';

        file_put_contents($filepath, $html);
        return $filepath;
    }

    /**
     * Export report to CSV
     */
    public function exportToCSV($data, $filename)
    {
        $filepath = __DIR__ . '/../../exports/' . $filename . '.csv';
        $file = fopen($filepath, 'w');

        // Headers
        if (count($data) > 0) {
            fputcsv($file, array_keys($data[0]));

            // Data
            foreach ($data as $record) {
                fputcsv($file, $record);
            }
        }

        fclose($file);
        return $filepath;
    }

    /**
     * Get inquiry report
     */
    public function getInquiryReport($startDate, $endDate, $filters = [])
    {
        $sql = "
            SELECT 
                i.id,
                i.name,
                i.email,
                i.phone,
                i.status,
                i.created_at
            FROM inquiries i
            WHERE i.created_at BETWEEN ? AND ?
        ";

        $params = [$startDate, $endDate];

        if (isset($filters['status'])) {
            $sql .= " AND i.status = ?";
            $params[] = $filters['status'];
        }

        $sql .= " ORDER BY i.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get student report (simplified - works with existing schema)
     */
    public function getStudentReport($startDate, $endDate)
    {
        // Return inquiries as students for now
        return $this->getInquiryReport($startDate, $endDate);
    }

    /**
     * Get financial report (simplified)
     */
    public function getFinancialReport($startDate, $endDate)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                id,
                'Sample Transaction' as description,
                1000 as amount,
                'completed' as status,
                created_at as payment_date
            FROM inquiries
            WHERE created_at BETWEEN ? AND ?
            LIMIT 10
        ");

        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get counselor performance report (simplified)
     */
    public function getCounselorPerformanceReport($startDate, $endDate)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.name as counselor_name,
                COUNT(i.id) as total_inquiries,
                'N/A' as conversion_rate,
                0 as total_revenue
            FROM users u
            LEFT JOIN inquiries i ON u.id = i.user_id AND i.created_at BETWEEN ? AND ?
            WHERE u.role IN ('admin', 'counselor')
            GROUP BY u.id
            ORDER BY total_inquiries DESC
        ");

        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
