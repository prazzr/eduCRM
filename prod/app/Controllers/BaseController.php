<?php
declare(strict_types=1);

namespace EduCRM\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Base Controller for EduCRM
 * 
 * Provides common response helpers for JSON and HTML responses.
 * All controllers should extend this class.
 * 
 * @package EduCRM\Controllers
 * @version 1.0.0 (Phase 1)
 */
abstract class BaseController
{
    /**
     * Return JSON response
     * 
     * @param Response $response PSR-7 response object
     * @param array $data Data to encode as JSON
     * @param int $status HTTP status code
     * @return Response
     */
    protected function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * Return success response
     * 
     * @param Response $response PSR-7 response object
     * @param mixed $data Response data
     * @param string $message Success message
     * @return Response
     */
    protected function success(Response $response, $data = null, string $message = 'Success'): Response
    {
        return $this->json($response, [
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Return error response
     * 
     * @param Response $response PSR-7 response object
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param string|null $code Error code
     * @return Response
     */
    protected function error(Response $response, string $message, int $status = 400, ?string $code = null): Response
    {
        return $this->json($response, [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code ?? 'ERROR_' . $status
            ]
        ], $status);
    }

    /**
     * Return paginated response
     * 
     * @param Response $response PSR-7 response object
     * @param array $items Items for current page
     * @param int $total Total items count
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @return Response
     */
    protected function paginated(Response $response, array $items, int $total, int $page = 1, int $perPage = 20): Response
    {
        return $this->json($response, [
            'success' => true,
            'data' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage)
            ]
        ]);
    }

    /**
     * Get authenticated user ID from session
     * 
     * @return int|null
     */
    protected function getUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Check if user is authenticated
     * 
     * @return bool
     */
    protected function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']);
    }
}
