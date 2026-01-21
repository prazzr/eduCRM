<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Reusable HTTP Client for API gateways
 * Standardizes cURL operations across all messaging gateways
 * 
 * This eliminates ~100+ lines of duplicated cURL code across:
 * - TwilioGateway
 * - WhatsAppGateway (3 providers)
 * - ViberGateway
 * 
 * @package App\Helpers
 */
class HttpClient
{
    private int $timeout = 30;
    private int $connectTimeout = 10;
    private bool $verifySSL = true;
    private ?string $userAgent = null;
    private array $defaultHeaders = [];
    /** @var callable|null */
    private $logger = null;

    /**
     * Set request timeout
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Set connection timeout
     */
    public function setConnectTimeout(int $seconds): self
    {
        $this->connectTimeout = $seconds;
        return $this;
    }

    /**
     * Set SSL verification (disable only for testing)
     */
    public function setVerifySSL(bool $verify): self
    {
        $this->verifySSL = $verify;
        return $this;
    }

    /**
     * Set custom user agent
     */
    public function setUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Set default headers for all requests
     */
    public function setDefaultHeaders(array $headers): self
    {
        $this->defaultHeaders = $headers;
        return $this;
    }

    /**
     * Set logger callback for debugging
     * 
     * @param callable $logger Function with signature: function(string $level, string $message, array $context)
     */
    public function setLogger(callable $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Log a message if logger is set
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            call_user_func($this->logger, $level, $message, $context);
        }
    }

    /**
     * Make a GET request
     * 
     * @param string $url The URL to request
     * @param array $headers Additional headers
     * @param array $auth Basic auth credentials ['username', 'password']
     * @return HttpResponse
     */
    public function get(string $url, array $headers = [], ?array $auth = null): HttpResponse
    {
        return $this->request('GET', $url, null, $headers, $auth);
    }

    /**
     * Make a POST request with JSON body
     * 
     * @param string $url The URL to request
     * @param array|null $data Data to send as JSON
     * @param array $headers Additional headers
     * @param array|null $auth Basic auth credentials ['username', 'password']
     * @return HttpResponse
     */
    public function postJson(string $url, ?array $data = null, array $headers = [], ?array $auth = null): HttpResponse
    {
        $headers = array_merge(['Content-Type: application/json'], $headers);
        $body = $data ? json_encode($data) : null;
        return $this->request('POST', $url, $body, $headers, $auth);
    }

    /**
     * Make a POST request with form-urlencoded body
     * 
     * @param string $url The URL to request
     * @param array $data Data to send as form data
     * @param array $headers Additional headers
     * @param array|null $auth Basic auth credentials ['username', 'password']
     * @return HttpResponse
     */
    public function postForm(string $url, array $data, array $headers = [], ?array $auth = null): HttpResponse
    {
        $headers = array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers);
        $body = http_build_query($data);
        return $this->request('POST', $url, $body, $headers, $auth);
    }

    /**
     * Make a raw POST request
     * 
     * @param string $url The URL to request
     * @param string|null $body Raw body content
     * @param array $headers Additional headers
     * @param array|null $auth Basic auth credentials
     * @return HttpResponse
     */
    public function post(string $url, ?string $body = null, array $headers = [], ?array $auth = null): HttpResponse
    {
        return $this->request('POST', $url, $body, $headers, $auth);
    }

    /**
     * Make a PUT request with JSON body
     */
    public function putJson(string $url, ?array $data = null, array $headers = [], ?array $auth = null): HttpResponse
    {
        $headers = array_merge(['Content-Type: application/json'], $headers);
        $body = $data ? json_encode($data) : null;
        return $this->request('PUT', $url, $body, $headers, $auth);
    }

    /**
     * Make a DELETE request
     */
    public function delete(string $url, array $headers = [], ?array $auth = null): HttpResponse
    {
        return $this->request('DELETE', $url, null, $headers, $auth);
    }

    /**
     * Core request method
     * 
     * @param string $method HTTP method
     * @param string $url The URL to request
     * @param string|null $body Request body
     * @param array $headers Additional headers
     * @param array|null $auth Basic auth credentials ['username', 'password']
     * @return HttpResponse
     */
    public function request(
        string $method,
        string $url,
        ?string $body = null,
        array $headers = [],
        ?array $auth = null
    ): HttpResponse {
        $this->log('DEBUG', "HTTP {$method} request", [
            'url' => $url,
            'has_body' => !empty($body),
            'has_auth' => !empty($auth)
        ]);

        $ch = curl_init($url);

        // Set method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Set return transfer
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Set timeouts
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);

        // SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySSL ? 2 : 0);

        // Set body for POST/PUT
        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        // Merge headers
        $allHeaders = array_merge($this->defaultHeaders, $headers);
        if (!empty($allHeaders)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        }

        // Set user agent
        if ($this->userAgent) {
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        }

        // Basic auth
        if ($auth && count($auth) === 2) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$auth[0]}:{$auth[1]}");
        }

        // Get response headers
        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$responseHeaders) {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) === 2) {
                $responseHeaders[strtolower(trim($header[0]))] = trim($header[1]);
            }
            return $len;
        });

        // Execute request
        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        // Create response object
        $response = new HttpResponse(
            $httpCode,
            $responseBody !== false ? $responseBody : '',
            $responseHeaders,
            $curlError,
            $curlErrno,
            $info
        );

        $this->log('DEBUG', "HTTP response received", [
            'http_code' => $httpCode,
            'has_error' => !empty($curlError),
            'response_size' => strlen($response->body)
        ]);

        return $response;
    }

    /**
     * Create a new \App\Helpers\HttpClient with common settings for messaging APIs
     */
    public static function forMessaging(?callable $logger = null): self
    {
        $client = new self();
        $client->setTimeout(30);
        $client->setConnectTimeout(10);
        $client->setUserAgent('EduCRM-Messaging/1.0');

        if ($logger) {
            $client->setLogger($logger);
        }

        return $client;
    }

    /**
     * Create client configured for Twilio API
     */
    public static function forTwilio(string $accountSid, string $authToken, ?callable $logger = null): self
    {
        $client = self::forMessaging($logger);
        $client->setDefaultHeaders(['Content-Type: application/x-www-form-urlencoded']);
        return $client;
    }

    /**
     * Create client configured for Meta/WhatsApp Cloud API
     */
    public static function forMetaCloudAPI(string $accessToken, ?callable $logger = null): self
    {
        $client = self::forMessaging($logger);
        $client->setDefaultHeaders([
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ]);
        return $client;
    }

    /**
     * Create client configured for Viber Bot API
     */
    public static function forViber(?callable $logger = null): self
    {
        $client = self::forMessaging($logger);
        $client->setDefaultHeaders(['Content-Type: application/json']);
        return $client;
    }

    /**
     * Create client configured for 360Dialog API
     */
    public static function for360Dialog(string $apiKey, ?callable $logger = null): self
    {
        $client = self::forMessaging($logger);
        $client->setDefaultHeaders([
            'Content-Type: application/json',
            'D360-API-KEY: ' . $apiKey
        ]);
        return $client;
    }
}

