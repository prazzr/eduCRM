<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
 * Validation Service
 * Comprehensive input validation and sanitization
 */

class ValidationService
{
    /**
     * Sanitize input based on type
     */
    public static function sanitizeInput($input, $type = 'string')
    {
        if (is_array($input)) {
            return array_map(function ($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $input);
        }

        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);

            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);

            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);

            case 'html':
                // Allow safe HTML tags
                return strip_tags($input, '<p><br><strong><em><ul><ol><li><a>');

            default:
                // Default: strip all tags and encode special chars
                return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Validate email address
     */
    public static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate phone number
     */
    public static function validatePhone($phone)
    {
        // Remove spaces and dashes
        $phone = preg_replace('/[\s\-]/', '', $phone);
        // Check format: optional +, 10-15 digits
        return preg_match('/^[+]?[0-9]{10,15}$/', $phone);
    }

    /**
     * Validate URL
     */
    public static function validateUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate date format
     */
    public static function validateDate($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Validate required field
     */
    public static function required($value, $fieldName = 'Field')
    {
        if (empty($value) && $value !== '0') {
            throw new \Exception("$fieldName is required");
        }
        return true;
    }

    /**
     * Validate string length
     */
    public static function validateLength($value, $min = null, $max = null, $fieldName = 'Field')
    {
        $length = strlen($value);

        if ($min !== null && $length < $min) {
            throw new \Exception("$fieldName must be at least $min characters");
        }

        if ($max !== null && $length > $max) {
            throw new \Exception("$fieldName must not exceed $max characters");
        }

        return true;
    }

    /**
     * Validate numeric range
     */
    public static function validateRange($value, $min = null, $max = null, $fieldName = 'Field')
    {
        if ($min !== null && $value < $min) {
            throw new \Exception("$fieldName must be at least $min");
        }

        if ($max !== null && $value > $max) {
            throw new \Exception("$fieldName must not exceed $max");
        }

        return true;
    }

    /**
     * Validate against allowed values
     */
    public static function validateEnum($value, $allowedValues, $fieldName = 'Field')
    {
        if (!in_array($value, $allowedValues)) {
            throw new \Exception("$fieldName has invalid value");
        }
        return true;
    }

    /**
     * Sanitize filename
     */
    public static function sanitizeFilename($filename)
    {
        // Remove path traversal attempts
        $filename = basename($filename);
        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        return $filename;
    }

    /**
     * Validate and sanitize array of inputs
     */
    public static function validateArray($data, $rules)
    {
        $validated = [];
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;

            try {
                // Check required
                if (isset($rule['required']) && $rule['required']) {
                    self::required($value, $field);
                }

                // Skip validation if not required and empty
                if (empty($value) && !isset($rule['required'])) {
                    $validated[$field] = null;
                    continue;
                }

                // Sanitize
                $type = $rule['type'] ?? 'string';
                $value = self::sanitizeInput($value, $type);

                // Validate type-specific
                if (isset($rule['email']) && $rule['email']) {
                    if (!self::validateEmail($value)) {
                        throw new \Exception("Invalid email format");
                    }
                }

                if (isset($rule['phone']) && $rule['phone']) {
                    if (!self::validatePhone($value)) {
                        throw new \Exception("Invalid phone format");
                    }
                }

                if (isset($rule['url']) && $rule['url']) {
                    if (!self::validateUrl($value)) {
                        throw new \Exception("Invalid URL format");
                    }
                }

                // Validate length
                if (isset($rule['min']) || isset($rule['max'])) {
                    self::validateLength($value, $rule['min'] ?? null, $rule['max'] ?? null, $field);
                }

                // Validate range
                if (isset($rule['min_value']) || isset($rule['max_value'])) {
                    self::validateRange($value, $rule['min_value'] ?? null, $rule['max_value'] ?? null, $field);
                }

                // Validate enum
                if (isset($rule['enum'])) {
                    self::validateEnum($value, $rule['enum'], $field);
                }

                $validated[$field] = $value;

            } catch (\Exception $e) {
                $errors[$field] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            throw new \Exception(json_encode($errors));
        }

        return $validated;
    }
}
