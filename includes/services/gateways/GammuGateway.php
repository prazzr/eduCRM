<?php
/**
 * Gammu Gateway Implementation
 * Local GSM modem support using Gammu CLI
 * Requires Gammu installed on server
 */

require_once __DIR__ . '/../MessagingService.php';

class GammuGateway extends MessagingService
{
    private $gammuPath;
    private $device;
    private $connection;

    public function __construct($pdo, $gateway = null)
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
    public function send($recipient, $message)
    {
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

            // Execute command
            exec($cmd, $output, $returnCode);

            $outputStr = implode("\n", $output);

            // Check if successful
            if ($returnCode === 0 && (stripos($outputStr, 'OK') !== false || stripos($outputStr, 'sent') !== false)) {
                // Extract message reference if available
                $messageId = $this->extractMessageId($outputStr);

                return [
                    'success' => true,
                    'message_id' => $messageId ?: uniqid('gammu_'),
                    'output' => $outputStr
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $outputStr ?: 'Gammu command failed'
                ];
            }

        } catch (Exception $e) {
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
    public function getStatus($gatewayMessageId)
    {
        return [
            'status' => 'sent',
            'note' => 'Gammu CLI does not support delivery status queries'
        ];
    }

    /**
     * Test Gammu connection
     */
    public function testConnection()
    {
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
            return $returnCode === 0 && (
                stripos($outputStr, 'Manufacturer') !== false ||
                stripos($outputStr, 'Model') !== false
            );

        } catch (Exception $e) {
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

        } catch (Exception $e) {
            return null;
        }
    }
}
