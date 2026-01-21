<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
 * Messaging Service - Abstract Base Class
 * Universal messaging interface for SMS, WhatsApp, Viber, etc.
 * 
 * This abstract class defines the contract that all messaging gateways must implement.
 * It provides common functionality for queue management, delivery tracking, and failover.
 * 
 * For HTTP-based gateways (Twilio, WhatsApp, Viber), extend AbstractHttpGateway instead.
 * For socket/CLI-based gateways (SMPP, Gammu), extend this class directly.
 * 
 * @package EduCRM\Services
 * @see \AbstractHttpGateway For HTTP-based gateway implementations
 */

// Include the interface
require_once __DIR__ . '/../Contracts/MessagingGatewayInterface.php';

use EduCRM\Contracts\MessagingGatewayInterface;

abstract class MessagingService implements \EduCRM\Contracts\MessagingGatewayInterface
{
    protected \PDO $pdo;
    protected ?array $gateway;
    protected ?array $config;
    protected string $logFile;
    protected string $defaultCountryCode;

    // For interface compliance - subclasses should override
    protected string $gatewayType = 'sms';
    protected array $capabilities = [];

    public function __construct(\PDO $pdo, ?array $gateway = null)
    {
        $this->pdo = $pdo;
        $this->logFile = dirname(__DIR__, 2) . '/logs/messaging.log';

        if ($gateway) {
            $this->gateway = $gateway;
            $this->config = json_decode($gateway['config'], true);
            $this->defaultCountryCode = $this->config['default_country_code'] ?? '+1';
        } else {
            $this->defaultCountryCode = '+1';
        }
    }

    /**
     * Get the gateway type (sms, whatsapp, viber, email)
     * Required by MessagingGatewayInterface
     * 
     * @return string The gateway type
     */
    public function getType(): string
    {
        return $this->gatewayType;
    }

    /**
     * Get supported capabilities of this gateway
     * Required by MessagingGatewayInterface
     * 
     * @return array<string> List of capability names
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * Test connection to the gateway
     * Required by MessagingGatewayInterface - must be implemented by subclasses
     * 
     * @return bool True if connection successful
     */
    abstract public function testConnection(): bool;

    /**
     * Log a message for debugging
     * 
     * @param string $level Log level: 'INFO', 'WARNING', 'ERROR', 'DEBUG'
     * @param string $message Log message
     * @param array $context Additional context data
     */
    protected function log($level, $message, $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $gatewayName = $this->gateway['name'] ?? 'Unknown';
        $gatewayId = $this->gateway['id'] ?? 0;
        
        $logEntry = sprintf(
            "[%s] [%s] [Gateway: %s (#%d)] %s",
            $timestamp,
            strtoupper($level),
            $gatewayName,
            $gatewayId,
            $message
        );

        if (!empty($context)) {
            $logEntry .= " | Context: " . json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        $logEntry .= PHP_EOL;

        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Append to log file
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);

        // Also log to PHP error log for critical errors
        if ($level === 'ERROR') {
            error_log("MessagingService: $message");
        }

        // Optionally log to database for analytics
        $this->logToDatabase($level, $message, $context);
    }

