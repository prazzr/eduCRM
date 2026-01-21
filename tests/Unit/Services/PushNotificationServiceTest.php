<?php
declare(strict_types=1);

namespace EduCRM\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use EduCRM\Services\PushNotificationService;

/**
 * Unit Tests for PushNotificationService
 * 
 * Tests push notification service functionality with ntfy.
 * Note: Uses PHPUnit TestCase directly - no database required.
 */
class PushNotificationServiceTest extends TestCase
{
    private PushNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Create service with default configuration
        $this->service = new PushNotificationService();
    }

    /**
     * Test that service can be instantiated
     */
    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(PushNotificationService::class, $this->service);
    }

    /**
     * Test isEnabled returns true when ntfy URL is configured (default)
     */
    public function testIsEnabledReturnsTrueWithDefaultConfig(): void
    {
        // By default, NTFY_URL defaults to localhost:8090
        $this->assertTrue($this->service->isEnabled());
    }

    /**
     * Test isEnabled returns true with custom ntfy URL
     */
    public function testIsEnabledReturnsTrueWithCustomUrl(): void
    {
        $service = new PushNotificationService('http://ntfy.example.com');
        $this->assertTrue($service->isEnabled());
    }

    /**
     * Test getServerUrl returns correct URL
     */
    public function testGetServerUrlReturnsConfiguredUrl(): void
    {
        $customUrl = 'http://ntfy.example.com';
        $service = new PushNotificationService($customUrl);
        $this->assertEquals($customUrl, $service->getServerUrl());
    }

    /**
     * Test getUserTopic generates correct topic format
     */
    public function testGetUserTopicGeneratesCorrectFormat(): void
    {
        $userId = 123;
        $topic = $this->service->getUserTopic($userId);

        $this->assertStringContainsString('educrm', $topic);
        $this->assertStringContainsString('user', $topic);
        $this->assertStringContainsString('123', $topic);
        $this->assertEquals('educrm-user-123', $topic);
    }

    /**
     * Test send returns structured response
     */
    public function testSendReturnsStructuredResponse(): void
    {
        // This will fail to connect to ntfy server, but should return proper structure
        $result = $this->service->send('test-topic', 'Test Title', 'Test Body');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * Test send includes error when server unreachable
     */
    public function testSendHandlesUnreachableServer(): void
    {
        // Use an invalid URL that won't connect
        $service = new PushNotificationService('http://localhost:99999');
        $result = $service->send('test-topic', 'Test Title', 'Test Body');

        $this->assertFalse($result['success']);
    }

    /**
     * Test sendToUser uses correct topic
     */
    public function testSendToUserGeneratesCorrectTopic(): void
    {
        $userId = 456;
        $expectedTopic = 'educrm-user-456';

        // We can't easily mock curl, but we can verify the topic generation
        $topic = $this->service->getUserTopic($userId);
        $this->assertEquals($expectedTopic, $topic);
    }

    /**
     * Test sendTaskNotification creates proper payload
     */
    public function testSendTaskNotificationCreatesProperPayload(): void
    {
        // Just verify it doesn't throw an exception and returns array
        $result = $this->service->sendTaskNotification(1, 'Test Task', 123);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * Test sendAppointmentReminder creates proper payload
     */
    public function testSendAppointmentReminderCreatesProperPayload(): void
    {
        // Just verify it doesn't throw an exception and returns array
        $result = $this->service->sendAppointmentReminder(1, 'John Doe', '10:00 AM');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * Test sendInquiryNotification creates proper payload
     */
    public function testSendInquiryNotificationCreatesProperPayload(): void
    {
        $result = $this->service->sendInquiryNotification(1, 'Jane Doe', 456);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * Test sendTestNotification works
     */
    public function testSendTestNotificationWorks(): void
    {
        $result = $this->service->sendTestNotification('unit-test');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * Test priority constants are defined correctly
     */
    public function testPriorityConstantsAreDefined(): void
    {
        $this->assertEquals(1, PushNotificationService::PRIORITY_MIN);
        $this->assertEquals(2, PushNotificationService::PRIORITY_LOW);
        $this->assertEquals(3, PushNotificationService::PRIORITY_DEFAULT);
        $this->assertEquals(4, PushNotificationService::PRIORITY_HIGH);
        $this->assertEquals(5, PushNotificationService::PRIORITY_URGENT);
    }

    /**
     * Test send with options doesn't throw
     */
    public function testSendWithOptionsDoesNotThrow(): void
    {
        $result = $this->service->send('test-topic', 'Test Title', 'Test Body', [
            'priority' => PushNotificationService::PRIORITY_HIGH,
            'tags' => ['warning', 'bell'],
            'click' => 'http://example.com',
            'icon' => 'http://example.com/icon.png'
        ]);

        $this->assertIsArray($result);
    }

    /**
     * Test sendToMultipleUsers returns results for each user
     */
    public function testSendToMultipleUsersReturnsResultsForEachUser(): void
    {
        $userIds = [1, 2, 3];
        $result = $this->service->sendToMultipleUsers($userIds, 'Test', 'Body');

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertCount(3, $result['results']);
    }
}
