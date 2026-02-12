<?php

declare(strict_types=1);

namespace EduCRM\Services\gateways;

/**
 * Abstract HTTP Gateway - Base class for all HTTP-based messaging gateways
 * 
 * This provides standardized functionality for:
 * - HTTP client initialization
 * - Phone number formatting (E.164)
 * - Common logging patterns
 * - Capability detection
 * - Connection testing
 * 
 * Gateways extending this class: \EduCRM\Services\gateways\TwilioGateway, WhatsAppGateway, ViberGateway
 * Non-HTTP gateways (\EduCRM\Services\gateways\SMPPGateway, GammuGateway) extend MessagingService directly
 * 
 * @package EduCRM\Services\Gateways
 */

require_once __DIR__ . '/../MessagingService.php';
require_once __DIR__ . '/../../Helpers/HttpClient.php';

use App\Helpers\HttpClient;
use App\Helpers\HttpResponse;

abstract class AbstractHttpGateway extends \EduCRM\Services\MessagingService
{
    protected ?HttpClient $http = null;
    protected string $gatewayType = 'sms';
    protected array $capabilities = [];

    /**
     * Constructor - sets up HTTP client with logging
     */
    public function __construct(\PDO $pdo, ?array $gateway = null)
    {
        parent::__construct($pdo, $gateway);
        $this->initHttpClient();
    }

    /**
     * Initialize HTTP client with logger
     * Override in subclasses for specific API configurations
     */
    protected function initHttpClient(): void
    {
        $this->http = \App\Helpers\HttpClient::forMessaging(function ($level, $message, $context) {
            $this->log($level, "[HTTP] " . $message, $context);
        });
    }

    /**
     * Get supported capabilities of this gateway
     * 
     * @return array<string> List of capability names
     */
    public function getCapabilities(): array
    {
        $caps = $this->capabilities;

        // Auto-detect capabilities from implemented interfaces
        if ($this instanceof \EduCRM\Contracts\MediaCapableInterface) {
            $caps[] = 'media';
        }
        if ($this instanceof \EduCRM\Contracts\TemplateCapableInterface) {
            $caps[] = 'templates';
        }
        if ($this instanceof \EduCRM\Contracts\InteractiveCapableInterface) {
            $caps[] = 'interactive';
        }
        if ($this instanceof \EduCRM\Contracts\BalanceCheckableInterface) {
            $caps[] = 'balance';
        }
        if ($this instanceof \EduCRM\Contracts\WebhookCapableInterface) {
            $caps[] = 'webhooks';
        }

        return array_unique($caps);
    }

    /**
     * Get the gateway type
     * 
     * @return string The gateway type (sms, whatsapp, viber, email)
     */
    public function getType(): string
    {
        return $this->gatewayType;
    }

