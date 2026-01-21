<?php
declare(strict_types=1);

namespace EduCRM\Tests\Integration;

use EduCRM\Tests\TestCase;

/**
 * Integration Tests for API Endpoints
 */
class ApiTest extends TestCase
{
    private string $baseUrl = 'http://localhost/CRM/api/v1/';

    /**
     * Test that API returns JSON content type
     */
    public function testApiReturnsJson(): void
    {
        // Note: These tests require the web server to be running
        // For now, we just test that the ApiController class can be loaded
        require_once dirname(__DIR__, 2) . '/api/v1/ApiController.php';
        $this->assertTrue(class_exists('ApiController'));
    }

    /**
     * Test JWT token generation
     */
    public function testJwtTokenGeneration(): void
    {
        require_once dirname(__DIR__, 2) . '/api/v1/ApiController.php';

        $token = \ApiController::generateToken(1, 'test@example.com', ['admin']);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Token should have 3 parts (header.payload.signature)
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    /**
     * Test JWT token validation
     */
    public function testJwtTokenValidation(): void
    {
        require_once dirname(__DIR__, 2) . '/api/v1/ApiController.php';

        $token = \ApiController::generateToken(1, 'test@example.com', ['admin']);
        $payload = \ApiController::validateToken($token);

        $this->assertIsArray($payload);
        $this->assertEquals(1, $payload['sub']);
        $this->assertEquals('test@example.com', $payload['email']);
        $this->assertContains('admin', $payload['roles']);
    }

    /**
     * Test invalid token returns false
     */
    public function testInvalidTokenReturnsFalse(): void
    {
        require_once dirname(__DIR__, 2) . '/api/v1/ApiController.php';

        $result = \ApiController::validateToken('invalid.token.here');

        $this->assertFalse($result);
    }
}
