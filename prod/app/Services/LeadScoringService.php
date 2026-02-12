<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
 * Lead Scoring Service
 * Handles automatic lead scoring and priority calculation
 * 
 * @package EduCRM\Services
 */
class LeadScoringService
{
    private \PDO $pdo;

    /**
     * Education level score weights
     * 
     * @var array<string, int>
     */
    private const EDUCATION_SCORES = [
        'high_school' => 10,
        'bachelors' => 15,
        'masters' => 20,
        'phd' => 25
    ];

    /**
     * Country score weights (high-demand countries)
     * 
     * @var array<string, int>
     */
    private const COUNTRY_SCORES = [
        'Australia' => 20,
        'Canada' => 20,
        'USA' => 18,
        'UK' => 18,
        'New Zealand' => 15
    ];

    /**
     * Course score weights
     * 
     * @var array<string, int>
     */
    private const COURSE_SCORES = [
        'Engineering' => 20,
        'Business' => 18,
        'IT' => 20,
        'Medicine' => 18,
        'Arts' => 12
    ];

    /**
     * Create a new \EduCRM\Services\LeadScoringService instance
     *
     * @param \PDO $pdo Database connection
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Calculate score for an inquiry based on various factors
     * Score range: 0-100
     *
     * @param array<string, mixed> $inquiryData Inquiry data
     * @return int Score between 0 and 100
     */
    public function calculateScore(array $inquiryData): int
    {
        $score = 0;

        // Factor 1: Education Level (0-25 points)
        $educationLevel = $inquiryData['education_level'] ?? '';
        $score += self::EDUCATION_SCORES[$educationLevel] ?? 10;

        // Factor 2: Intended Country (0-20 points)
        $country = $inquiryData['intended_country'] ?? '';
        $score += self::COUNTRY_SCORES[$country] ?? 10;

        // Factor 3: Contact Information Completeness (0-15 points)
        if (!empty($inquiryData['email'])) {
            $score += 8;
        }
        if (!empty($inquiryData['phone'])) {
            $score += 7;
        }

        // Factor 4: Response Time (0-20 points)
        $score += $this->calculateResponseTimeScore($inquiryData);

        // Factor 5: Intended Course (0-20 points)
        $course = $inquiryData['intended_course'] ?? '';
        $score += self::COURSE_SCORES[$course] ?? 10;

        return min(100, $score); // Cap at 100
    }

    /**
     * Calculate response time score component
     *
     * @param array<string, mixed> $inquiryData Inquiry data
     * @return int Score between 0 and 20
     */
    private function calculateResponseTimeScore(array $inquiryData): int
    {
        if (!isset($inquiryData['last_contact_date'])) {
            return 10; // New inquiry gets moderate score
        }

        $lastContact = strtotime($inquiryData['last_contact_date']);
        if ($lastContact === false) {
            return 10;
        }

        $daysSinceContact = (time() - $lastContact) / 86400;

        if ($daysSinceContact < 1) {
            return 20;
        }
        if ($daysSinceContact < 3) {
            return 15;
        }
        if ($daysSinceContact < 7) {
            return 10;
        }
        if ($daysSinceContact < 14) {
            return 5;
        }

        return 0;
    }

    /**
     * Determine priority based on score and other factors
     *
     * @param int $score The calculated score
     * @param array<string, mixed> $inquiryData Inquiry data
     * @return string Priority: 'hot', 'warm', or 'cold'
     */
    public function calculatePriority(int $score, array $inquiryData): string
    {
        // Hot: Score >= 70
        if ($score >= 70) {
            return 'hot';
        }

        // Check if recently contacted (within 24 hours) with decent score
        if (isset($inquiryData['last_contact_date'])) {
            $lastContact = strtotime($inquiryData['last_contact_date']);
            if ($lastContact !== false) {
                $hoursSinceContact = (time() - $lastContact) / 3600;
                if ($hoursSinceContact < 24 && $score >= 60) {
                    return 'hot';
                }
            }
        }

        // Warm: Score 40-69
        if ($score >= 40) {
            return 'warm';
        }

        // Cold: Score < 40
        return 'cold';
    }

