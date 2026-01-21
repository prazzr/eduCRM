<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
 * Security Service
 * Handles CSRF protection, rate limiting, and security features
 * 
 * @package EduCRM\Services
 */
class SecurityService
{
    private \PDO $pdo;

    /**
     * Create a new \EduCRM\Services\SecurityService instance
     *
     * @param \PDO $pdo Database connection
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Generate CSRF token
     *
     * @return string 64-character hex token
     */
    public static function generateCSRFToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token
     *
     * @param string $token Token to validate
     * @return bool True if valid
     */
    public static function validateCSRFToken(string $token): bool
    {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Get CSRF token HTML input
     *
     * @return string HTML hidden input element
     */
    public static function getCSRFInput(): string
    {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Check rate limit for a user action
     *
     * @param int $userId User ID
     * @param string $action Action identifier
     * @param int $limit Maximum allowed actions
     * @param int $period Time period in seconds
     * @return bool True if within limit
     */
    public function checkRateLimit(int $userId, string $action, int $limit = 100, int $period = 3600): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM security_logs
            WHERE user_id = ?
            AND action = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");

        $stmt->execute([$userId, $action, $period]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ((int) $result['count'] >= $limit) {
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
     *
     * @param int $userId User ID
     * @param string $action Action performed
     * @param array<string, mixed> $metadata Additional metadata
     * @return bool Success status
     */
    public function logSecurityEvent(int $userId, string $action, array $metadata = []): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO security_logs (user_id, action, ip_address, user_agent, metadata)
            VALUES (?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $userId,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            json_encode($metadata, JSON_THROW_ON_ERROR)
        ]);
    }

    /**
     * Set security headers for HTTP response
     *
     * @return void
     */
    public static function setSecurityHeaders(): void
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
     *
     * @param string $password Password to validate
     * @return bool|array True if valid, array of errors otherwise
     */
    public static function validatePasswordStrength(string $password): bool|array
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
     *
     * @return string Base32 encoded secret
     */
    public static function generate2FASecret(): string
    {
        return self::base32Encode(random_bytes(10));
    }

    /**
     * Verify 2FA code
     *
     * @param string $secret The user's 2FA secret
     * @param string $code The code to verify
     * @return bool True if valid
     */
    public static function verify2FACode(string $secret, string $code): bool
    {
        // Simple TOTP implementation (30-second window)
        $timeSlice = (int) floor(time() / 30);

        for ($i = -1; $i <= 1; $i++) {
            $calculatedCode = self::getTOTPCode($secret, $timeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get TOTP code for a given time slice
     *
     * @param string $secret The user's secret
     * @param int $timeSlice Time slice
     * @return string 6-digit code
     */
    private static function getTOTPCode(string $secret, int $timeSlice): string
    {
        $key = self::base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Sanitize input for XSS prevention
     *
     * @param string|array<string, mixed> $input Input to sanitize
     * @return string|array<string, mixed> Sanitized input
     */
    public static function sanitizeInput(string|array $input): string|array
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }

        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Base32 encode data
     *
     * @param string $data Data to encode
     * @return string Base32 encoded string
     */
    private static function base32Encode(string $data): string
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

    /**
     * Base32 decode data
     *
     * @param string $data Data to decode
     * @return string Decoded string
     */
    private static function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $v = 0;
        $vbits = 0;

        for ($i = 0, $j = strlen($data); $i < $j; $i++) {
            $pos = stripos($alphabet, $data[$i]);
            if ($pos === false) {
                continue;
            }
            $v <<= 5;
            $v += $pos;
            $vbits += 5;

            if ($vbits >= 8) {
                $vbits -= 8;
                $output .= chr($v >> $vbits);
                $v &= ((1 << $vbits) - 1);
            }
        }

        return $output;
    }
}

// Keep legacy global functions for backward compatibility
if (!function_exists('base32_encode')) {
    /**
     * @deprecated Use \EduCRM\Services\SecurityService::base32Encode() instead
     */
    function base32_encode(string $data): string
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
}

if (!function_exists('base32_decode')) {
    /**
     * @deprecated Use \EduCRM\Services\SecurityService::base32Decode() instead
     */
    function base32_decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $v = 0;
        $vbits = 0;

        for ($i = 0, $j = strlen($data); $i < $j; $i++) {
            $pos = stripos($alphabet, $data[$i]);
            if ($pos === false) {
                continue;
            }
            $v <<= 5;
            $v += $pos;
            $vbits += 5;

            if ($vbits >= 8) {
                $vbits -= 8;
                $output .= chr($v >> $vbits);
                $v &= ((1 << $vbits) - 1);
            }
        }

        return $output;
    }
}
