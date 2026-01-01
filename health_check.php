<?php
/**
 * Health Check Endpoint
 * Verifies system health and readiness
 */

require_once 'config.php';

header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// Check database connection
try {
    $stmt = $pdo->query("SELECT 1");
    $health['checks']['database'] = [
        'status' => 'ok',
        'message' => 'Database connection successful'
    ];
} catch (Exception $e) {
    $health['status'] = 'unhealthy';
    $health['checks']['database'] = [
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ];
}

// Check disk space
$freeSpace = disk_free_space('.');
$totalSpace = disk_total_space('.');
$usedPercentage = (($totalSpace - $freeSpace) / $totalSpace) * 100;

$health['checks']['disk_space'] = [
    'status' => $usedPercentage < 90 ? 'ok' : 'warning',
    'free' => round($freeSpace / 1024 / 1024 / 1024, 2) . ' GB',
    'total' => round($totalSpace / 1024 / 1024 / 1024, 2) . ' GB',
    'used_percentage' => round($usedPercentage, 2) . '%'
];

if ($usedPercentage >= 90) {
    $health['status'] = 'warning';
}

// Check required directories
$requiredDirs = ['uploads', 'exports', 'cache'];
foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        $health['status'] = 'unhealthy';
        $health['checks']['directories'][$dir] = [
            'status' => 'error',
            'message' => 'Directory does not exist'
        ];
    } elseif (!is_writable($dir)) {
        $health['status'] = 'unhealthy';
        $health['checks']['directories'][$dir] = [
            'status' => 'error',
            'message' => 'Directory is not writable'
        ];
    } else {
        $health['checks']['directories'][$dir] = [
            'status' => 'ok',
            'message' => 'Directory exists and is writable'
        ];
    }
}

// Check PHP version
$health['checks']['php_version'] = [
    'status' => version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'warning',
    'version' => PHP_VERSION,
    'required' => '8.0.0+'
];

// Check required extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'curl'];
foreach ($requiredExtensions as $ext) {
    $health['checks']['extensions'][$ext] = [
        'status' => extension_loaded($ext) ? 'ok' : 'error',
        'loaded' => extension_loaded($ext)
    ];

    if (!extension_loaded($ext)) {
        $health['status'] = 'unhealthy';
    }
}

// Check memory usage
$memoryUsage = memory_get_usage(true);
$memoryLimit = ini_get('memory_limit');
$health['checks']['memory'] = [
    'status' => 'ok',
    'usage' => round($memoryUsage / 1024 / 1024, 2) . ' MB',
    'limit' => $memoryLimit
];

// Overall status
http_response_code($health['status'] === 'healthy' ? 200 : 503);

echo json_encode($health, JSON_PRETTY_PRINT);

// Exit with appropriate code
exit($health['status'] === 'healthy' ? 0 : 1);
