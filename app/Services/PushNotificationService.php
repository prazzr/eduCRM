<?php
declare(strict_types=1);

namespace EduCRM\Services;

use EduCRM\Models\Device;
use EduCRM\Database\Eloquent;

/**
 * Push Notification Service
 * 
 * Phase 3: Mobile Readiness
 * Handles push notifications via self-hosted ntfy server.
 * 
 * ntfy is a simple HTTP-based pub-sub notification service that allows
 * sending notifications to mobile apps, desktop clients, and web browsers
 * without relying on third-party services like Firebase.
 * 
 * @see https://ntfy.sh/docs/
 * @package EduCRM\Services
 * @version 3.1.0
 */
class PushNotificationService
{
    private string $ntfyUrl;
    private ?string $accessToken;
    private string $topicPrefix;
    private bool $enabled;

    /**
     * Priority levels for ntfy notifications
     */
    public const PRIORITY_MIN = 1;
    public const PRIORITY_LOW = 2;
    public const PRIORITY_DEFAULT = 3;
    public const PRIORITY_HIGH = 4;
    public const PRIORITY_URGENT = 5;

    /**
     * Initialize the push notification service
     * 
     * @param string|null $ntfyUrl Optional ntfy server URL override
     */
    public function __construct(?string $ntfyUrl = null)
    {
        $this->ntfyUrl = $ntfyUrl ?? $_ENV['NTFY_URL'] ?? 'http://localhost:8090';
        $this->accessToken = $_ENV['NTFY_ACCESS_TOKEN'] ?? null;
        $this->topicPrefix = $_ENV['NTFY_TOPIC_PREFIX'] ?? 'educrm';
        $this->enabled = !empty($this->ntfyUrl);
    }

    /**
     * Check if push notifications are configured
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get the ntfy server URL
     */
    public function getServerUrl(): string
    {
        return $this->ntfyUrl;
    }

    /**
     * Get the topic prefix
     */
    public function getTopicPrefix(): string
    {
        return $this->topicPrefix;
    }

    /**
     * Generate a topic name for a user
     * 
     * @param int $userId User ID
     * @return string Topic name (e.g., "educrm-user-123")
     */
    public function getUserTopic(int $userId): string
    {
        return $this->topicPrefix . '-user-' . $userId;
    }

