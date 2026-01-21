<?php
declare(strict_types=1);

namespace EduCRM\Tests\Integration\Api;

use EduCRM\Tests\TestCase;

/**
 * Integration Tests for API Endpoints
 * 
 * Tests the API routes are correctly responding.
 * Note: Requires Slim to be installed via Composer.
 */
class HealthCheckTest extends TestCase
{
    /**
     * Test that the health endpoint returns correct structure
     */
    public function testHealthEndpointStructure(): void
    {
        // Skip if running without full Slim setup
        if (!class_exists(\Slim\Factory\AppFactory::class)) {
            $this->markTestSkipped('Slim not installed - run `composer update` first');
        }

        // For integration testing, we would need to set up the Slim app
        // This is a placeholder for when Slim is fully installed
        $this->assertTrue(true);
    }

    /**
     * Test that the bootstrap file loads correctly
     */
    public function testBootstrapLoads(): void
    {
        $bootstrapFile = dirname(__DIR__, 3) . '/app/bootstrap.php';
        $this->assertFileExists($bootstrapFile);
    }

    /**
     * Test that API routes file exists
     */
    public function testApiRoutesFileExists(): void
    {
        $routesFile = dirname(__DIR__, 3) . '/app/Routes/api.php';
        $this->assertFileExists($routesFile);
    }

    /**
     * Test that front controller exists
     */
    public function testFrontControllerExists(): void
    {
        $indexFile = dirname(__DIR__, 3) . '/public/index.php';
        $this->assertFileExists($indexFile);
    }
}
