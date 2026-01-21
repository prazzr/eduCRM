<?php
declare(strict_types=1);

namespace EduCRM\Tests\Unit\Services;

use EduCRM\Tests\TestCase;
use EduCRM\Services\JwtService;

/**
 * Unit Tests for JwtService
 * 
 * Tests JWT token generation and validation.
 */
class JwtServiceTest extends TestCase
{
    private JwtService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Must be at least 256 bits (32 bytes) for HS256
        $this->service = new JwtService('this-is-a-test-secret-key-that-is-at-least-32-bytes-long');
    }

    /**
     * Test that service can be instantiated
     */
    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(JwtService::class, $this->service);
    }

    /**
     * Test token generation returns all required fields
     */
    public function testGenerateTokensReturnsRequiredFields(): void
    {
        $tokens = $this->service->generateTokens(1, ['admin']);

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertArrayHasKey('expires_in', $tokens);
        $this->assertArrayHasKey('token_type', $tokens);
        $this->assertEquals('Bearer', $tokens['token_type']);
    }

    /**
     * Test access token is valid JWT format
     */
    public function testAccessTokenIsValidFormat(): void
    {
        $tokens = $this->service->generateTokens(1, ['admin']);

        // JWT has 3 parts separated by dots
        $parts = explode('.', $tokens['access_token']);
        $this->assertCount(3, $parts);
    }

    /**
     * Test token validation returns payload for valid token
     */
    public function testValidateTokenReturnsPayloadForValidToken(): void
    {
        $tokens = $this->service->generateTokens(1, ['admin']);

        $payload = $this->service->validateToken($tokens['access_token']);

        $this->assertNotNull($payload);
        $this->assertEquals(1, $payload->sub);
        $this->assertEquals(['admin'], $payload->roles);
        $this->assertEquals('access', $payload->type);
    }

    /**
     * Test token validation returns null for invalid token
     */
    public function testValidateTokenReturnsNullForInvalidToken(): void
    {
        $payload = $this->service->validateToken('invalid-token');

        $this->assertNull($payload);
    }

    /**
     * Test getUserIdFromToken returns correct user ID
     */
    public function testGetUserIdFromTokenReturnsCorrectId(): void
    {
        $tokens = $this->service->generateTokens(42, ['student']);

        $userId = $this->service->getUserIdFromToken($tokens['access_token']);

        $this->assertEquals(42, $userId);
    }

    /**
     * Test getRolesFromToken returns correct roles
     */
    public function testGetRolesFromTokenReturnsCorrectRoles(): void
    {
        $tokens = $this->service->generateTokens(1, ['admin', 'teacher']);

        $roles = $this->service->getRolesFromToken($tokens['access_token']);

        $this->assertEquals(['admin', 'teacher'], $roles);
    }

    /**
     * Test refresh token has correct type
     */
    public function testRefreshTokenHasCorrectType(): void
    {
        $tokens = $this->service->generateTokens(1, []);

        $type = $this->service->getTokenType($tokens['refresh_token']);

        $this->assertEquals('refresh', $type);
    }

    /**
     * Test access token has correct type
     */
    public function testAccessTokenHasCorrectType(): void
    {
        $tokens = $this->service->generateTokens(1, []);

        $type = $this->service->getTokenType($tokens['access_token']);

        $this->assertEquals('access', $type);
    }

    /**
     * Test isTokenExpired returns false for fresh token
     */
    public function testIsTokenExpiredReturnsFalseForFreshToken(): void
    {
        $tokens = $this->service->generateTokens(1, []);

        $isExpired = $this->service->isTokenExpired($tokens['access_token']);

        $this->assertFalse($isExpired);
    }
}
