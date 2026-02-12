/**
 * Alpine.js Component Library for EduCRM
 * Reusable UI components with Alpine.js
 */

// ============================================================================
// DROPDOWN COMPONENT
// Accessible dropdown menus with keyboard navigation
// ============================================================================
document.addEventListener('alpine:init', () => {
    Alpine.data('dropdown', () => ({
        open: false,

        toggle() {
            this.open = !this.open;
        },

        close() {
            this.open = false;
        },

        // Close on escape key
        handleEscape(e) {
            if (e.key === 'Escape') this.close();
        }
    }));

    // ============================================================================
    // MODAL COMPONENT
    // Modal dialogs with backdrop and focus trap
    // ============================================================================
    Alpine.data('modal', (initialOpen = false) => ({
        show: initialOpen,

        open() {
            this.show = true;
            document.body.classList.add('overflow-hidden');
            this.$nextTick(() => {
                // Focus first focusable element
                const focusable = this.$el.querySelector('button, [href], input, select, textarea');
                if (focusable) focusable.focus();
            });
        },

        close() {
            this.show = false;
            document.body.classList.remove('overflow-hidden');
        },

        // Close on escape
        handleEscape(e) {
            if (e.key === 'Escape') this.close();
        }
    }));

    // ============================================================================
    // CONFIRM DIALOG COMPONENT
    // Confirmation dialogs for destructive actions
    // ============================================================================
    Alpine.data('confirmDialog', () => ({
        show: false,
        title: 'Confirm Action',
        message: 'Are you sure you want to proceed?',
        confirmText: 'Confirm',
        cancelText: 'Cancel',
        confirmClass: 'btn-primary',
        onConfirm: null,
        onCancel: null,

        confirm(options = {}) {
            this.title = options.title || 'Confirm Action';
            this.message = options.message || 'Are you sure?';
            this.confirmText = options.confirmText || 'Confirm';
            this.cancelText = options.cancelText || 'Cancel';
            this.confirmClass = options.danger ? 'btn-danger' : 'btn-primary';
            this.onConfirm = options.onConfirm || null;
            this.onCancel = options.onCancel || null;
            this.show = true;
        },

        proceed() {
            if (this.onConfirm) this.onConfirm();
            this.show = false;
        },

        cancel() {
            if (this.onCancel) this.onCancel();
            this.show = false;
        }
    }));

    // ============================================================================
    // AJAX FORM COMPONENT
    // Form submission with loading states and error handling
    // ============================================================================
    Alpine.data('ajaxForm', (config = {}) => ({
        formData: config.initialData || {},
        loading: false,
        success: false,
        error: false,
        message: '',
        errors: {},

        init() {
            // Initialize form data from form inputs
            if (config.autoInit) {
                const form = this.$el;
                const inputs = form.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    if (input.name) {
                        this.formData[input.name] = input.value;
                    }
                });
            }
        },

        async submit(url = config.url, method = config.method || 'POST') {
            this.loading = true;
            this.success = false;
            this.error = false;
            this.errors = {};

            try {
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.formData)
                });

                const data = await response.json();

                if (data.success) {
                    this.success = true;
                    this.message = data.message || 'Saved successfully!';

                    // Callback on success
                    if (config.onSuccess) config.onSuccess(data);

                    // Redirect if specified
                    if (data.redirect) {
                        setTimeout(() => window.location.href = data.redirect, 1000);
                    }
                } else {
                    this.error = true;
                    this.message = data.message || data.error || 'An error occurred';
                    this.errors = data.errors || {};

                    if (config.onError) config.onError(data);
                }
            } catch (e) {
                this.error = true;
                this.message = 'Network error. Please try again.';
                console.error('Form submission error:', e);
            }

            this.loading = false;

            // Auto-hide success message
            if (this.success && config.autoHideSuccess !== false) {
                setTimeout(() => { this.success = false; }, 3000);
            }
        },

        hasError(field) {
            return this.errors && this.errors[field];
        },

        getError(field) {
            return this.errors && this.errors[field] ? this.errors[field] : '';
        },

        reset() {
            this.formData = config.initialData || {};
            this.success = false;
            this.error = false;
            this.message = '';
            this.errors = {};
        }
    }));

    // ============================================================================
    // SEARCH FILTER COMPONENT
    // Live search with debouncing and API integration
    // ============================================================================
    Alpine.data('searchFilter', (config = {}) => ({
        query: '',
        results: [],
        loading: false,
        showResults: false,
        selectedIndex: -1,
        debounceTimer: null,

        async search() {
            // Clear previous timer
            if (this.debounceTimer) clearTimeout(this.debounceTimer);

            // Debounce search
            this.debounceTimer = setTimeout(async () => {
                if (this.query.length < (config.minLength || 2)) {
                    this.results = [];
                    this.showResults = false;
                    return;
                }

                this.loading = true;

                try {
                    // If local data provided, filter locally
                    if (config.data) {
                        const q = this.query.toLowerCase();
                        this.results = config.data.filter(item => {
                            const searchFields = config.searchFields || ['name'];
                            return searchFields.some(field =>
                                String(item[field] || '').toLowerCase().includes(q)
                            );
                        }).slice(0, config.maxResults || 10);
                    }
                    // Otherwise fetch from API
                    else if (config.url) {
                        const url = `${config.url}?q=${encodeURIComponent(this.query)}`;
                        const response = await fetch(url);
                        const data = await response.json();
                        this.results = data.data || data.results || data;
                    }

                    this.showResults = this.results.length > 0;
                    this.selectedIndex = -1; // Reset selection on new search
                } catch (e) {
                    console.error('Search error:', e);
                    this.results = [];
                }

                this.loading = false;
            }, config.debounce || 300);
        },

        select(item) {
            if (config.onSelect) config.onSelect(item);
            this.query = item[config.displayField || 'name'];
            this.showResults = false;

            // Update hidden input if specified
            if (config.hiddenInput) {
                const hidden = document.getElementById(config.hiddenInput);
                if (hidden) hidden.value = item[config.valueField || 'id'];
            }
        },

        // Handle mouse hover to sync with keyboard selection
        setSelectedIndex(index) {
            this.selectedIndex = index;
        },

        // Check if item is selected (for highlighting)
        isSelected(index) {
            return this.selectedIndex === index;
        },

        handleKeydown(e) {
            if (!this.showResults || this.results.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.selectedIndex = Math.min(this.selectedIndex + 1, this.results.length - 1);
                this.scrollSelectedIntoView();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
                this.scrollSelectedIntoView();
            } else if (e.key === 'Enter' && this.selectedIndex >= 0) {
                e.preventDefault();
                this.select(this.results[this.selectedIndex]);
            } else if (e.key === 'Escape') {
                this.showResults = false;
            }
        },

        // Scroll selected item into view
        scrollSelectedIntoView() {
            this.$nextTick(() => {
                const container = document.querySelector('.search-results-container');
                if (container && this.selectedIndex >= 0) {
                    const items = container.querySelectorAll('[data-index]');
                    if (items[this.selectedIndex]) {
                        items[this.selectedIndex].scrollIntoView({ block: 'nearest', behavior: 'auto' });
                    }
                }
            });
        },

        close() {
            setTimeout(() => { this.showResults = false; }, 150);
        }
    }));

    // ============================================================================
    // TABS COMPONENT
    // Tab navigation with URL hash support
    // ============================================================================
    Alpine.data('tabs', (defaultTab = '') => ({
        activeTab: defaultTab || '',

        init() {
            // Check URL hash for initial tab
            if (window.location.hash) {
                this.activeTab = window.location.hash.substring(1);
            } else if (!this.activeTab) {
                // Default to first tab
                const firstTab = this.$el.querySelector('[data-tab]');
                if (firstTab) this.activeTab = firstTab.dataset.tab;
            }
        },

        setTab(tab) {
            this.activeTab = tab;
            // Update URL hash without scrolling
            history.replaceState(null, null, `#${tab}`);
        },

        isActive(tab) {
            return this.activeTab === tab;
        }
    }));

    // ============================================================================
    // TOAST NOTIFICATIONS
    // Global toast notification system
    // ============================================================================
    Alpine.data('toastContainer', () => ({
        toasts: [],

        add(message, type = 'info', duration = 5000) {
            const id = Date.now();
            this.toasts.push({ id, message, type });

            if (duration > 0) {
                setTimeout(() => this.remove(id), duration);
            }
        },

        remove(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        },

        success(message, duration) {
            this.add(message, 'success', duration);
        },

        error(message, duration) {
            this.add(message, 'error', duration);
        },

        warning(message, duration) {
            this.add(message, 'warning', duration);
        },

        info(message, duration) {
            this.add(message, 'info', duration);
        }
    }));

    // ============================================================================
    // SIDEBAR COMPONENT
    // Mobile-responsive sidebar toggle
    // ============================================================================
    Alpine.data('sidebar', () => ({
        open: false,

        toggle() {
            this.open = !this.open;
        },

        close() {
            this.open = false;
        },

        // Close on outside click (mobile)
        handleOutsideClick(e) {
            if (window.innerWidth <= 1024 && this.open) {
                const sidebar = document.getElementById('sidebar');
                const toggle = document.querySelector('.mobile-toggle');
                if (sidebar && toggle &&
                    !sidebar.contains(e.target) &&
                    !toggle.contains(e.target)) {
                    this.open = false;
                }
            }
        }
    }));

    // ============================================================================
    // NOTIFICATION DROPDOWN COMPONENT
    // Header notification dropdown with mark as read
    // ============================================================================
    Alpine.data('notificationDropdown', (config = {}) => ({
        open: false,
        notifications: config.initial || [],
        loading: false,

        toggle() {
            this.open = !this.open;
        },

        close() {
            this.open = false;
        },

        get unreadCount() {
            return this.notifications.length;
        },

        async markAllRead() {
            this.loading = true;
            try {
                const response = await fetch(config.markReadUrl || '?mark_read=1');
                if (response.ok) {
                    this.notifications = [];
                    this.close();
                    // Optionally reload to refresh the badge
                    if (config.reloadOnMarkRead) {
                        window.location.reload();
                    }
                }
            } catch (e) {
                console.error('Failed to mark notifications as read:', e);
            }
            this.loading = false;
        },

        handleEscape(e) {
            if (e.key === 'Escape') this.close();
        }
    }));

    // ============================================================================
    // PRIORITY FILTER COMPONENT
    // Inquiry priority filtering with live counts
    // ============================================================================
    Alpine.data('priorityFilter', (config = {}) => ({
        activePriority: config.initial || 'all',
        counts: config.counts || { hot: 0, warm: 0, cold: 0, all: 0 },

        setFilter(priority) {
            this.activePriority = priority;

            // Update URL
            const url = new URL(window.location);
            if (priority === 'all') {
                url.searchParams.delete('priority');
            } else {
                url.searchParams.set('priority', priority);
            }
            window.history.pushState({}, '', url);

            // Filter rows client-side
            this.filterRows(priority);

            // Callback
            if (config.onChange) config.onChange(priority);
        },

        isActive(priority) {
            return this.activePriority === priority;
        },

        filterRows(priority) {
            const rows = document.querySelectorAll('[data-priority]');
            rows.forEach(row => {
                if (priority === 'all') {
                    row.style.display = '';
                } else {
                    row.style.display = row.dataset.priority === priority ? '' : 'none';
                }
            });
        },

        async refreshCounts() {
            try {
                const response = await fetch('/api/priority_counts.php');
                const data = await response.json();
                this.counts = { ...this.counts, ...data };
            } catch (e) {
                console.error('Failed to refresh counts:', e);
            }
        }
    }));

    // ============================================================================
    // BULK ACTIONS COMPONENT
    // Table row selection and bulk operations
    // ============================================================================
    Alpine.data('bulkActions', () => ({
        selected: [],
        selectAll: false,

        toggle(id) {
            const index = this.selected.indexOf(id);
            if (index === -1) {
                this.selected.push(id);
            } else {
                this.selected.splice(index, 1);
            }
            this.updateSelectAll();
        },

        toggleAll(ids) {
            if (this.selectAll) {
                this.selected = [...ids];
            } else {
                this.selected = [];
            }
        },

        updateSelectAll() {
            const checkboxes = document.querySelectorAll('[data-bulk-item]');
            this.selectAll = this.selected.length === checkboxes.length && checkboxes.length > 0;
        },

        isSelected(id) {
            return this.selected.includes(id);
        },

        hasSelection() {
            return this.selected.length > 0;
        },

        count() {
            return this.selected.length;
        },

        clear() {
            this.selected = [];
            this.selectAll = false;
        },

        getIds() {
            return this.selected;
        }
    }));
});

// ============================================================================
// GLOBAL HELPER FUNCTIONS
// ============================================================================

// Global toast helper (available after Alpine init)
window.toast = {
    show(message, type = 'info', duration = 5000) {
        const container = document.querySelector('[x-data*="toastContainer"]');
        if (container && container.__x) {
            container.__x.$data.add(message, type, duration);
        }
    },
    success(message) { this.show(message, 'success'); },
    error(message) { this.show(message, 'error'); },
    warning(message) { this.show(message, 'warning'); },
    info(message) { this.show(message, 'info'); }
};

// Confirm helper
window.confirm = async function (message, options = {}) {
    return new Promise((resolve) => {
        const dialog = document.querySelector('[x-data*="confirmDialog"]');
        if (dialog && dialog.__x) {
            dialog.__x.$data.confirm({
                message,
                ...options,
                onConfirm: () => resolve(true),
                onCancel: () => resolve(false)
            });
        } else {
            // Fallback to native confirm
            resolve(window.confirm(message));
        }
    });
};
