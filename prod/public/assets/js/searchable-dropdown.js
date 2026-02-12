/**
 * SearchableDropdown - Reusable autocomplete component for large data lists
 * 
 * Usage:
 *   new SearchableDropdown({
 *     inputId: 'searchInput',        // ID of the text input
 *     hiddenInputId: 'hiddenValue',  // ID of the hidden input for form submission
 *     data: [{id: 1, name: 'John', email: 'john@test.com'}, ...],
 *     displayField: 'name',          // Field to display in results (default: 'name')
 *     secondaryField: 'email',       // Optional secondary field (e.g., email)
 *     valueField: 'id',              // Field to use as value (default: 'id')
 *     placeholder: 'Search...',      // Input placeholder text
 *     maxResults: 10,                // Maximum results to show
 *     onSelect: function(item) {}    // Optional callback on selection
 *   });
 */
class SearchableDropdown {
    constructor(options) {
        this.input = document.getElementById(options.inputId);
        this.hiddenInput = document.getElementById(options.hiddenInputId);
        this.data = options.data || [];
        this.displayField = options.displayField || 'name';
        this.secondaryField = options.secondaryField || null;
        this.valueField = options.valueField || 'id';
        this.maxResults = options.maxResults || 10;
        this.onSelect = options.onSelect || null;
        this.submitBtn = options.submitBtnId ? document.getElementById(options.submitBtnId) : null;
        
        this.selectedIndex = -1;
        this.dropdown = null;
        
        this.init();
    }
    
    init() {
        // Create dropdown container
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'searchable-dropdown-list';
        this.dropdown.style.cssText = `
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        
        // Wrap input in relative container if not already
        const parent = this.input.parentElement;
        if (getComputedStyle(parent).position === 'static') {
            parent.style.position = 'relative';
        }
        parent.appendChild(this.dropdown);
        
        this.bindEvents();
    }
    
    bindEvents() {
        // Input events
        this.input.addEventListener('input', () => this.onInput());
        this.input.addEventListener('focus', () => this.onFocus());
        this.input.addEventListener('keydown', (e) => this.onKeydown(e));
        
        // Dropdown events
        this.dropdown.addEventListener('click', (e) => this.onClick(e));
        this.dropdown.addEventListener('mouseover', (e) => this.onMouseover(e));
        
        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!this.input.contains(e.target) && !this.dropdown.contains(e.target)) {
                this.close();
            }
        });
    }
    
    onInput() {
        const query = this.input.value.toLowerCase().trim();
        this.clearSelection();
        
        if (query.length < 1) {
            this.close();
            return;
        }
        
        const filtered = this.filter(query);
        this.render(filtered);
    }
    
    onFocus() {
        if (this.input.value.length >= 1 && !this.hiddenInput.value) {
            const query = this.input.value.toLowerCase().trim();
            const filtered = this.filter(query);
            this.render(filtered);
        }
    }
    
    onKeydown(e) {
        const options = this.dropdown.querySelectorAll('.sd-option');
        if (options.length === 0) return;
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this.selectedIndex = Math.min(this.selectedIndex + 1, options.length - 1);
            this.highlightOption();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
            this.highlightOption();
        } else if (e.key === 'Enter' && this.selectedIndex >= 0) {
            e.preventDefault();
            this.selectByIndex(this.selectedIndex);
        } else if (e.key === 'Escape') {
            this.close();
        }
    }
    
    onClick(e) {
        const option = e.target.closest('.sd-option');
        if (option) {
            const id = option.dataset.id;
            const item = this.data.find(d => String(d[this.valueField]) === id);
            if (item) this.select(item);
        }
    }
    
    onMouseover(e) {
        const option = e.target.closest('.sd-option');
        if (option) {
            this.selectedIndex = parseInt(option.dataset.index);
            this.highlightOption();
        }
    }
    
    filter(query) {
        return this.data.filter(item => {
            const primary = String(item[this.displayField] || '').toLowerCase();
            const secondary = this.secondaryField ? String(item[this.secondaryField] || '').toLowerCase() : '';
            return primary.includes(query) || secondary.includes(query);
        }).slice(0, this.maxResults);
    }
    
    render(items) {
        if (items.length === 0) {
            this.dropdown.innerHTML = `
                <div style="padding: 12px; color: #64748b; text-align: center;">
                    No results found
                </div>
            `;
        } else {
            this.dropdown.innerHTML = items.map((item, i) => `
                <div class="sd-option" data-id="${item[this.valueField]}" data-index="${i}"
                    style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9;
                           display: flex; align-items: center; gap: 10px; transition: background 0.15s;">
                    <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #6366f1, #8b5cf6); 
                                border-radius: 50%; display: flex; align-items: center; justify-content: center; 
                                color: #fff; font-weight: bold; font-size: 12px; flex-shrink: 0;">
                        ${String(item[this.displayField] || '?').charAt(0).toUpperCase()}
                    </div>
                    <div style="overflow: hidden;">
                        <div style="font-weight: 500; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            ${this.escapeHtml(item[this.displayField])}
                        </div>
                        ${this.secondaryField ? `
                            <div style="font-size: 11px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                ${this.escapeHtml(item[this.secondaryField] || '')}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `).join('');
        }
        
        this.dropdown.style.display = 'block';
        this.selectedIndex = -1;
    }
    
    highlightOption() {
        const options = this.dropdown.querySelectorAll('.sd-option');
        options.forEach((opt, i) => {
            if (i === this.selectedIndex) {
                opt.style.background = '#ccfbf1'; // --primary-light (teal)
                opt.style.borderLeft = '3px solid #0f766e'; // --primary (teal)
                opt.style.paddingLeft = '9px'; // Adjust for border
            } else {
                opt.style.background = '#fff';
                opt.style.borderLeft = 'none';
                opt.style.paddingLeft = '12px';
            }
        });
        
        // Scroll highlighted option into view
        if (this.selectedIndex >= 0 && options[this.selectedIndex]) {
            options[this.selectedIndex].scrollIntoView({ block: 'nearest' });
        }
    }
    
    selectByIndex(index) {
        const option = this.dropdown.querySelector(`.sd-option[data-index="${index}"]`);
        if (option) {
            const id = option.dataset.id;
            const item = this.data.find(d => String(d[this.valueField]) === id);
            if (item) this.select(item);
        }
    }
    
    select(item) {
        this.hiddenInput.value = item[this.valueField];
        this.input.value = item[this.displayField];
        this.close();
        
        // Enable submit button if present
        if (this.submitBtn) {
            this.submitBtn.disabled = false;
            this.submitBtn.style.background = '#6366f1';
            this.submitBtn.style.color = '#fff';
        }
        
        // Callback
        if (this.onSelect) {
            this.onSelect(item);
        }
    }
    
    clearSelection() {
        this.hiddenInput.value = '';
        if (this.submitBtn) {
            this.submitBtn.disabled = true;
            this.submitBtn.style.background = '';
            this.submitBtn.style.color = '';
        }
    }
    
    close() {
        this.dropdown.style.display = 'none';
    }
    
    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    // Update data dynamically
    updateData(newData) {
        this.data = newData;
    }
    
    // Clear input and selection
    clear() {
        this.input.value = '';
        this.clearSelection();
        this.close();
    }
}

// Auto-initialize dropdowns with data-searchable attribute
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-searchable]').forEach(el => {
        const config = JSON.parse(el.dataset.searchable);
        new SearchableDropdown(config);
    });
});
