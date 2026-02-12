<?php

declare(strict_types=1);

namespace EduCRM\Services;

require_once __DIR__ . '/CacheService.php';
require_once __DIR__ . '/../../config/config.php';

/**
 * LookupCacheService - Caches normalized lookup tables
 * 
 * Provides fast access to frequently-used lookup data:
 * - Countries
 * - Education levels
 * - Visa stages
 * - Priority levels
 * - Inquiry statuses
 * - Communication types
 * - Test types
 * - Application statuses
 * 
 * @package EduCRM\Services
 * @version 1.0.0
 * @date January 5, 2026
 */
class LookupCacheService
{
    private static ?LookupCacheService $instance = null;
    
    private \PDO $pdo;
    private CacheService $cache;
    
    // Cache TTL for lookup tables (1 hour - they rarely change)
    private const LOOKUP_TTL = 3600;
    
    // Lookup table configurations - matches actual database schema
    private const LOOKUP_TABLES = [
        'countries' => [
            'table' => 'countries',
            'id' => 'id',
            'name' => 'name',
            'order_by' => 'name',
            'columns' => ['id', 'name', 'code']
        ],
        'education_levels' => [
            'table' => 'education_levels',
            'id' => 'id',
            'name' => 'name',
            'order_by' => 'level_order',
            'columns' => ['id', 'name', 'level_order']
        ],
        'visa_stages' => [
            'table' => 'visa_stages',
            'id' => 'id',
            'name' => 'name',
            'order_by' => 'stage_order',
            'columns' => ['id', 'name', 'stage_order', 'description', 'default_sla_days', 'allowed_next_stages']
        ],
        'priority_levels' => [
            'table' => 'priority_levels',
            'id' => 'id',
            'name' => 'name',
            'order_by' => 'priority_order',
            'columns' => ['id', 'name', 'priority_order', 'color_code']
        ],
        'inquiry_statuses' => [
            'table' => 'inquiry_statuses',
            'id' => 'id',
            'name' => 'name',
            'order_by' => 'status_order',
            'columns' => ['id', 'name', 'status_order', 'is_final']
        ],
        'communication_types' => [
            'table' => 'communication_types',
            'id' => 'id',
            'name' => 'name',
            'order_by' => 'name',
            'columns' => ['id', 'name', 'is_messaging']
        ],
        'test_types' => [
            'table' => 'test_types',
            'id' => 'id',
            'name' => 'name',
            'order_by' => 'name',
            'columns' => ['id', 'name', 'full_name', 'has_section_scores']
        ],
        'application_statuses' => [
            'table' => 'application_statuses',
            'id' => 'id',
            'name' => 'name',
            'order_by' => 'status_order',
            'columns' => ['id', 'name', 'status_order', 'is_final']
        ],
        'document_types' => [
            'table' => 'document_types',
            'id' => 'id',
            'name' => 'name',
            'order_by' => 'display_order',
            'columns' => ['id', 'name', 'code', 'description', 'is_required_default', 'display_order', 'is_active']
        ]
    ];
    
    /**
     * Private constructor for singleton
     */
    private function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->cache = \EduCRM\Services\CacheService::getInstance();
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
     * Get all records from a lookup table
     * 
     * @param string $tableName Lookup table key
     * @return array All records
     */
    public function getAll(string $tableName): array
    {
        if (!isset(self::LOOKUP_TABLES[$tableName])) {
            throw new \InvalidArgumentException("Unknown lookup table: {$tableName}");
        }
        
        $cacheKey = \EduCRM\Services\CacheService::lookupKey($tableName);
        
        return $this->cache->remember($cacheKey, function() use ($tableName) {
            return $this->fetchFromDatabase($tableName);
        }, self::LOOKUP_TTL);
    }
    
    /**
     * Get a single record by ID
     * 
     * @param string $tableName Lookup table key
     * @param int $id Record ID
     * @return array|null Record or null
     */
    public function getById(string $tableName, int $id): ?array
    {
        $all = $this->getAll($tableName);
        
        foreach ($all as $record) {
            if ((int) $record['id'] === $id) {
                return $record;
            }
        }
        
        return null;
    }
    
    /**
     * Get a single record by code (only for tables that have a code column)
     * 
     * @param string $tableName Lookup table key
     * @param string $code Record code
     * @return array|null Record or null
     */
    public function getByCode(string $tableName, string $code): ?array
    {
        $all = $this->getAll($tableName);
        
        foreach ($all as $record) {
            // Check if code column exists in this record
            if (isset($record['code']) && strtolower($record['code']) === strtolower($code)) {
                return $record;
            }
        }
        
        return null;
    }
    
    /**
     * Get a single record by name
     * 
     * @param string $tableName Lookup table key
     * @param string $name Record name
     * @return array|null Record or null
     */
    public function getByName(string $tableName, string $name): ?array
    {
        $all = $this->getAll($tableName);
        
        foreach ($all as $record) {
            if (strtolower($record['name']) === strtolower($name)) {
                return $record;
            }
        }
        
        return null;
    }
    
    /**
     * Get only active records from a lookup table
     * For tables that have an is_active column
     * 
     * @param string $tableName Lookup table key
     * @return array Active records only
     */
    public function getActiveRecords(string $tableName): array
    {
        $all = $this->getAll($tableName);
        
        return array_filter($all, function($record) {
            // If is_active column exists and is false, exclude it
            if (isset($record['is_active']) && !$record['is_active']) {
                return false;
            }
            return true;
        });
    }
    
    /**
     * Get ID by name (commonly used for FK lookups)
     * 
     * @param string $tableName Lookup table key
     * @param string $name Record name
     * @return int|null ID or null
     */
    public function getIdByName(string $tableName, string $name): ?int
    {
        $record = $this->getByName($tableName, $name);
        return $record ? (int) $record['id'] : null;
    }
    
