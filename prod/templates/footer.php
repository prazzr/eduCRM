</div><!-- /.content-wrapper -->
</main><!-- /.main-content -->
</div><!-- /.app-container -->

<!-- Alpine.js Toast Container -->
<div x-data="toastContainer()" class="toast-container" x-cloak>
    <template x-for="toast in toasts" :key="toast.id">
        <div :class="'toast toast-' + toast.type"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform translate-x-full"
             x-transition:enter-end="opacity-100 transform translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform translate-x-0"
             x-transition:leave-end="opacity-0 transform translate-x-full">
            <span class="toast-icon">
                <template x-if="toast.type === 'success'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </template>
                <template x-if="toast.type === 'error'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </template>
                <template x-if="toast.type === 'warning'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </template>
                <template x-if="toast.type === 'info'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </template>
            </span>
            <div class="toast-content">
                <p class="toast-message" x-text="toast.message"></p>
            </div>
            <button class="toast-close" @click="remove(toast.id)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    </template>
</div>

<!-- Alpine.js Confirm Dialog -->
<div x-data="confirmDialog()" x-cloak>
    <template x-if="show">
        <div class="modal-backdrop" @click.self="cancel()" @keydown.escape.window="cancel()">
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-body confirm-dialog">
                    <div class="confirm-icon" :class="confirmClass === 'btn-danger' ? 'danger' : 'warning'">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <h3 class="confirm-title" x-text="title"></h3>
                    <p class="confirm-message" x-text="message"></p>
                    <div class="confirm-actions">
                        <button class="btn btn-secondary" @click="cancel()" x-text="cancelText"></button>
                        <button :class="'btn ' + confirmClass" @click="proceed()" x-text="confirmText"></button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<!-- Legacy Toast & Modal System (for backward compatibility) -->
<div id="toastContainer" class="fixed top-4 right-4 z-50 flex flex-col gap-2"></div>

<div id="customModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 overflow-hidden">
        <div id="modalHeader" class="p-4 border-b border-slate-200 flex items-center gap-3">
            <div id="modalIcon" class="w-10 h-10 rounded-full flex items-center justify-center"></div>
            <h3 id="modalTitle" class="text-lg font-semibold text-slate-800"></h3>
        </div>
        <div class="p-4">
            <p id="modalMessage" class="text-slate-600"></p>
        </div>
        <div id="modalFooter" class="p-4 bg-slate-50 flex justify-end gap-3"></div>
    </div>
</div>

<script>
// Toast notification system
const Toast = {
    show(message, type = 'info', duration = 4000) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        
        const colors = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            warning: 'bg-amber-500',
            info: 'bg-blue-500'
        };
        
        const icons = {
            success: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>',
            error: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>',
            warning: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
            info: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
        };
        
        toast.className = `flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg text-white ${colors[type]} transform translate-x-full transition-transform duration-300`;
        toast.innerHTML = `
            <span class="flex-shrink-0">${icons[type]}</span>
            <span class="flex-1">${message}</span>
            <button onclick="this.parentElement.remove()" class="flex-shrink-0 opacity-70 hover:opacity-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        `;
        
        container.appendChild(toast);
        
        // Animate in
        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-full');
        });
        
        // Auto remove
        setTimeout(() => {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },
    
    success(message, duration) { this.show(message, 'success', duration); },
    error(message, duration) { this.show(message, 'error', duration); },
    warning(message, duration) { this.show(message, 'warning', duration); },
    info(message, duration) { this.show(message, 'info', duration); }
};

// Modal/Confirm dialog system
const Modal = {
    show(options) {
        const modal = document.getElementById('customModal');
        const icon = document.getElementById('modalIcon');
        const title = document.getElementById('modalTitle');
        const message = document.getElementById('modalMessage');
        const footer = document.getElementById('modalFooter');
        
        const types = {
            confirm: { color: 'bg-blue-100 text-blue-600', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' },
            success: { color: 'bg-green-100 text-green-600', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' },
            error: { color: 'bg-red-100 text-red-600', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' },
            warning: { color: 'bg-amber-100 text-amber-600', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>' },
            info: { color: 'bg-blue-100 text-blue-600', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' }
        };
        
        const type = types[options.type] || types.info;
        icon.className = `w-10 h-10 rounded-full flex items-center justify-center ${type.color}`;
        icon.innerHTML = type.icon;
        title.textContent = options.title || 'Notification';
        message.textContent = options.message || '';
        
        footer.innerHTML = '';
        
        if (options.showCancel !== false && options.onConfirm) {
            const cancelBtn = document.createElement('button');
            cancelBtn.className = 'px-4 py-2 text-sm font-medium text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors';
            cancelBtn.textContent = options.cancelText || 'Cancel';
            cancelBtn.onclick = () => { Modal.hide(); if (options.onCancel) options.onCancel(); };
            footer.appendChild(cancelBtn);
        }
        
        const confirmBtn = document.createElement('button');
        const btnColors = {
            confirm: 'bg-blue-600 hover:bg-blue-700',
            success: 'bg-green-600 hover:bg-green-700',
            error: 'bg-red-600 hover:bg-red-700',
            warning: 'bg-amber-600 hover:bg-amber-700',
            info: 'bg-blue-600 hover:bg-blue-700'
        };
        confirmBtn.className = `px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors ${btnColors[options.type] || btnColors.info}`;
        confirmBtn.textContent = options.confirmText || 'OK';
        confirmBtn.onclick = () => { Modal.hide(); if (options.onConfirm) options.onConfirm(); };
        footer.appendChild(confirmBtn);
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    },
    
    hide() {
        const modal = document.getElementById('customModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    },
    
    confirm(message, onConfirm, onCancel) {
        this.show({
            type: 'confirm',
            title: 'Confirm Action',
            message: message,
            onConfirm: onConfirm,
            onCancel: onCancel
        });
    },
    
    alert(message, type = 'info', title = 'Notification') {
        this.show({
            type: type,
            title: title,
            message: message,
            showCancel: false,
            onConfirm: () => {}
        });
    },
    
    success(message, title = 'Success') { this.alert(message, 'success', title); },
    error(message, title = 'Error') { this.alert(message, 'error', title); },
    warning(message, title = 'Warning') { this.alert(message, 'warning', title); },
    info(message, title = 'Information') { this.alert(message, 'info', title); }
};

// Close modal on backdrop click
document.getElementById('customModal')?.addEventListener('click', function(e) {
    if (e.target === this) Modal.hide();
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') Modal.hide();
});
</script>

</body>

</html>