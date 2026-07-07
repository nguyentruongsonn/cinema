/**
 * ═══════════════════════════════════════════════════════════════════════════
 * AdminBasePage - Base Class for All Admin Pages
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Core functionality shared across all admin pages:
 * - Page initialization & lifecycle
 * - API communication
 * - Loading states
 * - Error handling
 * - Toast notifications
 * - Confirmation dialogs
 * 
 * @version 1.0.0
 */

class AdminBasePage {
    constructor(config = {}) {
        this.config = {
            apiBaseUrl: '/api/admin',
            csrfToken: document.querySelector('meta[name="csrf-token"]')?.content,
            ...config
        };

        this.state = {
            isLoading: false,
            isInitialized: false
        };

        this.elements = {};
        this.eventHandlers = new Map();
    }

    /**
     * Initialize the page
     */
    async init() {
        if (this.state.isInitialized) {
            console.warn('Page already initialized');
            return;
        }

        try {
            this.cacheElements();
            this.attachEventListeners();
            await this.loadInitialData();
            this.state.isInitialized = true;
            this.onReady();
        } catch (error) {
            this.handleError('Initialization failed', error);
        }
    }

    /**
     * Cache DOM elements for reuse
     * Override in subclasses to cache specific elements
     */
    cacheElements() {
        // Common elements
        this.elements.body = document.body;
        this.elements.loadingOverlay = document.querySelector('.admin-loading-overlay');
    }

    /**
     * Attach event listeners
     * Override in subclasses to attach specific listeners
     */
    attachEventListeners() {
        // Base implementation - override in subclasses
    }

    /**
     * Load initial data
     * Override in subclasses to load page-specific data
     */
    async loadInitialData() {
        // Base implementation - override in subclasses
    }

    /**
     * Called when page is ready
     * Override in subclasses for custom initialization
     */
    onReady() {
        // Base implementation - override in subclasses
    }

    /**
     * Make API request
     */
    async apiRequest(endpoint, options = {}) {
        const {
            method = 'GET',
            data = null,
            headers = {},
            showLoading = true
        } = options;

        if (showLoading) {
            this.showLoading();
        }

        try {
            const config = {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.config.csrfToken,
                    ...headers
                }
            };

            if (data && ['POST', 'PUT', 'PATCH'].includes(method)) {
                config.body = JSON.stringify(data);
            }

            const response = await fetch(`${this.config.apiBaseUrl}${endpoint}`, config);
            
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Request failed');
            }

            return await response.json();
        } catch (error) {
            this.handleError('API request failed', error);
            throw error;
        } finally {
            if (showLoading) {
                this.hideLoading();
            }
        }
    }

    /**
     * Show loading state
     */
    showLoading(message = 'Loading...') {
        this.state.isLoading = true;
        this.elements.body.classList.add('loading');
        
        if (this.elements.loadingOverlay) {
            this.elements.loadingOverlay.classList.add('active');
            const loadingText = this.elements.loadingOverlay.querySelector('.loading-text');
            if (loadingText) {
                loadingText.textContent = message;
            }
        }
    }

    /**
     * Hide loading state
     */
    hideLoading() {
        this.state.isLoading = false;
        this.elements.body.classList.remove('loading');
        
        if (this.elements.loadingOverlay) {
            this.elements.loadingOverlay.classList.remove('active');
        }
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `admin-toast admin-toast-${type}`;
        
        const icon = this.getToastIcon(type);
        toast.innerHTML = `
            <div class="admin-toast-icon">${icon}</div>
            <div class="admin-toast-content">
                <div class="admin-toast-message">${message}</div>
            </div>
            <button class="admin-toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

        let container = document.querySelector('.admin-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'admin-toast-container';
            document.body.appendChild(container);
        }

        container.appendChild(toast);

        if (duration > 0) {
            setTimeout(() => {
                toast.classList.add('hiding');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
    }

    /**
     * Get icon for toast type
     */
    getToastIcon(type) {
        const icons = {
            success: '<i class="fas fa-check-circle"></i>',
            error: '<i class="fas fa-exclamation-circle"></i>',
            warning: '<i class="fas fa-exclamation-triangle"></i>',
            info: '<i class="fas fa-info-circle"></i>'
        };
        return icons[type] || icons.info;
    }

    /**
     * Show confirmation dialog
     */
    async confirm(options = {}) {
        const {
            title = 'Confirm',
            message = 'Are you sure?',
            confirmText = 'Confirm',
            cancelText = 'Cancel',
            type = 'warning'
        } = options;

        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'admin-modal-backdrop';
            modal.innerHTML = `
                <div class="admin-modal admin-modal-sm modal-${type}">
                    <div class="admin-modal-header">
                        <h3 class="admin-modal-title">${title}</h3>
                    </div>
                    <div class="admin-modal-body admin-modal-confirm">
                        <div class="admin-modal-icon">
                            ${this.getToastIcon(type)}
                        </div>
                        <p class="admin-modal-message">${message}</p>
                    </div>
                    <div class="admin-modal-footer">
                        <button class="admin-btn admin-btn-secondary" data-action="cancel">
                            ${cancelText}
                        </button>
                        <button class="admin-btn admin-btn-${type === 'danger' ? 'danger' : 'primary'}" data-action="confirm">
                            ${confirmText}
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            setTimeout(() => modal.classList.add('active'), 10);

            modal.addEventListener('click', (e) => {
                const action = e.target.closest('[data-action]')?.dataset.action;
                if (action === 'confirm') {
                    resolve(true);
                    this.closeModal(modal);
                } else if (action === 'cancel' || e.target === modal) {
                    resolve(false);
                    this.closeModal(modal);
                }
            });
        });
    }

    /**
     * Close modal
     */
    closeModal(modal) {
        modal.classList.remove('active');
        setTimeout(() => modal.remove(), 300);
    }

    /**
     * Handle errors
     */
    handleError(context, error) {
        console.error(`[${context}]`, error);
        this.showToast(
            error.message || 'An error occurred',
            'error',
            5000
        );
    }

    /**
     * Clean up resources
     */
    destroy() {
        // Remove event listeners
        this.eventHandlers.forEach((handler, element) => {
            element.removeEventListener(handler.event, handler.callback);
        });
        this.eventHandlers.clear();

        // Clear state
        this.state.isInitialized = false;
    }
}

export default AdminBasePage;