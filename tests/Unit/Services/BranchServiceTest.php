<?php
declare(strict_types=1);

namespace EduCRM\Tests\Unit\Services;

use EduCRM\Tests\TestCase;
use EduCRM\Services\BranchService;

/**
 * Unit Tests for BranchService
 */
class BranchServiceTest extends TestCase
{
    private BranchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BranchService($this->getPdo());
    }

    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(BranchService::class, $this->service);
    }

    public function testGetAllReturnsArray(): void
    {
        $branches = $this->service->getAll();
        $this->assertIsArray($branches);
    }

    public function testGetByIdReturnsArrayOrNull(): void
    {
        $branch = $this->service->getById(999999);
        $this->assertTrue($branch === null || is_array($branch));
    }

    public function testGetActiveReturnsArray(): void
    {
        $branches = $this->service->getActive();
        $this->assertIsArray($branches);
    }
}
