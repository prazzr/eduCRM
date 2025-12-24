<?php
// Configuration settings

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'edu_crm');

// Establish DB Connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("ERROR: Could not connect to database. " . $e->getMessage());
}

// Base URL
define('BASE_URL', 'http://localhost/CRM/');

// Security: Upload Directory (Relative to this file or Absolute)
// Recommendation: Ideally this should be OUTSIDE user-accessible web root.
define('SECURE_UPLOAD_DIR', __DIR__ . '/../secure_uploads/');

// Helper Functions
// ... (Include same helper functions as main config or require a common file if refactoring, 
// for now keeping it simple as a sample specific to credentials)

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}
// ... (Rest of the file would be here, but for sample just credential part is key)
// To verify full structure, I'd rather copy the whole file and sanitize it.
// see next step.
?>