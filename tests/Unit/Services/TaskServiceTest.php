<?php
declare(strict_types=1);

namespace EduCRM\Tests\Unit\Services;

use EduCRM\Tests\TestCase;
use EduCRM\Services\TaskService;

/**
 * Unit Tests for TaskService
 * 
 * Tests the task service methods for proper return types and behavior.
 */
class TaskServiceTest extends TestCase
{
    private TaskService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TaskService($this->getPdo());
    }

    /**
     * Test that TaskService can be instantiated
     */
    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(TaskService::class, $this->service);
    }

    /**
     * Test that getAllTasks returns an array
     */
    public function testGetAllTasksReturnsArray(): void
    {
        $tasks = $this->service->getAllTasks();
        $this->assertIsArray($tasks);
    }

    /**
     * Test that getUserTasks returns an array
     */
    public function testGetUserTasksReturnsArray(): void
    {
        $tasks = $this->service->getUserTasks(1);
        $this->assertIsArray($tasks);
    }

    /**
     * Test that getTask returns falsy value for non-existent task
     */
    public function testGetTaskReturnsFalsyForNonExistent(): void
    {
        $task = $this->service->getTask(99999);
        $this->assertEmpty($task);
    }

    /**
     * Test that getPendingTasksCount returns an integer
     */
    public function testGetPendingTasksCountReturnsInteger(): void
    {
        $count = $this->service->getPendingTasksCount(1);
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    /**
     * Test that getTaskStats returns an array
     */
    public function testGetTaskStatsReturnsArray(): void
    {
        $stats = $this->service->getTaskStats();
        $this->assertIsArray($stats);
    }
}
