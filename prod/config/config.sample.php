<?php
/**
 * Sample Configuration File
 * 
 * Copy this file to config.php and update with your credentials.
 * Alternatively, use .env file for environment-based configuration.
 */

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================
use EduCRM\Helpers\Env;

// DB Credentials - These will be loaded from .env if available
define('DB_HOST', Env::get('DB_HOST', 'localhost'));
define('DB_USER', Env::get('DB_USER', 'root'));
define('DB_PASS', Env::get('DB_PASS', ''));
define('DB_NAME', Env::get('DB_NAME', 'edu_crm'));

// ============================================================================
// CACHE CONFIGURATION
// ============================================================================
define('CACHE_DRIVER', Env::get('CACHE_DRIVER', 'file'));
define('CACHE_DIR', dirname(__DIR__) . '/storage/cache/');

// Redis (optional)
if (CACHE_DRIVER === 'redis') {
    define('REDIS_HOST', Env::get('REDIS_HOST', '127.0.0.1'));
    define('REDIS_PORT', (int) Env::get('REDIS_PORT', 6379));
    define('REDIS_PASSWORD', Env::get('REDIS_PASSWORD'));
}

// ============================================================================
// DATABASE CONNECTION
// ============================================================================
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("ERROR: Could not connect to database. " . $e->getMessage());
}

// Base URL
define('BASE_URL', Env::get('APP_URL', 'http://localhost/CRM/'));

// Security: Upload Directory
define('SECURE_UPLOAD_DIR', __DIR__ . '/../secure_uploads/');

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}