<?php

declare(strict_types=1);

namespace EduCRM\Services\gateways;

/**
 * Viber Gateway Implementation
 * Supports Viber Bot API
 * 
 * Capabilities: media, interactive (keyboards), webhooks
 * 
 * @package EduCRM\Services\Gateways
 */

require_once __DIR__ . '/AbstractHttpGateway.php';

use EduCRM\Contracts\MediaCapableInterface;
use EduCRM\Contracts\InteractiveCapableInterface;
use EduCRM\Contracts\WebhookCapableInterface;

class ViberGateway extends \EduCRM\Services\gateways\AbstractHttpGateway implements MediaCapableInterface, InteractiveCapableInterface, WebhookCapableInterface
{
    private string $authToken = '';
    private string $botName = 'eduCRM Bot';
    private string $botAvatar = '';

    protected string $gatewayType = 'viber';
    protected array $capabilities = ['viber', 'media', 'interactive', 'webhooks'];

    private const API_BASE = 'https://chatapi.viber.com/pa';

    public function __construct(\PDO $pdo, ?array $gateway = null)
    {
        parent::__construct($pdo, $gateway);

        if ($this->config) {
            $this->authToken = $this->config['auth_token'] ?? '';
            $this->botName = $this->config['bot_name'] ?? 'eduCRM Bot';
            $this->botAvatar = $this->config['bot_avatar'] ?? '';
        }
    }

    /**
     * Build standard Viber request data
     */
    private function buildBaseData(string $recipient): array
    {
        $data = [
            'auth_token' => $this->authToken,
            'receiver' => $recipient,
            'sender' => ['name' => $this->botName]
        ];

        if ($this->botAvatar) {
            $data['sender']['avatar'] = $this->botAvatar;
        }

        return $data;
    }

    /**
     * Handle Viber API response
     */
    private function handleViberResponse(\App\Helpers\HttpResponse $response, string $recipient): array
    {
        if ($response->hasError()) {
            $this->log('ERROR', "Viber cURL error", ['error' => $response->error]);
            return $this->errorResult("cURL error: {$response->error}");
        }

        $status = $response->get('status');
        
        if ($response->isSuccess() && $status === 0) {
            $messageToken = $response->get('message_token');
            $this->log('INFO', "Viber message sent successfully", [
                'recipient' => $recipient,
                'message_token' => $messageToken
            ]);
            return $this->successResult($messageToken);
        }

        $error = $response->get('status_message') ?? 'Unknown Viber error';
        $this->log('ERROR', "Viber send failed", [
            'recipient' => $recipient,
            'status_code' => $status,
            'error' => $error
        ]);
        return $this->errorResult($error);
    }

    /**
     * Send Viber message
     */
    public function send(string $recipient, string $message, array $options = []): array
    {
        $this->log('INFO', "Sending Viber message", ['recipient' => $recipient]);

        try {
            $data = $this->buildBaseData($recipient);
            $data['type'] = 'text';
            $data['text'] = $message;

            $response = $this->http->postJson(self::API_BASE . '/send_message', $data);
            return $this->handleViberResponse($response, $recipient);

        } catch (\Exception $e) {
            $this->log('ERROR', "Viber exception: " . $e->getMessage(), ['recipient' => $recipient]);
            return $this->errorResult($e->getMessage());
        }
    }

    /**
     * Send media message (image, video, file)
     * 
     * @implements MediaCapableInterface::sendMedia
     */
    public function sendMedia(string $recipient, string $mediaUrl, string $caption = '', string $mediaType = 'image'): array
    {
        $this->log('INFO', "Sending Viber media", [
            'recipient' => $recipient,
            'media_type' => $mediaType
        ]);

        try {
            // Map media types to Viber types
            $viberType = match ($mediaType) {
                'image' => 'picture',
                'video' => 'video',
                'document', 'file' => 'file',
                default => 'picture'
            };

            $data = $this->buildBaseData($recipient);
            $data['type'] = $viberType;
            $data['media'] = $mediaUrl;

            if ($caption && $viberType === 'picture') {
                $data['text'] = $caption;
            }

            $response = $this->http->postJson(self::API_BASE . '/send_message', $data);
            return $this->handleViberResponse($response, $recipient);

        } catch (\Exception $e) {
            $this->log('ERROR', "Viber media exception: " . $e->getMessage());
            return $this->errorResult($e->getMessage());
        }
    }

