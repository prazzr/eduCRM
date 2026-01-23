<?php

declare(strict_types=1);

namespace EduCRM\Services;

/**
 * Pagination Service
 * Provides reusable server-side pagination for list views
 * 
 * @package EduCRM\Services
 */
class PaginationService
{
    private \PDO $pdo;
    private int $perPage;
    private int $currentPage;
    private int $totalRecords = 0;
    private int $totalPages = 0;

    /**
     * Create pagination service
     * 
     * @param \PDO $pdo Database connection
     * @param int $perPage Items per page (default: 20)
     */
    public function __construct(\PDO $pdo, int $perPage = 20)
    {
        $this->pdo = $pdo;
        $this->perPage = max(1, min($perPage, 100)); // Clamp between 1-100
        $this->currentPage = max(1, (int) ($_GET['page'] ?? 1));
    }

    /**
     * Set current page manually
     */
    public function setPage(int $page): self
    {
        $this->currentPage = max(1, $page);
        return $this;
    }

    /**
     * Set items per page
     */
    public function setPerPage(int $perPage): self
    {
        $this->perPage = max(1, min($perPage, 100));
        return $this;
    }

    /**
     * Paginate a query and return results
     * 
     * @param string $baseQuery The base SQL query (without LIMIT/OFFSET)
     * @param array $params Parameters for the prepared statement
     * @param string|null $countQuery Optional custom count query for performance
     * @return array The paginated results
     */
    public function paginate(string $baseQuery, array $params = [], ?string $countQuery = null): array
    {
        // Calculate total records
        $countSql = $countQuery ?? "SELECT COUNT(*) FROM ({$baseQuery}) as count_table";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $this->totalRecords = (int) $stmt->fetchColumn();

        // Calculate total pages
        $this->totalPages = $this->totalRecords > 0
            ? (int) ceil($this->totalRecords / $this->perPage)
            : 1;

        // Adjust current page if it exceeds total pages
        if ($this->currentPage > $this->totalPages) {
            $this->currentPage = $this->totalPages;
        }

        // Calculate offset
        $offset = ($this->currentPage - 1) * $this->perPage;

        // Get paginated results
        $paginatedQuery = $baseQuery . " LIMIT {$this->perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($paginatedQuery);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get pagination metadata for rendering UI
     */
    public function getMetadata(): array
    {
        $start = $this->totalRecords > 0
            ? (($this->currentPage - 1) * $this->perPage) + 1
            : 0;
        $end = min($this->currentPage * $this->perPage, $this->totalRecords);

        return [
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'total_records' => $this->totalRecords,
            'total_pages' => $this->totalPages,
            'has_previous' => $this->currentPage > 1,
            'has_next' => $this->currentPage < $this->totalPages,
            'start_record' => $start,
            'end_record' => $end,
        ];
    }

    /**
     * Get URL for a specific page, preserving other query parameters
     */
    public function getPageUrl(int $page, string $baseUrl = ''): string
    {
        if (empty($baseUrl)) {
            $baseUrl = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        }

        $queryParams = $_GET;
        $queryParams['page'] = $page;

        return $baseUrl . '?' . http_build_query($queryParams);
    }

    /**
     * Get array of page numbers to display (with ellipsis handling)
     * 
     * @param int $range Number of pages to show on each side of current
     * @return array Page numbers (0 indicates ellipsis)
     */
    public function getPageRange(int $range = 2): array
    {
        $pages = [];

        if ($this->totalPages <= 1) {
            return [1];
        }

        // Always show first page
        $pages[] = 1;

        $start = max(2, $this->currentPage - $range);
        $end = min($this->totalPages - 1, $this->currentPage + $range);

        // Add ellipsis after first page if needed
        if ($start > 2) {
            $pages[] = 0; // 0 indicates ellipsis
        }

        // Add range around current page
        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }

        // Add ellipsis before last page if needed
        if ($end < $this->totalPages - 1) {
            $pages[] = 0;
        }

        // Always show last page
        if ($this->totalPages > 1) {
            $pages[] = $this->totalPages;
        }

        return $pages;
    }

    /**
     * Get current page number
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Get total records count
     */
    public function getTotalRecords(): int
    {
        return $this->totalRecords;
    }

    /**
     * Get total pages count
     */
    public function getTotalPages(): int
    {
        return $this->totalPages;
    }
}
