<?php
// tools/verify_entry_points.php
// Validates that all entry points load bootstrap.php and the path is correct.

$root = dirname(__DIR__);
$modulesDir = $root . '/modules';

echo "Verifying entry points in: $modulesDir\n";

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modulesDir));
$errors = 0;
$checked = 0;

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php')
        continue;

    $content = file_get_contents($file->getPathname());
    $checked++;

    // Check for config.php inclusion (Classic Error)
    if (preg_match('/require(_once)?\s+[\'"](.*?)config\/config\.php[\'"]/', $content, $matches)) {
        echo "[FAIL] " . $file->getFilename() . " still requires config.php directly.\n";
        $errors++;
        continue;
    }

    // Check for bootstrap.php inclusion
    if (preg_match('/require(_once)?\s+[\'"](.*?)bootstrap\.php[\'"]/', $content, $matches)) {
        $relativePath = $matches[2]; // e.g. '../../app/' or '/'

        // Construct absolute path to verify
        $dir = $file->getPath();
        $target = $dir . '/' . $relativePath . 'bootstrap.php';
        $target = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $target); // normalize

        // Resolve keys like ../
        $realPath = realpath($target);

        if ($realPath && file_exists($realPath) && strpos($realPath, 'bootstrap.php') !== false) {
            // echo "[OK] " . $file->getFilename() . "\n"; 
        } else {
            echo "[FAIL] " . $file->getFilename() . " has BROKEN bootstrap path: " . $relativePath . "bootstrap.php\n";
            echo "       Resolved to: " . ($realPath ?: 'False') . "\n";
            $errors++;
        }
    } else {
        // Maybe it doesn't need config? (Partial file / view)
        // If it uses $pdo or $taskService without inclusion?
        // But previously these files had config.php.
        // Warn if likely an entry point (e.g. list.php)
        if (in_array($file->getFilename(), ['list.php', 'add.php', 'edit.php', 'delete.php'])) {
            echo "[WARN] " . $file->getFilename() . " does NOT include bootstrap.php (might be broken ENTRY POINT)\n";
        }
    }
}

echo "Verification Complete. Checked: $checked. Errors: $errors.\n";
if ($errors === 0) {
    echo "SUCCESS: All tested files reference a valid bootstrap.php.\n";
} else {
    echo "FAILURE: Found broken paths.\n";
}
