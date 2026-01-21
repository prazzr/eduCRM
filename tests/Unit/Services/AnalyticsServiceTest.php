<?php
declare(strict_types=1);

namespace EduCRM\Tests\Unit\Services;

use EduCRM\Tests\TestCase;
use EduCRM\Services\AnalyticsService;

/**
 * Unit Tests for AnalyticsService
 */
class AnalyticsServiceTest extends TestCase
{
    private AnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AnalyticsService($this->getPdo());
    }

    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(AnalyticsService::class, $this->service);
    }

    public function testGetRealTimeMetricsReturnsArray(): void
    {
        $metrics = $this->service->getRealTimeMetrics();
        $this->assertIsArray($metrics);
    }

    public function testGetGoalProgressReturnsArray(): void
    {
        $goals = $this->service->getGoalProgress();
        $this->assertIsArray($goals);
    }

    public function testGetConversionFunnelReturnsArray(): void
    {
        $funnel = $this->service->getConversionFunnel();
        $this->assertIsArray($funnel);
    }

    public function testTakeSnapshotReturnsBool(): void
    {
        $result = $this->service->takeSnapshot();
        $this->assertIsBool($result);
    }
}
