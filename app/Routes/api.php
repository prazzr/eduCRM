<?php
declare(strict_types=1);

/**
 * API Routes - Version 1 & 2
 * 
 * v1: Migrated from legacy api/v1/ endpoints (Session-based)
 * v2: New mobile-first API with JWT auth (Phase 2)
 * 
 * @package EduCRM\Routes
 * @version 2.0.0 (Phase 2)
 */

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use EduCRM\Controllers\Api\DashboardController;
use EduCRM\Controllers\Api\AuthController;
use EduCRM\Middleware\JwtMiddleware;
use EduCRM\Middleware\RateLimitMiddleware;
use EduCRM\Database\Eloquent;
use EduCRM\Models\User;
use EduCRM\Models\Task;

/** @var App $app */

// ============================================================================
// API v1 Routes (Legacy - Session Based)
// ============================================================================
$app->group('/api/v1', function (RouteCollectorProxy $group) {

    // Health check
    $group->get('/health', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode([
            'status' => 'healthy',
            'version' => '1.0.0',
            'timestamp' => date('c'),
            'phase' => 'Phase 1 - Foundation'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Dashboard endpoints
    $group->group('/dashboard', function (RouteCollectorProxy $dashboard) {
        global $pdo;

        $dashboard->get('/stats', function (Request $request, Response $response) use ($pdo) {
            $controller = new DashboardController($pdo);
            return $controller->stats($request, $response);
        });

        $dashboard->get('/tasks', function (Request $request, Response $response) use ($pdo) {
            $controller = new DashboardController($pdo);
            return $controller->recentTasks($request, $response);
        });

        $dashboard->get('/appointments', function (Request $request, Response $response) use ($pdo) {
            $controller = new DashboardController($pdo);
            return $controller->upcomingAppointments($request, $response);
        });
    });

    // Priority counts endpoint (legacy compatibility)
    $group->get('/priority-counts', function (Request $request, Response $response) {
        global $pdo;
        $controller = new DashboardController($pdo);
        return $controller->priorityCounts($request, $response);
    });

});

// ============================================================================
// API v2 Routes (JWT Protected - Phase 2)
// ============================================================================
$app->group('/api/v2', function (RouteCollectorProxy $group) {

    // Health check (public)
    $group->get('/health', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode([
            'status' => 'healthy',
            'version' => '2.0.0',
            'timestamp' => date('c'),
            'phase' => 'Phase 2 - API First',
            'auth' => 'JWT'
        ], JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // ========================================================================
    // PUBLIC ROUTES (No auth required)
    // ========================================================================

    // Login
    $group->post('/auth/login', function (Request $request, Response $response) {
        $controller = new AuthController();
        return $controller->login($request, $response);
    });

    // Refresh token
    $group->post('/auth/refresh', function (Request $request, Response $response) {
        $controller = new AuthController();
        return $controller->refresh($request, $response);
    });

    // ========================================================================
    // PROTECTED ROUTES (JWT required)
    // ========================================================================
    $group->group('', function (RouteCollectorProxy $protected) {

        // Current user info
        $protected->get('/auth/me', function (Request $request, Response $response) {
            $controller = new AuthController();
            return $controller->me($request, $response);
        });

        // Students endpoints
        $protected->get('/students', function (Request $request, Response $response) {
            Eloquent::boot();

            $students = User::whereHas('roles', function ($q) {
                $q->where('name', 'student');
            })->with('branch')->get();

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $students->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'name' => $s->name,
                        'email' => $s->email,
                        'phone' => $s->phone,
                        'branch' => $s->branch ? $s->branch->name : null
                    ];
                })
            ], JSON_PRETTY_PRINT));

            return $response->withHeader('Content-Type', 'application/json');
        });

        // Tasks endpoints
        $protected->get('/tasks', function (Request $request, Response $response) {
            Eloquent::boot();
            $userId = $request->getAttribute('user_id');

            $tasks = Task::where('assigned_to', $userId)
                ->orWhere('created_by', $userId)
                ->orderBy('due_date')
                ->limit(20)
                ->get();

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $tasks
            ], JSON_PRETTY_PRINT));

            return $response->withHeader('Content-Type', 'application/json');
        });

        // ====================================================================
        // DEVICE REGISTRATION (Phase 3)
        // ====================================================================
        $protected->post('/devices/register', function (Request $request, Response $response) {
            $controller = new \EduCRM\Controllers\Api\DeviceController();
            return $controller->register($request, $response);
        });

        $protected->get('/devices', function (Request $request, Response $response) {
            $controller = new \EduCRM\Controllers\Api\DeviceController();
            return $controller->list($request, $response);
        });

        $protected->delete('/devices/{token}', function (Request $request, Response $response, array $args) {
            $controller = new \EduCRM\Controllers\Api\DeviceController();
            return $controller->unregister($request, $response, $args);
        });

        $protected->post('/devices/logout-all', function (Request $request, Response $response) {
            $controller = new \EduCRM\Controllers\Api\DeviceController();
            return $controller->logoutAll($request, $response);
        });

        // ====================================================================
        // MOBILE ENDPOINTS (Phase 3)
        // ====================================================================
        $protected->get('/mobile/dashboard', function (Request $request, Response $response) {
            $controller = new \EduCRM\Controllers\Api\MobileController();
            return $controller->dashboard($request, $response);
        });

        $protected->get('/mobile/sync', function (Request $request, Response $response) {
            $controller = new \EduCRM\Controllers\Api\MobileController();
            return $controller->sync($request, $response);
        });

        $protected->get('/mobile/actions', function (Request $request, Response $response) {
            $controller = new \EduCRM\Controllers\Api\MobileController();
            return $controller->actions($request, $response);
        });

    })->add(new JwtMiddleware());

})->add(new RateLimitMiddleware(60, 60)); // 60 requests per minute

