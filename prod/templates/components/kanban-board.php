<?php
/**
 * Reusable Kanban Board Component
 * 
 * Usage:
 * $kanbanConfig = [
 *     'entity' => 'tasks',
 *     'apiEndpoint' => '/api/v1/kanban/index.php?path=tasks'
 * ];
 * include 'templates/components/kanban-board.php';
 */
?>

<!-- Include SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<div x-data="kanbanBoard()" x-init="init()" class="kanban-container">
    <!-- Loading State -->
    <div x-show="loading" class="flex items-center justify-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
    </div>

    <!-- Kanban Board -->
    <div x-show="!loading" class="flex gap-4 overflow-x-auto pb-4" style="min-height: 600px;">
        <template x-for="(column, columnKey) in columns" :key="columnKey">
            <div class="kanban-column flex-shrink-0 w-80 bg-slate-100 dark:bg-slate-800 rounded-xl">
                <!-- Column Header -->
                <div
                    class="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 rounded-t-xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full" :class="`bg-${column.color}-500`"></span>
                            <h3 class="font-semibold text-slate-800 dark:text-slate-200" x-text="column.title"></h3>
                        </div>
                        <span
                            class="px-2 py-1 text-xs font-medium rounded-full bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300"
                            x-text="column.count"></span>
                    </div>
                </div>

                <!-- Cards Container (Sortable) -->
                <div class="kanban-cards p-2 space-y-2 min-h-[400px]" :data-column="columnKey"
                    :id="'column-' + columnKey">

                    <template x-for="item in column.items" :key="item.id">
                        <div class="kanban-card bg-white dark:bg-slate-700 rounded-lg shadow-sm border border-slate-200 dark:border-slate-600 p-3 cursor-grab active:cursor-grabbing hover:shadow-md transition-shadow"
                            :data-id="item.id" @click="openCard(item)">

                            <!-- Card Content - Tasks -->
                            <template x-if="entity === 'tasks'">
                                <div>
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <span
                                            class="font-medium text-slate-800 dark:text-slate-200 text-sm line-clamp-2"
                                            x-text="item.title"></span>
                                        <span class="flex-shrink-0 px-2 py-0.5 text-xs rounded-full"
                                            :class="getPriorityClass(item.priority)" x-text="item.priority"></span>
                                    </div>
                                    <div
                                        class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                        <span x-text="item.assigned_name || 'Unassigned'"></span>
                                        <span :class="item.is_overdue == 1 ? 'text-red-600 font-medium' : ''"
                                            x-text="formatDate(item.due_date)"></span>
                                    </div>
                                </div>
                            </template>

                            <!-- Card Content - Inquiries -->
                            <template x-if="entity === 'inquiries'">
                                <div>
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <span class="font-medium text-slate-800 dark:text-slate-200 text-sm"
                                            x-text="item.name"></span>
                                        <span class="flex-shrink-0 px-2 py-0.5 text-xs rounded-full"
                                            :class="getPriorityClass(item.priority)" x-text="item.priority"></span>
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 space-y-1">
                                        <div class="flex items-center gap-1 truncate">
                                            <span>📧</span>
                                            <span x-text="item.email" class="truncate"></span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span x-text="item.country_name || 'No country'"></span>
                                            <span class="font-medium text-primary-600"
                                                x-text="'Score: ' + (item.lead_score || 0)"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Card Content - Visa -->
                            <template x-if="entity === 'visa'">
                                <div>
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <span class="font-medium text-slate-800 dark:text-slate-200 text-sm"
                                            x-text="item.student_name"></span>
                                        <span class="flex-shrink-0 px-2 py-0.5 text-xs rounded-full"
                                            :class="getPriorityClass(item.priority)" x-text="item.priority"></span>
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 space-y-1">
                                        <div>🌍 <span x-text="item.destination_country || 'N/A'"></span></div>
                                        <div class="flex items-center justify-between">
                                            <span>Expected:</span>
                                            <span :class="item.is_overdue == 1 ? 'text-red-600 font-medium' : ''"
                                                x-text="formatDate(item.expected_date)"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Empty State -->
                    <div x-show="column.items.length === 0" class="text-center py-8 text-slate-400 text-sm">
                        No items
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Card Detail Modal -->
    <div x-show="selectedCard" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" @click.self="selectedCard = null"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-lg mx-4 max-h-[80vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex items-start justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-200"
                        x-text="selectedCard?.title || selectedCard?.name || selectedCard?.student_name"></h3>
                    <button @click="selectedCard = null"
                        class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Card Quick Info -->
                <div class="space-y-2 mb-6 text-sm text-slate-600 dark:text-slate-400">
                    <template x-if="entity === 'tasks' && selectedCard">
                        <div>
                            <p><strong>Assigned to:</strong> <span
                                    x-text="selectedCard.assigned_name || 'Unassigned'"></span></p>
                            <p><strong>Due:</strong> <span x-text="formatDate(selectedCard.due_date)"></span></p>
                            <p><strong>Priority:</strong> <span x-text="selectedCard.priority"></span></p>
                        </div>
                    </template>
                    <template x-if="entity === 'inquiries' && selectedCard">
                        <div>
                            <p><strong>Email:</strong> <span x-text="selectedCard.email"></span></p>
                            <p><strong>Phone:</strong> <span x-text="selectedCard.phone || 'N/A'"></span></p>
                            <p><strong>Lead Score:</strong> <span x-text="selectedCard.lead_score || 0"></span></p>
                        </div>
                    </template>
                    <template x-if="entity === 'visa' && selectedCard">
                        <div>
                            <p><strong>Student:</strong> <span x-text="selectedCard.student_name"></span></p>
                            <p><strong>Destination:</strong> <span
                                    x-text="selectedCard.destination_country || 'N/A'"></span></p>
                            <p><strong>Expected:</strong> <span x-text="formatDate(selectedCard.expected_date)"></span>
                            </p>
                        </div>
                    </template>
                </div>

                <div class="flex gap-3">
                    <a :href="getEditUrl(selectedCard)" class="btn btn-primary flex-1 text-center">
                        View Details
                    </a>
                    <button @click="selectedCard = null" class="btn btn-secondary">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function kanbanBoard() {
        return {
            entity: '<?php echo $kanbanConfig['entity']; ?>',
            apiEndpoint: '<?php echo $kanbanConfig['apiEndpoint']; ?>',
            columns: {},
            loading: true,
            selectedCard: null,
            sortables: [],

            async init() {
                await this.loadData();
                this.$nextTick(() => this.initSortables());
            },

            async loadData() {
                this.loading = true;
                try {
                    const response = await fetch(this.apiEndpoint);
                    const result = await response.json();
                    if (result.success) {
                        this.columns = result.data;
                    } else {
                        this.showToast(result.error || 'Failed to load data', 'error');
                    }
                } catch (error) {
                    console.error('Failed to load Kanban data:', error);
                    this.showToast('Failed to load board data', 'error');
                }
                this.loading = false;
            },

            initSortables() {
                // Destroy existing sortables
                this.sortables.forEach(s => s.destroy());
                this.sortables = [];

                // Initialize sortable for each column
                document.querySelectorAll('.kanban-cards').forEach(el => {
                    const sortable = new Sortable(el, {
                        group: 'kanban',
                        animation: 150,
                        ghostClass: 'opacity-50',
                        chosenClass: 'shadow-lg',
                        dragClass: 'rotate-2',
                        filter: '.no-drag',
                        onEnd: (evt) => this.onCardMove(evt)
                    });
                    this.sortables.push(sortable);
                });
            },

            async onCardMove(evt) {
                const itemId = evt.item.dataset.id;
                const newColumn = evt.to.dataset.column;
                const oldColumn = evt.from.dataset.column;

                if (newColumn === oldColumn) return;

                // Optimistic update - move card in local state
                const item = this.columns[oldColumn].items.find(i => i.id == itemId);
                if (item) {
                    this.columns[oldColumn].items = this.columns[oldColumn].items.filter(i => i.id != itemId);
                    this.columns[oldColumn].count--;
                    this.columns[newColumn].items.push(item);
                    this.columns[newColumn].count++;
                }

                // API call
                try {
                    const baseUrl = this.apiEndpoint.split('?')[0];
                    const response = await fetch(`${baseUrl}?path=${this.entity}/${itemId}/move`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ status: newColumn })
                    });
                    const result = await response.json();
                    if (!result.success) {
                        // Revert on failure
                        await this.loadData();
                        this.$nextTick(() => this.initSortables());
                        this.showToast(result.error || 'Failed to move card', 'error');
                    } else {
                        this.showToast('Status updated', 'success');
                    }
                } catch (error) {
                    await this.loadData();
                    this.$nextTick(() => this.initSortables());
                    this.showToast('Network error', 'error');
                }
            },

            openCard(item) {
                this.selectedCard = item;
            },

            getEditUrl(item) {
                if (!item) return '#';
                const base = '<?php echo dirname($_SERVER['PHP_SELF']); ?>';
                switch (this.entity) {
                    case 'tasks': return `${base}/edit.php?id=${item.id}`;
                    case 'inquiries': return `${base}/edit.php?id=${item.id}`;
                    case 'visa': return `${base}/update.php?student_id=${item.student_id}`;
                    default: return '#';
                }
            },

            getPriorityClass(priority) {
                const classes = {
                    'urgent': 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                    'high': 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300',
                    'hot': 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                    'warm': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                    'medium': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                    'normal': 'bg-slate-100 text-slate-700 dark:bg-slate-600 dark:text-slate-300',
                    'low': 'bg-slate-100 text-slate-700 dark:bg-slate-600 dark:text-slate-300',
                    'cold': 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                    'critical': 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                };
                return classes[priority?.toLowerCase()] || 'bg-slate-100 text-slate-700 dark:bg-slate-600 dark:text-slate-300';
            },

            formatDate(dateStr) {
                if (!dateStr) return 'No date';
                const date = new Date(dateStr);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const diffDays = Math.ceil((date - today) / (1000 * 60 * 60 * 24));

                if (diffDays < 0) return `${Math.abs(diffDays)}d overdue`;
                if (diffDays === 0) return 'Today';
                if (diffDays === 1) return 'Tomorrow';
                if (diffDays <= 7) return `${diffDays}d`;
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            },

            showToast(message, type = 'info') {
                const toast = document.createElement('div');
                const bgColor = type === 'error' ? 'bg-red-600' : 'bg-green-600';
                toast.className = `fixed bottom-4 right-4 px-4 py-2 rounded-lg shadow-lg text-white z-50 ${bgColor} transition-opacity duration-300`;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            }
        };
    }
</script>

<style>
    .kanban-container {
        --column-width: 320px;
    }

    .kanban-column {
        width: var(--column-width);
        min-width: var(--column-width);
    }

    .kanban-card {
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .kanban-card:hover {
        transform: translateY(-2px);
    }

    .kanban-card.sortable-ghost {
        opacity: 0.4;
    }

    .kanban-card.sortable-chosen {
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
    }

    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Scrollbar styling for kanban */
    .kanban-container::-webkit-scrollbar {
        height: 8px;
    }

    .kanban-container::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }

    .kanban-container::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .kanban-container::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    /* Dark mode scrollbar */
    .dark .kanban-container::-webkit-scrollbar-track {
        background: #1e293b;
    }

    .dark .kanban-container::-webkit-scrollbar-thumb {
        background: #475569;
    }
</style>