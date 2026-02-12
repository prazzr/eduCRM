<?php
declare(strict_types=1);

namespace EduCRM\Controllers\Api;

use EduCRM\Controllers\BaseController;
use EduCRM\Database\Eloquent;
use EduCRM\Models\Device;
use EduCRM\Services\PushNotificationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Device Controller
 * 
 * Phase 3: Mobile Readiness
 * Handles mobile device registration for push notifications via ntfy.
 * 
 * @package EduCRM\Controllers\Api
 * @version 3.1.0
 */
class DeviceController extends BaseController
{
    private PushNotificationService $pushService;

    public function __construct()
    {
        $this->pushService = new PushNotificationService();
    }

    /**
     * Register a device for push notifications
     * 
     * POST /api/v2/devices/register
     * Body: { "device_type": "ntfy_android|ntfy_ios|ntfy_web", "device_name": "..." }
     * 
     * Returns the ntfy topic and server URL for the client to subscribe to.
     */
    public function register(Request $request, Response $response): Response
    {
        Eloquent::boot();

        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        // Generate unique topic for this user
        $topic = $this->pushService->getUserTopic($userId);

        // Determine device type
        $deviceType = $data['device_type'] ?? 'ntfy_android';
        $validTypes = [
            Device::TYPE_NTFY_ANDROID,
            Device::TYPE_NTFY_IOS,
            Device::TYPE_NTFY_WEB,
            Device::TYPE_ANDROID,
            Device::TYPE_IOS,
            Device::TYPE_WEB
        ];

        if (!in_array($deviceType, $validTypes)) {
            $deviceType = Device::TYPE_NTFY_ANDROID;
        }

        // Deactivate any other devices for this user with same topic (re-registration)
        Device::where('user_id', $userId)
            ->where('ntfy_topic', $topic)
            ->update(['is_active' => false]);

        // Create or update device registration
        $device = Device::updateOrCreate(
            [
                'user_id' => $userId,
                'ntfy_topic' => $topic
            ],
            [
                'device_type' => $deviceType,
                'device_name' => $data['device_name'] ?? 'Mobile Device',
                'app_version' => $data['app_version'] ?? '1.0.0',
                'is_active' => true,
                'last_active_at' => now()
            ]
        );

        return $this->success($response, [
            'device_id' => $device->id,
            'ntfy_topic' => $topic,
            'ntfy_url' => $this->pushService->getServerUrl(),
            'subscription_url' => $device->getSubscriptionUrl(),
            'message' => 'Device registered successfully. Subscribe to the topic in your ntfy app to receive notifications.'
        ]);
    }

    /**
     * Unregister a device (e.g., on logout)
     * 
     * DELETE /api/v2/devices/{topic}
     */
    public function unregister(Request $request, Response $response, array $args): Response
    {
        Eloquent::boot();

        $userId = $request->getAttribute('user_id');
        $topic = $args['topic'] ?? '';

        $device = Device::where('user_id', $userId)
            ->where('ntfy_topic', $topic)
            ->first();

        if ($device) {
            $device->deactivate();
            return $this->success($response, ['message' => 'Device unregistered successfully']);
        }

        return $this->error($response, 'Device not found', 404);
    }

    /**
     * List user's registered devices
     * 
     * GET /api/v2/devices
     */
    public function list(Request $request, Response $response): Response
    {
        Eloquent::boot();

        $userId = $request->getAttribute('user_id');

        $devices = Device::where('user_id', $userId)
            ->where('is_active', true)
            ->get(['id', 'ntfy_topic', 'device_type', 'device_name', 'app_version', 'last_active_at']);

        // Add subscription URLs
        $devicesArray = $devices->map(function ($device) {
            $deviceData = $device->toArray();
            $deviceData['subscription_url'] = $device->getSubscriptionUrl();
            return $deviceData;
        });

        return $this->success($response, $devicesArray);
    }

    /**
     * Deactivate all devices (e.g., for security logout)
     * 
     * POST /api/v2/devices/logout-all
     */
    public function logoutAll(Request $request, Response $response): Response
    {
        Eloquent::boot();

        $userId = $request->getAttribute('user_id');

        $count = Device::where('user_id', $userId)->update(['is_active' => false]);

        return $this->success($response, [
            'message' => 'All devices logged out',
            'devices_affected' => $count
        ]);
    }

    /**
     * Send a test notification to the user
     * 
     * POST /api/v2/devices/test-notification
     */
    public function sendTestNotification(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        if (!$this->pushService->isEnabled()) {
            return $this->error($response, 'Push notifications are not configured', 503);
        }

        $result = $this->pushService->sendToUser(
            $userId,
            'Test Notification',
            'This is a test notification from EduCRM. If you see this, everything is working!',
            [
                'priority' => PushNotificationService::PRIORITY_DEFAULT,
                'tags' => ['white_check_mark', 'bell']
            ]
        );

        if ($result['success']) {
            return $this->success($response, [
                'message' => 'Test notification sent successfully',
                'topic' => $this->pushService->getUserTopic($userId)
            ]);
        }

        return $this->error($response, 'Failed to send notification: ' . ($result['error'] ?? 'Unknown error'), 500);
    }

    /**
     * Get ntfy configuration for client apps
     * 
     * GET /api/v2/devices/config
     */
    public function getConfig(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        return $this->success($response, [
            'ntfy_enabled' => $this->pushService->isEnabled(),
            'ntfy_url' => $this->pushService->getServerUrl(),
            'user_topic' => $this->pushService->getUserTopic($userId),
            'subscription_url' => $this->pushService->getServerUrl() . '/' . $this->pushService->getUserTopic($userId)
        ]);
    }
}
