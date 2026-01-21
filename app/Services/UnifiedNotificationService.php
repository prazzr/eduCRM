<?php

namespace EduCRM\Services;

/**
 * Unified Notification Service
 * Dispatches notifications to appropriate channels (Email/SMS/WhatsApp)
 * based on user preferences and event configuration.
 */
class UnifiedNotificationService
{
    private \PDO $pdo;
    private EmailNotificationService $emailService;
    private $messagingFactory; // Lazy loaded via MessagingFactory

    public function __construct(\PDO $pdo, EmailNotificationService $emailService)
    {
        $this->pdo = $pdo;
        $this->emailService = $emailService;
    }

    /**
     * Dispatch an event to a user
     * 
     * @param string $eventKey Key from notification_events (e.g., 'task_assigned')
     * @param int $userId Recipient User ID
     * @param array $data Data for template variables (e.g., ['task_title' => 'Fix Bug'])
     * @param string|null $overrideRecipient Optional override (e.g., specific email/phone)
     * @return array Status of each channel ['email' => bool, 'sms' => bool]
     */
    public function dispatch(string $eventKey, int $userId, array $data = [], ?string $overrideRecipient = null): array
    {
        // 1. Get Event Config & User Preferences
        $config = $this->getDispatchConfig($eventKey, $userId);
        if (!$config) {
            return ['status' => 'error', 'message' => 'Event or User not found'];
        }

        $user = $config['user'];
        $preferences = $config['preferences'];

        $results = [];

        // 2. Dispatch Email
        if (!empty($preferences['email']) && $preferences['email']['is_enabled']) {
            $emailParams = $data;
            // Inject user specific vars if not present
            if (!isset($emailParams['name']))
                $emailParams['name'] = $user['name'];
            if (!isset($emailParams['email']))
                $emailParams['email'] = $user['email'];

            // We need a way to call EmailService generically for an event
            // Proposal: EmailService->sendNotification($eventKey, $to, $data)
            // For now, let's map common events to their specific methods for backward compatibility
            // OR better, we use the unified render+queue method if available

            // Try to send via generic method if email service supports it, or individual methods
            // For Phase 5, we rely on individual methods or renderTemplate public access?
            // Actually, best approach: expose a generic `sendEventNotification` in EmailService

            // TEMPORARY: Map known events to methods (to start)
            $methodMap = [
                'task_assigned' => 'sendTaskAssignmentNotification',
                'appointment_reminder' => 'sendAppointmentReminder',
                'task_overdue' => 'sendOverdueTaskAlert',
                // others...
            ];

            if (isset($methodMap[$eventKey]) && method_exists($this->emailService, $methodMap[$eventKey])) {
                // Some methods take IDs, others take data. This is messy.
                // ideally we refactor EmailService to be generic. 
                // Let's assume for this specific execution we want to enable the GENERIC path.
                // We will add `sendEvent($eventKey, $toEmail, $toName, $data)` to EmailService.
                $results['email'] = $this->emailService->sendEvent(
                    $eventKey,
                    $user['email'],
                    $user['name'],
                    $data
                );
            } else {
                // Fallback to generic sending using keys
                $results['email'] = $this->emailService->sendEvent(
                    $eventKey,
                    $user['email'],
                    $user['name'],
                    $data
                );
            }
        }

        // 3. Dispatch Messaging (SMS/WhatsApp)
        // Check for enabled messaging channels
        foreach (['sms', 'whatsapp'] as $channel) {
            if (!empty($preferences[$channel]) && $preferences[$channel]['is_enabled']) {
                $results[$channel] = $this->dispatchMessage($channel, $eventKey, $user, $data);
            }
        }

        return $results;
    }

    /**
     * Dispatch SMS/WhatsApp Message
     */
    private function dispatchMessage($channel, $eventKey, $user, $data)
    {
        // 1. Find Template for this Event + Channel
        $stmt = $this->pdo->prepare("
            SELECT content, variables 
            FROM messaging_templates 
            WHERE event_key = ? AND message_type = ? AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute([$eventKey, $channel]);
        $template = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$template) {
            return false; // No template configured
        }

        // 2. Prepare Message
        $message = $template['content'];
        foreach ($data as $key => $value) {
            // Flatten arrays if any
            if (is_array($value))
                continue;
            $message = str_replace('{' . $key . '}', $value, $message);
        }
        // Also replace user standard vars
        $message = str_replace('{name}', $user['name'], $message);

        // 3. Send via Factory
        // Need to require factory if not autloaded
        if (!class_exists('EduCRM\Services\MessagingFactory')) {
            require_once __DIR__ . '/MessagingFactory.php';
        }

        try {
            $gateway = \EduCRM\Services\MessagingFactory::create(null, $channel);
            // Queue it to avoid blocking
            $gateway->queue($user['phone'], $message, [
                'template_id' => $eventKey, // We track by event key roughly
                'metadata' => ['event' => $eventKey, 'user_id' => $user['id']]
            ]);
            return true;
        } catch (\Exception $e) {
            error_log("UnifiedNotification Error ($channel): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Configuration for Dispatch
     * Returns: User info + their preferences for this event
     */
    private function getDispatchConfig($eventKey, $userId)
    {
        // Get User
        $stmt = $this->pdo->prepare("SELECT id, name, email, phone FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user)
            return null;

        // Get Event Definition
        $stmt = $this->pdo->prepare("SELECT * FROM notification_events WHERE event_key = ?");
        $stmt->execute([$eventKey]);
        $event = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$event)
            return null; // Event not registered

        // Get Preferences
        $stmt = $this->pdo->prepare("SELECT channel, is_enabled FROM user_notification_preferences WHERE user_id = ? AND event_key = ?");
        $stmt->execute([$userId, $eventKey]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $prefs = [];
        foreach ($rows as $row) {
            $prefs[$row['channel']] = $row;
        }

        // Apply defaults if no preference set
        $defaults = json_decode($event['default_channels'], true) ?? [];
        foreach (['email', 'sms', 'whatsapp'] as $ch) {
            if (!isset($prefs[$ch])) {
                // If not set in DB, use default from event definition
                $isEnabled = in_array($ch, $defaults);
                $prefs[$ch] = ['channel' => $ch, 'is_enabled' => $isEnabled];
            }
        }

        return ['user' => $user, 'event' => $event, 'preferences' => $prefs];
    }
}