    /**
     * Log to database for analytics and audit trail
     */
    protected function logToDatabase($level, $message, $context = [])
    {
        try {
            // Determine status from level or context
            $status = 'pending';
            if (isset($context['status'])) {
                $status = $context['status'];
            } elseif ($level === 'ERROR') {
                $status = 'failed';
            } elseif (stripos($message, 'delivered') !== false) {
                $status = 'delivered';
            } elseif (stripos($message, 'sent') !== false || stripos($message, 'success') !== false) {
                $status = 'sent';
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO messaging_logs 
                (gateway_id, gateway_name, recipient, message_id, message, level, status, error_message, metadata)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $this->gateway['id'] ?? 0,
                $this->gateway['name'] ?? 'Unknown',
                $context['recipient'] ?? null,
                $context['message_id'] ?? null,
                $message,
                strtoupper($level),
                $status,
                $context['error'] ?? null,
                json_encode($context)
            ]);
        } catch (\PDOException $e) {
            // Table might not exist - silently fail
            // Don't fail main operation if logging fails
        }
    }

    /**
     * Send a message immediately
     * Must be implemented by each gateway
     * 
     * @param string $recipient Phone number, WhatsApp ID, etc.
     * @param string $message Message content
     * @param array<string, mixed> $options Additional options (template_id, variables, etc.)
     * @return array{success: bool, message_id: string|null, error: string|null}
     */
    abstract public function send(string $recipient, string $message, array $options = []): array;

    /**
     * Get delivery status from gateway
     * Must be implemented by each gateway
     * 
     * @param string $messageId External message ID
     * @return array{status: string, delivered_at: string|null, error: string|null}
     */
    abstract public function getStatus(string $messageId): array;

    /**
     * Get gateway balance/credits (if applicable)
     * Optional - return null if not supported
     * 
     * @return float|null Balance amount
     */
    public function getBalance()
    {
        return null;
    }

    /**
     * Queue a message for later sending
     * 
     * @param string $recipient The recipient identifier
     * @param string $message The message content
     * @param array<string, mixed> $options Additional options (scheduled_at, template_id, etc.)
     * @return int|false The queue ID or false on failure
     */
    public function queue(string $recipient, string $message, array $options = []): int|false
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO messaging_queue (
                    gateway_id, message_type, recipient, message,
                    template_id, entity_type, entity_id,
                    scheduled_at, metadata, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $this->gateway['id'] ?? null,
                $this->gateway['type'] ?? 'sms',
                $recipient,
                $message,
                $options['template_id'] ?? null,
                $options['entity_type'] ?? null,
                $options['entity_id'] ?? null,
                $options['scheduled_at'] ?? null,
                json_encode($options['metadata'] ?? []),
                $_SESSION['user_id'] ?? null
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            $this->log('ERROR', 'Failed to queue message', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send bulk messages
     * 
     * @param array $recipients Array of phone numbers/IDs
     * @param string $message Message content
     * @param array $options Additional options
     * @return array ['queued' => int, 'failed' => int]
     */
    public function sendBulk($recipients, $message, $options = [])
    {
        $queued = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            try {
                $this->queue($recipient, $message, $options);
                $queued++;
            } catch (\Exception $e) {
                $failed++;
            }
        }

        return ['queued' => $queued, 'failed' => $failed];
    }

    /**
     * Process message queue
     * Sends pending messages that are due
     * 
     * @param int $limit Number of messages to process
     * @return array ['sent' => int, 'failed' => int]
     */
    public function processQueue($limit = 50)
    {
        $limit = (int) $limit;
        // Get pending messages
        $stmt = $this->pdo->prepare("
            SELECT * FROM messaging_queue
            WHERE status = 'pending'
            AND (scheduled_at IS NULL OR scheduled_at <= NOW())
            AND retry_count < max_retries
            ORDER BY created_at ASC
            LIMIT $limit
        ");
        $stmt->execute();
        $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $sent = 0;
        $failed = 0;

        foreach ($messages as $msg) {
            try {
                // Mark as processing
                $this->updateQueueStatus($msg['id'], 'processing');

                // Send message
                $result = $this->send($msg['recipient'], $msg['message']);

                if ($result['success']) {
                    // Mark as sent
                    $this->updateQueueStatus($msg['id'], 'sent', [
                        'sent_at' => date('Y-m-d H:i:s'),
                        'gateway_message_id' => $result['message_id'] ?? null,
                        'cost' => $this->gateway['cost_per_message'] ?? 0
                    ]);

                    // Update gateway stats
                    $this->updateGatewayStats('sent');

                    $sent++;
                } else {
                    throw new \Exception($result['error'] ?? 'Unknown error');
                }

            } catch (\Exception $e) {
                // Mark as failed or retry
                $retryCount = $msg['retry_count'] + 1;

                if ($retryCount >= $msg['max_retries']) {
                    $this->updateQueueStatus($msg['id'], 'failed', [
                        'error_message' => $e->getMessage(),
                        'retry_count' => $retryCount
                    ]);
                    $this->updateGatewayStats('failed');
                } else {
                    $this->updateQueueStatus($msg['id'], 'pending', [
                        'error_message' => $e->getMessage(),
                        'retry_count' => $retryCount
                    ]);
                }

                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Update queue message status
     */
    protected function updateQueueStatus($queueId, $status, $data = [])
    {
        $updates = ['status = ?'];
        $params = [$status];

        foreach ($data as $key => $value) {
            $updates[] = "$key = ?";
            $params[] = $value;
        }

        $params[] = $queueId;

        $sql = "UPDATE messaging_queue SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Update gateway statistics
     */
    protected function updateGatewayStats($type)
    {
        if (!$this->gateway)
            return;

        $field = $type === 'sent' ? 'total_sent' : 'total_failed';

        $stmt = $this->pdo->prepare("
            UPDATE messaging_gateways 
            SET $field = $field + 1,
                daily_sent = daily_sent + 1,
                last_used_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$this->gateway['id']]);
    }

    /**
     * Replace template variables in message
     * 
     * @param string $template Template content with {variables}
     * @param array $data Associative array of variable => value
     * @return string Processed message
     */
    public function replaceVariables($template, $data)
    {
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        return $template;
    }

    /**
     * Validate phone number format
     * 
     * @param string $phone Phone number
     * @return bool Valid or not
     */
    public function validatePhone($phone)
    {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        // Must be at least 10 digits
        if (strlen($cleaned) < 10) {
            return false;
        }

        return true;
    }

    /**
     * Format phone number to E.164 format
     * 
     * @param string $phone Phone number
     * @param string|null $countryCode Country code override (uses gateway config if null)
     * @return string Formatted phone number
     */
    public function formatPhone($phone, $countryCode = null)
    {
        // Use provided code, or gateway config, or default
        $defaultCode = $countryCode ?? $this->defaultCountryCode ?? '+1';

        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        // If doesn't start with +, add default country code
        if (substr($cleaned, 0, 1) !== '+') {
            $cleaned = $defaultCode . $cleaned;
        }

        $this->log('DEBUG', "Formatted phone number", [
            'original' => $phone,
            'formatted' => $cleaned,
            'country_code' => $defaultCode
        ]);

        return $cleaned;
    }

    /**
     * Validate a recipient identifier (phone number)
     * Can be overridden by specific gateways
     *
     * @param string $recipient The recipient to validate
     * @return bool True if valid, false otherwise
     */
    public function validateRecipient(string $recipient): bool
    {
        // Remove spaces and dashes
        $cleaned = preg_replace('/[\s\-\(\)]/', '', $recipient);
        // Check format: optional +, 10-15 digits
        return preg_match('/^[+]?[0-9]{10,15}$/', $cleaned) === 1;
    }

    /**
     * Get the gateway name/identifier
     *
     * @return string The gateway name (e.g., 'twilio', 'smpp')
     */
    public function getName(): string
    {
        return $this->gateway['name'] ?? 'unknown';
    }
}
