<?php

declare(strict_types=1);

namespace EduCRM\Services\gateways;

/**
 * ntfy Gateway Implementation
 * Supports self-hosted ntfy server for push notifications
 */

require_once __DIR__ . '/AbstractHttpGateway.php';

class NtfyGateway extends \EduCRM\Services\gateways\AbstractHttpGateway
{
    private string $ntfyUrl = 'http://localhost:8090';
    private string $topicPrefix = 'educrm';
    private ?string $accessToken = null;

    protected string $gatewayType = 'push';
    protected array $capabilities = ['push', 'actions', 'priority', 'tags'];

    public function __construct(\PDO $pdo, ?array $gateway = null)
    {
        parent::__construct($pdo, $gateway);

        if ($this->config) {
            $this->ntfyUrl = $this->config['url'] ?? 'http://localhost:8090';
            $this->topicPrefix = $this->config['topic_prefix'] ?? 'educrm';
            $this->accessToken = $this->config['access_token'] ?? null;
        }
    }

    /**
     * Send ntfy notification
     */
    public function send(string $recipient, string $message, array $options = []): array
    {
        // For ntfy, the "recipient" is the topic name (without prefix)
        // If recipient starts with prefix, use as is, otherwise prepend
        $topic = $recipient;
        if (!empty($this->topicPrefix) && strpos($recipient, $this->topicPrefix) !== 0) {
            $topic = $this->topicPrefix . '-' . $recipient;
        }

        $this->log('INFO', "Sending ntfy notification", [
            'recipient' => $recipient,
            'topic' => $topic
        ]);

        try {
            $headers = [];

            // Add Title
            if (!empty($options['title'])) {
                $headers['Title'] = $options['title'];
            }

            // Add Priority
            if (!empty($options['priority'])) {
                $headers['Priority'] = (string) $options['priority'];
            }

            // Add Tags
            if (!empty($options['tags'])) {
                $headers['Tags'] = is_array($options['tags']) ? implode(',', $options['tags']) : $options['tags'];
            }

            // Add Click Action
            if (!empty($options['click'])) {
                $headers['Click'] = $options['click'];
            }

            // Add Actions
            if (!empty($options['actions'])) {
                $headers['Actions'] = $this->formatActions($options['actions']);
            }

            // Add Auth
            if ($this->accessToken) {
                $headers['Authorization'] = 'Bearer ' . $this->accessToken;
            }

            // Send request
            // Clean URL and append topic
            $url = rtrim($this->ntfyUrl, '/') . '/' . $topic;

            // Using raw body for message
            $response = $this->http->post($url, $message, $headers);

            if ($response->hasError()) {
                $this->log('ERROR', "ntfy cURL error", ['error' => $response->error]);
                return $this->errorResult("cURL error: {$response->error}");
            }

            // ntfy returns JSON with id/time on success
            $result = $response->getJson();

            if ($response->isSuccess()) {
                $messageId = $result['id'] ?? uniqid('ntfy_');
                $this->log('INFO', "ntfy sent successfully", [
                    'topic' => $topic,
                    'message_id' => $messageId
                ]);
                return $this->successResult($messageId);
            }

            $error = $result['error'] ?? 'Unknown ntfy error';
            $this->log('ERROR', "ntfy send failed", [
                'topic' => $topic,
                'status_code' => $response->statusCode,
                'error' => $error
            ]);
            return $this->errorResult($error);

        } catch (\Exception $e) {
            $this->log('ERROR', "ntfy exception: " . $e->getMessage());
            return $this->errorResult($e->getMessage());
        }
    }

    /**
     * Format actions for ntfy header
     */
    private function formatActions(array $actions): string
    {
        $formatted = [];
        foreach ($actions as $action) {
            if (isset($action['action'], $action['label'])) {
                $parts = [$action['action'], $action['label']];
                if (isset($action['url'])) {
                    $parts[] = $action['url'];
                }
                $formatted[] = implode(', ', $parts);
            }
        }
        return implode('; ', $formatted);
    }

    /**
     * Test connection
     */
    public function testConnection(): bool
    {
        $this->log('INFO', "Testing ntfy connection", ['url' => $this->ntfyUrl]);

        try {
            $url = rtrim($this->ntfyUrl, '/') . '/v1/health';
            $response = $this->http->get($url);

            if ($response->isSuccess()) {
                $data = $response->getJson();
                return isset($data['healthy']) && $data['healthy'] === true;
            }

            // Fallback: Try checking version if health check fails or doesn't exist on older versions
            $url = rtrim($this->ntfyUrl, '/') . '/v1/json';
            $response = $this->http->get($url);

            return $response->isSuccess();

        } catch (\Exception $e) {
            $this->log('ERROR', "ntfy connection test exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get status (ntfy doesn't support polling status easily without stream, assuming delivered)
     */
    public function getStatus(string $messageId): array
    {
        return ['status' => 'sent', 'delivered_at' => null, 'error' => null];
    }
}