    /**
     * Get ID by code
     * 
     * @param string $tableName Lookup table key
     * @param string $code Record code
     * @return int|null ID or null
     */
    public function getIdByCode(string $tableName, string $code): ?int
    {
        $record = $this->getByCode($tableName, $code);
        return $record ? (int) $record['id'] : null;
    }
    
    /**
     * Get name by ID
     * 
     * @param string $tableName Lookup table key
     * @param int $id Record ID
     * @return string|null Name or null
     */
    public function getNameById(string $tableName, int $id): ?string
    {
        $record = $this->getById($tableName, $id);
        return $record ? $record['name'] : null;
    }
    
    /**
     * Get options array for HTML select dropdowns
     * 
     * @param string $tableName Lookup table key
     * @param string $valueField Field to use as option value
     * @param string $labelField Field to use as option label
     * @return array Options array [value => label]
     */
    public function getSelectOptions(string $tableName, string $valueField = 'id', string $labelField = 'name'): array
    {
        $all = $this->getAll($tableName);
        $options = [];
        
        foreach ($all as $record) {
            if (isset($record[$valueField]) && isset($record[$labelField])) {
                $options[$record[$valueField]] = $record[$labelField];
            }
        }
        
        return $options;
    }
    
    /**
     * Clear cache for a specific lookup table
     * 
     * @param string $tableName Lookup table key
     * @return bool Success
     */
    public function clearCache(string $tableName): bool
    {
        if (!isset(self::LOOKUP_TABLES[$tableName])) {
            return false;
        }
        
        return $this->cache->delete(\EduCRM\Services\CacheService::lookupKey($tableName));
    }
    
    /**
     * Clear all lookup caches
     * 
     * @return bool Success
     */
    public function clearAllCaches(): bool
    {
        foreach (array_keys(self::LOOKUP_TABLES) as $tableName) {
            $this->cache->delete(\EduCRM\Services\CacheService::lookupKey($tableName));
        }
        return true;
    }
    
    /**
     * Warm up all lookup caches
     * 
     * @return array Stats about warmed caches
     */
    public function warmUp(): array
    {
        $stats = [];
        
        foreach (array_keys(self::LOOKUP_TABLES) as $tableName) {
            try {
                $records = $this->getAll($tableName);
                $stats[$tableName] = count($records);
            } catch (\Exception $e) {
                $stats[$tableName] = 'error: ' . $e->getMessage();
            }
        }
        
        return $stats;
    }
    
    /**
     * Fetch data from database
     */
    private function fetchFromDatabase(string $tableName): array
    {
        $config = self::LOOKUP_TABLES[$tableName];
        $table = $config['table'];
        $columns = $config['columns'];
        $orderBy = $config['order_by'];
        
        $columnList = implode(', ', $columns);
        
        try {
            $stmt = $this->pdo->query("SELECT {$columnList} FROM {$table} ORDER BY {$orderBy} ASC");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("LookupCacheService: Failed to fetch {$tableName}: " . $e->getMessage());
            return [];
        }
    }
    
    // =========================================================================
    // CONVENIENCE METHODS FOR COMMON LOOKUPS
    // =========================================================================
    
    /**
     * Get all countries
     */
    public function getCountries(): array
    {
        return $this->getAll('countries');
    }
    
    /**
     * Get country by name (handles variations like "Australia" vs "AUS")
     */
    public function getCountryId(string $nameOrCode): ?int
    {
        // Try by name first
        $id = $this->getIdByName('countries', $nameOrCode);
        if ($id !== null) {
            return $id;
        }
        
        // Try by code
        return $this->getIdByCode('countries', $nameOrCode);
    }
    
    /**
     * Get all visa stages with transition rules
     */
    public function getVisaStages(): array
    {
        return $this->getAll('visa_stages');
    }
    
    /**
     * Get allowed next stages for a visa stage
     */
    public function getAllowedNextStages(int $currentStageId): array
    {
        $stage = $this->getById('visa_stages', $currentStageId);
        if (!$stage || empty($stage['allowed_next_stages'])) {
            return [];
        }
        
        $allowedIds = json_decode($stage['allowed_next_stages'], true) ?? [];
        $stages = [];
        
        foreach ($allowedIds as $id) {
            $nextStage = $this->getById('visa_stages', $id);
            if ($nextStage) {
                $stages[] = $nextStage;
            }
        }
        
        return $stages;
    }
    
    /**
     * Get all priority levels with score ranges
     */
    public function getPriorityLevels(): array
    {
        return $this->getAll('priority_levels');
    }
    
    /**
     * Get priority ID by score
     * Based on standard thresholds: Hot (>=70), Warm (40-69), Cold (<40)
     */
    public function getPriorityIdByScore(int $score): ?int
    {
        $priorities = $this->getPriorityLevels();
        
        // Map score to priority name based on standard thresholds
        $priorityName = 'cold';
        if ($score >= 70) {
            $priorityName = 'hot';
        } elseif ($score >= 40) {
            $priorityName = 'warm';
        }
        
        foreach ($priorities as $priority) {
            if (strtolower($priority['name']) === $priorityName) {
                return (int) $priority['id'];
            }
        }
        
        return null;
    }
    
    /**
     * Get all education levels
     */
    public function getEducationLevels(): array
    {
        return $this->getAll('education_levels');
    }
    
    /**
     * Get all inquiry statuses
     */
    public function getInquiryStatuses(): array
    {
        return $this->getAll('inquiry_statuses');
    }
    
    /**
     * Get all document types
     */
    public function getDocumentTypes(): array
    {
        return $this->getAll('document_types');
    }
    
    // Prevent cloning
    private function __clone() {}
}
