<?php
declare(strict_types=1);

namespace EduCRM\Exceptions;

/**
 * Exception for validation errors
 * 
 * Thrown when input validation fails. Contains the validation
 * errors as context for detailed error reporting.
 */
class ValidationException extends \EduCRM\Exceptions\AppException
{
    /**
     * @var array<string, string[]> Field-specific validation errors
     */
    protected array $errors = [];

    /**
     * Create a new validation exception
     *
     * @param string $message The main error message
     * @param array<string, string[]> $errors Field-specific errors
     * @param int $code HTTP status code (default 422)
     */
    public function __construct(
        string $message = "Validation failed",
        array $errors = [],
        int $code = 422
    ) {
        $this->errors = $errors;
        parent::__construct($message, $code, null, ['errors' => $errors]);
    }

    /**
     * Get all validation errors
     *
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field
     *
     * @param string $field The field name
     * @return string[]
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Check if a field has errors
     *
     * @param string $field The field name
     * @return bool
     */
    public function hasFieldError(string $field): bool
    {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }

    /**
     * Create from an array of field errors
     *
     * @param array<string, string|string[]> $errors
     * @return static
     */
    public static function fromErrors(array $errors): static
    {
        $normalized = [];
        foreach ($errors as $field => $messages) {
            $normalized[$field] = is_array($messages) ? $messages : [$messages];
        }
        return new static("Validation failed", $normalized);
    }
}
