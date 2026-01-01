<?php
/**
 * Messaging Factory
 * Creates appropriate gateway instance based on configuration
 * Handles gateway selection, failover, and load balancing
 */

require_once 'MessagingService.php';
require_once 'gateways/TwilioGateway.php';
require_once 'gateways/SMPPGateway.php';
require_once 'gateways/GammuGateway.php';
require_once 'gateways/WhatsAppGateway.php';
require_once 'gateways/ViberGateway.php';

class MessagingFactory
{
    private static $pdo;

    /**
     * Initialize factory with database connection
     */
    public static function init($pdo)
    {
        self::$pdo = $pdo;
    }

    /**
     * Create messaging gateway instance
     * 
     * @param int|null $gatewayId Specific gateway ID (null = use default/best available)
     * @param string $type Message type (sms, whatsapp, viber)
     * @return MessagingService Gateway instance
     */
    public static function create($gatewayId = null, $type = 'sms')
    {
        if ($gatewayId) {
            // Get specific gateway
            $gateway = self::getGateway($gatewayId);
        } else {
            // Get best available gateway for type
            $gateway = self::getBestGateway($type);
        }

        if (!$gateway) {
            throw new Exception("No active gateway found for type: $type");
        }

        // Create appropriate gateway instance
        switch ($gateway['provider']) {
            case 'twilio':
                return new TwilioGateway(self::$pdo, $gateway);

            case 'smpp':
                return new SMPPGateway(self::$pdo, $gateway);

            case 'gammu':
                return new GammuGateway(self::$pdo, $gateway);

            // WhatsApp gateways (Phase 3C)
            case 'twilio_whatsapp':
            case 'whatsapp_business':
            case '360dialog':
                return new WhatsAppGateway(self::$pdo, $gateway);

            // Viber gateway (Phase 3D)
            case 'viber_bot':
                return new ViberGateway(self::$pdo, $gateway);

            default:
                throw new Exception("Unknown gateway provider: {$gateway['provider']}");
        }
    }

    /**
     * Get gateway by ID
     */
    private static function getGateway($gatewayId)
    {
        $stmt = self::$pdo->prepare("
            SELECT * FROM messaging_gateways
            WHERE id = ? AND is_active = TRUE
        ");
        $stmt->execute([$gatewayId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get best available gateway
     * Considers: priority, daily limits, active status
     */
    private static function getBestGateway($type = 'sms')
    {
        // First try default gateway
        $stmt = self::$pdo->prepare("
            SELECT * FROM messaging_gateways
            WHERE type = ? AND is_active = TRUE AND is_default = TRUE
            AND (daily_limit = 0 OR daily_sent < daily_limit)
            LIMIT 1
        ");
        $stmt->execute([$type]);
        $gateway = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($gateway) {
            return $gateway;
        }

        // Otherwise, get highest priority gateway with capacity
        $stmt = self::$pdo->prepare("
            SELECT * FROM messaging_gateways
            WHERE type = ? AND is_active = TRUE
            AND (daily_limit = 0 OR daily_sent < daily_limit)
            ORDER BY priority DESC, total_sent ASC
            LIMIT 1
        ");
        $stmt->execute([$type]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all active gateways for a type
     */
    public static function getAvailableGateways($type = 'sms')
    {
        $stmt = self::$pdo->prepare("
            SELECT * FROM messaging_gateways
            WHERE type = ? AND is_active = TRUE
            ORDER BY priority DESC, is_default DESC
        ");
        $stmt->execute([$type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Send with automatic failover
     * Tries multiple gateways if first one fails
     */
    public static function sendWithFailover($recipient, $message, $type = 'sms')
    {
        $gateways = self::getAvailableGateways($type);
        $lastError = null;

        foreach ($gateways as $gatewayConfig) {
            try {
                $gateway = self::create($gatewayConfig['id'], $type);
                $result = $gateway->send($recipient, $message);

                if ($result['success']) {
                    return $result;
                }

                $lastError = $result['error'] ?? 'Unknown error';

            } catch (Exception $e) {
                $lastError = $e->getMessage();
                continue;
            }
        }

        return [
            'success' => false,
            'error' => 'All gateways failed. Last error: ' . $lastError
        ];
    }

    /**
     * Reset daily counters (should be run via cron at midnight)
     */
    public static function resetDailyCounters()
    {
        $stmt = self::$pdo->prepare("UPDATE messaging_gateways SET daily_sent = 0");
        return $stmt->execute();
    }
}
