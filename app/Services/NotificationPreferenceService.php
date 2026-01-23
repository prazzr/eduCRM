<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
 * Notification Preference Service
 * Manages user notification channel preferences based on available messaging gateways
 * 
 * @package EduCRM\Services
 */
class NotificationPreferenceService
{
    private \PDO $pdo;

    private const CHANNEL_LABELS = [
        'email' => ['label' => 'Email', 'icon' => '📧'],
        'sms' => ['label' => 'SMS', 'icon' => '📱'],
        'whatsapp' => ['label' => 'WhatsApp', 'icon' => '💬'],
        'viber' => ['label' => 'Viber', 'icon' => '📲'],
        'push' => ['label' => 'Push Notifications', 'icon' => '🔔'],
    ];

    private const DEFAULT_EVENT = 'all';

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get available notification channels based on active gateways
     */
    public function getAvailableChannels(): array
    {
        $stmt = $this->pdo->query("
            SELECT DISTINCT 
                CASE WHEN type IS NULL OR type = '' THEN 'push' ELSE type END as channel_type,
                GROUP_CONCAT(name SEPARATOR ', ') as gateway_names
            FROM messaging_gateways 
            WHERE is_active = 1 
            GROUP BY channel_type
            ORDER BY channel_type
        ");
        $gateways = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $channels = [
            'email' => [
                'type' => 'email',
                'label' => 'Email',
                'icon' => '📧',
                'gateways' => 'System Email',
                'default' => true
            ]
        ];

        foreach ($gateways as $gw) {
            $type = $gw['channel_type'];
            $info = self::CHANNEL_LABELS[$type] ?? ['label' => ucfirst($type), 'icon' => '📨'];
            $channels[$type] = [
                'type' => $type,
                'label' => $info['label'],
                'icon' => $info['icon'],
                'gateways' => $gw['gateway_names'],
                'default' => ($type === 'email')
            ];
        }

        return $channels;
    }

    /**
     * Get user's notification preferences
     */
    public function getUserPreferences(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT channel, is_enabled 
            FROM user_notification_preferences 
            WHERE user_id = ? AND event_key = ?
        ");
        $stmt->execute([$userId, self::DEFAULT_EVENT]);
        $prefs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($prefs as $pref) {
            $result[$pref['channel']] = (bool) $pref['is_enabled'];
        }
        return $result;
    }

    /**
     * Save user notification preferences
     */
    public function saveUserPreferences(int $userId, array $enabledChannels): void
    {
        $availableChannels = array_keys($this->getAvailableChannels());

        $deleteStmt = $this->pdo->prepare("
            DELETE FROM user_notification_preferences WHERE user_id = ? AND event_key = ?
        ");
        $deleteStmt->execute([$userId, self::DEFAULT_EVENT]);

        $insertStmt = $this->pdo->prepare("
            INSERT INTO user_notification_preferences (user_id, event_key, channel, is_enabled)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($availableChannels as $channel) {
            $isEnabled = in_array($channel, $enabledChannels) ? 1 : 0;
            $insertStmt->execute([$userId, self::DEFAULT_EVENT, $channel, $isEnabled]);
        }
    }

    /**
     * Check if a specific channel is enabled for a user
     */
    public function isChannelEnabled(int $userId, string $channel): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT is_enabled FROM user_notification_preferences 
            WHERE user_id = ? AND channel = ? AND event_key = ?
        ");
        $stmt->execute([$userId, $channel, self::DEFAULT_EVENT]);
        $result = $stmt->fetchColumn();
        return $result === false ? true : (bool) $result;
    }

    /**
     * Set default preferences for a new user (all channels enabled)
     */
    public function setDefaultPreferences(int $userId): void
    {
        $this->saveUserPreferences($userId, array_keys($this->getAvailableChannels()));
    }
}
