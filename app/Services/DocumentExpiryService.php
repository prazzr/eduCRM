<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
 * Document Expiry Service
 * Tracks document expiry dates and sends proactive alerts
 * 
 * @package EduCRM\Services
 */
class DocumentExpiryService
{
    private \PDO $pdo;

    private const ALERT_THRESHOLDS = [
        '30_days' => 30,
        '14_days' => 14,
        '7_days' => 7,
        'expired' => 0
    ];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get documents expiring within given days
     * 
     * @param int $daysAhead Number of days to look ahead
     * @return array List of expiring documents
     */
    public function getExpiringDocuments(int $daysAhead = 30): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, u.name as student_name, u.email as student_email,
                   DATEDIFF(d.expiry_date, CURDATE()) as days_until_expiry,
                   uploader.name as uploaded_by_name
            FROM documents d
            LEFT JOIN users u ON d.entity_type = 'student' AND d.entity_id = u.id
            LEFT JOIN users uploader ON d.uploaded_by = uploader.id
            WHERE d.expiry_date IS NOT NULL
              AND d.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
              AND d.expiry_date >= CURDATE()
            ORDER BY d.expiry_date ASC
        ");
        $stmt->execute([$daysAhead]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get already expired documents
     * 
     * @return array List of expired documents
     */
    public function getExpiredDocuments(): array
    {
        $stmt = $this->pdo->query("
            SELECT d.*, u.name as student_name, u.email as student_email,
                   DATEDIFF(CURDATE(), d.expiry_date) as days_expired,
                   uploader.name as uploaded_by_name
            FROM documents d
            LEFT JOIN users u ON d.entity_type = 'student' AND d.entity_id = u.id
            LEFT JOIN users uploader ON d.uploaded_by = uploader.id
            WHERE d.expiry_date IS NOT NULL
              AND d.expiry_date < CURDATE()
            ORDER BY d.expiry_date DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Process and send expiry alerts (called by cron)
     * 
     * @return array Results with alerts_sent count and errors
     */
    public function processExpiryAlerts(): array
    {
        $results = ['alerts_sent' => 0, 'errors' => []];

        foreach (self::ALERT_THRESHOLDS as $alertType => $days) {
            $documents = $this->getDocumentsForAlert($alertType, $days);

            foreach ($documents as $doc) {
                try {
                    $this->sendExpiryAlert($doc, $alertType);
                    $this->recordAlertSent($doc['id'], $alertType, $doc['uploaded_by']);
                    $results['alerts_sent']++;
                } catch (\Exception $e) {
                    $results['errors'][] = "Doc #{$doc['id']}: {$e->getMessage()}";
                }
            }
        }

        return $results;
    }

    /**
     * Get documents that need a specific alert type
     */
    private function getDocumentsForAlert(string $alertType, int $days): array
    {
        $dateCondition = $days === 0
            ? "d.expiry_date = CURDATE()"
            : "d.expiry_date = DATE_ADD(CURDATE(), INTERVAL {$days} DAY)";

        $stmt = $this->pdo->prepare("
            SELECT d.*, u.name as student_name, u.email as student_email
            FROM documents d
            LEFT JOIN users u ON d.entity_type = 'student' AND d.entity_id = u.id
            LEFT JOIN document_expiry_alerts dea ON d.id = dea.document_id AND dea.alert_type = ?
            WHERE d.expiry_date IS NOT NULL
              AND {$dateCondition}
              AND dea.id IS NULL
        ");
        $stmt->execute([$alertType]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Send expiry alert notification
     */
    private function sendExpiryAlert(array $document, string $alertType): void
    {
        $daysText = match ($alertType) {
            '30_days' => '30 days',
            '14_days' => '14 days',
            '7_days' => '7 days',
            'expired' => 'TODAY (EXPIRED)',
            default => 'soon'
        };

        $priority = ($alertType === 'expired' || $alertType === '7_days') ? 'high' : 'normal';

        // Create in-app notification
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, priority, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $document['uploaded_by'],
            'document_expiry',
            "Document Expiring: " . basename($document['file_name']),
            "The document '" . basename($document['file_name']) . "'" .
            ($document['student_name'] ? " for {$document['student_name']}" : "") .
            " expires in {$daysText}.",
            "/modules/documents/view.php?id={$document['id']}",
            $priority
        ]);
    }

    /**
     * Record that an alert was sent
     */
    private function recordAlertSent(int $documentId, string $alertType, int $userId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO document_expiry_alerts (document_id, alert_type, sent_at, sent_to)
            VALUES (?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE sent_at = NOW()
        ");
        $stmt->execute([$documentId, $alertType, $userId]);
    }

    /**
     * Get expiry summary for dashboard widget
     * 
     * @return array Counts of expired and expiring documents
     */
    public function getExpirySummary(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                COALESCE(SUM(CASE WHEN expiry_date < CURDATE() THEN 1 ELSE 0 END), 0) as expired,
                COALESCE(SUM(CASE WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) as expiring_7_days,
                COALESCE(SUM(CASE WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) as expiring_30_days
            FROM documents
            WHERE expiry_date IS NOT NULL
        ");
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get documents by expiry filter
     * 
     * @param string $filter Filter type: 'expired', 'expiring_soon', 'expiring_30'
     * @return array Filtered documents
     */
    public function getDocumentsByExpiryFilter(string $filter): array
    {
        $condition = match ($filter) {
            'expired' => "expiry_date < CURDATE()",
            'expiring_soon' => "expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
            'expiring_30' => "expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)",
            default => "expiry_date IS NOT NULL"
        };

        $stmt = $this->pdo->query("
            SELECT d.*, u.name as student_name,
                   CASE 
                       WHEN expiry_date < CURDATE() THEN 'expired'
                       WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'critical'
                       WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'warning'
                       ELSE 'normal'
                   END as expiry_status,
                   DATEDIFF(expiry_date, CURDATE()) as days_until_expiry
            FROM documents d
            LEFT JOIN users u ON d.entity_type = 'student' AND d.entity_id = u.id
            WHERE {$condition}
            ORDER BY expiry_date ASC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Update document expiry date
     */
    public function updateExpiryDate(int $documentId, ?string $expiryDate): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE documents SET expiry_date = ? WHERE id = ?
        ");
        return $stmt->execute([$expiryDate, $documentId]);
    }
}
