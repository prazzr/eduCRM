<?php

declare(strict_types=1);

namespace EduCRM\Services\gateways;

/**
 * Twilio Gateway Implementation
 * Cloud-based SMS gateway using Twilio API
 * 
 * Supports: SMS, MMS
 * Capabilities: media, balance
 * 
 * @package EduCRM\Services\Gateways
 */

require_once __DIR__ . '/AbstractHttpGateway.php';

use EduCRM\Contracts\MediaCapableInterface;
use EduCRM\Contracts\BalanceCheckableInterface;

class TwilioGateway extends \EduCRM\Services\gateways\AbstractHttpGateway implements MediaCapableInterface, BalanceCheckableInterface
{
    private string $accountSid = '';
    private string $authToken = '';
    private string $fromNumber = '';

    protected string $gatewayType = 'sms';
    protected array $capabilities = ['sms', 'mms'];

    public function __construct(\PDO $pdo, ?array $gateway = null)
    {
        parent::__construct($pdo, $gateway);

        if ($this->config) {
            $this->accountSid = $this->config['account_sid'] ?? '';
            $this->authToken = $this->config['auth_token'] ?? '';
            $this->fromNumber = $this->config['from_number'] ?? '';
        }
    }

    /**
     * Get Twilio API base URL
     */
    private function getBaseUrl(): string
    {
        return "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}";
    }

    /**
     * Get authentication credentials for HTTP client
     */
    private function getAuth(): array
    {
        return [$this->accountSid, $this->authToken];
    }

    /**
     * Send SMS via Twilio API
     */
    public function send(string $recipient, string $message, array $options = []): array
    {
        $this->log('INFO', "Sending SMS via Twilio", ['recipient' => $recipient]);

        try {
            $recipient = $this->formatPhone($recipient);
            $url = $this->getBaseUrl() . "/Messages.json";

            $data = [
                'From' => $this->fromNumber,
                'To' => $recipient,
                'Body' => $message
            ];

            $this->log('DEBUG', "Twilio API request", [
                'from' => $this->fromNumber,
                'to' => $recipient
            ]);

            $response = $this->http->postForm($url, $data, [], $this->getAuth());

            return $this->handleHttpResponse($response, 'sid', 'message', function ($response) use ($recipient) {
                // Twilio returns 201 for success
                if ($response->statusCode === 201 && $response->get('sid')) {
                    $this->log('INFO', "Twilio SMS sent successfully", [
                        'recipient' => $recipient,
                        'message_id' => $response->get('sid'),
                        'status' => $response->get('status')
                    ]);
                    return $this->successResult($response->get('sid'), $response->get('status') ?? 'queued');
                }
                return null; // Let default handler process
            });

        } catch (\Exception $e) {
            $this->log('ERROR', "Twilio exception: " . $e->getMessage(), [
                'recipient' => $recipient
            ]);
            return $this->errorResult($e->getMessage());
        }
    }

    /**
     * Send MMS with media via Twilio API
     * 
     * @implements MediaCapableInterface::sendMedia
     */
    public function sendMedia(string $recipient, string $mediaUrl, string $caption = '', string $mediaType = 'image'): array
    {
        $this->log('INFO', "Sending MMS via Twilio", ['recipient' => $recipient, 'media_type' => $mediaType]);

        try {
            $recipient = $this->formatPhone($recipient);
            $url = $this->getBaseUrl() . "/Messages.json";

            $data = [
                'From' => $this->fromNumber,
                'To' => $recipient,
                'MediaUrl' => $mediaUrl
            ];

            if ($caption) {
                $data['Body'] = $caption;
            }

            $response = $this->http->postForm($url, $data, [], $this->getAuth());

            return $this->handleHttpResponse($response, 'sid', 'message', function ($response) {
                if ($response->statusCode === 201 && $response->get('sid')) {
                    return $this->successResult($response->get('sid'), 'queued');
                }
                return null;
            });

        } catch (\Exception $e) {
            $this->log('ERROR', "Twilio MMS exception: " . $e->getMessage());
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
        return ['image', 'gif', 'audio', 'video'];
    }

    /**
     * Get message status from Twilio
     */
    public function getStatus(string $messageId): array
    {
        $this->log('DEBUG', "Fetching Twilio message status", ['message_id' => $messageId]);

        try {
            $url = $this->getBaseUrl() . "/Messages/{$messageId}.json";
            $response = $this->http->get($url, [], $this->getAuth());

            if ($response->hasError()) {
                return ['status' => 'error', 'delivered_at' => null, 'error' => $response->error];
            }

            $this->log('DEBUG', "Twilio status response", [
                'message_id' => $messageId,
                'status' => $response->get('status') ?? 'unknown'
            ]);

            return [
                'status' => $response->get('status') ?? 'unknown',
                'delivered_at' => $response->get('date_sent'),
                'error' => null
            ];

        } catch (\Exception $e) {
            $this->log('ERROR', "Twilio status check failed: " . $e->getMessage());
            return ['status' => 'error', 'delivered_at' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get Twilio account balance
     * 
     * @implements BalanceCheckableInterface::getBalance
     */
    public function getBalance(): array
    {
        $this->log('DEBUG', "Fetching Twilio account balance");

        try {
            $url = $this->getBaseUrl() . "/Balance.json";
            $response = $this->http->get($url, [], $this->getAuth());

            if ($response->hasError()) {
                return ['balance' => null, 'currency' => null, 'error' => $response->error];
            }

            $balance = floatval($response->get('balance') ?? 0);
            $currency = $response->get('currency') ?? 'USD';

            $this->log('INFO', "Twilio balance retrieved", ['balance' => $balance, 'currency' => $currency]);

            return [
                'balance' => $balance,
                'currency' => $currency,
                'error' => null
            ];

        } catch (\Exception $e) {
            $this->log('ERROR', "Twilio balance check failed: " . $e->getMessage());
            return ['balance' => null, 'currency' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Test Twilio connection
     */
    public function testConnection(): bool
    {
        $this->log('INFO', "Testing Twilio connection");

        try {
            $url = $this->getBaseUrl() . ".json";
            $response = $this->http->get($url, [], $this->getAuth());

            $success = $response->isSuccess();
            $this->log($success ? 'INFO' : 'ERROR', 
                "Twilio connection test " . ($success ? 'passed' : 'failed'), 
                ['http_code' => $response->statusCode]
            );

            return $success;

        } catch (\Exception $e) {
            $this->log('ERROR', "Twilio connection test exception: " . $e->getMessage());
            return false;
        }
    }
}
