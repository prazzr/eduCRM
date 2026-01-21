<?php
declare(strict_types=1);

namespace EduCRM\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

/**
 * JWT Authentication Service
 * 
 * Phase 2: API-First Architecture
 * Handles token generation and validation for stateless API authentication.
 * 
 * @package EduCRM\Services
 * @version 2.0.0
 */
class JwtService
{
    private string $secretKey;
    private string $algorithm = 'HS256';
    private int $accessTokenTTL = 86400;    // 24 hours
    private int $refreshTokenTTL = 604800;  // 7 days

    public function __construct(?string $secretKey = null)
    {
        $this->secretKey = $secretKey ?? $_ENV['JWT_SECRET'] ?? 'educrm-jwt-secret-key-change-in-production';
    }

    /**
     * Generate access and refresh tokens for a user
     * 
     * @param int $userId User ID
     * @param array $roles User roles
     * @return array Token pair with expiry info
     */
    public function generateTokens(int $userId, array $roles = []): array
    {
        $now = time();

        $accessPayload = [
            'iss' => 'educrm',
            'sub' => $userId,
            'roles' => $roles,
            'iat' => $now,
            'exp' => $now + $this->accessTokenTTL,
            'type' => 'access'
        ];

        $refreshPayload = [
            'iss' => 'educrm',
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $this->refreshTokenTTL,
            'type' => 'refresh'
        ];

        return [
            'access_token' => JWT::encode($accessPayload, $this->secretKey, $this->algorithm),
            'refresh_token' => JWT::encode($refreshPayload, $this->secretKey, $this->algorithm),
            'expires_in' => $this->accessTokenTTL,
            'token_type' => 'Bearer'
        ];
    }

    /**
     * Validate a JWT token
     * 
     * @param string $token JWT token
     * @return object|null Decoded payload or null if invalid
     */
    public function validateToken(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->secretKey, $this->algorithm));
        } catch (ExpiredException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get user ID from token
     * 
     * @param string $token JWT token
     * @return int|null User ID or null if invalid
     */
    public function getUserIdFromToken(string $token): ?int
    {
        $payload = $this->validateToken($token);
        return $payload ? (int) $payload->sub : null;
    }

    /**
     * Get user roles from token
     * 
     * @param string $token JWT token
     * @return array User roles
     */
    public function getRolesFromToken(string $token): array
    {
        $payload = $this->validateToken($token);
        return $payload ? ($payload->roles ?? []) : [];
    }

    /**
     * Check if token is expired
     * 
     * @param string $token JWT token
     * @return bool
     */
    public function isTokenExpired(string $token): bool
    {
        try {
            JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            return false;
        } catch (ExpiredException $e) {
            return true;
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Get token type (access or refresh)
     * 
     * @param string $token JWT token
     * @return string|null Token type
     */
    public function getTokenType(string $token): ?string
    {
        $payload = $this->validateToken($token);
        return $payload ? ($payload->type ?? null) : null;
    }
}
