<?php
/**
 * Security Audit Script
 * Runs comprehensive security checks on the system
 */

require_once 'config.php';
require_once 'includes/services/SecurityService.php';

echo "========================================\n";
echo "eduCRM Security Audit\n";
echo "========================================\n\n";

$issues = [];
$warnings = [];
$passed = [];

// 1. Check CSRF Protection
echo "1. Checking CSRF Protection...\n";
try {
    $token = SecurityService::generateCSRFToken();
    if (SecurityService::validateCSRFToken($token)) {
        $passed[] = "CSRF token generation and validation working";
    }
} catch (Exception $e) {
    $issues[] = "CSRF protection error: " . $e->getMessage();
}

// 2. Check Security Headers
echo "2. Checking Security Headers...\n";
ob_start();
SecurityService::setSecurityHeaders();
$headers = headers_list();
ob_end_clean();

$requiredHeaders = [
    'X-Frame-Options',
    'X-Content-Type-Options',
    'X-XSS-Protection',
    'Strict-Transport-Security'
];

foreach ($requiredHeaders as $header) {
    $found = false;
    foreach ($headers as $h) {
        if (stripos($h, $header) !== false) {
            $found = true;
            break;
        }
    }
    if ($found) {
        $passed[] = "Security header present: $header";
    } else {
        $warnings[] = "Missing security header: $header";
    }
}

// 3. Check Database Connection Security
echo "3. Checking Database Security...\n";
try {
    // Check if using PDO with prepared statements
    if ($pdo instanceof PDO) {
        $passed[] = "Using PDO for database connections";
    } else {
        $issues[] = "Not using PDO - SQL injection risk";
    }
} catch (Exception $e) {
    $issues[] = "Database connection error: " . $e->getMessage();
}

// 4. Check File Permissions
echo "4. Checking File Permissions...\n";
$criticalFiles = [
    'config.php',
    'includes/services/SecurityService.php'
];

foreach ($criticalFiles as $file) {
    if (file_exists($file)) {
        $perms = fileperms($file);
        $octal = substr(sprintf('%o', $perms), -4);

        if ($octal === '0644' || $octal === '0444') {
            $passed[] = "File permissions OK: $file ($octal)";
        } else {
            $warnings[] = "File permissions may be too open: $file ($octal)";
        }
    }
}

// 5. Check Upload Directory Security
echo "5. Checking Upload Directory...\n";
if (is_dir('uploads')) {
    // Check if .htaccess exists
    if (file_exists('uploads/.htaccess')) {
        $passed[] = "Upload directory has .htaccess protection";
    } else {
        $warnings[] = "Upload directory missing .htaccess file";
    }

    // Check permissions
    $perms = fileperms('uploads');
    $octal = substr(sprintf('%o', $perms), -4);

    if ($octal === '0755' || $octal === '0777') {
        $passed[] = "Upload directory permissions: $octal";
    } else {
        $warnings[] = "Upload directory permissions may be incorrect: $octal";
    }
}

// 6. Check Session Security
echo "6. Checking Session Security...\n";
if (ini_get('session.cookie_httponly') == 1) {
    $passed[] = "Session cookies are HTTP-only";
} else {
    $warnings[] = "Session cookies should be HTTP-only";
}

if (ini_get('session.cookie_secure') == 1 || !isset($_SERVER['HTTPS'])) {
    $passed[] = "Session cookie security appropriate for environment";
} else {
    $warnings[] = "Session cookies should be secure (HTTPS only)";
}

// 7. Check Password Hashing
echo "7. Checking Password Security...\n";
try {
    $stmt = $pdo->query("SELECT password FROM users LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_get_info($user['password'])['algo'] !== null) {
        $passed[] = "Passwords are properly hashed";
    } else {
        $issues[] = "Passwords may not be properly hashed";
    }
} catch (Exception $e) {
    $warnings[] = "Could not verify password hashing: " . $e->getMessage();
}

// 8. Check for Exposed Sensitive Files
echo "8. Checking for Exposed Files...\n";
$sensitiveFiles = [
    '.env',
    '.git/config',
    'composer.json',
    'phpinfo.php',
    'test.php'
];

foreach ($sensitiveFiles as $file) {
    if (file_exists($file)) {
        $warnings[] = "Sensitive file exposed: $file (should be removed or protected)";
    }
}

// 9. Check Database Tables for Security
echo "9. Checking Database Tables...\n";
try {
    // Check if security_logs table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'security_logs'");
    if ($stmt->rowCount() > 0) {
        $passed[] = "Security logging table exists";
    } else {
        $warnings[] = "Security logging table not found";
    }
} catch (Exception $e) {
    $warnings[] = "Could not check database tables: " . $e->getMessage();
}

// 10. Check Error Reporting
echo "10. Checking Error Reporting...\n";
if (ini_get('display_errors') == 0) {
    $passed[] = "Error display is disabled (production setting)";
} else {
    $warnings[] = "Error display is enabled (should be disabled in production)";
}

if (ini_get('log_errors') == 1) {
    $passed[] = "Error logging is enabled";
} else {
    $warnings[] = "Error logging should be enabled";
}

// Summary
echo "\n========================================\n";
echo "Security Audit Summary\n";
echo "========================================\n\n";

echo "✅ PASSED (" . count($passed) . "):\n";
foreach ($passed as $item) {
    echo "  ✓ $item\n";
}

if (!empty($warnings)) {
    echo "\n⚠️  WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $item) {
        echo "  ! $item\n";
    }
}

if (!empty($issues)) {
    echo "\n❌ CRITICAL ISSUES (" . count($issues) . "):\n";
    foreach ($issues as $item) {
        echo "  ✗ $item\n";
    }
}

echo "\n========================================\n";

if (empty($issues)) {
    if (empty($warnings)) {
        echo "✅ Security audit PASSED with no issues!\n";
        exit(0);
    } else {
        echo "⚠️  Security audit PASSED with " . count($warnings) . " warnings\n";
        exit(0);
    }
} else {
    echo "❌ Security audit FAILED with " . count($issues) . " critical issues\n";
    exit(1);
}
