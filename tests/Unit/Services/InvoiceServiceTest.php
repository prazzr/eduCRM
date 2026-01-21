<?php
declare(strict_types=1);

namespace EduCRM\Tests\Unit\Services;

use EduCRM\Tests\TestCase;
use EduCRM\Services\InvoiceService;

/**
 * Unit Tests for InvoiceService
 */
class InvoiceServiceTest extends TestCase
{
    private InvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvoiceService($this->getPdo());
    }

    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(InvoiceService::class, $this->service);
    }

    public function testGetCompanyDetailsReturnsArray(): void
    {
        $details = $this->service->getCompanyDetails();
        $this->assertIsArray($details);
        $this->assertArrayHasKey('name', $details);
        $this->assertArrayHasKey('address', $details);
        $this->assertArrayHasKey('email', $details);
    }

    public function testSetCompanyDetailsUpdatesDetails(): void
    {
        $newName = 'Test Company';
        $this->service->setCompanyDetails(['name' => $newName]);
        $details = $this->service->getCompanyDetails();
        $this->assertEquals($newName, $details['name']);
    }

    public function testGenerateInvoiceReturnsNullForInvalidId(): void
    {
        $invoice = $this->service->generateInvoice(999999);
        $this->assertNull($invoice);
    }

    public function testGetStudentInvoicesReturnsArray(): void
    {
        $invoices = $this->service->getStudentInvoices(1);
        $this->assertIsArray($invoices);
    }
}
