<?php
/**
 * Authentication API Endpoint
 * 
 * POST /api/v1/auth/login.php
 * Request: { "email": "...", "password": "..." }
 * Response: { "success": true, "token": "...", "user": {...}, "expires_in": 86400 }
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../ApiController.php';

$api = new ApiController($pdo);

// Only allow POST
if ($api->getMethod() !== 'POST') {
    $api->error('Method not allowed', 405);
}

// Get credentials
$body = $api->getJsonBody();

if (empty($body['email']) || empty($body['password'])) {
    $api->error('Email and password are required', 400);
}

$email = filter_var($body['email'], FILTER_SANITIZE_EMAIL);
$password = $body['password'];

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $api->error('Invalid email format', 400);
}

try {
    // Find user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $api->error('Invalid credentials', 401);
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        $api->error('Invalid credentials', 401);
    }
    
    // Fetch user roles
    $roleStmt = $pdo->prepare("
        SELECT r.name 
        FROM roles r 
        JOIN user_roles ur ON r.id = ur.role_id 
        WHERE ur.user_id = ?
    ");
    $roleStmt->execute([$user['id']]);
    $roles = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($roles)) {
        $roles = ['student']; // Default role
    }
    
    // Generate JWT token
    $token = ApiController::generateToken($user['id'], $user['email'], $roles);
    
    // Log successful login
    $logStmt = $pdo->prepare("
        INSERT INTO system_logs (user_id, action, details, ip_address) 
        VALUES (?, 'api_login', 'API login successful', ?)
    ");
    $logStmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    
    // Return response
    $api->success([
        'token' => $token,
        'expires_in' => 86400,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'roles' => $roles
        ]
    ]);
    
} catch (PDOException $e) {
    $api->error('Authentication failed', 500);
}
