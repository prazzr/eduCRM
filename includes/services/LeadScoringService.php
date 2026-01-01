<?php
/**
 * Lead Scoring Service
 * Handles automatic lead scoring and priority calculation
 */

class LeadScoringService
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Calculate score for an inquiry based on various factors
     * Score range: 0-100
     */
    public function calculateScore($inquiryData)
    {
        $score = 0;

        // Factor 1: Education Level (0-25 points)
        $educationScores = [
            'high_school' => 10,
            'bachelors' => 15,
            'masters' => 20,
            'phd' => 25
        ];
        $score += $educationScores[$inquiryData['education_level']] ?? 10;

        // Factor 2: Intended Country (0-20 points)
        // High-demand countries get higher scores
        $countryScores = [
            'Australia' => 20,
            'Canada' => 20,
            'USA' => 18,
            'UK' => 18,
            'New Zealand' => 15
        ];
        $score += $countryScores[$inquiryData['intended_country']] ?? 10;

        // Factor 3: Contact Information Completeness (0-15 points)
        if (!empty($inquiryData['email']))
            $score += 8;
        if (!empty($inquiryData['phone']))
            $score += 7;

        // Factor 4: Response Time (0-20 points)
        // If last_contact_date exists, calculate days since last contact
        if (isset($inquiryData['last_contact_date'])) {
            $daysSinceContact = (time() - strtotime($inquiryData['last_contact_date'])) / 86400;
            if ($daysSinceContact < 1)
                $score += 20;
            elseif ($daysSinceContact < 3)
                $score += 15;
            elseif ($daysSinceContact < 7)
                $score += 10;
            elseif ($daysSinceContact < 14)
                $score += 5;
        } else {
            $score += 10; // New inquiry gets moderate score
        }

        // Factor 5: Intended Course (0-20 points)
        $courseScores = [
            'Engineering' => 20,
            'Business' => 18,
            'IT' => 20,
            'Medicine' => 18,
            'Arts' => 12
        ];
        $score += $courseScores[$inquiryData['intended_course']] ?? 10;

        return min(100, $score); // Cap at 100
    }

    /**
     * Determine priority based on score and other factors
     */
    public function calculatePriority($score, $inquiryData)
    {
        // Hot: Score >= 70 OR contacted within 24 hours
        if ($score >= 70)
            return 'hot';

        // Check if recently contacted
        if (isset($inquiryData['last_contact_date'])) {
            $hoursSinceContact = (time() - strtotime($inquiryData['last_contact_date'])) / 3600;
            if ($hoursSinceContact < 24 && $score >= 60)
                return 'hot';
        }

        // Warm: Score 40-69
        if ($score >= 40)
            return 'warm';

        // Cold: Score < 40
        return 'cold';
    }

    /**
     * Update inquiry score and priority
     */
    public function updateInquiryScore($inquiryId)
    {
        // Fetch inquiry data
        $stmt = $this->pdo->prepare("
            SELECT * FROM inquiries WHERE id = ?
        ");
        $stmt->execute([$inquiryId]);
        $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inquiry)
            return false;

        // Calculate score and priority
        $score = $this->calculateScore($inquiry);
        $priority = $this->calculatePriority($score, $inquiry);

        // Update database
        $stmt = $this->pdo->prepare("
            UPDATE inquiries 
            SET score = ?, priority = ?
            WHERE id = ?
        ");

        return $stmt->execute([$score, $priority, $inquiryId]);
    }

    /**
     * Get priority statistics for dashboard
     */
    public function getPriorityStats()
    {
        $stmt = $this->pdo->query("
            SELECT 
                priority,
                COUNT(*) as count
            FROM inquiries
            WHERE status != 'closed' AND status != 'converted'
            GROUP BY priority
        ");

        $stats = [
            'hot' => 0,
            'warm' => 0,
            'cold' => 0
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[$row['priority']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get top scored inquiries
     */
    public function getTopInquiries($limit = 10)
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
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update last contact date for an inquiry
     */
    public function updateLastContact($inquiryId)
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
     */
    public function recalculateAllScores()
    {
        $stmt = $this->pdo->query("
            SELECT id FROM inquiries 
            WHERE status != 'closed' AND status != 'converted'
        ");

        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($this->updateInquiryScore($row['id'])) {
                $count++;
            }
        }

        return $count;
    }
}
