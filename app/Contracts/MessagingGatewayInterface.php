<?php
declare(strict_types=1);

namespace EduCRM\Contracts;

/**
 * Core Interface for messaging gateway implementations
 * 
 * All messaging gateways (SMS, WhatsApp, Email, Viber, SMPP, Gammu) must implement
 * this interface to ensure consistent plug-and-play message sending behavior.
 * 
 * Gateways may additionally implement optional capability interfaces:
 * - MediaCapableInterface: For gateways that support media/file sending
 * - TemplateCapableInterface: For gateways with template support (WhatsApp, etc.)
 * - InteractiveCapableInterface: For gateways supporting buttons/keyboards
 * - BalanceCheckableInterface: For gateways with balance/credit checking
 * 
 * @see MediaCapableInterface
 * @see TemplateCapableInterface
 * @see InteractiveCapableInterface
 * @see BalanceCheckableInterface
 */
interface MessagingGatewayInterface
{
    /**
     * Send a message to a recipient
     *
     * @param string $recipient The recipient identifier (phone number, email, etc.)
     * @param string $message The message content
     * @param array<string, mixed> $options Additional options (template_id, variables, etc.)
     * @return array{success: bool, message_id: string|null, error: string|null, status?: string}
     */
    public function send(string $recipient, string $message, array $options = []): array;

    /**
     * Queue a message for later delivery
     *
     * @param string $recipient The recipient identifier
     * @param string $message The message content
     * @param array<string, mixed> $options Additional options including scheduled_at
     * @return int|false The queue ID or false on failure
     */
    public function queue(string $recipient, string $message, array $options = []): int|false;

    /**
     * Check the delivery status of a sent message
     *
     * @param string $messageId The message identifier
     * @return array{status: string, delivered_at: string|null, error: string|null}
     */
    public function getStatus(string $messageId): array;

    /**
     * Validate a recipient identifier
     *
     * @param string $recipient The recipient to validate
     * @return bool True if valid, false otherwise
     */
    public function validateRecipient(string $recipient): bool;

    /**
     * Get the gateway name/identifier
     *
     * @return string The gateway name (e.g., 'twilio', 'meta_whatsapp', 'smpp', 'gammu')
     */
    public function getName(): string;

    /**
     * Test connectivity to the gateway
     *
     * @return bool True if connection successful, false otherwise
     */
    public function testConnection(): bool;

    /**
     * Get supported capabilities of this gateway
     * Allows runtime capability detection for plug-and-play
     *
     * @return array<string> List of capability names (e.g., ['media', 'templates', 'interactive'])
     */
    public function getCapabilities(): array;

    /**
     * Get the gateway type (sms, whatsapp, viber, email)
     *
     * @return string The gateway type
     */
    public function getType(): string;
}

/**
 * Interface for gateways that support sending media (images, videos, documents)
 */
interface MediaCapableInterface
{
    /**
     * Send a media message
     *
     * @param string $recipient The recipient identifier
     * @param string $mediaUrl URL of the media to send
     * @param string $caption Optional caption for the media
     * @param string $mediaType Type of media: 'image', 'video', 'document', 'audio'
     * @return array{success: bool, message_id: string|null, error: string|null}
     */
    public function sendMedia(string $recipient, string $mediaUrl, string $caption = '', string $mediaType = 'image'): array;

    /**
     * Get supported media types
     *
     * @return array<string> List of supported media types
     */
    public function getSupportedMediaTypes(): array;
}

/**
 * Interface for gateways that support message templates (WhatsApp Business API, etc.)
 */
interface TemplateCapableInterface
{
    /**
     * Send a template message
     *
     * @param string $recipient The recipient identifier
     * @param string $templateName The template name/ID
     * @param array<string, mixed> $variables Template variables
     * @param string $language Template language code (e.g., 'en', 'en_US')
     * @return array{success: bool, message_id: string|null, error: string|null}
     */
    public function sendTemplate(string $recipient, string $templateName, array $variables = [], string $language = 'en'): array;

    /**
     * Get available templates
     *
     * @return array<array{id: string, name: string, status: string, language: string}>
     */
    public function getTemplates(): array;
}

/**
 * Interface for gateways that support interactive elements (buttons, keyboards)
 */
interface InteractiveCapableInterface
{
    /**
     * Send a message with interactive buttons
     *
     * @param string $recipient The recipient identifier
     * @param string $message The message content
     * @param array<array{id: string, text: string, action?: string}> $buttons Button definitions
     * @return array{success: bool, message_id: string|null, error: string|null}
     */
    public function sendWithButtons(string $recipient, string $message, array $buttons): array;

    /**
     * Send a message with keyboard/quick replies
     *
     * @param string $recipient The recipient identifier
     * @param string $message The message content
     * @param array<array{text: string, payload?: string}> $options Quick reply options
     * @return array{success: bool, message_id: string|null, error: string|null}
     */
    public function sendWithKeyboard(string $recipient, string $message, array $options): array;
}

/**
 * Interface for gateways that support checking account balance/credits
 */
interface BalanceCheckableInterface
{
    /**
     * Get account balance or remaining credits
     *
     * @return array{balance: float|null, currency: string|null, credits?: int|null, error: string|null}
     */
    public function getBalance(): array;
}

/**
 * Interface for gateways that support webhook registration
 */
interface WebhookCapableInterface
{
    /**
     * Register a webhook URL for delivery receipts and incoming messages
     *
     * @param string $webhookUrl The webhook URL
     * @param array<string> $events Events to subscribe to
     * @return array{success: bool, webhook_id: string|null, error: string|null}
     */
    public function registerWebhook(string $webhookUrl, array $events = []): array;

    /**
     * Process an incoming webhook payload
     *
     * @param array<string, mixed> $payload The webhook payload
     * @return array{type: string, data: array<string, mixed>}
     */
    public function processWebhook(array $payload): array;
}
