<?php

declare(strict_types=1);

namespace EduCRM\Services\gateways;

/**
 * Gammu Gateway Implementation
 * Local GSM modem support using Gammu CLI
 * Requires Gammu installed on server
 * 
 * This is a CLI-based gateway, not HTTP-based.
 * Extends \EduCRM\Services\MessagingService directly (not AbstractHttpGateway)
 * 
 * @package EduCRM\Services\Gateways
 */

require_once __DIR__ . '/../MessagingService.php';

class GammuGateway extends \EduCRM\Services\MessagingService
{
    // Gateway metadata
    protected string $gatewayType = 'sms';
    protected array $capabilities = ['sms', 'local_modem'];

    private string $gammuPath = 'gammu';
    private string $device = 'COM3';
    private string $connection = 'at';

    public function __construct(\PDO $pdo, ?array $gateway = null)
    {
        parent::__construct($pdo, $gateway);

        if ($this->config) {
            $this->gammuPath = $this->config['gammu_path'] ?? 'gammu';
            $this->device = $this->config['device'] ?? 'COM3';
            $this->connection = $this->config['connection'] ?? 'at';
        }
    }

    /**
     * Send SMS via Gammu CLI
     */
    public function send(string $recipient, string $message, array $options = []): array
    {
        $this->log('INFO', "Sending SMS via Gammu", ['recipient' => $recipient]);

        try {
            // Format phone number
            $recipient = $this->formatPhone($recipient);

            // Escape message for shell
            $escapedMessage = escapeshellarg($message);
            $escapedRecipient = escapeshellarg($recipient);

            // Build Gammu command
            $cmd = sprintf(
                '%s -c %s sendsms TEXT %s -text %s 2>&1',
                escapeshellcmd($this->gammuPath),
                $this->getConfigFile(),
                $escapedRecipient,
                $escapedMessage
            );

            $this->log('DEBUG', "Gammu command", ['cmd' => $cmd]);

            // Execute command
            exec($cmd, $output, $returnCode);

            $outputStr = implode("\n", $output);

            $this->log('DEBUG', "Gammu output", [
                'return_code' => $returnCode,
                'output' => $outputStr
            ]);

            // Check if successful
            if ($returnCode === 0 && (stripos($outputStr, 'OK') !== false || stripos($outputStr, 'sent') !== false)) {
                // Extract message reference if available
                $messageId = $this->extractMessageId($outputStr);

                $this->log('INFO', "Gammu SMS sent successfully", [
                    'recipient' => $recipient,
                    'message_id' => $messageId
                ]);

                return [
                    'success' => true,
                    'message_id' => $messageId ?: uniqid('gammu_'),
                    'output' => $outputStr
                ];
            } else {
                $this->log('ERROR', "Gammu send failed", [
                    'recipient' => $recipient,
                    'return_code' => $returnCode,
                    'output' => $outputStr
                ]);
                return [
                    'success' => false,
                    'error' => $outputStr ?: 'Gammu command failed'
                ];
            }

        } catch (\Exception $e) {
            $this->log('ERROR', "Gammu exception: " . $e->getMessage(), ['recipient' => $recipient]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get message status
     * Gammu doesn't provide delivery reports via CLI easily
     */
    public function getStatus(string $messageId): array
    {
        $this->log('DEBUG', "Gammu status query (not supported)", ['message_id' => $messageId]);
        return [
            'status' => 'sent',
            'delivered_at' => null,
            'error' => 'Gammu CLI does not support delivery status queries'
        ];
    }

    /**
     * Test Gammu connection
     */
    public function testConnection(): bool
    {
        $this->log('INFO', "Testing Gammu connection", ['device' => $this->device]);

        try {
            // Try to identify the phone
            $cmd = sprintf(
                '%s -c %s identify 2>&1',
                escapeshellcmd($this->gammuPath),
                $this->getConfigFile()
            );

            exec($cmd, $output, $returnCode);

            $outputStr = implode("\n", $output);

            // Check if phone is detected
            $success = $returnCode === 0 && (
                stripos($outputStr, 'Manufacturer') !== false ||
                stripos($outputStr, 'Model') !== false
            );

            $this->log($success ? 'INFO' : 'ERROR', "Gammu connection test " . ($success ? 'passed' : 'failed'), [
                'return_code' => $returnCode,
                'output' => $outputStr
            ]);

            return $success;

        } catch (\Exception $e) {
            $this->log('ERROR', "Gammu connection test exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Gammu configuration file path
     * Creates temporary config if needed
     */
    private function getConfigFile()
    {
        $configPath = sys_get_temp_dir() . '/gammu_' . md5($this->device) . '.conf';

        // Create config file if doesn't exist
        if (!file_exists($configPath)) {
            $config = "[gammu]\n";
            $config .= "device = {$this->device}\n";
            $config .= "connection = {$this->connection}\n";

            file_put_contents($configPath, $config);
        }

        return escapeshellarg($configPath);
    }

    /**
     * Extract message ID from Gammu output
     */
    private function extractMessageId($output)
    {
        // Try to find message reference
        if (preg_match('/reference\s*[:=]\s*(\d+)/i', $output, $matches)) {
            return $matches[1];
        }

        if (preg_match('/message\s*(\d+)/i', $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get modem signal strength
     */
    public function getSignalStrength()
    {
        try {
            $cmd = sprintf(
                '%s -c %s getsignalquality 2>&1',
                escapeshellcmd($this->gammuPath),
                $this->getConfigFile()
            );

            exec($cmd, $output, $returnCode);

            $outputStr = implode("\n", $output);

            if (preg_match('/(\d+)%/', $outputStr, $matches)) {
                return intval($matches[1]);
            }

            return null;

        } catch (\Exception $e) {
            return null;
        }
    }
}
