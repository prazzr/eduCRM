<?php
declare(strict_types=1);

namespace EduCRM\Exceptions;

/**
 * Exception for authorization failures
 * 
 * Thrown when an authenticated user attempts to access a resource
 * or perform an action they don't have permission for.
 */
class AuthorizationException extends \EduCRM\Exceptions\AppException
{
    public const INSUFFICIENT_PERMISSIONS = 2001;
    public const ROLE_REQUIRED = 2002;
    public const RESOURCE_FORBIDDEN = 2003;
    public const OWNERSHIP_REQUIRED = 2004;

    /**
     * The required roles/permissions
     * 
     * @var string[]
     */
    protected array $requiredRoles = [];

    /**
     * Create a new authorization exception
     *
     * @param string $message The error message
     * @param int $code The error code
     * @param string[] $requiredRoles The roles that would grant access
     */
    public function __construct(
        string $message = "Access denied",
        int $code = self::INSUFFICIENT_PERMISSIONS,
        array $requiredRoles = []
    ) {
        $this->requiredRoles = $requiredRoles;
        parent::__construct($message, $code, null, [
            'required_roles' => $requiredRoles
        ]);
    }

    /**
     * Create exception for missing role
     *
     * @param string|string[] $roles The required roles
     */
    public static function missingRole(string|array $roles): static
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $rolesStr = implode(', ', $roles);
        
        return new static(
            "This action requires one of these roles: {$rolesStr}",
            self::ROLE_REQUIRED,
            $roles
        );
    }

    /**
     * Create exception for forbidden resource
     *
     * @param string $resource The resource type
     */
    public static function forbidden(string $resource = 'resource'): static
    {
        return new static(
            "You don't have permission to access this {$resource}",
            self::RESOURCE_FORBIDDEN
        );
    }

    /**
     * Create exception for ownership requirement
     *
     * @param string $resource The resource type
     */
    public static function ownershipRequired(string $resource = 'resource'): static
    {
        return new static(
            "You can only access your own {$resource}",
            self::OWNERSHIP_REQUIRED
        );
    }

    /**
     * Get required roles
     *
     * @return string[]
     */
    public function getRequiredRoles(): array
    {
        return $this->requiredRoles;
    }

    /**
     * Get HTTP status code for this exception
     */
    public function getHttpStatusCode(): int
    {
        return 403;
    }
}
