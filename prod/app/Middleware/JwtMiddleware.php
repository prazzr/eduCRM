<?php
declare(strict_types=1);

namespace EduCRM\Middleware;

use EduCRM\Services\JwtService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * JWT Authentication Middleware
 * 
 * Phase 2: API-First Architecture
 * Validates JWT tokens for protected API routes.
 * 
 * @package EduCRM\Middleware
 * @version 2.0.0
 */
class JwtMiddleware implements MiddlewareInterface
{
    private JwtService $jwtService;

    public function __construct()
    {
        $this->jwtService = new JwtService();
    }

    /**
     * Process the request through the middleware
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        // Check for authorization header
        if (empty($authHeader)) {
            return $this->unauthorizedResponse('Authorization header required');
        }

        // Check Bearer token format
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->unauthorizedResponse('Invalid authorization format. Use: Bearer <token>');
        }

        $token = $matches[1];

        // Validate token
        $payload = $this->jwtService->validateToken($token);

        if (!$payload) {
            return $this->unauthorizedResponse('Invalid or expired token');
        }

        // Check token type (must be access token)
        if (($payload->type ?? '') !== 'access') {
            return $this->unauthorizedResponse('Invalid token type. Use access token.');
        }

        // Add user info to request attributes for use in controllers
        $request = $request->withAttribute('user_id', $payload->sub);
        $request = $request->withAttribute('user_roles', $payload->roles ?? []);
        $request = $request->withAttribute('token_payload', $payload);

        return $handler->handle($request);
    }

    /**
     * Generate unauthorized response
     */
    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => 'UNAUTHORIZED'
            ]
        ], JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('WWW-Authenticate', 'Bearer realm="api"')
            ->withStatus(401);
    }
}
