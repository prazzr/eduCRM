<?php
declare(strict_types=1);

namespace EduCRM\Services;

/**
 * ServiceContainer - Simple Dependency Injection Container
 * 
 * Manages service instantiation and dependency resolution.
 * Supports singleton and factory patterns.
 * 
 * @package EduCRM\Services
 * @version 1.0.0
 * @date January 5, 2026
 */
class ServiceContainer
{
    private static ?ServiceContainer $instance = null;

    /** @var array<string, object> Singleton instances */
    private array $instances = [];

    /** @var array<string, callable> Factory callbacks */
    private array $factories = [];

    /** @var array<string, string> Service aliases */
    private array $aliases = [];

    /** @var array<string, bool> Services marked as singletons */
    private array $singletons = [];

    /** @var \PDO|null Database connection */
    private ?\PDO $pdo = null;

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        $this->registerDefaults();
    }

    /**
     * Get container instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set database connection
     */
    public function setDatabase(\PDO $pdo): void
    {
        $this->pdo = $pdo;
        $this->instances['pdo'] = $pdo;
        $this->instances[\PDO::class] = $pdo;
    }

    /**
     * Get database connection
     */
    public function getDatabase(): ?\PDO
    {
        return $this->pdo;
    }

    /**
     * Register a service
     * 
     * @param string $name Service name or class name
     * @param callable|object|string $resolver Service instance, factory, or class name
     * @param bool $singleton Whether to cache as singleton
     */
    public function register(string $name, callable|object|string $resolver, bool $singleton = true): void
    {
        if (is_object($resolver) && !is_callable($resolver)) {
            // Direct instance
            $this->instances[$name] = $resolver;
        } elseif (is_callable($resolver)) {
            // Factory callback
            $this->factories[$name] = $resolver;
            $this->singletons[$name] = $singleton;
        } else {
            // Class name - create factory
            $this->factories[$name] = fn() => $this->build($resolver);
            $this->singletons[$name] = $singleton;
        }
    }

    /**
     * Register an alias for a service
     */
    public function alias(string $alias, string $service): void
    {
        $this->aliases[$alias] = $service;
    }

    /**
     * Resolve and get a service
     * 
     * @param string $name Service name or class name
     * @return object|null Service instance
     */
    public function get(string $name): ?object
    {
        // Resolve alias
        $name = $this->aliases[$name] ?? $name;

        // Return cached singleton
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        // Use factory if registered
        if (isset($this->factories[$name])) {
            $instance = ($this->factories[$name])($this);

            // Cache if singleton
            if ($this->singletons[$name] ?? true) {
                $this->instances[$name] = $instance;
            }

            return $instance;
        }

        // Try to auto-resolve class
        if (class_exists($name)) {
            $instance = $this->build($name);
            $this->instances[$name] = $instance;
            return $instance;
        }

        return null;
    }

    /**
     * Check if service is registered
     */
    public function has(string $name): bool
    {
        $name = $this->aliases[$name] ?? $name;
        return isset($this->instances[$name]) || isset($this->factories[$name]) || class_exists($name);
    }

    /**
     * Build a class with automatic dependency injection
     * 
     * @param string $class Class name
     * @return object Class instance
     */
    public function build(string $class): object
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Class not found: {$class}");
        }

        $reflection = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new \InvalidArgumentException("Class is not instantiable: {$class}");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            // Handle PDO dependency
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                if ($typeName === \PDO::class || $typeName === 'PDO') {
                    if ($this->pdo === null) {
                        throw new \RuntimeException("Database connection not set");
                    }
                    $dependencies[] = $this->pdo;
                    continue;
                }

                // Try to resolve other dependencies
                $resolved = $this->get($typeName);
                if ($resolved !== null) {
                    $dependencies[] = $resolved;
                    continue;
                }
            }

            // Use default value if available
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            // Allow null if nullable
            if ($parameter->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            throw new \RuntimeException(
                "Cannot resolve parameter '{$parameter->getName()}' for class {$class}"
            );
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Register default services
     */
    private function registerDefaults(): void
    {
        // Config service
        $this->register('config', fn() => \EduCRM\Services\ConfigService::getInstance());
        $this->alias(\EduCRM\Services\ConfigService::class, 'config');

        // Cache service
        $this->register('cache', function () {
            require_once __DIR__ . '/CacheService.php';
            return \EduCRM\Services\CacheService::getInstance();
        });

        // Lookup cache service
        $this->register('lookup', function ($container) {
            require_once __DIR__ . '/LookupCacheService.php';
            return \EduCRM\Services\LookupCacheService::getInstance($container->getDatabase());
        });

        // Security service
        $this->register('security', function ($container) {
            require_once __DIR__ . '/SecurityService.php';
            return new \EduCRM\Services\SecurityService($container->getDatabase());
        });

        // Dashboard service factory (not singleton - user-specific)
        $this->register('dashboard', function ($container) {
            require_once __DIR__ . '/DashboardService.php';
            // Returns a factory function, not a direct instance
            return new class ($container) {
                private ServiceContainer $container;

                public function __construct(\EduCRM\Services\ServiceContainer $container)
                {
                    $this->container = $container;
                }

                public function forUser(int $userId, string $role): \EduCRM\Services\DashboardService
                {
                    return new \EduCRM\Services\DashboardService(
                        $this->container->getDatabase(),
                        $userId,
                        $role
                    );
                }
            };
        });

        // Workflow service
        $this->register('workflow', function ($container) {
            require_once __DIR__ . '/WorkflowService.php';
            return new \EduCRM\Services\WorkflowService($container->getDatabase());
        });

        // Lead scoring service
        $this->register('leadScoring', function ($container) {
            require_once __DIR__ . '/LeadScoringService.php';
            return new \EduCRM\Services\LeadScoringService($container->getDatabase());
        });

        // Task service
        $this->register('task', function ($container) {
            require_once __DIR__ . '/TaskService.php';
            return new \EduCRM\Services\TaskService($container->getDatabase());
        });

        // Appointment service
        $this->register('appointment', function ($container) {
            require_once __DIR__ . '/AppointmentService.php';
            return new \EduCRM\Services\AppointmentService($container->getDatabase());
        });

        // Analytics service
        $this->register('analytics', function ($container) {
            require_once __DIR__ . '/AnalyticsService.php';
            return new \EduCRM\Services\AnalyticsService($container->getDatabase());
        });

        // Document service
        $this->register('document', function ($container) {
            require_once __DIR__ . '/DocumentService.php';
            return new \EduCRM\Services\DocumentService($container->getDatabase());
        });

        // Invoice service
        $this->register('invoice', function ($container) {
            require_once __DIR__ . '/InvoiceService.php';
            return new \EduCRM\Services\InvoiceService($container->getDatabase());
        });

        // Notification service (factory - requires user context)
        $this->register('notification', function ($container) {
            require_once __DIR__ . '/NotificationService.php';
            // Returns a factory, not direct instance (needs userId)
            return new class ($container) {
                private ServiceContainer $container;

                public function __construct(\EduCRM\Services\ServiceContainer $container)
                {
                    $this->container = $container;
                }

                public function forUser(int $userId): \EduCRM\Services\NotificationService
                {
                    return new \EduCRM\Services\NotificationService(
                        $this->container->getDatabase(),
                        $userId
                    );
                }
            };
        });

        // Email notification service
        $this->register('emailNotification', function ($container) {
            require_once __DIR__ . '/EmailNotificationService.php';
            return new \EduCRM\Services\EmailNotificationService($container->getDatabase());
        });

        // Messaging factory (static class)
        $this->register('messaging', function ($container) {
            require_once __DIR__ . '/MessagingFactory.php';
            \EduCRM\Services\MessagingFactory::init($container->getDatabase());
            return \EduCRM\Services\MessagingFactory::class;
        });

        // Branch service
        $this->register('branch', function ($container) {
            require_once __DIR__ . '/BranchService.php';
            return new \EduCRM\Services\BranchService($container->getDatabase());
        });

        // Activity service
        $this->register('activity', function ($container) {
            require_once __DIR__ . '/ActivityService.php';
            return new \EduCRM\Services\ActivityService($container->getDatabase());
        });
    }

    /**
     * Clear all cached instances (useful for testing)
     */
    public function clear(): void
    {
        $this->instances = [];
        if ($this->pdo !== null) {
            $this->instances['pdo'] = $this->pdo;
            $this->instances[\PDO::class] = $this->pdo;
        }
    }

    // Prevent cloning
    private function __clone()
    {
    }

    // Prevent unserialization
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}

// =========================================================================
// HELPER FUNCTION
// =========================================================================

/**
 * Get a service from the container
 * 
 * @param string $name Service name
 * @return object|null Service instance
 */
function service(string $name): ?object
{
    return \EduCRM\Services\ServiceContainer::getInstance()->get($name);
}
