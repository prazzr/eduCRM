<?php
/**
 * Pagination Component
 * 
 * Renders pagination controls for list views.
 * 
 * Required variables:
 * - $pagination: array with pagination metadata from PaginationService::getMetadata()
 * 
 * Optional:
 * - $paginationBaseUrl: custom base URL for pagination links
 * 
 * Usage:
 * <?php 
 * $pagination = $paginationService->getMetadata();
 * include __DIR__ . '/../../templates/partials/pagination.php'; 
 * ?>
 */

// Don't render if no pagination data or only one page
if (!isset($pagination) || $pagination['total_pages'] <= 1) {
    return;
}

// Calculate pagination URLs
$baseUrl = $paginationBaseUrl ?? strtok($_SERVER['REQUEST_URI'] ?? '', '?');
$queryParams = $_GET;
unset($queryParams['page']);
$queryString = http_build_query($queryParams);
$urlPrefix = $baseUrl . ($queryString ? "?{$queryString}&" : '?');

// Calculate page range to display
$range = 2;
$currentPage = $pagination['current_page'];
$totalPages = $pagination['total_pages'];
$start = max(1, $currentPage - $range);
$end = min($totalPages, $currentPage + $range);
?>

    <div
        class="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6 mt-4 rounded-lg shadow-sm">
        <!-- Mobile view -->
        <div class="flex flex-1 justify-between sm:hidden">
            <?php if ($pagination['has_previous']): ?>
                <a href="<?php echo htmlspecialchars($urlPrefix . 'page=' . ($currentPage - 1)); ?>"
                    class="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Previous
                </a>
            <?php else: ?>
                <span
                    class="relative inline-flex items-center rounded-md border border-slate-200 bg-slate-100 px-4 py-2 text-sm font-medium text-slate-400 cursor-not-allowed">
                    Previous
                </span>
            <?php endif; ?>

            <span class="text-sm text-slate-700 self-center">
                Page
                <?php echo $currentPage; ?> of
                <?php echo $totalPages; ?>
            </span>

            <?php if ($pagination['has_next']): ?>
                <a href="<?php echo htmlspecialchars($urlPrefix . 'page=' . ($currentPage + 1)); ?>"
                    class="relative ml-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Next
                </a>
            <?php else: ?>
                <span
                    class="relative ml-3 inline-flex items-center rounded-md border border-slate-200 bg-slate-100 px-4 py-2 text-sm font-medium text-slate-400 cursor-not-allowed">
                    Next
                </span>
            <?php endif; ?>
        </div>

        <!-- Desktop view -->
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-slate-700">
                    Showing
                    <span class="font-medium">
                        <?php echo $pagination['start_record']; ?>
                    </span>
                    to
                    <span class="font-medium">
                        <?php echo $pagination['end_record']; ?>
                    </span>
                    of
                    <span class="font-medium">
                        <?php echo number_format($pagination['total_records']); ?>
                    </span>
                    results
                </p>
            </div>
            <div>
                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                    <!-- Previous Button -->
                    <?php if ($pagination['has_previous']): ?>
                        <a href="<?php echo htmlspecialchars($urlPrefix . 'page=' . ($currentPage - 1)); ?>"
                            class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0">
                            <span class="sr-only">Previous</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd"
                                    d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z"
                                    clip-rule="evenodd" />
                            </svg>
                        </a>
                    <?php else: ?>
                        <span
                            class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-300 ring-1 ring-inset ring-slate-300 cursor-not-allowed">
                            <span class="sr-only">Previous</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd"
                                    d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z"
                                    clip-rule="evenodd" />
                            </svg>
                        </span>
                    <?php endif; ?>

                    <!-- First page (always show) -->
                    <?php if ($start > 1): ?>
                        <a href="<?php echo htmlspecialchars($urlPrefix . 'page=1'); ?>"
                            class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0">
                            1
                        </a>
                        <?php if ($start > 2): ?>
                            <span
                                class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-inset ring-slate-300">
                                ...
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <?php if ($i == $currentPage): ?>
                            <span aria-current="page"
                                class="relative z-10 inline-flex items-center bg-primary-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                                <?php echo $i; ?>
                            </span>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars($urlPrefix . 'page=' . $i); ?>"
                                class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <!-- Last page (always show) -->
                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?>
                            <span
                                class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-inset ring-slate-300">
                                ...
                            </span>
                        <?php endif; ?>
                        <a href="<?php echo htmlspecialchars($urlPrefix . 'page=' . $totalPages); ?>"
                            class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0">
                            <?php echo $totalPages; ?>
                        </a>
                    <?php endif; ?>

                    <!-- Next Button -->
                    <?php if ($pagination['has_next']): ?>
                        <a href="<?php echo htmlspecialchars($urlPrefix . 'page=' . ($currentPage + 1)); ?>"
                            class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0">
                            <span class="sr-only">Next</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd"
                                    d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                                    clip-rule="evenodd" />
                            </svg>
                        </a>
                    <?php else: ?>
                        <span
                            class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-300 ring-1 ring-inset ring-slate-300 cursor-not-allowed">
                            <span class="sr-only">Next</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd"
                                    d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                                    clip-rule="evenodd" />
                            </svg>
                            </a>
                        <?php endif; ?>
                </nav>
            </div>
        </div>
    </div>