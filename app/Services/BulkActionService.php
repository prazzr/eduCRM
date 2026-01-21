<?php

declare(strict_types=1);

namespace EduCRM\Services;

class BulkActionService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Bulk update tasks
     */
    public function bulkUpdateTasks($ids, $updates)
    {
        if (empty($ids) || empty($updates)) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $setClauses = [];
            $params = [];

            foreach ($updates as $field => $value) {
                $setClauses[] = "$field = ?";
                $params[] = $value;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge($params, $ids);

            $sql = "UPDATE tasks SET " . implode(', ', $setClauses) . " WHERE id IN ($placeholders)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Bulk update appointments
     */
    public function bulkUpdateAppointments($ids, $updates)
    {
        if (empty($ids) || empty($updates)) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $setClauses = [];
            $params = [];

            foreach ($updates as $field => $value) {
                $setClauses[] = "$field = ?";
                $params[] = $value;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge($params, $ids);

            $sql = "UPDATE appointments SET " . implode(', ', $setClauses) . " WHERE id IN ($placeholders)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Bulk update inquiries
     */
    public function bulkUpdateInquiries($ids, $updates)
    {
        if (empty($ids) || empty($updates)) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $setClauses = [];
            $params = [];

            foreach ($updates as $field => $value) {
                $setClauses[] = "$field = ?";
                $params[] = $value;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge($params, $ids);

            $sql = "UPDATE inquiries SET " . implode(', ', $setClauses) . " WHERE id IN ($placeholders)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Bulk delete with validation
     */
    public function bulkDelete($table, $ids)
    {
        if (empty($ids)) {
            return false;
        }

        // Whitelist allowed tables
        $allowedTables = ['tasks', 'appointments', 'inquiries'];
        if (!in_array($table, $allowedTables)) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "DELETE FROM $table WHERE id IN ($placeholders)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($ids);

            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Bulk reschedule appointments
     */
    public function bulkRescheduleAppointments($ids, $dateOffset)
    {
        if (empty($ids) || empty($dateOffset)) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE appointments 
                    SET appointment_date = DATE_ADD(appointment_date, INTERVAL ? DAY) 
                    WHERE id IN ($placeholders)";

            $params = array_merge([$dateOffset], $ids);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Get bulk email recipients from inquiries
     */
    public function getBulkEmailRecipients($ids)
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id, name, email FROM inquiries WHERE id IN ($placeholders) AND email IS NOT NULL AND email != ''";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ids);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
