<?php
/**
 * Twilio Gateway Implementation
 * Cloud-based SMS gateway using Twilio API
 */

require_once 'MessagingService.php';

class TwilioGateway extends MessagingService
{
    private $accountSid;
    private $authToken;
    private $fromNumber;

    public function __construct($pdo, $gateway = null)
    {
        parent::__construct($pdo, $gateway);

        if ($this->config) {
            $this->accountSid = $this->config['account_sid'] ?? '';
            $this->authToken = $this->config['auth_token'] ?? '';
            $this->fromNumber = $this->config['from_number'] ?? '';
        }
    }

    /**
     * Send SMS via Twilio API
     */
    public function send($recipient, $message)
    {
        try {
            // Format phone number
            $recipient = $this->formatPhone($recipient);

            // Prepare Twilio API request
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

            $data = [
                'From' => $this->fromNumber,
                'To' => $recipient,
                'Body' => $message
            ];

            // Make HTTP request
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->accountSid}:{$this->authToken}");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode === 201 && isset($result['sid'])) {
                return [
                    'success' => true,
                    'message_id' => $result['sid'],
                    'status' => $result['status']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['message'] ?? 'Unknown Twilio error'
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
     * Get message status from Twilio
     */
    public function getStatus($gatewayMessageId)
    {
        try {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages/{$gatewayMessageId}.json";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->accountSid}:{$this->authToken}");

            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($response, true);

            return [
                'status' => $result['status'] ?? 'unknown',
                'delivered_at' => $result['date_sent'] ?? null
            ];

        } catch (Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Get Twilio account balance
     */
    public function getBalance()
    {
        try {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Balance.json";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->accountSid}:{$this->authToken}");

            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($response, true);

            return floatval($result['balance'] ?? 0);

        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Test Twilio connection
     */
    public function testConnection()
    {
        try {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}.json";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->accountSid}:{$this->authToken}");

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200;

        } catch (Exception $e) {
            return false;
        }
    }
}