    /**
     * Send push notification to a topic
     * 
     * @param string $topic Topic name (e.g., "educrm-user-123")
     * @param string $title Notification title
     * @param string $body Notification body/message
     * @param array $options Additional options:
     *   - priority: int (1-5, default 3)
     *   - tags: array of emoji tags (e.g., ['warning', 'skull'])
     *   - click: string URL to open when notification is clicked
     *   - actions: array of action buttons
     *   - attach: string URL of attachment
     *   - icon: string URL of notification icon
     * @return array Response with 'success', 'http_code', and 'response' keys
     */
    public function send(string $topic, string $title, string $body, array $options = []): array
    {
        if (!$this->enabled) {
            return [
                'success' => false,
                'error' => 'ntfy not configured. Set NTFY_URL in .env',
                'simulated' => true
            ];
        }

        $ch = curl_init();

        $headers = [
            'Content-Type: text/plain',
            'Title: ' . $this->sanitizeHeader($title),
        ];

        // Priority (1=min, 2=low, 3=default, 4=high, 5=urgent)
        if (isset($options['priority'])) {
            $priority = max(1, min(5, (int) $options['priority']));
            $headers[] = 'Priority: ' . $priority;
        }

        // Tags/Emojis (comma-separated)
        if (!empty($options['tags'])) {
            $tags = is_array($options['tags']) ? implode(',', $options['tags']) : $options['tags'];
            $headers[] = 'Tags: ' . $this->sanitizeHeader($tags);
        }

        // Click action URL
        if (!empty($options['click'])) {
            $headers[] = 'Click: ' . $options['click'];
        }

        // Attachment URL
        if (!empty($options['attach'])) {
            $headers[] = 'Attach: ' . $options['attach'];
        }

        // Icon URL
        if (!empty($options['icon'])) {
            $headers[] = 'Icon: ' . $options['icon'];
        }

        // Actions (for interactive notifications)
        if (!empty($options['actions'])) {
            $headers[] = 'Actions: ' . $this->formatActions($options['actions']);
        }

        // Access token for authenticated ntfy server
        if ($this->accessToken) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($this->ntfyUrl, '/') . '/' . $topic,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response' => is_string($response) ? (json_decode($response, true) ?? $response) : null
        ];
    }

    /**
     * Send notification to all devices of a user
     * 
     * @param int $userId User ID
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $options Additional options
     * @return array Results
     */
    public function sendToUser(int $userId, string $title, string $body, array $options = []): array
    {
        $topic = $this->getUserTopic($userId);
        return $this->send($topic, $title, $body, $options);
    }

    /**
     * Send notification to multiple users
     * 
     * @param array $userIds Array of user IDs
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $options Additional options
     * @return array Results for each user
     */
    public function sendToMultipleUsers(array $userIds, string $title, string $body, array $options = []): array
    {
        $results = [];
        foreach ($userIds as $userId) {
            $results[$userId] = $this->sendToUser($userId, $title, $body, $options);
        }
        return ['success' => true, 'results' => $results];
    }

    /**
     * Send notification for task assignment
     * 
     * @param int $userId User ID
     * @param string $taskTitle Task title
     * @param int $taskId Task ID
     * @return array Response
     */
    public function sendTaskNotification(int $userId, string $taskTitle, int $taskId): array
    {
        return $this->sendToUser(
            $userId,
            'New Task Assigned',
            "You have been assigned: {$taskTitle}",
            [
                'priority' => self::PRIORITY_HIGH,
                'tags' => ['clipboard', 'task'],
                'click' => ($_ENV['APP_URL'] ?? 'http://localhost/CRM/') . 'modules/tasks/view.php?id=' . $taskId
            ]
        );
    }

    /**
     * Send notification for appointment reminder
     * 
     * @param int $userId User ID
     * @param string $clientName Client name
     * @param string $time Appointment time
     * @param int|null $appointmentId Optional appointment ID for click action
     * @return array Response
     */
    public function sendAppointmentReminder(int $userId, string $clientName, string $time, ?int $appointmentId = null): array
    {
        $options = [
            'priority' => self::PRIORITY_HIGH,
            'tags' => ['calendar', 'bell']
        ];

        if ($appointmentId) {
            $options['click'] = ($_ENV['APP_URL'] ?? 'http://localhost/CRM/') . 'modules/appointments/view.php?id=' . $appointmentId;
        }

        return $this->sendToUser(
            $userId,
            'Upcoming Appointment',
            "Appointment with {$clientName} at {$time}",
            $options
        );
    }

    /**
     * Send notification for new inquiry
     * 
     * @param int $userId User ID to notify
     * @param string $inquiryName Name from inquiry
     * @param int $inquiryId Inquiry ID
     * @return array Response
     */
    public function sendInquiryNotification(int $userId, string $inquiryName, int $inquiryId): array
    {
        return $this->sendToUser(
            $userId,
            'New Inquiry Received',
            "New inquiry from: {$inquiryName}",
            [
                'priority' => self::PRIORITY_DEFAULT,
                'tags' => ['inbox_tray', 'new'],
                'click' => ($_ENV['APP_URL'] ?? 'http://localhost/CRM/') . 'modules/inquiries/view.php?id=' . $inquiryId
            ]
        );
    }

    /**
     * Send a test notification
     * 
     * @param string $topic Topic to send to
     * @return array Response
     */
    public function sendTestNotification(string $topic = 'test'): array
    {
        return $this->send(
            $this->topicPrefix . '-' . $topic,
            'EduCRM Test Notification',
            'This is a test notification from EduCRM. If you see this, ntfy is working correctly!',
            [
                'priority' => self::PRIORITY_DEFAULT,
                'tags' => ['white_check_mark', 'test']
            ]
        );
    }

    /**
     * Sanitize header value to prevent injection
     */
    private function sanitizeHeader(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }

    /**
     * Format action buttons for ntfy
     * 
     * @param array $actions Array of actions, each with 'action', 'label', 'url', etc.
     * @return string Formatted actions string
     */
    private function formatActions(array $actions): string
    {
        $formatted = [];
        foreach ($actions as $action) {
            if (isset($action['action'], $action['label'])) {
                $parts = [$action['action'], $action['label']];
                if (isset($action['url'])) {
                    $parts[] = $action['url'];
                }
                $formatted[] = implode(', ', $parts);
            }
        }
        return implode('; ', $formatted);
    }
}
