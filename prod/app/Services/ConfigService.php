<?php
declare(strict_types=1);

namespace EduCRM\Services;

/**
 * ConfigService - Centralized Configuration Management
 * 
 * Replaces direct use of global constants and $_ENV variables.
 * Provides type-safe access to configuration values.
 * 
 * @package EduCRM\Services
 * @version 1.0.0
 * @date January 5, 2026
 */
class ConfigService
{
    private static ?ConfigService $instance = null;
    private array $config = [];
    private array $env = [];
    private bool $loaded = false;
    
    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        $this->loadConfig();
        $this->loadEnvironment();
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
     * Load configuration from config/config.php
     */
    private function loadConfig(): void
    {
        // Database configuration
        $this->config['database'] = [
            'host' => defined('DB_HOST') ? DB_HOST : 'localhost',
            'name' => defined('DB_NAME') ? DB_NAME : 'edu_crm',
            'user' => defined('DB_USER') ? DB_USER : 'root',
            'pass' => defined('DB_PASS') ? DB_PASS : '',
            'charset' => 'utf8mb4',
        ];
        
        // Application configuration
        $this->config['app'] = [
            'base_url' => defined('BASE_URL') ? BASE_URL : 'http://localhost/CRM/',
            'name' => 'EduCRM',
            'version' => '2.2.0',
            'environment' => $this->getEnv('APP_ENV', 'development'),
            'debug' => $this->getEnv('APP_DEBUG', 'true') === 'true',
            'timezone' => 'UTC',
        ];
        
        // Cache configuration
        $this->config['cache'] = [
            'driver' => defined('CACHE_DRIVER') ? CACHE_DRIVER : 'file',
            'directory' => defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/../../cache/',
            'default_ttl' => 300, // 5 minutes
        ];
        
        // Redis configuration
        $this->config['redis'] = [
            'host' => defined('REDIS_HOST') ? REDIS_HOST : '127.0.0.1',
            'port' => defined('REDIS_PORT') ? REDIS_PORT : 6379,
            'password' => defined('REDIS_PASSWORD') ? REDIS_PASSWORD : null,
        ];
        
        // Security configuration
        $this->config['security'] = [
            'jwt_secret' => $this->getEnv('JWT_SECRET', 'your-secret-key-change-in-production'),
            'jwt_expiry' => 86400, // 24 hours
            'csrf_token_length' => 32,
            'password_min_length' => 8,
            'rate_limit_default' => 100,
            'rate_limit_window' => 60, // 1 minute
            'session_lifetime' => 7200, // 2 hours
        ];
        
        // Upload configuration
        $this->config['upload'] = [
            'secure_directory' => defined('SECURE_UPLOAD_DIR') ? SECURE_UPLOAD_DIR : __DIR__ . '/../../uploads/',
            'max_file_size' => 10 * 1024 * 1024, // 10MB
            'allowed_extensions' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'],
            'allowed_mime_types' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'image/jpeg',
                'image/png',
                'image/gif'
            ],
        ];
        
        // Messaging configuration
        $this->config['messaging'] = [
            'default_gateway' => 'twilio',
            'queue_batch_size' => 50,
            'retry_attempts' => 3,
            'retry_delay' => 300, // 5 minutes
        ];
        
        // Logging configuration
        $this->config['logging'] = [
            'directory' => __DIR__ . '/../../logs/',
            'level' => $this->getEnv('LOG_LEVEL', 'info'),
            'max_files' => 30,
        ];
        
        $this->loaded = true;
    }
    
    /**
     * Load environment variables from .env file if exists
     */
    private function loadEnvironment(): void
    {
        $envFile = __DIR__ . '/../../.env';
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                // Skip comments
                if (str_starts_with(trim($line), '#')) {
                    continue;
                }
                
                // Parse KEY=value
                if (strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    
                    $this->env[$key] = $value;
                    
                    // Also set in $_ENV for compatibility
                    if (!isset($_ENV[$key])) {
                        $_ENV[$key] = $value;
                    }
                }
            }
        }
    }
    
    /**
     * Get a configuration value using dot notation
     * 
     * @param string $key Configuration key (e.g., 'database.host')
     * @param mixed $default Default value if not found
     * @return mixed Configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Set a configuration value at runtime
     * 
     * @param string $key Configuration key
     * @param mixed $value Value to set
     */
    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
    }
    
    /**
     * Check if a configuration key exists
     * 
     * @param string $key Configuration key
     * @return bool True if exists
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
    
    /**
     * Get an environment variable
     * 
     * @param string $key Environment variable name
     * @param string|null $default Default value
     * @return string|null Environment value
     */
    public function getEnv(string $key, ?string $default = null): ?string
    {
        // Check local env first
        if (isset($this->env[$key])) {
            return $this->env[$key];
        }
        
        // Check $_ENV
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        // Check getenv()
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        return $default;
    }
    
    /**
     * Get all configuration as array
     * 
     * @return array All configuration
     */
    public function all(): array
    {
        return $this->config;
    }
    
    /**
     * Check if running in production
     * 
     * @return bool True if production
     */
    public function isProduction(): bool
    {
        return $this->get('app.environment') === 'production';
    }
    
    /**
     * Check if debug mode is enabled
     * 
     * @return bool True if debug enabled
     */
    public function isDebug(): bool
    {
        return $this->get('app.debug', false);
    }
    
    // =========================================================================
    // SHORTCUT METHODS FOR COMMON CONFIG
    // =========================================================================
    
    /**
     * Get database configuration
     */
    public function database(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->get('database');
        }
        return $this->get("database.{$key}");
    }
    
    /**
     * Get security configuration
     */
    public function security(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->get('security');
        }
        return $this->get("security.{$key}");
    }
    
    /**
     * Get cache configuration
     */
    public function cache(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->get('cache');
        }
        return $this->get("cache.{$key}");
    }
    
    /**
     * Get application base URL
     */
    public function baseUrl(): string
    {
        return $this->get('app.base_url', '/');
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}

// =========================================================================
// HELPER FUNCTIONS (for backward compatibility with legacy code)
// =========================================================================

/**
 * Get configuration value (helper function)
 * 
 * @param string $key Configuration key
 * @param mixed $default Default value
 * @return mixed Configuration value
 */
function config(string $key, mixed $default = null): mixed
{
    return \EduCRM\Services\ConfigService::getInstance()->get($key, $default);
}

/**
 * Get environment variable (helper function)
 * 
 * @param string $key Environment variable name
 * @param string|null $default Default value
 * @return string|null Environment value
 */
function env(string $key, ?string $default = null): ?string
{
    return \EduCRM\Services\ConfigService::getInstance()->getEnv($key, $default);
}
