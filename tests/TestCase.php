<?php
declare(strict_types=1);

namespace EduCRM\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use PDO;

/**
 * Base Test Case for EduCRM Tests
 * 
 * Provides database connection and common test utilities.
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected static ?PDO $pdo = null;

    /**
     * Set up database connection before tests run
     */
    public static function setUpBeforeClass(): void
    {
        // Start output buffering to prevent "headers already sent" issues
        if (!ob_get_level()) {
            ob_start();
        }

        // Start session if not already started (prevents session_start() errors)
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (self::$pdo === null) {
            // Load environment
            $envFile = dirname(__DIR__) . '/.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                        [$key, $value] = explode('=', $line, 2);
                        $_ENV[trim($key)] = trim($value);
                    }
                }
            }

            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $dbname = $_ENV['DB_NAME'] ?? 'edu_crm';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';

            self::$pdo = new PDO(
                "mysql:host={$host};dbname={$dbname}",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        }
    }

    /**
     * Clean up after all tests
     */
    public static function tearDownAfterClass(): void
    {
        // End output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }
    }

    /**
     * Get PDO connection for tests
     */
    protected function getPdo(): PDO
    {
        return self::$pdo;
    }

    /**
     * Assert that an array has all expected keys
     */
    protected function assertArrayHasKeys(array $keys, array $array): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, "Array is missing key: {$key}");
        }
    }
}

