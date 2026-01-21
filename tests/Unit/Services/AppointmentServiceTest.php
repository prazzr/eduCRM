<?php
declare(strict_types=1);

namespace EduCRM\Tests\Unit\Services;

use EduCRM\Tests\TestCase;
use EduCRM\Services\AppointmentService;

/**
 * Unit Tests for AppointmentService
 */
class AppointmentServiceTest extends TestCase
{
    private AppointmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AppointmentService($this->getPdo());
    }

    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(AppointmentService::class, $this->service);
    }

    public function testGetAllReturnsArray(): void
    {
        $appointments = $this->service->getAll();
        $this->assertIsArray($appointments);
    }

    public function testGetByIdReturnsArrayOrNull(): void
    {
        $appointment = $this->service->getById(999999);
        $this->assertTrue($appointment === null || is_array($appointment));
    }

    public function testGetUpcomingAppointmentsReturnsArray(): void
    {
        $appointments = $this->service->getUpcoming(1);
        $this->assertIsArray($appointments);
    }

    public function testGetByUserReturnsArray(): void
    {
        $appointments = $this->service->getByUser(1);
        $this->assertIsArray($appointments);
    }

    public function testGetTodaysAppointmentsReturnsArray(): void
    {
        $appointments = $this->service->getTodaysAppointments(1);
        $this->assertIsArray($appointments);
    }
}
