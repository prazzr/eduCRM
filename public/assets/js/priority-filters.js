/**
 * Priority Filter Functionality
 * Handles filtering inquiries by priority
 */

document.addEventListener('DOMContentLoaded', function () {

    // Initialize priority filters
    initPriorityFilters();

    // Apply heat map to inquiry rows
    applyHeatMap();

    // Load priority counts
    loadPriorityCounts();
});

/**
 * Initialize priority filter chips
 */
function initPriorityFilters() {
    const filterChips = document.querySelectorAll('.filter-chip');

    filterChips.forEach(chip => {
        chip.addEventListener('click', function () {
            const priority = this.dataset.priority;

            // Update active state
            filterChips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');

            // Filter inquiries
            filterInquiriesByPriority(priority);
        });
    });
}

/**
 * Filter inquiries by priority
 */
function filterInquiriesByPriority(priority) {
    const rows = document.querySelectorAll('.inquiry-row');

    rows.forEach(row => {
        if (priority === 'all') {
            row.style.display = '';
        } else {
            const rowPriority = row.dataset.priority;
            row.style.display = rowPriority === priority ? '' : 'none';
        }
    });

    // Update URL parameter
    const url = new URL(window.location);
    if (priority === 'all') {
        url.searchParams.delete('priority');
    } else {
        url.searchParams.set('priority', priority);
    }
    window.history.pushState({}, '', url);
}

/**
 * Apply heat map styling to inquiry rows
 */
function applyHeatMap() {
    const rows = document.querySelectorAll('.inquiry-row');

    rows.forEach(row => {
        const priority = row.dataset.priority;
        if (priority) {
            row.classList.add(`priority-${priority}`);
        }
    });
}

/**
 * Load priority counts from API
 */
function loadPriorityCounts() {
    fetch('/api/priority_counts.php')
        .then(response => response.json())
        .then(data => {
            updatePriorityCounts(data);
        })
        .catch(error => {
            console.error('Error loading priority counts:', error);
        });
}

/**
 * Update priority count badges
 */
function updatePriorityCounts(counts) {
    // Update count badges
    const hotBadge = document.querySelector('.count-badge.hot .count');
    const warmBadge = document.querySelector('.count-badge.warm .count');
    const coldBadge = document.querySelector('.count-badge.cold .count');

    if (hotBadge) hotBadge.textContent = counts.hot || 0;
    if (warmBadge) warmBadge.textContent = counts.warm || 0;
    if (coldBadge) coldBadge.textContent = counts.cold || 0;

    // Update filter chip counts
    const hotChip = document.querySelector('.filter-chip.hot');
    const warmChip = document.querySelector('.filter-chip.warm');
    const coldChip = document.querySelector('.filter-chip.cold');

    if (hotChip) {
        const countText = hotChip.querySelector('.count') || document.createElement('span');
        countText.className = 'count';
        countText.textContent = ` (${counts.hot || 0})`;
        if (!hotChip.querySelector('.count')) {
            hotChip.appendChild(countText);
        }
    }

    if (warmChip) {
        const countText = warmChip.querySelector('.count') || document.createElement('span');
        countText.className = 'count';
        countText.textContent = ` (${counts.warm || 0})`;
        if (!warmChip.querySelector('.count')) {
            warmChip.appendChild(countText);
        }
    }

    if (coldChip) {
        const countText = coldChip.querySelector('.count') || document.createElement('span');
        countText.className = 'count';
        countText.textContent = ` (${counts.cold || 0})`;
        if (!coldChip.querySelector('.count')) {
            coldChip.appendChild(countText);
        }
    }
}

/**
 * Make count badges clickable to filter
 */
document.addEventListener('DOMContentLoaded', function () {
    const countBadges = document.querySelectorAll('.count-badge');

    countBadges.forEach(badge => {
        badge.addEventListener('click', function () {
            const priority = this.classList.contains('hot') ? 'hot' :
                this.classList.contains('warm') ? 'warm' : 'cold';

            // Trigger filter
            const filterChip = document.querySelector(`.filter-chip.${priority}`);
            if (filterChip) {
                filterChip.click();
            }
        });
    });
});

/**
 * Quick search functionality
 */
document.addEventListener('DOMContentLoaded', function () {
    const quickSearch = document.querySelector('.quick-search');

    if (quickSearch) {
        quickSearch.addEventListener('input', function (e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.inquiry-row');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
});

/**
 * Auto-refresh priority counts every 30 seconds
 */
setInterval(loadPriorityCounts, 30000);
