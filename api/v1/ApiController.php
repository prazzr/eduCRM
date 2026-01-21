<?php
declare(strict_types=1);

/**
 * API Base Controller
 * Provides JWT authentication and common API utilities
 * 
 * @package EduCRM\Api
 */
class ApiController
{
    protected \PDO $pdo;
    
    /** @var array{id: int, email: string, roles: string[]}|null */
    protected ?array $user = null;
    
    /** JWT token expiry in seconds */
    private static int $jwtExpiry = 86400; // 24 hours
    
    /**
     * Create a new API controller instance
     *
     * @param \PDO $pdo Database connection
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        
        // Set JSON headers
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        
        // Handle preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * Get JWT secret key from environment or fallback
     *
     * @return string
     */
    private static function getJwtSecret(): string
    {
        // Try environment variable first
        $secret = getenv('JWT_SECRET');
        if ($secret !== false && strlen($secret) >= 32) {
            return $secret;
        }
        
        // Try $_ENV
        if (isset($_ENV['JWT_SECRET']) && strlen($_ENV['JWT_SECRET']) >= 32) {
            return $_ENV['JWT_SECRET'];
        }
        
        // Fallback to config constant if defined
        if (defined('JWT_SECRET')) {
            return JWT_SECRET;
        }
        
        // Last resort fallback (should not be used in production)
        return 'educrm_api_secret_change_in_production_' . md5(__DIR__);
    }
    
    /**
     * Generate JWT token
     *
     * @param int $userId User ID
     * @param string $email User email
     * @param string[] $roles User roles
     * @return string JWT token
     */
    public static function generateToken(int $userId, string $email, array $roles): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $payload = json_encode([
            'sub' => $userId,
            'email' => $email,
            'roles' => $roles,
            'iat' => time(),
            'exp' => time() + self::$jwtExpiry
        ]);
        
        $base64Header = self::base64UrlEncode($header);
        $base64Payload = self::base64UrlEncode($payload);
        
        $signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", self::getJwtSecret(), true);
        $base64Signature = self::base64UrlEncode($signature);
        
        return "{$base64Header}.{$base64Payload}.{$base64Signature}";
    }
    
    /**
     * Validate JWT token and return payload
     *
     * @param string $token JWT token
     * @return array<string, mixed>|false Payload array or false if invalid
     */
    public static function validateToken(string $token): array|false
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        [$base64Header, $base64Payload, $base64Signature] = $parts;
        
        // Verify signature
        $signature = self::base64UrlDecode($base64Signature);
        $expectedSignature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", self::getJwtSecret(), true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            return false;
        }
        
        // Decode payload
        $payload = json_decode(self::base64UrlDecode($base64Payload), true);
        
        if (!is_array($payload)) {
            return false;
        }
        
        // Check expiry
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
    
    /**
     * Require authentication - call at start of protected endpoints
     *
     * @return array{id: int, email: string, roles: string[]} User data
     * @throws \RuntimeException If authentication fails
     */
    public function requireAuth(): array
    {
        $token = $this->getBearerToken();
        
        if ($token === null) {
            $this->error('Missing authorization token', 401);
        }
        
        $payload = self::validateToken($token);
        
        if ($payload === false) {
            $this->error('Invalid or expired token', 401);
        }
        
        // Set user context
        $this->user = [
            'id' => (int) $payload['sub'],
            'email' => (string) $payload['email'],
            'roles' => (array) $payload['roles']
        ];
        
        return $this->user;
    }
    
    /**
     * Check if authenticated user has specific role
     *
     * @param string|string[] $roles Required role(s)
     * @return bool True if authorized
     */
    public function requireRole(string|array $roles): bool
    {
        $this->requireAuth();
        
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        $hasRole = !empty(array_intersect($roles, $this->user['roles']));
        
        if (!$hasRole) {
            $this->error('Insufficient permissions', 403);
        }
        
        return true;
    }
    
    /**
     * Get Bearer token from Authorization header
     *
     * @return string|null Token or null if not present
     */
    protected function getBearerToken(): ?string
    {
        $headers = $this->getAuthorizationHeader();
        
        if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get Authorization header cross-platform
     *
     * @return string|null Authorization header value
     */
    protected function getAuthorizationHeader(): ?string
    {
        if (isset($_SERVER['Authorization'])) {
            return trim($_SERVER['Authorization']);
        }
        
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return trim($_SERVER['HTTP_AUTHORIZATION']);
        }
        
        if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if ($requestHeaders !== false) {
                $requestHeaders = array_combine(
                    array_map('ucwords', array_keys($requestHeaders)),
                    array_values($requestHeaders)
                );
                if (isset($requestHeaders['Authorization'])) {
                    return trim($requestHeaders['Authorization']);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get JSON body from request
     *
     * @return array<string, mixed> Parsed JSON data
     */
    public function getJsonBody(): array
    {
        $json = file_get_contents('php://input');
        
        if ($json === false || $json === '') {
            return [];
        }
        
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON body: ' . json_last_error_msg(), 400);
        }
        
        return is_array($data) ? $data : [];
    }
    
    /**
     * Get query parameter with default
     *
     * @param string $key Parameter name
     * @param mixed $default Default value
     * @return mixed Parameter value or default
     */
    public function getParam(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }
    
    /**
     * Get request method
     *
     * @return string HTTP method
     */
    public function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
    
    /**
     * Send success response
     *
     * @param mixed $data Response data
     * @param int $code HTTP status code
     * @return never
     */
    public function success(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'data' => $data
        ], JSON_THROW_ON_ERROR);
        exit;
    }
    
    /**
     * Send error response
     *
     * @param string $message Error message
     * @param int $code HTTP status code
     * @return never
     */
    public function error(string $message, int $code = 400): never
    {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message
        ], JSON_THROW_ON_ERROR);
        exit;
    }
    
    /**
     * Send paginated response
     *
     * @param array<int, mixed> $data Response data
     * @param int $total Total record count
     * @param int $page Current page
     * @param int $perPage Items per page
     * @return never
     */
    public function paginate(array $data, int $total, int $page, int $perPage): never
    {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / max(1, $perPage))
            ]
        ], JSON_THROW_ON_ERROR);
        exit;
    }
    
    /**
     * Base64 URL-safe encoding
     *
     * @param string $data Data to encode
     * @return string Encoded string
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL-safe decoding
     *
     * @param string $data Data to decode
     * @return string Decoded string
     */
    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
