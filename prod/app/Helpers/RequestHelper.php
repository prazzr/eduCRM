<?php

declare(strict_types=1);

namespace EduCRM\Helpers;

/**
 * Request Helper
 * Centralized request handling utilities for ID validation, POST processing, and AJAX responses
 * 
 * Replaces duplicated patterns across all module files:
 * - ID validation (20+ files)
 * - POST input sanitization (15+ files)
 * - JSON response handling (5+ files)
 * 
 * @package EduCRM\Helpers
 * @version 1.0.0
 * @date January 6, 2026
 */
class RequestHelper
{
    /**
     * Validate and return ID from GET parameter
     * Replaces: $id = isset($_GET['id']) ? (int) $_GET['id'] : 0; if (!$id) die("Invalid ID");
     * 
     * @param string $param Parameter name (default: 'id')
     * @param bool $required Whether to halt on missing ID
     * @param string $errorMessage Custom error message
     * @return int|null The ID or null if not required and missing
     */
    public static function getIdParam(string $param = 'id', bool $required = true, string $errorMessage = ''): ?int
    {
        $id = isset($_GET[$param]) ? (int) $_GET[$param] : 0;
        
        if ($id <= 0 && $required) {
            $message = $errorMessage ?: "Invalid {$param}";
            self::abortWithError($message);
        }
        
        return $id > 0 ? $id : null;
    }
    
    /**
     * Validate and return ID from POST parameter
     * 
     * @param string $param Parameter name
     * @param bool $required Whether field is required
     * @return int|null The ID or null
     */
    public static function postIdParam(string $param, bool $required = false): ?int
    {
        $id = isset($_POST[$param]) ? (int) $_POST[$param] : 0;
        
        if ($id <= 0 && $required) {
            self::abortWithError("Missing required field: {$param}");
        }
        
        return $id > 0 ? $id : null;
    }
    
    /**
     * Get sanitized string from POST
     * Replaces: $name = sanitize($_POST['name']);
     * 
     * @param string $key POST parameter name
     * @param string $default Default value if empty
     * @param bool $required Whether field is required
     * @return string Sanitized value
     */
    public static function postString(string $key, string $default = '', bool $required = false): string
    {
        $value = trim($_POST[$key] ?? '');
        $sanitized = htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
        
        if (empty($sanitized) && $required) {
            self::abortWithError("Missing required field: {$key}");
        }
        
        return $sanitized ?: $default;
    }
    
    /**
     * Get email from POST with validation
     * 
     * @param string $key POST parameter name
     * @param bool $required Whether field is required
     * @return string|null Valid email or null
     */
    public static function postEmail(string $key = 'email', bool $required = false): ?string
    {
        $email = filter_var($_POST[$key] ?? '', FILTER_SANITIZE_EMAIL);
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::abortWithError("Invalid email format");
        }
        
        if (empty($email) && $required) {
            self::abortWithError("Email is required");
        }
        
        return !empty($email) ? $email : null;
    }
    
    /**
     * Get phone from POST with basic sanitization
     * 
     * @param string $key POST parameter name
     * @return string|null Sanitized phone or null
     */
    public static function postPhone(string $key = 'phone'): ?string
    {
        $phone = preg_replace('/[^0-9+\-\s()]/', '', $_POST[$key] ?? '');
        return !empty($phone) ? $phone : null;
    }
    
    /**
     * Get integer from POST
     * 
     * @param string $key POST parameter name
     * @param int|null $default Default value
     * @return int|null Integer value or default
     */
    public static function postInt(string $key, ?int $default = null): ?int
    {
        if (!isset($_POST[$key]) || $_POST[$key] === '') {
            return $default;
        }
        return (int) $_POST[$key];
    }
    
    /**
     * Get date from POST with validation
     * 
     * @param string $key POST parameter name
     * @param string $format Expected format
     * @return string|null Valid date or null
     */
    public static function postDate(string $key, string $format = 'Y-m-d'): ?string
    {
        $date = $_POST[$key] ?? '';
        if (empty($date)) {
            return null;
        }
        
        $d = \DateTime::createFromFormat($format, $date);
        if (!$d || $d->format($format) !== $date) {
            return null;
        }
        
        return $date;
    }
    
    /**
     * Check if request is POST method
     * 
     * @return bool
     */
    public static function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
    
    /**
     * Check if request is AJAX
     * 
     * @return bool
     */
    public static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Abort request with error message
     * Uses JSON for AJAX, die() for regular requests
     * 
     * @param string $message Error message
     * @param int $httpCode HTTP status code
     * @return never
     */
    public static function abortWithError(string $message, int $httpCode = 400): never
    {
        if (self::isAjax()) {
            self::jsonError($message, $httpCode);
        }
        
        http_response_code($httpCode);
        die($message);
    }
    
    /**
     * Send JSON success response
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @return never
     */
    public static function jsonSuccess(mixed $data = null, string $message = 'Success'): never
    {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], JSON_THROW_ON_ERROR);
        exit;
    }
    
    /**
     * Send JSON error response
     * 
     * @param string $message Error message
     * @param int $httpCode HTTP status code
     * @param array $errors Additional error details
     * @return never
     */
    public static function jsonError(string $message, int $httpCode = 400, array $errors = []): never
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], JSON_THROW_ON_ERROR);
        exit;
    }
    
    /**
     * Validate required POST fields exist
     * 
     * @param array $fields List of required field names
     * @return array Validation errors (empty if all valid)
     */
    public static function validateRequired(array $fields): array
    {
        $errors = [];
        
        foreach ($fields as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                $label = ucwords(str_replace('_', ' ', $field));
                $errors[$field] = "{$label} is required";
            }
        }
        
        return $errors;
    }
    
    /**
     * Get multiple POST values at once with sanitization
     * 
     * @param array $schema Array of [field => type] pairs (string, int, email, date)
     * @return array Sanitized values
     */
    public static function getPostData(array $schema): array
    {
        $data = [];
        
        foreach ($schema as $field => $type) {
            $data[$field] = match($type) {
                'int' => self::postInt($field),
                'email' => self::postEmail($field),
                'phone' => self::postPhone($field),
                'date' => self::postDate($field),
                default => self::postString($field)
            };
        }
        
        return $data;
    }
}
