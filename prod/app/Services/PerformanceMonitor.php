<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
 * Performance Monitor
 * Tracks and logs application performance metrics
 */

class PerformanceMonitor
{
    private \PDO $pdo;
    private float $startTime;
    private int $startMemory;
    private int $queryCount = 0;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
    }

    /**
     * Track query execution
     */
    public function trackQuery()
    {
        $this->queryCount++;
    }

    /**
     * Log page performance
     */
    public function logPerformance($pageUrl, $userId = null)
    {
        $executionTime = microtime(true) - $this->startTime;
        $memoryUsage = memory_get_usage() - $this->startMemory;

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO performance_logs 
                (page_url, execution_time, memory_usage, query_count, user_id)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $pageUrl,
                round($executionTime, 4),
                $memoryUsage,
                $this->queryCount,
                $userId
            ]);
        } catch (\PDOException $e) {
            // Silent fail - don't break page if logging fails
            error_log("Performance logging failed: " . $e->getMessage());
        }
    }

    /**
     * Get slow pages report
     */
    public function getSlowPages($threshold = 2.0, $limit = 20)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                page_url,
                AVG(execution_time) as avg_time,
                MAX(execution_time) as max_time,
                COUNT(*) as hit_count
            FROM performance_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY page_url
            HAVING avg_time > ?
            ORDER BY avg_time DESC
            LIMIT ?
        ");

        $stmt->execute([$threshold, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get performance summary
     */
    public function getPerformanceSummary()
    {
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(*) as total_requests,
                AVG(execution_time) as avg_time,
                MAX(execution_time) as max_time,
                AVG(memory_usage) as avg_memory,
                AVG(query_count) as avg_queries
            FROM performance_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}

// Auto-initialize performance monitoring
if (isset($pdo)) {
    $performanceMonitor = new \EduCRM\Services\PerformanceMonitor($pdo);

    // Log performance at script end
    register_shutdown_function(function () use ($performanceMonitor) {
        $pageUrl = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $userId = $_SESSION['user_id'] ?? null;
        $performanceMonitor->logPerformance($pageUrl, $userId);
    });
}
