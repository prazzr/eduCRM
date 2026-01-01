<?php
/**
 * Messaging Service - Abstract Base Class
 * Universal messaging interface for SMS, WhatsApp, Viber, etc.
 * 
 * This abstract class defines the contract that all messaging gateways must implement.
 * It provides common functionality for queue management, delivery tracking, and failover.
 */

abstract class MessagingService
{
    protected $pdo;
    protected $gateway;
    protected $config;

    public function __construct($pdo, $gateway = null)
    {
        $this->pdo = $pdo;

        if ($gateway) {
            $this->gateway = $gateway;
            $this->config = json_decode($gateway['config'], true);
        }
    }

    /**
     * Send a message immediately
     * Must be implemented by each gateway
     * 
     * @param string $recipient Phone number, WhatsApp ID, etc.
     * @param string $message Message content
     * @return array ['success' => bool, 'message_id' => string, 'error' => string]
     */
    abstract public function send($recipient, $message);

    /**
     * Get delivery status from gateway
     * Must be implemented by each gateway
     * 
     * @param string $gatewayMessageId External message ID
     * @return array ['status' => string, 'delivered_at' => datetime]
     */
    abstract public function getStatus($gatewayMessageId);

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
     * Test gateway connection
     * Optional - return true if not implemented
     * 
     * @return bool Connection status
     */
    public function testConnection()
    {
        return true;
    }

    /**
     * Queue a message for later sending
     * 
     * @param string $recipient
     * @param string $message
     * @param array $options Additional options (scheduled_at, template_id, etc.)
     * @return int Queue ID
     */
    public function queue($recipient, $message, $options = [])
    {
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

        return $this->pdo->lastInsertId();
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
            } catch (Exception $e) {
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
        // Get pending messages
        $stmt = $this->pdo->prepare("
            SELECT * FROM messaging_queue
            WHERE status = 'pending'
            AND (scheduled_at IS NULL OR scheduled_at <= NOW())
            AND retry_count < max_retries
            ORDER BY created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                    throw new Exception($result['error'] ?? 'Unknown error');
                }

            } catch (Exception $e) {
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
     * @param string $defaultCountryCode Default country code (e.g., '+977' for Nepal)
     * @return string Formatted phone number
     */
    public function formatPhone($phone, $defaultCountryCode = '+1')
    {
        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        // If doesn't start with +, add default country code
        if (substr($cleaned, 0, 1) !== '+') {
            $cleaned = $defaultCountryCode . $cleaned;
        }

        return $cleaned;
    }
}
