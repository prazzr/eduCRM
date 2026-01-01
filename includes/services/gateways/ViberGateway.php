<?php
/**
 * Viber Gateway Implementation
 * Supports Viber Bot API
 */

require_once __DIR__ . '/../MessagingService.php';

class ViberGateway extends MessagingService
{
    private $authToken;
    private $botName;
    private $botAvatar;

    public function __construct($pdo, $gateway = null)
    {
        parent::__construct($pdo, $gateway);

        if ($this->config) {
            $this->authToken = $this->config['auth_token'] ?? '';
            $this->botName = $this->config['bot_name'] ?? 'eduCRM Bot';
            $this->botAvatar = $this->config['bot_avatar'] ?? '';
        }
    }

    /**
     * Send Viber message
     */
    public function send($recipient, $message)
    {
        try {
            $url = "https://chatapi.viber.com/pa/send_message";

            $data = [
                'auth_token' => $this->authToken,
                'receiver' => $recipient,
                'type' => 'text',
                'text' => $message,
                'sender' => [
                    'name' => $this->botName
                ]
            ];

            if ($this->botAvatar) {
                $data['sender']['avatar'] = $this->botAvatar;
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode === 200 && $result['status'] === 0) {
                return [
                    'success' => true,
                    'message_id' => $result['message_token'] ?? null,
                    'status' => 'sent'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['status_message'] ?? 'Unknown Viber error'
                ];
            }

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send media message (image, video, file)
     */
    public function sendMedia($recipient, $mediaUrl, $caption = '', $mediaType = 'picture')
    {
        try {
            $url = "https://chatapi.viber.com/pa/send_message";

            // Map media types
            $viberType = match ($mediaType) {
                'image' => 'picture',
                'video' => 'video',
                'document', 'file' => 'file',
                default => 'picture'
            };

            $data = [
                'auth_token' => $this->authToken,
                'receiver' => $recipient,
                'type' => $viberType,
                'media' => $mediaUrl,
                'sender' => [
                    'name' => $this->botName
                ]
            ];

            if ($caption && $viberType === 'picture') {
                $data['text'] = $caption;
            }

            if ($this->botAvatar) {
                $data['sender']['avatar'] = $this->botAvatar;
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode === 200 && $result['status'] === 0) {
                return [
                    'success' => true,
                    'message_id' => $result['message_token'] ?? null
                ];
            }

            return [
                'success' => false,
                'error' => $result['status_message'] ?? 'Failed to send media'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send message with keyboard buttons
     */
    public function sendWithKeyboard($recipient, $message, $buttons = [])
    {
        try {
            $url = "https://chatapi.viber.com/pa/send_message";

            $keyboard = [
                'Type' => 'keyboard',
                'Buttons' => []
            ];

            foreach ($buttons as $button) {
                $keyboard['Buttons'][] = [
                    'ActionType' => 'reply',
                    'ActionBody' => $button['value'] ?? $button['text'],
                    'Text' => $button['text'],
                    'TextSize' => 'regular'
                ];
            }

            $data = [
                'auth_token' => $this->authToken,
                'receiver' => $recipient,
                'type' => 'text',
                'text' => $message,
                'keyboard' => $keyboard,
                'sender' => [
                    'name' => $this->botName
                ]
            ];

            if ($this->botAvatar) {
                $data['sender']['avatar'] = $this->botAvatar;
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode === 200 && $result['status'] === 0) {
                return ['success' => true, 'message_id' => $result['message_token'] ?? null];
            }

            return ['success' => false, 'error' => $result['status_message'] ?? 'Failed to send keyboard'];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get message status
     */
    public function getStatus($gatewayMessageId)
    {
        // Viber doesn't have a direct status API
        // Status updates come via webhooks
        return ['status' => 'sent'];
    }

    /**
     * Test connection
     */
    public function testConnection()
    {
        try {
            $url = "https://chatapi.viber.com/pa/get_account_info";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'auth_token' => $this->authToken
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            return $httpCode === 200 && $result['status'] === 0;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Set webhook
     */
    public function setWebhook($webhookUrl)
    {
        try {
            $url = "https://chatapi.viber.com/pa/set_webhook";

            $data = [
                'auth_token' => $this->authToken,
                'url' => $webhookUrl,
                'event_types' => [
                    'delivered',
                    'seen',
                    'failed',
                    'subscribed',
                    'unsubscribed',
                    'conversation_started',
                    'message'
                ]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            return $httpCode === 200 && $result['status'] === 0;

        } catch (Exception $e) {
            return false;
        }
    }
}
