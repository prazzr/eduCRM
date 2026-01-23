<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
 * Student Bulk Action Service
 * Handles bulk operations for students including email, SMS, enrollment, and status changes
 * 
 * @package EduCRM\Services
 */
class StudentBulkService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get students by IDs
     */
    public function getStudentsByIds(array $studentIds): array
    {
        if (empty($studentIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.name, u.email, u.phone
            FROM users u
            WHERE u.id IN ({$placeholders})
        ");
        $stmt->execute($studentIds);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Queue bulk emails to selected students
     * 
     * @param array $studentIds Array of student IDs
     * @param string $subject Email subject
     * @param string $body Email body (can contain {name} placeholder)
     * @return array Result with success/failed counts
     */
    public function bulkEmail(array $studentIds, string $subject, string $body): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        $students = $this->getStudentsByIds($studentIds);

        foreach ($students as $student) {
            if (empty($student['email'])) {
                $results['failed']++;
                $results['errors'][] = "No email for {$student['name']}";
                continue;
            }

            try {
                // Replace placeholders
                $personalizedBody = str_replace(
                    ['{name}', '{email}'],
                    [$student['name'], $student['email']],
                    $body
                );

                // Queue email using email_queue table
                $stmt = $this->pdo->prepare("
                    INSERT INTO email_queue (recipient_email, recipient_name, subject, body, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $student['email'],
                    $student['name'],
                    $subject,
                    $personalizedBody
                ]);

                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Failed for {$student['email']}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Send bulk SMS to selected students
     * 
     * @param array $studentIds Array of student IDs
     * @param string $message SMS message (can contain {name} placeholder)
     * @return array Result with success/failed counts
     */
    public function bulkSms(array $studentIds, string $message): array
    {
        $results = ['success' => 0, 'failed' => 0, 'no_phone' => 0, 'errors' => []];

        $students = $this->getStudentsByIds($studentIds);

        foreach ($students as $student) {
            if (empty($student['phone'])) {
                $results['no_phone']++;
                continue;
            }

            try {
                // Replace placeholders
                $personalizedMessage = str_replace('{name}', $student['name'], $message);

                // Queue SMS using messaging_queue table
                $stmt = $this->pdo->prepare("
                    INSERT INTO messaging_queue (phone_number, message, entity_type, entity_id, status, created_at)
                    VALUES (?, ?, 'student', ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $student['phone'],
                    $personalizedMessage,
                    $student['id']
                ]);

                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Failed for {$student['name']}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Bulk enroll students in a class
     * 
     * @param array $studentIds Array of student IDs
     * @param int $classId Class ID to enroll in
     * @return array Result with success/already_enrolled/failed counts
     */
    public function bulkEnroll(array $studentIds, int $classId): array
    {
        $results = ['success' => 0, 'already_enrolled' => 0, 'failed' => 0];

        foreach ($studentIds as $studentId) {
            // Check if already enrolled
            $check = $this->pdo->prepare("
                SELECT id FROM enrollments WHERE student_id = ? AND class_id = ?
            ");
            $check->execute([$studentId, $classId]);

            if ($check->fetch()) {
                $results['already_enrolled']++;
                continue;
            }

            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO enrollments (student_id, class_id, enrolled_at, status)
                    VALUES (?, ?, NOW(), 'active')
                ");
                $stmt->execute([$studentId, $classId]);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Bulk update student status
     * 
     * @param array $studentIds Array of student IDs
     * @param string $status New status (active, inactive, alumni, suspended)
     * @return int Number of updated records
     */
    public function bulkUpdateStatus(array $studentIds, string $status): int
    {
        $validStatuses = ['active', 'inactive', 'alumni', 'suspended'];
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        if (empty($studentIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $this->pdo->prepare("
            UPDATE users SET status = ? WHERE id IN ({$placeholders})
        ");
        $params = array_merge([$status], $studentIds);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Export selected students to CSV
     * 
     * @param array $studentIds Array of student IDs
     * @return string Filename of generated CSV
     */
    public function exportToCsv(array $studentIds): string
    {
        if (empty($studentIds)) {
            throw new \InvalidArgumentException("No students selected for export");
        }

        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.name, u.email, u.phone, 
                   c.name as country, el.name as education_level,
                   u.created_at
            FROM users u
            LEFT JOIN countries c ON u.country_id = c.id
            LEFT JOIN education_levels el ON u.education_level_id = el.id
            WHERE u.id IN ({$placeholders})
            ORDER BY u.name
        ");
        $stmt->execute($studentIds);
        $students = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Ensure exports directory exists
        $exportDir = dirname(__DIR__, 2) . '/storage/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $filename = 'students_export_' . date('Y-m-d_His') . '.csv';
        $filepath = $exportDir . '/' . $filename;

        $fp = fopen($filepath, 'w');

        // Headers
        fputcsv($fp, ['ID', 'Name', 'Email', 'Phone', 'Country', 'Education Level', 'Joined Date']);

        // Data rows
        foreach ($students as $student) {
            fputcsv($fp, [
                $student['id'],
                $student['name'],
                $student['email'],
                $student['phone'],
                $student['country'] ?? '',
                $student['education_level'] ?? '',
                date('Y-m-d', strtotime($student['created_at']))
            ]);
        }

        fclose($fp);

        return $filename;
    }

    /**
     * Bulk delete students (Admin only)
     * 
     * @param array $studentIds Array of student IDs
     * @return array Result with success/failed counts
     */
    public function bulkDelete(array $studentIds): array
    {
        $results = ['success' => 0, 'failed' => 0];

        $this->pdo->beginTransaction();

        try {
            foreach ($studentIds as $studentId) {
                // Delete related records first (cascade)
                $this->pdo->prepare("DELETE FROM enrollments WHERE student_id = ?")->execute([$studentId]);
                $this->pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$studentId]);

                // Delete user
                $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$studentId]);

                if ($stmt->rowCount() > 0) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            }

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $results['failed'] = count($studentIds);
            $results['success'] = 0;
        }

        return $results;
    }

    /**
     * Get available classes for enrollment dropdown
     */
    public function getAvailableClasses(): array
    {
        $stmt = $this->pdo->query("
            SELECT c.id, c.name, co.name as course_name, u.name as teacher_name
            FROM classes c
            LEFT JOIN courses co ON c.course_id = co.id
            LEFT JOIN users u ON c.teacher_id = u.id
            WHERE c.status = 'active'
            ORDER BY c.name
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
