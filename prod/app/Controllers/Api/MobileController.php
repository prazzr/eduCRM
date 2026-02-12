<?php
declare(strict_types=1);

namespace EduCRM\Controllers\Api;

use EduCRM\Controllers\BaseController;
use EduCRM\Database\Eloquent;
use EduCRM\Models\User;
use EduCRM\Models\Task;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Mobile Controller
 * 
 * Phase 3: Mobile Readiness
 * Provides mobile-optimized endpoints returning combined data.
 * 
 * @package EduCRM\Controllers\Api
 * @version 3.0.0
 */
class MobileController extends BaseController
{
    /**
     * Mobile Dashboard - Combined data endpoint
     * 
     * GET /api/v2/mobile/dashboard
     * Returns: user info, tasks, stats in a single request
     */
    public function dashboard(Request $request, Response $response): Response
    {
        Eloquent::boot();

        $userId = $request->getAttribute('user_id');
        $user = User::with('roles', 'branch')->find($userId);

        if (!$user) {
            return $this->error($response, 'User not found', 404);
        }

        // Get pending tasks
        $tasks = Task::where('assigned_to', $userId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('due_date')
            ->limit(10)
            ->get(['id', 'title', 'priority', 'status', 'due_date']);

        // Get overdue tasks count
        $overdueCount = Task::where('assigned_to', $userId)
            ->where('status', '!=', 'completed')
            ->where('due_date', '<', now())
            ->count();

        // Get today's tasks
        $todayTasks = Task::where('assigned_to', $userId)
            ->whereDate('due_date', today())
            ->whereIn('status', ['pending', 'in_progress'])
            ->get(['id', 'title', 'priority']);

        return $this->success($response, [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'branch' => $user->branch ? $user->branch->name : null
            ],
            'stats' => [
                'pending_tasks' => $tasks->count(),
                'overdue_tasks' => $overdueCount,
                'today_tasks' => $todayTasks->count()
            ],
            'tasks' => $tasks,
            'today' => $todayTasks,
            'last_sync' => now()->toIso8601String()
        ]);
    }

    /**
     * Sync endpoint - Get updated data since timestamp
     * 
     * GET /api/v2/mobile/sync?since=2026-01-01T00:00:00Z
     */
    public function sync(Request $request, Response $response): Response
    {
        Eloquent::boot();

        $userId = $request->getAttribute('user_id');
        $since = $request->getQueryParams()['since'] ?? null;

        $query = Task::where(function ($q) use ($userId) {
            $q->where('assigned_to', $userId)
                ->orWhere('created_by', $userId);
        });

        if ($since) {
            $query->where('updated_at', '>=', $since);
        }

        $tasks = $query->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get();

        return $this->success($response, [
            'tasks' => $tasks,
            'sync_timestamp' => now()->toIso8601String(),
            'has_more' => $tasks->count() === 50
        ]);
    }

    /**
     * Quick actions endpoint - Get available quick actions
     * 
     * GET /api/v2/mobile/actions
     */
    public function actions(Request $request, Response $response): Response
    {
        $roles = $request->getAttribute('user_roles') ?? [];

        $actions = [
            ['id' => 'view_tasks', 'label' => 'My Tasks', 'icon' => 'tasks'],
            ['id' => 'view_calendar', 'label' => 'Calendar', 'icon' => 'calendar']
        ];

        if (in_array('admin', $roles) || in_array('counselor', $roles)) {
            $actions[] = ['id' => 'new_inquiry', 'label' => 'New Inquiry', 'icon' => 'plus'];
            $actions[] = ['id' => 'view_students', 'label' => 'Students', 'icon' => 'users'];
        }

        if (in_array('teacher', $roles)) {
            $actions[] = ['id' => 'take_attendance', 'label' => 'Attendance', 'icon' => 'check'];
            $actions[] = ['id' => 'grade_tasks', 'label' => 'Grade Tasks', 'icon' => 'edit'];
        }

        return $this->success($response, ['actions' => $actions]);
    }
}
