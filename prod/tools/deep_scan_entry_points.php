<?php
/**
 * Deep Scan Verification Tool
 * Recursively scans all PHP files in modules/ directory.
 * Checks for banned 'config.php' inclusions and verifies 'bootstrap.php' usage.
 */

$rootDetails = [
    'path' => __DIR__ . '/../modules',
    'banned' => ['config/config.php', 'config.php'], // Naive check for config.php
    'required' => ['app/bootstrap.php', 'bootstrap.php']
];

echo "Starting Deep Scan of: " . $rootDetails['path'] . "\n";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootDetails['path'])
);

$filesScanned = 0;
$issuesFound = 0;

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filesScanned++;
        $content = file_get_contents($file->getPathname());
        $relativePath = str_replace(dirname(__DIR__) . '/', '', $file->getPathname());

        // 1. Check for BANNED config.php inclusion
        // Regex to catch: require '...config.php', require_once __DIR__ . '...config/config.php'
        if (
            preg_match('/(require|include)(_once)?\s*\(?\s*[\'"].*config\/config\.php[\'"]/', $content) ||
            preg_match('/(require|include)(_once)?\s*\(?\s*__DIR__\s*\.\s*[\'"].*config\/config\.php[\'"]/', $content)
        ) {

            echo "[FAIL] Direct Config Include found in: $relativePath\n";
            $issuesFound++;
            continue;
        }

        // 2. Check if it's likely an entry point (has HTML or logic) but misses bootstrap
        // This is heuristic, but good for verification.
        // If it calls requireLogin(), it NEEDS bootstrap.
        if (
            strpos($content, 'requireLogin(') !== false &&
            strpos($content, 'bootstrap.php') === false
        ) {
            echo "[WARN] Uses requireLogin() but missing bootstrap.php: $relativePath\n";
            // We won't count as 'issue' for the config.php check, but good to know.
            // Actually, this IS an issue because functions are in helpers.php loaded by bootstrap.
            $issuesFound++;
        }
    }
}

echo "\n----------------------------------------\n";
echo "Scan Complete.\n";
echo "Total Files Scanned: $filesScanned\n";
echo "Issues Found: $issuesFound\n";

if ($issuesFound === 0) {
    echo "RESULT: SUCCESS (Green). No direct config.php inclusions found.\n";
} else {
    echo "RESULT: FAILURE (Red). Found $issuesFound files to fix.\n";
}
