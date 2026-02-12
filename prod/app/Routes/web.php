<?php
declare(strict_types=1);

/**
 * Web Routes (Future)
 * 
 * This file will contain web routes for HTML page rendering.
 * Currently, legacy module access continues via direct file access.
 * 
 * Phase 1: Placeholder for future migration
 * Phase 4: Full Laravel migration will move all modules here
 * 
 * @package EduCRM\Routes
 * @version 1.0.0 (Phase 1)
 */

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** @var App $app */

// ============================================================================
// WEB ROUTES (Phase 1 - Minimal)
// ============================================================================

// Root redirect to legacy dashboard
$app->get('/', function (Request $request, Response $response) {
    // Redirect to legacy dashboard for now
    return $response
        ->withHeader('Location', '../index.php')
        ->withStatus(302);
});

// ============================================================================
// FUTURE: Phase 4 - Laravel Migration
// ============================================================================
// 
// These routes will be implemented during the Laravel migration:
// 
// $app->get('/dashboard', [DashboardController::class, 'index']);
// $app->get('/students', [StudentController::class, 'index']);
// $app->get('/students/{id}', [StudentController::class, 'show']);
// $app->get('/tasks', [TaskController::class, 'index']);
// $app->get('/inquiries', [InquiryController::class, 'index']);
// 
// For now, these continue to work via direct module access:
// /modules/students/list.php
// /modules/tasks/list.php
// /modules/inquiries/list.php
