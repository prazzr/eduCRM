<?php
/**
 * PHPUnit Bootstrap File
 * 
 * This file is loaded before any tests run.
 * It sets up the session and output buffering to prevent
 * "headers already sent" errors from config.php.
 */

// Start output buffering FIRST thing
ob_start();

// Start session BEFORE PHPUnit sends any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}
