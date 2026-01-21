<?php
declare(strict_types=1);

/**
 * EduCRM Slim Front Controller
 * 
 * Phase 1: Foundation Strengthening
 * This file serves as the entry point for API routes via Slim Framework.
 * Legacy module access continues to work directly.
 * 
 * @package EduCRM
 * @version 1.0.0
 */

// Bootstrap application (loads autoloader, config, helpers)
require __DIR__ . '/../app/bootstrap.php';

use DI\Container;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// ============================================================================
// CREATE SLIM APPLICATION
// ============================================================================

// Create DI Container
$container = new Container();

// Register PDO in container
$container->set('pdo', function () {
    global $pdo;
    return $pdo;
});

// Set container
AppFactory::setContainer($container);

// Create Slim App
$app = AppFactory::create();

// ============================================================================
// MIDDLEWARE
// ============================================================================

// Add Error Middleware (with full error details in development)
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Add Body Parsing Middleware (for JSON POST bodies)
$app->addBodyParsingMiddleware();

// CORS Middleware (for API access from different origins)
$app->add(function (Request $request, $handler): Response {
    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// Handle OPTIONS preflight requests
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

// ============================================================================
// ROUTES
// ============================================================================

// Health Check (always available)
$app->get('/health', function (Request $request, Response $response) {
    $data = [
        'status' => 'healthy',
        'application' => 'EduCRM',
        'version' => '1.0.0',
        'phase' => 'Phase 1 - Foundation',
        'timestamp' => date('c'),
        'php_version' => PHP_VERSION
    ];

    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// Load API Routes
require __DIR__ . '/../app/Routes/api.php';

// Load Web Routes
require __DIR__ . '/../app/Routes/web.php';

// ============================================================================
// 404 HANDLER
// ============================================================================

// Catch-all route for undefined paths
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode([
        'success' => false,
        'error' => [
            'message' => 'Route not found',
            'code' => 'NOT_FOUND',
            'path' => $request->getUri()->getPath()
        ]
    ], JSON_PRETTY_PRINT));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(404);
});

// ============================================================================
// RUN APPLICATION
// ============================================================================

$app->run();
