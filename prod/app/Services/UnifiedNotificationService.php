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

        // 2. Load Centralized Template
        $stmt = $this->pdo->prepare("SELECT * FROM centralized_templates WHERE event_key = ?");
        $stmt->execute([$eventKey]);
        $template = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$template) {
            // Fallback for Phase 5 backward compatibility or logging
            // error_log("UnifiedNotification: No centralized template for $eventKey");
            // We can return here or try legacy paths. 
            // For Phase 6, we assume migration populated this.
        }

        // 3. Dispatch Email
        if (
            !empty($preferences['email']) && $preferences['email']['is_enabled'] &&
            $template && $template['is_email_enabled']
        ) {

            $subject = $this->renderString($template['email_subject'], $data);
            $body = $this->renderString($template['email_body'], $data);

            // Inject User Vars
            $subject = str_replace('{name}', $user['name'], $subject);
            $body = str_replace('{name}', $user['name'], $body);

            // Use EmailService just for queuing
            $results['email'] = $this->emailService->queueEmail(
                $user['email'],
                $user['name'],
                $subject,
                $body,
                $eventKey
            );
        }

        // 4. Dispatch Messaging (Dynamic Channels)
        // 4. Dispatch Messaging (Dynamic Channels)
        // Check for enabled messaging channels

        // Phase 6: Specific Gateway Logic
        // We get ALL channels configured for this event's template
        // This query finds:
        // 1. The template for this event key
        // 2. All active channels/gateways linked to it
        // 3. The gateway details (id, provider, type)
        $stmt = $this->pdo->prepare("
            SELECT c.channel_type, c.gateway_id, g.provider, t.subject, t.body_html, c.custom_content 
            FROM email_templates t
            JOIN email_template_channels c ON t.id = c.template_id
            LEFT JOIN messaging_gateways g ON c.gateway_id = g.id
            WHERE t.template_key = ? AND t.is_active = 1 AND c.is_active = 1
        ");
        $stmt->execute([$eventKey]);
        $activeConfig = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($activeConfig as $config) {
            $channelType = $config['channel_type'];

            // Check User Preference for this channel type
            // Note: Users opt-in/out of "SMS", not "Twilio" specifically.
            $isEnabledPref = !empty($preferences[$channelType]) && $preferences[$channelType]['is_enabled'];

            if ($isEnabledPref) {
                // Pass the specific config to dispatchMessage
                // Using gateway_id to force correct gateway usage
                $results[$channelType . ':' . $config['gateway_id']] = $this->dispatchMessage($config, $user, $data);
            }
        }

        return $results;
    }

    /**
     * Dispatch Message Dynamically
     */
    /**
     * Dispatch Message Dynamically
     * Now accepts $config array with gateway info
     */
    private function dispatchMessage($config, $user, $data)
    {
        $channel = $config['channel_type'];
        $gatewayId = $config['gateway_id'];

        // Check for valid phone number
        if (empty($user['phone'])) {
            return false;
        }

        // 2. Prepare Content
        // Use custom content if exists, otherwise fallback to stripped email body
        $rawContent = !empty($config['custom_content']) ? $config['custom_content'] : strip_tags($config['body_html']);
        if (empty($rawContent))
            return false;

        // 3. Render Variables
        $message = $this->renderString($rawContent, $data);
        $message = str_replace('{name}', $user['name'], $message);
        $message = str_replace('{site_name}', 'EduCRM', $message);

        // 4. Send via Factory
        if (!class_exists('EduCRM\Services\MessagingFactory')) {
            require_once __DIR__ . '/MessagingFactory.php';
        }

        \EduCRM\Services\MessagingFactory::init($this->pdo);

        try {
            // Force specific gateway creation
            $gateway = \EduCRM\Services\MessagingFactory::create($gatewayId, $channel);
            // Queue it to avoid blocking
            $gateway->queue($user['phone'], $message, [
                'template_id' => $eventKey,
                'metadata' => ['event' => $eventKey, 'user_id' => $user['id'], 'channel' => $channel]
            ]);
            return true;
        } catch (\Exception $e) {
            error_log("UnifiedNotification Error ($channel): " . $e->getMessage());
            return false;
        }
    }

    private function renderString($string, $data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value))
                continue;
            $string = str_replace('{' . $key . '}', $value, $string);
        }
        return $string;
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

        // Dynamic Channel Defaults
        try {
            $stmt = $this->pdo->query("SELECT DISTINCT type FROM messaging_gateways WHERE is_active = 1");
            $dynamicChannels = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\PDOException $e) {
            $dynamicChannels = [];
        }

        $allChannels = array_unique(array_merge(['email'], $dynamicChannels));

        foreach ($allChannels as $ch) {
            if (!isset($prefs[$ch])) {
                // If not set in DB, use default from event definition
                $isEnabled = in_array($ch, $defaults);
                $prefs[$ch] = ['channel' => $ch, 'is_enabled' => $isEnabled];
            }
        }

        return ['user' => $user, 'event' => $event, 'preferences' => $prefs];
    }
}
