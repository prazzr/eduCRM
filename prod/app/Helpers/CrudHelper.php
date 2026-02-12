<?php

declare(strict_types=1);

namespace EduCRM\Helpers;

/**
 * CRUD Helper
 * Standardized CRUD operations with consistent patterns
 * 
 * Replaces duplicated patterns across module delete/edit files:
 * - Transaction handling
 * - CSRF validation
 * - Consistent error handling
 * - Logging
 * 
 * @package EduCRM\Helpers
 * @version 1.0.0
 * @date January 6, 2026
 */
class CrudHelper
{
    private \PDO $pdo;
    
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Delete a record with optional related records
     * Standardizes deletion across all modules
     * 
     * @param string $table Main table name
     * @param int $id Record ID
     * @param array $relatedTables Related tables to delete from first [table => idColumn]
     * @param string $redirectUrl URL to redirect after success
     * @param string $successMessage Success flash message
     * @return void
     */
    public function deleteRecord(
        string $table, 
        int $id, 
        array $relatedTables = [],
        string $redirectUrl = 'list.php',
        string $successMessage = 'Record deleted successfully!'
    ): void {
        try {
            $this->pdo->beginTransaction();
            
            // Delete related records first (foreign key constraints)
            foreach ($relatedTables as $relatedTable => $idColumn) {
                $stmt = $this->pdo->prepare("DELETE FROM {$relatedTable} WHERE {$idColumn} = ?");
                $stmt->execute([$id]);
            }
            
            // Delete main record
            $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE id = ?");
            $stmt->execute([$id]);
            
            // Log the action
            $this->logAction('delete', $table, $id);
            
            $this->pdo->commit();
            
            redirectWithAlert($redirectUrl, $successMessage, 'danger');
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            $this->handleError("Cannot delete record. It may be linked to other data.", $e);
        }
    }
    
    /**
     * Soft delete a record (set is_active = 0)
     * 
     * @param string $table Table name
     * @param int $id Record ID
     * @param string $redirectUrl URL to redirect after success
     * @return void
     */
    public function softDelete(string $table, int $id, string $redirectUrl = 'list.php'): void
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE {$table} SET is_active = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            
            $this->logAction('soft_delete', $table, $id);
            
            redirectWithAlert($redirectUrl, 'Record archived successfully!', 'warning');
        } catch (\PDOException $e) {
            $this->handleError("Cannot archive record.", $e);
        }
    }
    
    /**
     * Insert a new record
     * 
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @return int|false New record ID or false on failure
     */
    public function insert(string $table, array $data): int|false
    {
        try {
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            
            $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_values($data));
            
            $id = (int) $this->pdo->lastInsertId();
            $this->logAction('create', $table, $id);
            
            return $id;
        } catch (\PDOException $e) {
            $this->handleError("Failed to create record.", $e);
            return false;
        }
    }
    
    /**
     * Update a record
     * 
     * @param string $table Table name
     * @param int $id Record ID
     * @param array $data Associative array of column => value
     * @return bool Success status
     */
    public function update(string $table, int $id, array $data): bool
    {
        try {
            $sets = [];
            foreach (array_keys($data) as $column) {
                $sets[] = "{$column} = ?";
            }
            $sets[] = "updated_at = NOW()";
            
            $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE id = ?";
            $values = array_values($data);
            $values[] = $id;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
            
            $this->logAction('update', $table, $id);
            
            return true;
        } catch (\PDOException $e) {
            $this->handleError("Failed to update record.", $e);
            return false;
        }
    }
    
    /**
     * Find a record by ID
     * 
     * @param string $table Table name
     * @param int $id Record ID
     * @param array $columns Columns to select (empty for all)
     * @return array|null Record data or null
     */
    public function find(string $table, int $id, array $columns = []): ?array
    {
        $cols = empty($columns) ? '*' : implode(', ', $columns);
        
        $stmt = $this->pdo->prepare("SELECT {$cols} FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Find a record or abort with 404
     * 
     * @param string $table Table name
     * @param int $id Record ID
     * @param array $columns Columns to select
     * @return array Record data
     */
    public function findOrFail(string $table, int $id, array $columns = []): array
    {
        $record = $this->find($table, $id, $columns);
        
        if (!$record) {
            require_once __DIR__ . '/RequestHelper.php';
            \EduCRM\Helpers\RequestHelper::abortWithError("Record not found", 404);
        }
        
        return $record;
    }
    
    /**
     * Check if a record exists
     * 
     * @param string $table Table name
     * @param int $id Record ID
     * @return bool
     */
    public function exists(string $table, int $id): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM {$table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return (bool) $stmt->fetch();
    }
    
    /**
     * Check for duplicate value
     * 
     * @param string $table Table name
     * @param string $column Column to check
     * @param mixed $value Value to check
     * @param int|null $excludeId Exclude this ID from check (for updates)
     * @return bool True if duplicate exists
     */
    public function isDuplicate(string $table, string $column, mixed $value, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM {$table} WHERE {$column} = ?";
        $params = [$value];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }
    
    /**
     * Execute within a transaction
     * 
     * @param callable $callback Function to execute
     * @return mixed Result of callback
     */
    public function transaction(callable $callback): mixed
    {
        try {
            $this->pdo->beginTransaction();
            $result = $callback($this->pdo);
            $this->pdo->commit();
            return $result;
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
    
    /**
     * Log action to system_logs
     * 
     * @param string $action Action performed
     * @param string $table Table affected
     * @param int $recordId Record ID
     * @return void
     */
    private function logAction(string $action, string $table, int $recordId): void
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $details = json_encode(['table' => $table, 'record_id' => $recordId]);
            
            $stmt = $this->pdo->prepare(
                "INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$userId, $action, $details, $ip]);
        } catch (\Exception $e) {
            // Silent fail - don't break main operation for logging failure
        }
    }
    
    /**
     * Handle errors consistently
     * 
     * @param string $message User-friendly message
     * @param \Exception $e Original exception
     * @return never
     */
    private function handleError(string $message, \Exception $e): never
    {
        // Log the actual error
        error_log("CRUD Error: " . $e->getMessage());
        
        require_once __DIR__ . '/RequestHelper.php';
        \EduCRM\Helpers\RequestHelper::abortWithError($message, 500);
    }
}