/**
 * HTTP Response wrapper class
 */
class HttpResponse
{
    public int $statusCode;
    public string $body;
    public array $headers;
    public string $error;
    public int $errorCode;
    public array $info;

    public function __construct(
        int $statusCode,
        string $body,
        array $headers = [],
        string $error = '',
        int $errorCode = 0,
        array $info = []
    ) {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->headers = $headers;
        $this->error = $error;
        $this->errorCode = $errorCode;
        $this->info = $info;
    }

    /**
     * Check if request was successful (2xx status)
     */
    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Check if there was a cURL error
     */
    public function hasError(): bool
    {
        return !empty($this->error) || $this->errorCode > 0;
    }

    /**
     * Get response body as JSON array
     */
    public function json(): ?array
    {
        if (empty($this->body)) {
            return null;
        }
        return json_decode($this->body, true);
    }

    /**
     * Get specific field from JSON response
     */
    public function get(string $path, $default = null)
    {
        $data = $this->json();
        if (!$data) {
            return $default;
        }

        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Create a standardized messaging result array
     */
    public function toMessagingResult(
        string $messageIdPath = 'id',
        string $errorPath = 'error.message'
    ): array {
        if ($this->hasError()) {
            return [
                'success' => false,
                'error' => "cURL error: {$this->error}"
            ];
        }

        if ($this->isSuccess()) {
            return [
                'success' => true,
                'message_id' => $this->get($messageIdPath),
                'status' => 'sent'
            ];
        }

        return [
            'success' => false,
            'error' => $this->get($errorPath) ?? "HTTP {$this->statusCode} error"
        ];
    }
}
