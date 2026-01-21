<?php
declare(strict_types=1);

namespace EduCRM\Services;

/**
 * Invoice Service
 * Handles invoice generation and PDF creation for student fees
 */
class InvoiceService
{
    private \PDO $pdo;

    /** @var array<string, mixed> Company details - customize these */
    private array $companyDetails = [
        'name' => 'EduCRM Education Consultancy',
        'address' => 'Kathmandu, Nepal',
        'phone' => '+977-1-XXXXXXX',
        'email' => 'info@educrm.local',
        'website' => 'www.educrm.local',
        'logo' => null // Path to logo file if available
    ];

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Set company details
     */
    public function setCompanyDetails(array $details): void
    {
        $this->companyDetails = array_merge($this->companyDetails, $details);
    }

    /**
     * Get company details
     */
    public function getCompanyDetails(): array
    {
        return $this->companyDetails;
    }

    /**
     * Generate invoice data for a fee
     */
    public function generateInvoice(int $feeId): ?array
    {
        // Get fee details
        $stmt = $this->pdo->prepare("
            SELECT sf.*, ft.name as fee_type_name, 
                   u.name as student_name, u.email as student_email, u.phone as student_phone, u.address as student_address
            FROM student_fees sf
            LEFT JOIN fee_types ft ON sf.fee_type_id = ft.id
            LEFT JOIN users u ON sf.student_id = u.id
            WHERE sf.id = ?
        ");
        $stmt->execute([$feeId]);
        $fee = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$fee) {
            return null;
        }

        // Get payments
        $stmt = $this->pdo->prepare("
            SELECT * FROM payments WHERE student_fee_id = ? ORDER BY transaction_date ASC
        ");
        $stmt->execute([$feeId]);
        $payments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Calculate totals
        $totalPaid = array_sum(array_column($payments, 'amount'));
        $balance = $fee['amount'] - $totalPaid;

        return [
            'invoice_number' => 'INV-' . str_pad((string) $feeId, 6, '0', STR_PAD_LEFT),
            'invoice_date' => date('Y-m-d', strtotime($fee['created_at'])),
            'due_date' => $fee['due_date'],
            'status' => $fee['status'],
            'company' => $this->companyDetails,
            'student' => [
                'id' => $fee['student_id'],
                'name' => $fee['student_name'],
                'email' => $fee['student_email'],
                'phone' => $fee['student_phone'],
                'address' => $fee['student_address']
            ],
            'items' => [
                [
                    'description' => $fee['fee_type_name'] . ($fee['description'] ? ' - ' . $fee['description'] : ''),
                    'amount' => $fee['amount']
                ]
            ],
            'subtotal' => $fee['amount'],
            'total' => $fee['amount'],
            'payments' => $payments,
            'total_paid' => $totalPaid,
            'balance_due' => $balance
        ];
    }

    /**
     * Generate PDF invoice
     * Returns PDF content as string (HTML-based)
     */
    public function generatePDF(array $invoiceData): string
    {
        $company = $invoiceData['company'];
        $student = $invoiceData['student'];

        $statusClass = match ($invoiceData['status']) {
            'paid' => 'status-paid',
            'partial' => 'status-partial',
            default => 'status-unpaid'
        };

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice {$this->escape($invoiceData['invoice_number'])}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body { padding: 40px; font-size: 12px; color: #333; }
        .header { display: flex; justify-content: space-between; margin-bottom: 40px; border-bottom: 2px solid #4f46e5; padding-bottom: 20px; }
        .company-info h1 { color: #4f46e5; font-size: 24px; margin-bottom: 5px; }
        .company-info p { color: #666; line-height: 1.6; }
        .invoice-title { text-align: right; }
        .invoice-title h2 { font-size: 32px; color: #4f46e5; }
        .invoice-title p { color: #666; }
        .addresses { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .address-block h3 { color: #4f46e5; margin-bottom: 10px; font-size: 14px; }
        .address-block p { line-height: 1.6; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th { background: #4f46e5; color: white; padding: 12px; text-align: left; }
        .items-table td { padding: 12px; border-bottom: 1px solid #e5e7eb; }
        .items-table .amount { text-align: right; }
        .totals { width: 300px; margin-left: auto; }
        .totals table { width: 100%; }
        .totals td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .totals td:last-child { text-align: right; font-weight: bold; }
        .totals .total-row { background: #f0fdf4; }
        .totals .balance-row { background: #fef3c7; }
        .totals .paid-row { background: #dcfce7; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-partial { background: #fef3c7; color: #d97706; }
        .status-unpaid { background: #fee2e2; color: #991b1b; }
        .footer { margin-top: 50px; text-align: center; color: #666; font-size: 11px; border-top: 1px solid #e5e7eb; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <h1>{$this->escape($company['name'])}</h1>
            <p>
                {$this->escape($company['address'])}<br>
                Phone: {$this->escape($company['phone'])}<br>
                Email: {$this->escape($company['email'])}
            </p>
        </div>
        <div class="invoice-title">
            <h2>INVOICE</h2>
            <p>{$this->escape($invoiceData['invoice_number'])}</p>
        </div>
    </div>

    <div class="addresses">
        <div class="address-block">
            <h3>Bill To:</h3>
            <p>
                <strong>{$this->escape($student['name'])}</strong><br>
                {$this->formatStudentInfo($student)}
            </p>
        </div>
        <div class="address-block" style="text-align: right;">
            <h3>Invoice Details:</h3>
            <p>
                <strong>Date:</strong> {$this->escape($invoiceData['invoice_date'])}<br>
                {$this->formatDueDate($invoiceData['due_date'])}
                <strong>Status:</strong> <span class="status-badge {$statusClass}">{$this->escape(strtoupper($invoiceData['status']))}</span>
            </p>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            {$this->formatItems($invoiceData['items'])}
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr class="total-row">
                <td>Total</td>
                <td>\${$this->formatNumber($invoiceData['total'])}</td>
            </tr>
            <tr class="paid-row">
                <td>Amount Paid</td>
                <td>\${$this->formatNumber($invoiceData['total_paid'])}</td>
            </tr>
            <tr class="balance-row">
                <td><strong>Balance Due</strong></td>
                <td><strong>\${$this->formatNumber($invoiceData['balance_due'])}</strong></td>
            </tr>
        </table>
    </div>

    {$this->formatPaymentHistory($invoiceData['payments'])}

    <div class="footer">
        <p>Thank you for your business!</p>
        <p>This is a computer-generated invoice. No signature required.</p>
        <p>{$this->escape($company['website'])}</p>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function formatNumber(float $value): string
    {
        return number_format($value, 2);
    }

    private function formatStudentInfo(array $student): string
    {
        $lines = [];
        if (!empty($student['email'])) {
            $lines[] = 'Email: ' . $this->escape($student['email']);
        }
        if (!empty($student['phone'])) {
            $lines[] = 'Phone: ' . $this->escape($student['phone']);
        }
        if (!empty($student['address'])) {
            $lines[] = $this->escape($student['address']);
        }
        return implode('<br>', $lines);
    }

    private function formatDueDate(?string $dueDate): string
    {
        if ($dueDate) {
            return '<strong>Due Date:</strong> ' . $this->escape($dueDate) . '<br>';
        }
        return '';
    }

    private function formatItems(array $items): string
    {
        $html = '';
        foreach ($items as $item) {
            $html .= '<tr>';
            $html .= '<td>' . $this->escape($item['description']) . '</td>';
            $html .= '<td class="amount">$' . $this->formatNumber($item['amount']) . '</td>';
            $html .= '</tr>';
        }
        return $html;
    }

    private function formatPaymentHistory(array $payments): string
    {
        if (empty($payments)) {
            return '';
        }

        $html = '<div class="payments-section" style="margin-top: 30px;">';
        $html .= '<h3 style="color: #4f46e5; margin-bottom: 15px;">Payment History</h3>';
        $html .= '<table class="items-table"><thead><tr>';
        $html .= '<th>Date</th><th>Method</th><th>Remarks</th><th class="amount">Amount</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($payments as $payment) {
            $date = date('Y-m-d', strtotime($payment['transaction_date']));
            $html .= '<tr>';
            $html .= '<td>' . $this->escape($date) . '</td>';
            $html .= '<td>' . $this->escape($payment['payment_method']) . '</td>';
            $html .= '<td>' . $this->escape($payment['remarks'] ?? '-') . '</td>';
            $html .= '<td class="amount">$' . $this->formatNumber($payment['amount']) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    /**
     * Get recent invoices for a student
     */
    public function getStudentInvoices(int $studentId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT sf.*, ft.name as fee_type_name
            FROM student_fees sf
            LEFT JOIN fee_types ft ON sf.fee_type_id = ft.id
            WHERE sf.student_id = ?
            ORDER BY sf.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$studentId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
