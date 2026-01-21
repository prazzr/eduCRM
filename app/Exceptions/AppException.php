<?php
declare(strict_types=1);

namespace EduCRM\Exceptions;

/**
 * Base exception for all application-specific exceptions
 * 
 * All custom exceptions should extend this class to allow
 * for consistent exception handling throughout the application.
 */
class AppException extends \Exception
{
    /**
     * Additional context data for the exception
     * 
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Create a new application exception
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous exception
     * @param array<string, mixed> $context Additional context data
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get the exception context
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get a specific context value
     *
     * @param string $key The context key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function getContextValue(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Convert exception to array for logging/API responses
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'context' => $this->context,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }
}
