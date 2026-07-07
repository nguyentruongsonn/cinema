/**
 * ═══════════════════════════════════════════════════════════════════════════
 * AdminModal - Reusable Modal Component
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Handles modal operations:
 * - Open/close animations
 * - Content management
 * - Event handling
 * - Multiple modal sizes
 * 
 * @version 1.0.0
 */

class AdminModal {
    constructor(page, config = {}) {
        this.page = page;
        this.config = {
            size: 'md', // sm, md, lg, xl
            closeOnBackdrop: true,
            closeOnEscape: true,
            ...config
        };

        this.state = {
            isOpen: false
        };

        this.elements = {};
        this.onOpenCallback = null;
        this.onCloseCallback = null;
    }

    /**
     * Create modal element
     */
    create(content, title = '') {
        const modal = document.createElement('div');
        modal.className = 'admin-modal-backdrop';
        modal.innerHTML = `
            <div class="admin-modal admin-modal-${this.config.size}">
                ${title ? `
                    <div class="admin-modal-header">
                        <h3 class="admin-modal-title">${title}</h3>
                        <button class="admin-modal-close" data-action="close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                ` : ''}
                <div class="admin-modal-body">
                    ${content}
                </div>
            </div>
        `;

        this.elements.backdrop = modal;
        this.elements.modal = modal.querySelector('.admin-modal');
        this.elements.body = modal.querySelector('.admin-modal-body');
        
        return modal;
    }

    /**
     * Open modal
     */
    open(content, title = '') {
        if (this.state.isOpen) return;

        const modal = this.create(content, title);
        document.body.appendChild(modal);
        
        this.attachEventListeners();
        
        // Trigger animation
        requestAnimationFrame(() => {
            modal.classList.add('active');
        });

        this.state.isOpen = true;
        document.body.style.overflow = 'hidden';
        
        if (this.onOpenCallback) {
            this.onOpenCallback();
        }
    }

    /**
     * Close modal
     */
    close() {
        if (!this.state.isOpen) return;

        this.elements.backdrop.classList.remove('active');
        
        setTimeout(() => {
            this.elements.backdrop.remove();
            this.state.isOpen = false;
            document.body.style.overflow = '';
            
            if (this.onCloseCallback) {
                this.onCloseCallback();
            }
        }, 300);
    }

    /**
     * Update modal content
     */
    setContent(content) {
        if (this.elements.body) {
            this.elements.body.innerHTML = content;
        }
    }

    /**
     * Attach event listeners
     */
    attachEventListeners() {
        // Close button
        this.elements.backdrop.querySelectorAll('[data-action="close"]').forEach(btn => {
            btn.addEventListener('click', () => this.close());
        });

        // Close on backdrop click
        if (this.config.closeOnBackdrop) {
            this.elements.backdrop.addEventListener('click', (e) => {
                if (e.target === this.elements.backdrop) {
                    this.close();
                }
            });
        }

        // Close on escape key
        if (this.config.closeOnEscape) {
            const escapeHandler = (e) => {
                if (e.key === 'Escape' && this.state.isOpen) {
                    this.close();
                    document.removeEventListener('keydown', escapeHandler);
                }
            };
            document.addEventListener('keydown', escapeHandler);
        }
    }

    /**
     * Add footer to modal
     */
    addFooter(footerContent) {
        if (!this.elements.modal) return;

        let footer = this.elements.modal.querySelector('.admin-modal-footer');
        if (!footer) {
            footer = document.createElement('div');
            footer.className = 'admin-modal-footer';
            this.elements.modal.appendChild(footer);
        }
        footer.innerHTML = footerContent;
    }

    /**
     * Show loading state
     */
    showLoading(message = 'Loading...') {
        this.setContent(`
            <div class="admin-modal-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>${message}</p>
            </div>
        `);
    }

    /**
     * Event callbacks
     */
    onOpen(callback) {
        this.onOpenCallback = callback;
    }

    onClose(callback) {
        this.onCloseCallback = callback;
    }

    /**
     * Static helper methods
     */
    static confirm(options = {}) {
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
                            <i class="fas fa-${type === 'danger' ? 'exclamation-triangle' : 'question-circle'}"></i>
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
                    closeModal(modal);
                } else if (action === 'cancel' || e.target === modal) {
                    resolve(false);
                    closeModal(modal);
                }
            });

            function closeModal(m) {
                m.classList.remove('active');
                setTimeout(() => m.remove(), 300);
            }
        });
    }

    static alert(message, type = 'info') {
        return AdminModal.confirm({
            title: type.charAt(0).toUpperCase() + type.slice(1),
            message,
            confirmText: 'OK',
            cancelText: null,
            type
        });
    }
}

export default AdminModal;