    /**
     * Update inquiry score and priority
     *
     * @param int $inquiryId Inquiry ID
     * @return bool Success status
     */
    public function updateInquiryScore(int $inquiryId): bool
    {
        // Fetch inquiry data with JOINs to get lookup names for scoring
        $stmt = $this->pdo->prepare("
            SELECT i.*, 
                   c.name as country_name,
                   el.name as education_level_name
            FROM inquiries i
            LEFT JOIN countries c ON i.country_id = c.id
            LEFT JOIN education_levels el ON i.education_level_id = el.id
            WHERE i.id = ?
        ");
        $stmt->execute([$inquiryId]);
        $inquiry = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$inquiry) {
            return false;
        }

        // Map FK joined names to expected keys for scoring (supports both legacy and FK)
        if (!empty($inquiry['country_name'])) {
            $inquiry['intended_country'] = $inquiry['country_name'];
        }
        if (!empty($inquiry['education_level_name'])) {
            $inquiry['education_level'] = $inquiry['education_level_name'];
        }

        // Calculate score and priority
        $score = $this->calculateScore($inquiry);
        $priority = $this->calculatePriority($score, $inquiry);

        // Get priority_id from lookup table
        $priorityStmt = $this->pdo->prepare("SELECT id FROM priority_levels WHERE name = ?");
        $priorityStmt->execute([$priority]);
        $priority_id = $priorityStmt->fetchColumn();

        // Update database using FK column
        $stmt = $this->pdo->prepare("
            UPDATE inquiries 
            SET score = ?, priority_id = ?
            WHERE id = ?
        ");

        return $stmt->execute([$score, $priority_id, $inquiryId]);
    }

    /**
     * Get priority statistics for dashboard
     *
     * @return array{hot: int, warm: int, cold: int}
     */
    public function getPriorityStats(): array
    {
        // Use FK column with JOIN to get priority names
        $stmt = $this->pdo->query("
            SELECT 
                COALESCE(pl.name, i.priority, 'cold') as priority_name,
                COUNT(*) as count
            FROM inquiries i
            LEFT JOIN priority_levels pl ON i.priority_id = pl.id
            LEFT JOIN inquiry_statuses ist ON i.status_id = ist.id
            WHERE COALESCE(ist.name, i.status) NOT IN ('closed', 'converted')
            GROUP BY priority_name
        ");

        $stats = [
            'hot' => 0,
            'warm' => 0,
            'cold' => 0
        ];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $priority = $row['priority_name'] ?? 'cold';
            if (isset($stats[$priority])) {
                $stats[$priority] = (int) $row['count'];
            }
        }

        return $stats;
    }

    /**
     * Get top scored inquiries
     *
     * @param int $limit Maximum number of results
     * @return array<int, array<string, mixed>>
     */
    public function getTopInquiries(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                id,
                name,
                email,
                phone,
                intended_country,
                intended_course,
                priority,
                score,
                status,
                created_at
            FROM inquiries
            WHERE status != 'closed' AND status != 'converted'
            ORDER BY score DESC, created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Update last contact date for an inquiry
     *
     * @param int $inquiryId Inquiry ID
     * @return bool Success status
     */
    public function updateLastContact(int $inquiryId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE inquiries 
            SET last_contact_date = NOW()
            WHERE id = ?
        ");

        $result = $stmt->execute([$inquiryId]);

        // Recalculate score after contact update
        if ($result) {
            $this->updateInquiryScore($inquiryId);
        }

        return $result;
    }

    /**
     * Bulk recalculate scores for all active inquiries
     *
     * @return int Number of inquiries updated
     */
    public function recalculateAllScores(): int
    {
        $stmt = $this->pdo->query("
            SELECT id FROM inquiries 
            WHERE status != 'closed' AND status != 'converted'
        ");

        $count = 0;
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($this->updateInquiryScore((int) $row['id'])) {
                $count++;
            }
        }

        return $count;
    }
}
