<?php
/**
 * WhatsApp Gateway Implementation
 * Supports Twilio WhatsApp API and Meta WhatsApp Business Cloud API
 */

require_once __DIR__ . '/../MessagingService.php';

class WhatsAppGateway extends MessagingService
{
    private $provider; // 'twilio_whatsapp', 'whatsapp_business', '360dialog'
    private $config;

    // Twilio
    private $accountSid;
    private $authToken;
    private $fromNumber;

    // Meta Cloud API
    private $phoneNumberId;
    private $accessToken;
    private $businessAccountId;

    // 360Dialog
    private $apiKey;
    private $clientId;

    public function __construct($pdo, $gateway = null)
    {
        parent::__construct($pdo, $gateway);

        if ($this->config) {
            $this->provider = $gateway['provider'];

            // Twilio WhatsApp
            if ($this->provider === 'twilio_whatsapp') {
                $this->accountSid = $this->config['account_sid'] ?? '';
                $this->authToken = $this->config['auth_token'] ?? '';
                $this->fromNumber = $this->config['from_number'] ?? '';
            }

            // Meta Cloud API
            if ($this->provider === 'whatsapp_business') {
                $this->phoneNumberId = $this->config['phone_number_id'] ?? '';
                $this->accessToken = $this->config['access_token'] ?? '';
                $this->businessAccountId = $this->config['business_account_id'] ?? '';
            }

            // 360Dialog
            if ($this->provider === '360dialog') {
                $this->apiKey = $this->config['api_key'] ?? '';
                $this->clientId = $this->config['client_id'] ?? '';
            }
        }
    }

    /**
     * Send WhatsApp message
     */
    public function send($recipient, $message)
    {
        switch ($this->provider) {
            case 'twilio_whatsapp':
                return $this->sendViaTwilio($recipient, $message);
            case 'whatsapp_business':
                return $this->sendViaMetaCloudAPI($recipient, $message);
            case '360dialog':
                return $this->sendVia360Dialog($recipient, $message);
            default:
                return ['success' => false, 'error' => 'Unknown provider'];
        }
    }

    /**
     * Send via Twilio WhatsApp API
     */
    private function sendViaTwilio($recipient, $message)
    {
        try {
            // Format number for WhatsApp
            $to = 'whatsapp:' . $this->formatPhone($recipient);

            $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

            $data = [
                'From' => $this->fromNumber,
                'To' => $to,
                'Body' => $message
            ];

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
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send via Meta WhatsApp Business Cloud API
     */
    private function sendViaMetaCloudAPI($recipient, $message)
    {
        try {
            $url = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages";

            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $this->formatPhone($recipient),
                'type' => 'text',
                'text' => ['body' => $message]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode === 200 && isset($result['messages'][0]['id'])) {
                return [
                    'success' => true,
                    'message_id' => $result['messages'][0]['id']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['error']['message'] ?? 'Unknown Meta API error'
                ];
            }

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send via 360Dialog
     */
    private function sendVia360Dialog($recipient, $message)
    {
        try {
            $url = "https://waba.360dialog.io/v1/messages";

            $data = [
                'to' => $this->formatPhone($recipient),
                'type' => 'text',
                'text' => ['body' => $message]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'D360-API-KEY: ' . $this->apiKey
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode === 201 && isset($result['messages'][0]['id'])) {
                return [
                    'success' => true,
                    'message_id' => $result['messages'][0]['id']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Unknown 360Dialog error'
                ];
            }

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send media message (image, video, document)
     */
    public function sendMedia($recipient, $mediaUrl, $caption = '', $mediaType = 'image')
    {
        if ($this->provider === 'twilio_whatsapp') {
            return $this->sendMediaViaTwilio($recipient, $mediaUrl, $caption);
        } elseif ($this->provider === 'whatsapp_business') {
            return $this->sendMediaViaMetaCloudAPI($recipient, $mediaUrl, $caption, $mediaType);
        }

        return ['success' => false, 'error' => 'Media not supported for this provider'];
    }

    /**
     * Send media via Twilio
     */
    private function sendMediaViaTwilio($recipient, $mediaUrl, $caption)
    {
        try {
            $to = 'whatsapp:' . $this->formatPhone($recipient);
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

            $data = [
                'From' => $this->fromNumber,
                'To' => $to,
                'MediaUrl' => $mediaUrl
            ];

            if ($caption) {
                $data['Body'] = $caption;
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->accountSid}:{$this->authToken}");

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode === 201) {
                return ['success' => true, 'message_id' => $result['sid']];
            }

            return ['success' => false, 'error' => $result['message'] ?? 'Failed to send media'];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send media via Meta Cloud API
     */
    private function sendMediaViaMetaCloudAPI($recipient, $mediaUrl, $caption, $mediaType)
    {
        try {
            $url = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages";

            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $this->formatPhone($recipient),
                'type' => $mediaType,
                $mediaType => [
                    'link' => $mediaUrl
                ]
            ];

            if ($caption) {
                $data[$mediaType]['caption'] = $caption;
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode === 200) {
                return ['success' => true, 'message_id' => $result['messages'][0]['id']];
            }

            return ['success' => false, 'error' => $result['error']['message'] ?? 'Failed to send media'];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send template message
     */
    public function sendTemplate($recipient, $templateName, $params = [])
    {
        if ($this->provider === 'whatsapp_business') {
            return $this->sendTemplateViaMetaCloudAPI($recipient, $templateName, $params);
        }

        return ['success' => false, 'error' => 'Templates only supported for Meta Cloud API'];
    }

    /**
     * Send template via Meta Cloud API
     */
    private function sendTemplateViaMetaCloudAPI($recipient, $templateName, $params)
    {
        try {
            $url = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages";

            $components = [];
            if (count($params) > 0) {
                $parameters = [];
                foreach ($params as $param) {
                    $parameters[] = ['type' => 'text', 'text' => $param];
                }
                $components[] = [
                    'type' => 'body',
                    'parameters' => $parameters
                ];
            }

            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $this->formatPhone($recipient),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => 'en'],
                    'components' => $components
                ]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode === 200) {
                return ['success' => true, 'message_id' => $result['messages'][0]['id']];
            }

            return ['success' => false, 'error' => $result['error']['message'] ?? 'Failed to send template'];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get message status
     */
    public function getStatus($gatewayMessageId)
    {
        // Status tracking varies by provider
        // For now, return sent status
        return ['status' => 'sent'];
    }

    /**
     * Test connection
     */
    public function testConnection()
    {
        try {
            // Send a test request to verify credentials
            if ($this->provider === 'twilio_whatsapp') {
                $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}.json";
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERPWD, "{$this->accountSid}:{$this->authToken}");
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                return $httpCode === 200;
            }

            if ($this->provider === 'whatsapp_business') {
                $url = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}";
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $this->accessToken
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                return $httpCode === 200;
            }

            return true;

        } catch (Exception $e) {
            return false;
        }
    }
}
