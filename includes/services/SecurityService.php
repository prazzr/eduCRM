<?php
/**
 * Security Service
 * Handles CSRF protection, rate limiting, and security features
 */

class SecurityService
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token)
    {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Get CSRF token HTML input
     */
    public static function getCSRFInput()
    {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Check rate limit
     */
    public function checkRateLimit($userId, $action, $limit = 100, $period = 3600)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM security_logs
            WHERE user_id = ?
            AND action = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");

        $stmt->execute([$userId, $action, $period]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] >= $limit) {
            $this->logSecurityEvent($userId, 'rate_limit_exceeded', [
                'action' => $action,
                'limit' => $limit,
                'period' => $period
            ]);
            return false;
        }

        // Log the action
        $this->logSecurityEvent($userId, $action);
        return true;
    }

    /**
     * Log security event
     */
    public function logSecurityEvent($userId, $action, $metadata = [])
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO security_logs (user_id, action, ip_address, user_agent, metadata)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            json_encode($metadata)
        ]);
    }

    /**
     * Set security headers
     */
    public static function setSecurityHeaders()
    {
        header("X-Frame-Options: DENY");
        header("X-Content-Type-Options: nosniff");
        header("X-XSS-Protection: 1; mode=block");
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
        header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net;");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    }

    /**
     * Validate password strength
     */
    public static function validatePasswordStrength($password)
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Generate 2FA secret
     */
    public static function generate2FASecret()
    {
        return base32_encode(random_bytes(10));
    }

    /**
     * Verify 2FA code
     */
    public static function verify2FACode($secret, $code)
    {
        // Simple TOTP implementation (30-second window)
        $timeSlice = floor(time() / 30);

        for ($i = -1; $i <= 1; $i++) {
            $calculatedCode = self::getTOTPCode($secret, $timeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get TOTP code
     */
    private static function getTOTPCode($secret, $timeSlice)
    {
        $key = base32_decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Sanitize input
     */
    public static function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }

        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
}

// Helper functions for base32 encoding/decoding
function base32_encode($data)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output = '';
    $v = 0;
    $vbits = 0;

    for ($i = 0, $j = strlen($data); $i < $j; $i++) {
        $v <<= 8;
        $v += ord($data[$i]);
        $vbits += 8;

        while ($vbits >= 5) {
            $vbits -= 5;
            $output .= $alphabet[$v >> $vbits];
            $v &= ((1 << $vbits) - 1);
        }
    }

    if ($vbits > 0) {
        $v <<= (5 - $vbits);
        $output .= $alphabet[$v];
    }

    return $output;
}

function base32_decode($data)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output = '';
    $v = 0;
    $vbits = 0;

    for ($i = 0, $j = strlen($data); $i < $j; $i++) {
        $v <<= 5;
        $v += stripos($alphabet, $data[$i]);
        $vbits += 5;

        if ($vbits >= 8) {
            $vbits -= 8;
            $output .= chr($v >> $vbits);
            $v &= ((1 << $vbits) - 1);
        }
    }

    return $output;
}
