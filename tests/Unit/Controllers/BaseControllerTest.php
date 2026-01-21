<?php
declare(strict_types=1);

namespace EduCRM\Tests\Unit\Controllers;

use EduCRM\Tests\TestCase;
use EduCRM\Controllers\BaseController;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Unit Tests for BaseController
 * 
 * Tests the base controller helper methods.
 */
class BaseControllerTest extends TestCase
{
    private TestableBaseController $controller;
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new TestableBaseController();
        $this->responseFactory = new ResponseFactory();
    }

    /**
     * Test JSON response has correct content type
     */
    public function testJsonResponseHasCorrectContentType(): void
    {
        $response = $this->responseFactory->createResponse();
        $result = $this->controller->testJson($response, ['key' => 'value']);

        $this->assertEquals('application/json', $result->getHeaderLine('Content-Type'));
    }

    /**
     * Test JSON response has correct status code
     */
    public function testJsonResponseHasCorrectStatusCode(): void
    {
        $response = $this->responseFactory->createResponse();
        $result = $this->controller->testJson($response, ['key' => 'value'], 201);

        $this->assertEquals(201, $result->getStatusCode());
    }

    /**
     * Test success response structure
     */
    public function testSuccessResponseStructure(): void
    {
        $response = $this->responseFactory->createResponse();
        $result = $this->controller->testSuccess($response, ['id' => 1]);

        $body = json_decode((string) $result->getBody(), true);

        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('message', $body);
    }

    /**
     * Test error response structure
     */
    public function testErrorResponseStructure(): void
    {
        $response = $this->responseFactory->createResponse();
        $result = $this->controller->testError($response, 'Something went wrong', 400);

        $body = json_decode((string) $result->getBody(), true);

        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('error', $body);
        $this->assertEquals('Something went wrong', $body['error']['message']);
        $this->assertEquals(400, $result->getStatusCode());
    }
}

/**
 * Testable version of BaseController that exposes protected methods
 */
class TestableBaseController extends BaseController
{
    public function testJson($response, array $data, int $status = 200)
    {
        return $this->json($response, $data, $status);
    }

    public function testSuccess($response, $data = null, string $message = 'Success')
    {
        return $this->success($response, $data, $message);
    }

    public function testError($response, string $message, int $status = 400)
    {
        return $this->error($response, $message, $status);
    }
}
