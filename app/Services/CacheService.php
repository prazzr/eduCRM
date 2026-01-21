<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
 * CacheService - Flexible caching layer for EduCRM
 * 
 * Supports multiple backends:
 * - File-based caching (default, works out of the box)
 * - Redis (optional, for production high-traffic)
 * - APCu (optional, for single-server deployments)
 * 
 * @package EduCRM\Services
 * @version 1.0.0
 * @date January 5, 2026
 */
class CacheService
{
    private static ?CacheService $instance = null;
    
    private string $driver;
    private string $cacheDir;
    private ?object $redis = null;
    private int $defaultTTL = 300; // 5 minutes default
    private string $prefix = 'educrm_';
    
    // Cache statistics
    private int $hits = 0;
    private int $misses = 0;
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->cacheDir = defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/../../cache/';
        $this->driver = $this->detectDriver();
        
        // Ensure cache directory exists for file driver
        if ($this->driver === 'file' && !is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        // Initialize Redis if available and configured
        if ($this->driver === 'redis') {
            $this->initRedis();
        }
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Detect best available cache driver
     */
    private function detectDriver(): string
    {
        // Check for Redis first (best for production)
        if (defined('CACHE_DRIVER') && CACHE_DRIVER === 'redis') {
            if (class_exists('Redis') || class_exists('Predis\Client')) {
                return 'redis';
            }
        }
        
        // Check for APCu (good for single-server)
        if (defined('CACHE_DRIVER') && CACHE_DRIVER === 'apcu') {
            if (function_exists('apcu_fetch')) {
                return 'apcu';
            }
        }
        
        // Fall back to file-based caching
        return 'file';
    }
    
    /**
     * Initialize Redis connection
     */
    private function initRedis(): void
    {
        try {
            $host = defined('REDIS_HOST') ? REDIS_HOST : '127.0.0.1';
            $port = defined('REDIS_PORT') ? REDIS_PORT : 6379;
            $password = defined('REDIS_PASSWORD') ? REDIS_PASSWORD : null;
            
            if (class_exists('Redis')) {
                $this->redis = new \Redis();
                $this->redis->connect($host, $port);
                if ($password) {
                    $this->redis->auth($password);
                }
            } elseif (class_exists('Predis\Client')) {
                $config = ['host' => $host, 'port' => $port];
                if ($password) {
                    $config['password'] = $password;
                }
                $this->redis = new \Predis\Client($config);
            }
        } catch (\Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
            $this->driver = 'file'; // Fallback to file
        }
    }
    
    /**
     * Get a cached value
     * 
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed Cached value or default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $prefixedKey = $this->prefix . $key;
        
        switch ($this->driver) {
            case 'redis':
                $value = $this->getFromRedis($prefixedKey);
                break;
            case 'apcu':
                $value = $this->getFromApcu($prefixedKey);
                break;
            default:
                $value = $this->getFromFile($prefixedKey);
        }
        
        if ($value === null) {
            $this->misses++;
            return $default;
        }
        
        $this->hits++;
        return $value;
    }
    
    /**
     * Set a cached value
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds (null = default)
     * @return bool Success
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $prefixedKey = $this->prefix . $key;
        $ttl = $ttl ?? $this->defaultTTL;
        
        switch ($this->driver) {
            case 'redis':
                return $this->setToRedis($prefixedKey, $value, $ttl);
            case 'apcu':
                return $this->setToApcu($prefixedKey, $value, $ttl);
            default:
                return $this->setToFile($prefixedKey, $value, $ttl);
        }
    }
    
    /**
     * Delete a cached value
     * 
     * @param string $key Cache key
     * @return bool Success
     */
    public function delete(string $key): bool
    {
        $prefixedKey = $this->prefix . $key;
        
        switch ($this->driver) {
            case 'redis':
                return $this->redis ? (bool) $this->redis->del($prefixedKey) : false;
            case 'apcu':
                return apcu_delete($prefixedKey);
            default:
                $file = $this->getCacheFilePath($prefixedKey);
                return file_exists($file) ? unlink($file) : true;
        }
    }
    
    /**
     * Clear all cached values with optional prefix pattern
     * 
     * @param string|null $pattern Pattern to match (null = all)
     * @return bool Success
     */
    public function clear(?string $pattern = null): bool
    {
        switch ($this->driver) {
            case 'redis':
                if ($this->redis) {
                    $keys = $this->redis->keys($this->prefix . ($pattern ?? '*'));
                    if (!empty($keys)) {
                        $this->redis->del($keys);
                    }
                }
                return true;
                
            case 'apcu':
                if ($pattern) {
                    $iterator = new \APCUIterator('/^' . preg_quote($this->prefix . $pattern, '/') . '/');
                    return apcu_delete($iterator);
                }
                return apcu_clear_cache();
                
            default:
                $files = glob($this->cacheDir . $this->prefix . ($pattern ?? '*') . '.cache');
                foreach ($files as $file) {
                    unlink($file);
                }
                return true;
        }
    }
    
    /**
     * Get or set cached value using callback
     * 
     * @param string $key Cache key
     * @param callable $callback Function to generate value if not cached
     * @param int|null $ttl Time to live in seconds
     * @return mixed Cached or generated value
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Check if a key exists in cache
     * 
     * @param string $key Cache key
     * @return bool Exists
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
    
    /**
     * Increment a numeric value
     * 
     * @param string $key Cache key
     * @param int $step Increment step
     * @return int|false New value or false on failure
     */
    public function increment(string $key, int $step = 1): int|false
    {
        $prefixedKey = $this->prefix . $key;
        
        switch ($this->driver) {
            case 'redis':
                return $this->redis ? $this->redis->incrBy($prefixedKey, $step) : false;
            case 'apcu':
                return apcu_inc($prefixedKey, $step);
            default:
                $value = (int) $this->get($key, 0);
                $newValue = $value + $step;
                $this->set($key, $newValue);
                return $newValue;
        }
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Cache statistics
     */
    public function getStats(): array
    {
        return [
            'driver' => $this->driver,
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hit_rate' => $this->hits + $this->misses > 0 
                ? round($this->hits / ($this->hits + $this->misses) * 100, 2) 
                : 0,
            'prefix' => $this->prefix,
            'default_ttl' => $this->defaultTTL
        ];
    }
    
    // =========================================================================
    // FILE DRIVER METHODS
    // =========================================================================
    
    private function getFromFile(string $key): mixed
    {
        $file = $this->getCacheFilePath($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }
        
        $data = unserialize($content);
        
        // Check expiration
        if ($data['expires_at'] !== 0 && $data['expires_at'] < time()) {
            unlink($file);
            return null;
        }
        
        return $data['value'];
    }
    
    private function setToFile(string $key, mixed $value, int $ttl): bool
    {
        $file = $this->getCacheFilePath($key);
        
        $data = [
            'value' => $value,
            'created_at' => time(),
            'expires_at' => $ttl > 0 ? time() + $ttl : 0
        ];
        
        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }
    
    private function getCacheFilePath(string $key): string
    {
        // Sanitize key for filesystem
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->cacheDir . $safeKey . '.cache';
    }
    
    // =========================================================================
    // REDIS DRIVER METHODS
    // =========================================================================
    
    private function getFromRedis(string $key): mixed
    {
        if (!$this->redis) {
            return null;
        }
        
        try {
            $value = $this->redis->get($key);
            if ($value === false || $value === null) {
                return null;
            }
            return unserialize($value);
        } catch (\Exception $e) {
            error_log("Redis get error: " . $e->getMessage());
            return null;
        }
    }
    
    private function setToRedis(string $key, mixed $value, int $ttl): bool
    {
        if (!$this->redis) {
            return false;
        }
        
        try {
            $serialized = serialize($value);
            if ($ttl > 0) {
                return (bool) $this->redis->setex($key, $ttl, $serialized);
            }
            return (bool) $this->redis->set($key, $serialized);
        } catch (\Exception $e) {
            error_log("Redis set error: " . $e->getMessage());
            return false;
        }
    }
    
    // =========================================================================
    // APCU DRIVER METHODS
    // =========================================================================
    
    private function getFromApcu(string $key): mixed
    {
        $success = false;
        $value = apcu_fetch($key, $success);
        return $success ? $value : null;
    }
    
    private function setToApcu(string $key, mixed $value, int $ttl): bool
    {
        return apcu_store($key, $value, $ttl);
    }
    
    // =========================================================================
    // CACHE KEY GENERATORS (Static Helpers)
    // =========================================================================
    
    /**
     * Generate cache key for dashboard data
     */
    public static function dashboardKey(int $userId, string $role): string
    {
        return "dashboard_{$role}_{$userId}";
    }
    
    /**
     * Generate cache key for lookup tables
     */
    public static function lookupKey(string $table): string
    {
        return "lookup_{$table}";
    }
    
    /**
     * Generate cache key for user-specific data
     */
    public static function userKey(int $userId, string $context): string
    {
        return "user_{$userId}_{$context}";
    }
    
    /**
     * Generate cache key for aggregate stats
     */
    public static function statsKey(string $type, ?string $date = null): string
    {
        $date = $date ?? date('Y-m-d');
        return "stats_{$type}_{$date}";
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
