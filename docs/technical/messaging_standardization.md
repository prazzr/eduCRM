# Messaging System Standardization

## Overview

This document describes the refactored messaging system architecture that provides **plug-and-play** support for multiple messaging protocols: SMS (SMPP, Twilio), WhatsApp, Viber, and Gammu (local GSM modem).

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        MessagingGatewayInterface                            │
│  + send(), queue(), getStatus(), validateRecipient(), getName()            │
│  + testConnection(), getCapabilities(), getType()                           │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┴───────────────┐
                    ▼                               ▼
     ┌──────────────────────────┐    ┌──────────────────────────┐
     │    MessagingService      │    │   AbstractHttpGateway    │
     │    (Socket/CLI based)    │    │   (HTTP API based)       │
     │    - SMPP, Gammu         │    │   - HttpClient helper    │
     └──────────────────────────┘    └──────────────────────────┘
              │                               │
     ┌────────┴────────┐           ┌──────────┼──────────┐
     ▼                 ▼           ▼          ▼          ▼
 SMPPGateway    GammuGateway   Twilio   WhatsApp    Viber
                               Gateway   Gateway    Gateway
```

## Capability Interfaces

### Core Interface: `MessagingGatewayInterface`

All gateways implement this interface:

```php
interface MessagingGatewayInterface {
    public function send(string $recipient, string $message, array $options = []): array;
    public function queue(string $recipient, string $message, array $options = []): int|false;
    public function getStatus(string $messageId): array;
    public function validateRecipient(string $recipient): bool;
    public function getName(): string;
    public function testConnection(): bool;
    public function getCapabilities(): array;
    public function getType(): string;
}
```

### Optional Capability Interfaces

Gateways can implement additional interfaces for extended functionality:

| Interface | Methods | Implementing Gateways |
|-----------|---------|----------------------|
| `MediaCapableInterface` | `sendMedia()`, `getSupportedMediaTypes()` | Twilio, WhatsApp, Viber |
| `TemplateCapableInterface` | `sendTemplate()`, `getTemplates()` | WhatsApp |
| `InteractiveCapableInterface` | `sendWithButtons()`, `sendWithKeyboard()` | Viber, WhatsApp |
| `BalanceCheckableInterface` | `getBalance()` | Twilio |
| `WebhookCapableInterface` | `registerWebhook()`, `processWebhook()` | Viber, WhatsApp |

## Gateway Capabilities Matrix

| Gateway | Type | Capabilities |
|---------|------|-------------|
| **TwilioGateway** | sms | sms, mms, media, balance |
| **SMPPGateway** | sms | sms, smpp |
| **GammuGateway** | sms | sms, local_modem |
| **WhatsAppGateway** | whatsapp | whatsapp, media, templates, interactive, webhooks |
| **ViberGateway** | viber | viber, media, interactive, webhooks |

## Plug-and-Play Usage

### 1. Create Gateway via Factory

```php
use MessagingFactory;

// Initialize factory
MessagingFactory::init($pdo);

// Create specific gateway
$smsGateway = MessagingFactory::create($gatewayId, 'sms');

// Or get best available gateway for type
$whatsappGateway = MessagingFactory::getBestGateway('whatsapp');

// Send with automatic failover
$result = MessagingFactory::sendWithFailover($phone, $message, 'sms');
```

### 2. Check Capabilities at Runtime

```php
// Check if gateway supports media
if ($gateway instanceof MediaCapableInterface) {
    $result = $gateway->sendMedia($recipient, $imageUrl, 'Check this out!', 'image');
}

// Or use getCapabilities()
if (in_array('media', $gateway->getCapabilities())) {
    // Gateway supports media
}
```

### 3. Standardized Response Format

All gateways return consistent response arrays:

```php
// Success response
[
    'success' => true,
    'message_id' => 'SM123456789',
    'status' => 'sent'
]

