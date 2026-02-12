<?php
/**
 * EduCRM Production Health Check
 * Upload this file to your server root (e.g., system.mul.edu.np/production_check.php)
 * Access it via browser to verify your deployment.
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduCRM Production Check</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            line-height: 1.5;
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
            background: #f8fafc;
            color: #334155;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 1.5rem;
        }

        h1 {
            margin-top: 0;
            color: #0f172a;
        }

        h2 {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 0.5rem;
            margin-top: 2rem;
            font-size: 1.25rem;
            color: #475569;
        }

        .status {
            font-weight: bold;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .item:last-child {
            border-bottom: none;
        }

        .details {
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 0.25rem;
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>🚀 Production Health Check</h1>
        <p>Verifying server configuration for EduCRM...</p>

        <h2>1. PHP Environment</h2>
        <?php
        $checks = [
            'PHP Version' => ['status' => version_compare(PHP_VERSION, '8.0', '>='), 'msg' => 'Current: ' . PHP_VERSION . ' (Required: 8.0+)'],
            'PDO Extension' => ['status' => extension_loaded('pdo'), 'msg' => 'Required for database'],
            'MySQL Extension' => ['status' => extension_loaded('pdo_mysql'), 'msg' => 'Required for MySQL'],
            'GD Library' => ['status' => extension_loaded('gd'), 'msg' => 'Required for images'],
        ];
        foreach ($checks as $name => $check) {
            echo '<div class="item"><div><strong>' . htmlspecialchars($name) . '</strong><div class="details">' . htmlspecialchars($check['msg']) . '</div></div><span class="status ' . ($check['status'] ? 'success' : 'error') . '">' . ($check['status'] ? 'OK' : 'MISSING') . '</span></div>';
        }
        ?>

        <h2>2. Critical Files</h2>
        <?php
        $files = [
            '.env' => 'Configuration file',
            '.htaccess' => 'Main routing rules',
            'public/.htaccess' => 'Public asset routing',
            'app/bootstrap.php' => 'Application bootstrapper',
            'login.php' => 'Login entry point'
        ];
        foreach ($files as $path => $desc) {
            $exists = file_exists(__DIR__ . '/' . $path);
            echo '<div class="item"><div><strong>' . htmlspecialchars($path) . '</strong><div class="details">' . htmlspecialchars($desc) . '</div></div><span class="status ' . ($exists ? 'success' : 'error') . '">' . ($exists ? 'FOUND' : 'MISSING') . '</span></div>';
        }
        ?>

        <h2>3. Directory Permissions & Creation</h2>
        <?php
        $dirs = [
            'storage' => 'Must be writable',
            'storage/cache' => 'Cache directory',
            'storage/logs' => 'Logs directory',
            'public/uploads' => 'Uploads directory'
        ];
        foreach ($dirs as $path => $desc) {
            $fullPath = __DIR__ . '/' . $path;
            if (!file_exists($fullPath)) {
                @mkdir($fullPath, 0755, true);
            }

            if (!file_exists($fullPath)) {
                $status = 'MISSING';
                $class = 'error';
                $details = 'Directory does not exist. Please create manually.';
            } else {
                $writable = is_writable($fullPath);
                $status = $writable ? 'WRITABLE' : 'NOT WRITABLE';
                $class = $writable ? 'success' : 'error';
                $details = $desc . " (Current: " . substr(sprintf('%o', fileperms($fullPath)), -4) . ")";
            }
            echo '<div class="item"><div><strong>' . htmlspecialchars($path) . '</strong><div class="details">' . htmlspecialchars($details) . '</div></div><span class="status ' . $class . '">' . $status . '</span></div>';
        }
        ?>

        <h2>4. Database Connection</h2>
        <?php
        $dbStatus = false;
        $dbMsg = '';

        // Robust custom .env parser
        function parseEnv($path)
        {
            if (!file_exists($path))
                return null;
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $env = [];
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0)
                    continue;
                list($name, $value) = explode('=', $line, 2) + [NULL, NULL];
                if ($name !== NULL && $value !== NULL) {
                    $env[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
                }
            }
            return $env;
        }

        if (file_exists(__DIR__ . '/.env')) {
            $env = parseEnv(__DIR__ . '/.env');
            if ($env) {
                try {
                    $dsn = "mysql:host=" . ($env['DB_HOST'] ?? 'localhost') . ";dbname=" . ($env['DB_NAME'] ?? 'edu_crm') . ";charset=utf8mb4";
                    $pdo = new PDO($dsn, $env['DB_USER'] ?? 'root', $env['DB_PASS'] ?? '');
                    $dbStatus = true;
                    $dbMsg = "Connected to " . ($env['DB_NAME'] ?? 'unknown');
                } catch (PDOException $e) {
                    $dbStatus = false;
                    $dbMsg = "Connection failed: " . $e->getMessage();
                }
            } else {
                $dbMsg = "Could not parse .env file (Empty or Invalid)";
            }
        } else {
            $dbMsg = ".env file missing";
        }
        echo '<div class="item"><div><strong>Connection Test</strong><div class="details">' . htmlspecialchars($dbMsg) . '</div></div><span class="status ' . ($dbStatus ? 'success' : 'error') . '">' . ($dbStatus ? 'SUCCESS' : 'FAILED') . '</span></div>';
        ?>
    </div>
</body>

</html>