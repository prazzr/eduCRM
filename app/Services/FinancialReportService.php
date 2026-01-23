<?php
namespace EduCRM\Services;

/**
 * Financial Report Service
 * Provides KPIs, charts, and analytics for the accounting module
 */
class FinancialReportService
{
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get financial overview KPIs
     */
    public function getOverview(?string $startDate = null, ?string $endDate = null): array
    {
        $dateFilter = '';
        $params = [];

        if ($startDate && $endDate) {
            $dateFilter = 'AND created_at BETWEEN ? AND ?';
            $params = [$startDate, $endDate];
        }

        // Total Revenue (Payments Received)
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM payments
            WHERE status = 'completed' {$dateFilter}
        ");
        $stmt->execute($params);
        $revenue = $stmt->fetchColumn();

        // Total Invoiced
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM invoices
            WHERE status != 'cancelled' {$dateFilter}
        ");
        $stmt->execute($params);
        $invoiced = $stmt->fetchColumn();

        // Outstanding Balance
        $outstanding = $this->pdo->query("
            SELECT COALESCE(SUM(i.amount - COALESCE(
                (SELECT SUM(amount) FROM payments WHERE invoice_id = i.id AND status = 'completed'), 0
            )), 0) as total
            FROM invoices i
            WHERE i.status IN ('unpaid', 'partial')
        ")->fetchColumn();

        // Students with Overdue Payments
        $overdueCount = $this->pdo->query("
            SELECT COUNT(DISTINCT student_id) 
            FROM invoices 
            WHERE status IN ('unpaid', 'partial') AND due_date < CURDATE()
        ")->fetchColumn();

        // This Month Revenue
        $thisMonth = $this->pdo->query("
            SELECT COALESCE(SUM(amount), 0) 
            FROM payments 
            WHERE status = 'completed' 
            AND MONTH(created_at) = MONTH(CURDATE()) 
            AND YEAR(created_at) = YEAR(CURDATE())
        ")->fetchColumn();

        // Last Month Revenue
        $lastMonth = $this->pdo->query("
            SELECT COALESCE(SUM(amount), 0) 
            FROM payments 
            WHERE status = 'completed' 
            AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
            AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        ")->fetchColumn();

        $monthChange = $lastMonth > 0 ? (($thisMonth - $lastMonth) / $lastMonth) * 100 : 0;

        return [
            'total_revenue' => (float) $revenue,
            'total_invoiced' => (float) $invoiced,
            'outstanding' => (float) $outstanding,
            'collection_rate' => $invoiced > 0 ? ($revenue / $invoiced) * 100 : 0,
            'overdue_students' => (int) $overdueCount,
            'this_month' => (float) $thisMonth,
            'last_month' => (float) $lastMonth,
            'month_change' => round($monthChange, 1),
        ];
    }

    /**
     * Get revenue trend (last N months)
     */
    public function getRevenueTrend(int $months = 6): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                DATE_FORMAT(created_at, '%b %Y') as label,
                SUM(amount) as revenue
            FROM payments
            WHERE status = 'completed'
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->execute([$months]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get payment method breakdown
     */
    public function getPaymentMethodBreakdown(): array
    {
        return $this->pdo->query("
            SELECT 
                COALESCE(payment_method, 'Cash') as method,
                COUNT(*) as count,
                SUM(amount) as total
            FROM payments
            WHERE status = 'completed'
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY payment_method
            ORDER BY total DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get fee type breakdown
     */
    public function getFeeTypeBreakdown(): array
    {
        return $this->pdo->query("
            SELECT 
                COALESCE(ft.name, 'Other') as fee_type,
                COUNT(i.id) as invoice_count,
                SUM(i.amount) as total_amount
            FROM invoices i
            LEFT JOIN fee_types ft ON i.fee_type_id = ft.id
            WHERE i.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            AND i.status != 'cancelled'
            GROUP BY ft.id, ft.name
            ORDER BY total_amount DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get top defaulters (students with highest outstanding balance)
     */
    public function getTopDefaulters(int $limit = 10): array
    {
        return $this->pdo->query("
            SELECT 
                s.id, s.name, s.email, s.phone,
                SUM(i.amount) - COALESCE(SUM(p.paid_amount), 0) as outstanding,
                MIN(CASE WHEN i.due_date < CURDATE() THEN i.due_date ELSE NULL END) as oldest_due
            FROM users s
            JOIN invoices i ON s.id = i.student_id AND i.status IN ('unpaid', 'partial')
            LEFT JOIN (
                SELECT invoice_id, SUM(amount) as paid_amount 
                FROM payments WHERE status = 'completed' 
                GROUP BY invoice_id
            ) p ON i.id = p.invoice_id
            WHERE s.id IN (SELECT user_id FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE r.name = 'student')
            GROUP BY s.id, s.name, s.email, s.phone
            HAVING outstanding > 0
            ORDER BY outstanding DESC
            LIMIT {$limit}
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get recent transactions
     */
    public function getRecentTransactions(int $limit = 10): array
    {
        return $this->pdo->query("
            SELECT p.*, s.name as student_name
            FROM payments p
            JOIN users s ON p.student_id = s.id
            ORDER BY p.created_at DESC
            LIMIT {$limit}
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get projected collections (based on pending invoices)
     */
    public function getProjectedCollections(): array
    {
        return $this->pdo->query("
            SELECT 
                CASE 
                    WHEN due_date < CURDATE() THEN 'Overdue'
                    WHEN due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'This Week'
                    WHEN due_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 8 DAY) AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'This Month'
                    ELSE 'Future'
                END as period,
                SUM(amount) as amount,
                COUNT(*) as invoice_count
            FROM invoices
            WHERE status IN ('unpaid', 'partial')
            GROUP BY 
                CASE 
                    WHEN due_date < CURDATE() THEN 'Overdue'
                    WHEN due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'This Week'
                    WHEN due_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 8 DAY) AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'This Month'
                    ELSE 'Future'
                END
            ORDER BY FIELD(period, 'Overdue', 'This Week', 'This Month', 'Future')
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get monthly comparison stats
     */
    public function getMonthlyComparison(): array
    {
        return $this->pdo->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as collected,
                COUNT(DISTINCT CASE WHEN status = 'completed' THEN student_id END) as paying_students
            FROM payments
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
            LIMIT 12
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }
}
