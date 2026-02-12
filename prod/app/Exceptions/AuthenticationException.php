<?php
declare(strict_types=1);

namespace EduCRM\Exceptions;

/**
 * Exception for authentication failures
 * 
 * Thrown when user authentication fails due to invalid credentials,
 * expired tokens, or missing authorization.
 */
class AuthenticationException extends \EduCRM\Exceptions\AppException
{
    public const INVALID_CREDENTIALS = 1001;
    public const TOKEN_EXPIRED = 1002;
    public const TOKEN_INVALID = 1003;
    public const TOKEN_MISSING = 1004;
    public const ACCOUNT_LOCKED = 1005;
    public const ACCOUNT_DISABLED = 1006;

    /**
     * Create a new authentication exception
     *
     * @param string $message The error message
     * @param int $code The error code (use class constants)
     * @param array<string, mixed> $context Additional context
     */
    public function __construct(
        string $message = "Authentication failed",
        int $code = self::INVALID_CREDENTIALS,
        array $context = []
    ) {
        parent::__construct($message, $code, null, $context);
    }

    /**
     * Create exception for invalid credentials
     */
    public static function invalidCredentials(): static
    {
        return new static("Invalid email or password", self::INVALID_CREDENTIALS);
    }

    /**
     * Create exception for expired token
     */
    public static function tokenExpired(): static
    {
        return new static("Token has expired", self::TOKEN_EXPIRED);
    }

    /**
     * Create exception for invalid token
     */
    public static function tokenInvalid(): static
    {
        return new static("Invalid authentication token", self::TOKEN_INVALID);
    }

    /**
     * Create exception for missing token
     */
    public static function tokenMissing(): static
    {
        return new static("Authorization token is required", self::TOKEN_MISSING);
    }

    /**
     * Create exception for locked account
     */
    public static function accountLocked(int $minutesRemaining = 0): static
    {
        $message = $minutesRemaining > 0
            ? "Account is locked. Try again in {$minutesRemaining} minutes"
            : "Account is locked due to too many failed attempts";
        
        return new static($message, self::ACCOUNT_LOCKED, [
            'minutes_remaining' => $minutesRemaining
        ]);
    }

    /**
     * Create exception for disabled account
     */
    public static function accountDisabled(): static
    {
        return new static("Account has been disabled", self::ACCOUNT_DISABLED);
    }

    /**
     * Get HTTP status code for this exception
     */
    public function getHttpStatusCode(): int
    {
        return 401;
    }
}
