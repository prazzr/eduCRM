<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
 * Branch Service
 * Handles multi-branch office management and data isolation
 */

class BranchService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new branch
     */
    public function createBranch(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO branches (name, code, address, city, phone, email, manager_id, is_headquarters, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['name'],
            $data['code'] ?? $this->generateCode($data['name']),
            $data['address'] ?? null,
            $data['city'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['manager_id'] ?? null,
            $data['is_headquarters'] ?? false,
            $data['is_active'] ?? true
        ]);

        $newBranchId = (int) $this->pdo->lastInsertId();

        // Assign the new manager's branch_id so they can see branch-scoped data
        $newManagerId = $data['manager_id'] ?? null;
        if ($newManagerId) {
            $this->assignUserToBranch((int) $newManagerId, $newBranchId);
        }

        return $newBranchId;
    }

    /**
     * Update a branch
     */
    public function updateBranch(int $branchId, array $data): bool
    {
        // Get current branch to detect manager change
        $currentBranch = $this->getBranch($branchId);
        $oldManagerId = $currentBranch ? ($currentBranch['manager_id'] ?? null) : null;
        $newManagerId = $data['manager_id'] ?? null;

        $stmt = $this->pdo->prepare("
            UPDATE branches SET
                name = ?,
                code = ?,
                address = ?,
                city = ?,
                phone = ?,
                email = ?,
                manager_id = ?,
                is_active = ?
            WHERE id = ?
        ");

        $result = $stmt->execute([
            $data['name'],
            $data['code'],
            $data['address'] ?? null,
            $data['city'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $newManagerId,
            $data['is_active'] ?? true,
            $branchId
        ]);

        // Handle manager change: update users.branch_id
        if ($result && $oldManagerId != $newManagerId) {
            // Assign new manager to this branch
            if ($newManagerId) {
                $this->assignUserToBranch((int) $newManagerId, $branchId);
            }

            // Unassign old manager — only if they are not managing another branch
            if ($oldManagerId) {
                $checkStmt = $this->pdo->prepare("
                    SELECT COUNT(*) FROM branches 
                    WHERE manager_id = ? AND id != ? AND is_active = 1
                ");
                $checkStmt->execute([$oldManagerId, $branchId]);
                $managingOtherBranch = $checkStmt->fetchColumn() > 0;

                if (!$managingOtherBranch) {
                    // Old manager no longer manages any branch, clear their branch_id
                    $clearStmt = $this->pdo->prepare("UPDATE users SET branch_id = NULL WHERE id = ?");
                    $clearStmt->execute([$oldManagerId]);
                }
            }
        }

        return $result;
    }

    /**
     * Get all branches
     */
    public function getBranches(bool $activeOnly = true): array
    {
        $sql = "
            SELECT b.*, u.name as manager_name,
                   (SELECT COUNT(*) FROM users WHERE branch_id = b.id) as user_count,
                   (SELECT COUNT(*) FROM inquiries WHERE branch_id = b.id) as inquiry_count
            FROM branches b
            LEFT JOIN users u ON b.manager_id = u.id
        ";

        if ($activeOnly) {
            $sql .= " WHERE b.is_active = 1";
        }

        $sql .= " ORDER BY b.is_headquarters DESC, b.name ASC";

        return $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get a single branch
     */
    public function getBranch(int $branchId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, u.name as manager_name
            FROM branches b
            LEFT JOIN users u ON b.manager_id = u.id
            WHERE b.id = ?
        ");
        $stmt->execute([$branchId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get user's branch
     */
    public function getUserBranch(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*
            FROM branches b
            JOIN users u ON u.branch_id = b.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Assign user to branch
     */
    public function assignUserToBranch(int $userId, int $branchId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE users SET branch_id = ? WHERE id = ?");
        return $stmt->execute([$branchId, $userId]);
    }

    /**
     * Get branch filter SQL condition
     * Returns empty string for admins (see all) or SQL condition for branch-scoped users
     * Now uses session for performance instead of querying DB every time
     */
    public function getBranchFilter(int $userId, string $tableAlias = ''): string
    {
        $prefix = $tableAlias ? $tableAlias . '.' : '';

        // Admin with branch selector active
        if (hasRole('admin')) {
            if (isset($_SESSION['selected_branch_id']) && $_SESSION['selected_branch_id'] > 0) {
                return " AND {$prefix}branch_id = " . (int) $_SESSION['selected_branch_id'];
            }
            return ''; // Admin sees all
        }

        // Non-admin: use session branch_id (set during login)
        $branchId = $_SESSION['branch_id'] ?? null;
        if (!$branchId) {
            // Fallback: query DB if session not set (shouldn't happen after login fix)
            $branch = $this->getUserBranch($userId);
            $branchId = $branch ? $branch['id'] : null;
        }

        if (!$branchId) {
            return " AND {$prefix}branch_id IS NULL"; // No branch = see nothing (security default)
        }

        return " AND {$prefix}branch_id = " . (int) $branchId;
    }

    /**
     * Get branch filter as parameterized query parts (safer than string concat)
     * Returns ['sql' => string, 'params' => array]
     */
    public function getBranchFilterParams(string $tableAlias = ''): array
    {
        $prefix = $tableAlias ? $tableAlias . '.' : '';

        if (hasRole('admin')) {
            if (isset($_SESSION['selected_branch_id']) && $_SESSION['selected_branch_id'] > 0) {
                return ['sql' => " AND {$prefix}branch_id = ?", 'params' => [(int) $_SESSION['selected_branch_id']]];
            }
            return ['sql' => '', 'params' => []];
        }

        $branchId = $_SESSION['branch_id'] ?? null;
        if (!$branchId) {
            $branch = $this->getUserBranch($_SESSION['user_id'] ?? 0);
            $branchId = $branch ? $branch['id'] : null;
        }

        if (!$branchId) {
            return ['sql' => " AND {$prefix}branch_id IS NULL", 'params' => []];
        }

        return ['sql' => " AND {$prefix}branch_id = ?", 'params' => [(int) $branchId]];
    }

    /**
     * Get the current session branch ID (quick helper, no DB hit)
     */
    public static function getSessionBranchId(): ?int
    {
        if (hasRole('admin')) {
            return isset($_SESSION['selected_branch_id']) && $_SESSION['selected_branch_id'] > 0
                ? (int) $_SESSION['selected_branch_id']
                : null;
        }
        return isset($_SESSION['branch_id']) ? (int) $_SESSION['branch_id'] : null;
    }

    /**
     * Get branches for dropdown
     */
    public function getBranchesDropdown(): array
    {
        return $this->pdo->query("
            SELECT id, name, code FROM branches WHERE is_active = 1 ORDER BY name
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get branch statistics
     */
    public function getBranchStats(int $branchId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM users WHERE branch_id = ?) as total_users,
                (SELECT COUNT(*) FROM inquiries WHERE branch_id = ? AND status = 'new') as new_inquiries,
                (SELECT COUNT(*) FROM inquiries WHERE branch_id = ? AND status = 'converted') as conversions,
                (SELECT SUM(amount) FROM student_fees WHERE branch_id = ? AND status != 'paid') as pending_fees
        ");
        $stmt->execute([$branchId, $branchId, $branchId, $branchId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Generate branch code from name
     */
    private function generateCode(string $name): string
    {
        $words = explode(' ', strtoupper(trim($name)));
        $code = '';

        foreach ($words as $word) {
            $code .= substr($word, 0, 1);
        }

        // Ensure uniqueness
        $baseCode = substr($code, 0, 3);
        $counter = 1;
        $finalCode = $baseCode;

        while ($this->codeExists($finalCode)) {
            $finalCode = $baseCode . $counter;
            $counter++;
        }

        return $finalCode;
    }

    /**
     * Check if code exists
     */
    private function codeExists(string $code): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM branches WHERE code = ?");
        $stmt->execute([$code]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Delete branch (soft delete by deactivating)
     */
    public function deleteBranch(int $branchId): bool
    {
        // Check if it's headquarters
        $branch = $this->getBranch($branchId);
        if ($branch && $branch['is_headquarters']) {
            return false; // Can't delete headquarters
        }

        $stmt = $this->pdo->prepare("UPDATE branches SET is_active = 0 WHERE id = ?");
        return $stmt->execute([$branchId]);
    }
}