    /**
     * Check if gateway has a specific capability
     * 
     * @param string $capability Capability name
     * @return bool True if capability is supported
     */
    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->getCapabilities());
    }

    /**
     * Format phone number to E.164 format
     * Standardized across all gateways
     * 
     * @param string $phone Phone number to format
     * @param string|null $countryCode Default country code (uses config if null)
     * @return string Formatted phone number
     */
    public function formatPhone($phone, $countryCode = null)
    {
        // Use provided code, gateway config, or default
        $defaultCode = $countryCode ?? $this->defaultCountryCode ?? '+1';

        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        // If doesn't start with +, add default country code
        if (substr($cleaned, 0, 1) !== '+') {
            // Remove leading 0 if present (common in local formats)
            $cleaned = ltrim($cleaned, '0');
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
     * Format phone for specific platform (e.g., WhatsApp prefix)
     * Override in subclasses as needed
     * 
     * @param string $phone Phone number
     * @param string $prefix Platform prefix (e.g., 'whatsapp:')
     * @return string Formatted phone with prefix
     */
    protected function formatPhoneForPlatform(string $phone, string $prefix = ''): string
    {
        $formatted = $this->formatPhone($phone);
        return $prefix . $formatted;
    }

    /**
     * Validate phone number format (E.164)
     * 
     * @param string $recipient Phone number to validate
     * @return bool True if valid
     */
    public function validateRecipient(string $recipient): bool
    {
        // Remove spaces and formatting
        $cleaned = preg_replace('/[\s\-\(\)]/', '', $recipient);
        
        // Check format: optional +, 10-15 digits
        return preg_match('/^[+]?[0-9]{10,15}$/', $cleaned) === 1;
    }

    /**
     * Create standardized success result
     * 
     * @param string|null $messageId Message ID from provider
     * @param string $status Message status
     * @param array $extra Additional data to include
     * @return array Standardized result array
     */
    protected function successResult(?string $messageId, string $status = 'sent', array $extra = []): array
    {
        return array_merge([
            'success' => true,
            'message_id' => $messageId,
            'status' => $status
        ], $extra);
    }

    /**
     * Create standardized error result
     * 
     * @param string $error Error message
     * @param array $extra Additional data to include
     * @return array Standardized result array
     */
    protected function errorResult(string $error, array $extra = []): array
    {
        return array_merge([
            'success' => false,
            'error' => $error
        ], $extra);
    }

    /**
     * Handle HTTP response and convert to standardized messaging result
     * 
     * @param HttpResponse $response The HTTP response
     * @param string $messageIdPath Path to message ID in response JSON
     * @param string $errorPath Path to error message in response JSON
     * @param callable|null $customHandler Custom handler for special cases
     * @return array Standardized result array
     */
    protected function handleHttpResponse(
        HttpResponse $response,
        string $messageIdPath = 'id',
        string $errorPath = 'error.message',
        ?callable $customHandler = null
    ): array {
        // Handle cURL errors
        if ($response->hasError()) {
            $this->log('ERROR', "HTTP request failed", ['error' => $response->error]);
            return $this->errorResult("cURL error: {$response->error}");
        }

        // Custom handler for provider-specific logic
        if ($customHandler) {
            $result = $customHandler($response);
            if ($result !== null) {
                return $result;
            }
        }

        // Default handling
        if ($response->isSuccess()) {
            $messageId = $response->get($messageIdPath);
            $this->log('INFO', "Message sent successfully", ['message_id' => $messageId]);
            return $this->successResult($messageId);
        }

        // Error response
        $error = $response->get($errorPath) ?? "HTTP {$response->statusCode} error";
        $this->log('ERROR', "API error", ['error' => $error, 'http_code' => $response->statusCode]);
        return $this->errorResult($error);
    }

    /**
     * Test connection by making a simple API call
     * Override in subclasses for specific test endpoints
     * 
     * @return bool True if connection successful
     */
    abstract public function testConnection(): bool;

    /**
     * Get test endpoint URL for connection testing
     * Override in subclasses
     * 
     * @return string|null Test endpoint URL
     */
    protected function getTestEndpoint(): ?string
    {
        return null;
    }

    /**
     * Process test response
     * Override for provider-specific response handling
     * 
     * @param HttpResponse $response Test endpoint response
     * @return bool True if test passed
     */
    protected function processTestResponse(HttpResponse $response): bool
    {
        return $response->isSuccess();
    }

    /**
     * Common test connection implementation
     * 
     * @return bool True if connection test passed
     */
    protected function doTestConnection(): bool
    {
        $endpoint = $this->getTestEndpoint();
        if (!$endpoint) {
            $this->log('WARNING', "No test endpoint defined for gateway");
            return false;
        }

        $this->log('INFO', "Testing connection", ['endpoint' => $endpoint]);

        try {
            $response = $this->http->get($endpoint);
            $success = $this->processTestResponse($response);

            $this->log($success ? 'INFO' : 'ERROR', 
                "Connection test " . ($success ? 'passed' : 'failed'),
                ['http_code' => $response->statusCode]
            );

            return $success;
        } catch (\Exception $e) {
            $this->log('ERROR', "Connection test exception", ['error' => $e->getMessage()]);
            return false;
        }
    }
}
