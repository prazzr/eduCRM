<?php

declare(strict_types=1);

namespace EduCRM\Helpers;

/**
 * User Helper
 * Common user-related query functions to avoid repetition
 * 
 * Replaces duplicated queries like:
 * - $pdo->query("SELECT id, name FROM users ORDER BY name")
 * - $pdo->query("SELECT id, name FROM users WHERE role='student'...")
 * 
 * @package EduCRM\Helpers
 * @version 1.0.0
 * @date January 6, 2026
 */
class UserHelper
{
    private \PDO $pdo;
    private static ?UserHelper $instance = null;

    private function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(?\PDO $pdo = null): self
    {
        if (self::$instance === null) {
            if ($pdo === null) {
                global $pdo;
            }
            self::$instance = new self($pdo);
        }
        return self::$instance;
    }

    /**
     * Get all users for dropdown (id, name)
     * Replaces: $pdo->query("SELECT id, name FROM users ORDER BY name")
     * 
     * @return array
     */
    public function getAllUsers(): array
    {
        return $this->pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all staff users (non-students)
     * 
     * @return array
     */
    public function getStaffUsers(): array
    {
        return $this->pdo->query("
            SELECT DISTINCT u.id, u.name, u.email
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE r.name NOT IN ('student')
            ORDER BY u.name
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all students
     * Replaces: $pdo->query("SELECT id, name FROM users WHERE role='student'...")
     * 
     * @return array
     */
    public function getStudents(): array
    {
        return $this->pdo->query("
            SELECT DISTINCT u.id, u.name, u.email
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE r.name = 'student'
            ORDER BY u.name
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get counselors and admins
     * Replaces queries fetching counselor/admin users for assignment dropdowns
     * 
     * @return array
     */
    public function getCounselors(): array
    {
        return $this->pdo->query("
            SELECT DISTINCT u.id, u.name 
            FROM users u 
            JOIN user_roles ur ON u.id = ur.user_id 
            JOIN roles r ON ur.role_id = r.id 
            WHERE r.name IN ('admin', 'counselor')
            ORDER BY u.name
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get teachers
     * 
     * @return array
     */
    public function getTeachers(): array
    {
        return $this->pdo->query("
            SELECT DISTINCT u.id, u.name 
            FROM users u 
            JOIN user_roles ur ON u.id = ur.user_id 
            JOIN roles r ON ur.role_id = r.id 
            WHERE r.name IN ('admin', 'teacher')
            ORDER BY u.name
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get users by role
     * 
     * @param string|array $roles Role name(s)
     * @return array
     */
    public function getUsersByRole(string|array $roles): array
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }

        $placeholders = implode(',', array_fill(0, count($roles), '?'));

        $stmt = $this->pdo->prepare("
            SELECT DISTINCT u.id, u.name, u.email
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE r.name IN ({$placeholders})
            ORDER BY u.name
        ");
        $stmt->execute($roles);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get a user by ID
     * 
     * @param int $id User ID
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get user roles
     * 
     * @param int $userId User ID
     * @return array Role names
     */
    public function getUserRoles(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.name
            FROM roles r
            JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Check if email exists (for duplicate check)
     * 
     * @param string $email Email to check
     * @param int|null $excludeId Exclude this user ID (for updates)
     * @return bool
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM users WHERE email = ?";
        $params = [$email];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }
}
