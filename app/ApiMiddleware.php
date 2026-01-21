<?php

declare(strict_types=1);

namespace EduCRM;/**
  * API Middleware
  * Provides common middleware functions for API endpoints
  * Including rate limiting, authentication, and response helpers
  */

require_once __DIR__ . '/services/SecurityService.php';

class ApiMiddleware
{
    private static $pdo = null;

    /**
     * Initialize PDO connection
     */
    private static function getPdo()
    {
        if (self::$pdo === null) {
            require_once __DIR__ . '/bootstrap.php';
            global $pdo;
            self::$pdo = $pdo;
        }
        return self::$pdo;
    }

    /**
     * Enforce rate limiting for API endpoints
     * 
     * @param int $limit Maximum requests allowed
     * @param int $period Time window in seconds
     * @param string $action Action identifier for rate limit grouping
     * @return bool True if request is allowed
     */
    public static function enforceRateLimit(int $limit = 60, int $period = 60, string $action = 'api_request'): bool
    {
        $pdo = self::getPdo();

        // Get user ID from session, or use IP for unauthenticated requests
        session_start();
        $userId = $_SESSION['user_id'] ?? 0;
        $identifier = $userId ?: ip2long($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        $security = new \EduCRM\Services\SecurityService($pdo);

        // Check rate limit
        $allowed = $security->checkRateLimit($identifier, $action, $limit, $period);

        // Get current count for headers
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM security_logs
            WHERE user_id = ?
            AND action = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$identifier, $action, $period]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $currentCount = $result['count'] ?? 0;

        // Set rate limit headers
        header("X-RateLimit-Limit: $limit");
        header("X-RateLimit-Remaining: " . max(0, $limit - $currentCount));
        header("X-RateLimit-Reset: " . (time() + $period));

        if (!$allowed) {
            http_response_code(429);
            header('Retry-After: ' . $period);
            echo json_encode([
                'success' => false,
                'error' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $period
            ]);
            exit;
        }

        return true;
    }

    /**
     * Require API authentication
     * Returns user data if authenticated, false otherwise
     */
    public static function requireAuth(): array|false
    {
        session_start();

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Authentication required'
            ]);
            exit;
        }

        return [
            'user_id' => $_SESSION['user_id'],
            'role' => $_SESSION['role'] ?? 'user',
            'name' => $_SESSION['name'] ?? ''
        ];
    }

    /**
     * Set standard API response headers
     */
    public static function setApiHeaders(): void
    {
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }

    /**
     * Send JSON response and exit
     */
    public static function respond(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        self::setApiHeaders();
        echo json_encode($data);
        exit;
    }

    /**
     * Send error response and exit
     */
    public static function error(string $message, int $statusCode = 400): void
    {
        self::respond([
            'success' => false,
            'error' => $message
        ], $statusCode);
    }
}
