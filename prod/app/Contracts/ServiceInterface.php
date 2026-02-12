<?php
declare(strict_types=1);

namespace EduCRM\Contracts;

/**
 * Base interface for all services
 * 
 * All service classes should implement this interface to ensure
 * consistent dependency injection and testing patterns.
 */
interface ServiceInterface
{
    /**
     * Get the PDO database connection
     */
    public function getConnection(): \PDO;
}