    /**
     * Get supported media types
     * 
     * @implements MediaCapableInterface::getSupportedMediaTypes
     */
    public function getSupportedMediaTypes(): array
    {
        return ['image', 'video', 'file', 'document'];
    }

    /**
     * Send message with interactive buttons
     * 
     * @implements InteractiveCapableInterface::sendWithButtons
     */
    public function sendWithButtons(string $recipient, string $message, array $buttons): array
    {
        return $this->sendWithKeyboard($recipient, $message, $buttons);
    }

    /**
     * Send message with keyboard
     * 
     * @implements InteractiveCapableInterface::sendWithKeyboard
     */
    public function sendWithKeyboard(string $recipient, string $message, array $options): array
    {
        $this->log('INFO', "Sending Viber message with keyboard", [
            'recipient' => $recipient,
            'buttons_count' => count($options)
        ]);

        try {
            $keyboard = [
                'Type' => 'keyboard',
                'Buttons' => []
            ];

            foreach ($options as $button) {
                $keyboard['Buttons'][] = [
                    'ActionType' => 'reply',
                    'ActionBody' => $button['payload'] ?? $button['id'] ?? $button['text'],
                    'Text' => $button['text'],
                    'TextSize' => 'regular'
                ];
            }

            $data = $this->buildBaseData($recipient);
            $data['type'] = 'text';
            $data['text'] = $message;
            $data['keyboard'] = $keyboard;

            $response = $this->http->postJson(self::API_BASE . '/send_message', $data);
            return $this->handleViberResponse($response, $recipient);

        } catch (\Exception $e) {
            return $this->errorResult($e->getMessage());
        }
    }

    /**
     * Get message status
     */
    public function getStatus(string $messageId): array
    {
        // Viber doesn't have a direct status API - status updates come via webhooks
        $this->log('DEBUG', "Viber status query (via webhooks only)", ['message_id' => $messageId]);
        return ['status' => 'sent', 'delivered_at' => null, 'error' => null];
    }

    /**
     * Test connection
     */
    public function testConnection(): bool
    {
        $this->log('INFO', "Testing Viber connection");

        try {
            $response = $this->http->postJson(self::API_BASE . '/get_account_info', [
                'auth_token' => $this->authToken
            ]);

            if ($response->hasError()) {
                $this->log('ERROR', "Viber connection test cURL error", ['error' => $response->error]);
                return false;
            }

            $success = $response->isSuccess() && $response->get('status') === 0;

            $this->log($success ? 'INFO' : 'ERROR', 
                "Viber connection test " . ($success ? 'passed' : 'failed'), [
                    'http_code' => $response->statusCode,
                    'status' => $response->get('status'),
                    'bot_name' => $response->get('name')
                ]
            );

            return $success;

        } catch (\Exception $e) {
            $this->log('ERROR', "Viber connection test exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Register webhook
     * 
     * @implements WebhookCapableInterface::registerWebhook
     */
    public function registerWebhook(string $webhookUrl, array $events = []): array
    {
        $this->log('INFO', "Setting Viber webhook", ['url' => $webhookUrl]);

        try {
            $data = [
                'auth_token' => $this->authToken,
                'url' => $webhookUrl,
                'event_types' => !empty($events) ? $events : [
                    'delivered', 'seen', 'failed', 
                    'subscribed', 'unsubscribed',
                    'conversation_started', 'message'
                ]
            ];

            $response = $this->http->postJson(self::API_BASE . '/set_webhook', $data);

            if ($response->isSuccess() && $response->get('status') === 0) {
                return [
                    'success' => true,
                    'webhook_id' => null,
                    'error' => null
                ];
            }

            return [
                'success' => false,
                'webhook_id' => null,
                'error' => $response->get('status_message') ?? 'Failed to set webhook'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'webhook_id' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process webhook payload
     * 
     * @implements WebhookCapableInterface::processWebhook
     */
    public function processWebhook(array $payload): array
    {
        $event = $payload['event'] ?? 'unknown';
        
        return [
            'type' => $event,
            'data' => [
                'message_token' => $payload['message_token'] ?? null,
                'user_id' => $payload['sender']['id'] ?? $payload['user_id'] ?? null,
                'timestamp' => $payload['timestamp'] ?? null,
                'message' => $payload['message'] ?? null
            ]
        ];
    }
}
