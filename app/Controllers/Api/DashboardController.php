<?php
declare(strict_types=1);

namespace EduCRM\Controllers\Api;

use EduCRM\Controllers\BaseController;
use EduCRM\Services\DashboardService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

/**
 * Dashboard API Controller
 * 
 * Provides API endpoints for dashboard data.
 * Migrated from legacy api/priority_counts.php
 * 
 * @package EduCRM\Controllers\Api
 * @version 1.0.0 (Phase 1)
 */
class DashboardController extends BaseController
{
    private DashboardService $dashboardService;

    public function __construct(PDO $pdo)
    {
        $this->dashboardService = new DashboardService($pdo);
    }

    /**
     * Get dashboard statistics
     * 
     * GET /api/v1/dashboard/stats
     */
    public function stats(Request $request, Response $response): Response
    {
        try {
            $data = [
                'total_students' => $this->dashboardService->getTotalStudentsCount(),
                'pending_tasks' => $this->dashboardService->getPendingTasksCount(),
                'hot_leads' => $this->dashboardService->getHotLeadsCount(),
                'active_visa' => $this->dashboardService->getActiveVisaProcessesCount(),
            ];

            return $this->success($response, $data, 'Dashboard stats retrieved successfully');
        } catch (\Exception $e) {
            return $this->error($response, $e->getMessage(), 500);
        }
    }

    /**
     * Get lead priority statistics
     * 
     * GET /api/v1/priority-counts
     * (Migrated from api/priority_counts.php)
     */
    public function priorityCounts(Request $request, Response $response): Response
    {
        try {
            $stats = $this->dashboardService->getLeadPriorityStats();
            return $this->success($response, $stats);
        } catch (\Exception $e) {
            return $this->error($response, $e->getMessage(), 500);
        }
    }

    /**
     * Get recent tasks for dashboard widget
     * 
     * GET /api/v1/dashboard/tasks
     */
    public function recentTasks(Request $request, Response $response): Response
    {
        try {
            $limit = (int) ($request->getQueryParams()['limit'] ?? 5);
            $tasks = $this->dashboardService->getRecentTasks($limit);
            return $this->success($response, $tasks);
        } catch (\Exception $e) {
            return $this->error($response, $e->getMessage(), 500);
        }
    }

    /**
     * Get upcoming appointments for dashboard widget
     * 
     * GET /api/v1/dashboard/appointments
     */
    public function upcomingAppointments(Request $request, Response $response): Response
    {
        try {
            $limit = (int) ($request->getQueryParams()['limit'] ?? 5);
            $appointments = $this->dashboardService->getUpcomingAppointments($limit);
            return $this->success($response, $appointments);
        } catch (\Exception $e) {
            return $this->error($response, $e->getMessage(), 500);
        }
    }
}
