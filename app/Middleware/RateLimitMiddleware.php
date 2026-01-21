<?php
declare(strict_types=1);

namespace EduCRM\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Rate Limiting Middleware
 * 
 * Phase 2: API-First Architecture
 * Limits API requests per IP address to prevent abuse.
 * 
 * @package EduCRM\Middleware
 * @version 2.0.0
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $windowSeconds;
    private string $cacheDir;

    public function __construct(int $maxRequests = 60, int $windowSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->cacheDir = dirname(__DIR__, 2) . '/storage/rate_limits';

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Process the request through rate limiting
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $ip = $this->getClientIp($request);
        $key = md5($ip);
        $cacheFile = $this->cacheDir . '/' . $key . '.json';

        // Get current request count
        $data = $this->getCacheData($cacheFile);

        // Check if rate limit exceeded
        if ($data['count'] >= $this->maxRequests) {
            $retryAfter = $data['expires'] - time();
            return $this->tooManyRequestsResponse($retryAfter);
        }

        // Increment request count
        $this->incrementCount($cacheFile, $data);

        // Process request
        $response = $handler->handle($request);

        // Add rate limit headers
        $remaining = max(0, $this->maxRequests - ($data['count'] + 1));
        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining)
            ->withHeader('X-RateLimit-Reset', (string) $data['expires']);
    }

    /**
     * Get client IP address
     */
    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();

        // Check for forwarded IP
        if (isset($serverParams['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $serverParams['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        return $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get cached rate limit data
     */
    private function getCacheData(string $cacheFile): array
    {
        if (!file_exists($cacheFile)) {
            return [
                'count' => 0,
                'expires' => time() + $this->windowSeconds
            ];
        }

        $data = json_decode(file_get_contents($cacheFile), true);

        // Check if window has expired
        if (time() > $data['expires']) {
            return [
                'count' => 0,
                'expires' => time() + $this->windowSeconds
            ];
        }

        return $data;
    }

    /**
     * Increment request count
     */
    private function incrementCount(string $cacheFile, array $data): void
    {
        $data['count']++;
        file_put_contents($cacheFile, json_encode($data));
    }

    /**
     * Generate 429 Too Many Requests response
     */
    private function tooManyRequestsResponse(int $retryAfter): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => [
                'message' => 'Rate limit exceeded. Please try again later.',
                'code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $retryAfter
            ]
        ], JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Retry-After', (string) $retryAfter)
            ->withStatus(429);
    }
}
