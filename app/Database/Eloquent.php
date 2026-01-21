<?php
declare(strict_types=1);

namespace EduCRM\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

/**
 * Eloquent ORM Bootstrap
 * 
 * Phase 2: API-First Architecture
 * Initializes standalone Eloquent ORM for database operations.
 * 
 * @package EduCRM\Database
 * @version 2.0.0
 */
class Eloquent
{
    private static ?Capsule $capsule = null;

    /**
     * Boot Eloquent ORM
     * 
     * @return Capsule
     */
    public static function boot(): Capsule
    {
        if (self::$capsule !== null) {
            return self::$capsule;
        }

        self::$capsule = new Capsule;

        // Load environment if not already loaded
        self::loadEnv();

        self::$capsule->addConnection([
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'database' => $_ENV['DB_NAME'] ?? 'edu_crm',
            'username' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]);

        // Set up event dispatcher for model events
        self::$capsule->setEventDispatcher(
            new Dispatcher(new Container)
        );

        // Make this Capsule instance available globally
        self::$capsule->setAsGlobal();

        // Boot Eloquent ORM
        self::$capsule->bootEloquent();

        return self::$capsule;
    }

    /**
     * Load environment variables from .env file
     */
    private static function loadEnv(): void
    {
        if (isset($_ENV['DB_HOST'])) {
            return; // Already loaded
        }

        $envFile = dirname(__DIR__, 2) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    [$key, $value] = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }
    }

    /**
     * Get the Capsule instance
     * 
     * @return Capsule|null
     */
    public static function getCapsule(): ?Capsule
    {
        return self::$capsule;
    }

    /**
     * Get PDO connection from Eloquent
     * 
     * @return \PDO
     */
    public static function getPdo(): \PDO
    {
        if (self::$capsule === null) {
            self::boot();
        }
        return self::$capsule->getConnection()->getPdo();
    }
}
