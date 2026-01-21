<?php
declare(strict_types=1);

namespace EduCRM\Tests\Unit\Services;

use EduCRM\Tests\TestCase;
use EduCRM\Services\DashboardService;

/**
 * Unit Tests for DashboardService
 * 
 * Tests the dashboard service methods for proper return types and behavior.
 */
class DashboardServiceTest extends TestCase
{
    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // DashboardService requires PDO, userId, and role
        $this->service = new DashboardService($this->getPdo(), 1, 'admin');
    }

    /**
     * Test that service can be instantiated
     */
    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(DashboardService::class, $this->service);
    }

    /**
     * Test that getPendingTasksCount returns an integer
     */
    public function testGetPendingTasksCountReturnsInteger(): void
    {
        $count = $this->service->getPendingTasksCount();

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    /**
     * Test that getActiveVisaProcessesCount returns an integer
     */
    public function testGetActiveVisaProcessesCountReturnsInteger(): void
    {
        $count = $this->service->getActiveVisaProcessesCount();

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    /**
     * Test that getLeadPriorityStats returns an array
     */
    public function testGetLeadPriorityStatsReturnsArray(): void
    {
        $stats = $this->service->getLeadPriorityStats();

        $this->assertIsArray($stats);
    }

    /**
     * Test that getRecentTasks returns an array with correct limit
     */
    public function testGetRecentTasksReturnsArrayWithLimit(): void
    {
        $limit = 5;
        $tasks = $this->service->getRecentTasks($limit);

        $this->assertIsArray($tasks);
        $this->assertLessThanOrEqual($limit, count($tasks));
    }

    /**
     * Test that getUpcomingAppointments returns an array
     */
    public function testGetUpcomingAppointmentsReturnsArray(): void
    {
        $appointments = $this->service->getUpcomingAppointments(5);

        $this->assertIsArray($appointments);
    }

    /**
     * Test that getNewInquiriesCount returns a numeric value
     */
    public function testGetNewInquiriesCountReturnsNumeric(): void
    {
        $count = $this->service->getNewInquiriesCount();

        // Service may return string or int, both are valid
        $this->assertIsNumeric($count);
        $this->assertGreaterThanOrEqual(0, (int) $count);
    }
}