// Error response
[
    'success' => false,
    'error' => 'Invalid recipient format'
]
```

## HttpClient Helper

The `HttpClient` helper class eliminates ~200+ lines of duplicated cURL code across HTTP-based gateways.

### Features

- Fluent interface for configuration
- Built-in logging support
- Automatic JSON parsing
- Standard response wrapper (`HttpResponse`)
- Factory methods for common API patterns

### Usage

```php
use App\Helpers\HttpClient;

// Create client for messaging
$http = HttpClient::forMessaging($loggerCallback);

// POST JSON
$response = $http->postJson($url, $data);

// POST form data with auth
$response = $http->postForm($url, $data, [], [$username, $password]);

// Check response
if ($response->isSuccess()) {
    $messageId = $response->get('messages.0.id');
}
```

### Pre-configured Factory Methods

```php
// Twilio API
$http = HttpClient::forTwilio($accountSid, $authToken, $logger);

// Meta/WhatsApp Cloud API
$http = HttpClient::forMetaCloudAPI($accessToken, $logger);

// Viber Bot API
$http = HttpClient::forViber($logger);

// 360Dialog API
$http = HttpClient::for360Dialog($apiKey, $logger);
```

## Adding New Gateways

### Step 1: Choose Base Class

- **HTTP API-based**: Extend `AbstractHttpGateway`
- **Socket/CLI-based**: Extend `MessagingService`

### Step 2: Implement Required Methods

```php
class MyNewGateway extends AbstractHttpGateway {
    protected string $gatewayType = 'sms';
    protected array $capabilities = ['sms', 'custom_feature'];

    public function send(string $recipient, string $message, array $options = []): array {
        // Your implementation
        $response = $this->http->postJson($apiUrl, $data);
        return $this->handleHttpResponse($response, 'id', 'error.message');
    }

    public function testConnection(): bool {
        // Test API connectivity
        $response = $this->http->get($testEndpoint);
        return $response->isSuccess();
    }
}
```

### Step 3: Implement Optional Capability Interfaces

```php
class MyNewGateway extends AbstractHttpGateway implements MediaCapableInterface {
    
    public function sendMedia(string $recipient, string $mediaUrl, string $caption = '', string $mediaType = 'image'): array {
        // Media sending logic
    }

    public function getSupportedMediaTypes(): array {
        return ['image', 'video', 'audio'];
    }
}
```

### Step 4: Register in Factory

Update `MessagingFactory.php` to recognize the new gateway provider.

## File Structure

```
includes/
├── Contracts/
│   └── MessagingGatewayInterface.php      # Core + capability interfaces
├── Helpers/
│   └── HttpClient.php                      # Reusable HTTP client
└── services/
    ├── MessagingService.php                # Abstract base (socket/CLI)
    ├── MessagingFactory.php                # Gateway factory
    └── gateways/
        ├── AbstractHttpGateway.php         # Abstract base (HTTP APIs)
        ├── TwilioGateway.php               # Twilio SMS/MMS
        ├── SMPPGateway.php                 # SMPP v3.4 protocol
        ├── GammuGateway.php                # Local GSM modem
        ├── WhatsAppGateway.php             # WhatsApp (multi-provider)
        └── ViberGateway.php                # Viber Bot API
```

## Benefits of Standardization

1. **Plug-and-Play**: Add new gateways by implementing the interface
2. **Runtime Capability Detection**: Check what features a gateway supports
3. **Code Reuse**: ~200+ lines of cURL code eliminated via HttpClient
4. **Consistent APIs**: Same method signatures across all gateways
5. **Automatic Failover**: MessagingFactory handles gateway failures
6. **Type Safety**: PHP 8 strict types throughout
7. **Extensible**: Optional interfaces for advanced features

## Migration Notes

### Breaking Changes

- `getBalance()` now returns `array` instead of `float|null`
- `testConnection()` is now required (abstract in base class)
- All gateways must implement `getCapabilities()` and `getType()`

### Backward Compatibility

- Existing gateway method signatures unchanged
- Factory patterns work the same way
- Queue and logging systems unchanged
