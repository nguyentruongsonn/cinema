/**
 * Modal Component
 * Cinema Booking System - User Interface
 *
 * Accessible modal dialog system
 */

class Modal {
    constructor(options = {}) {
        this.options = {
            title: options.title || '',
            content: options.content || '',
            contentHtml: options.contentHtml || '',
            size: options.size || 'md', // sm, md, lg, xl, fullscreen
            animation: options.animation || 'fade', // fade, slide-top, slide-bottom, zoom
            variant: options.variant || '', // confirm, success, warning, error
            closeOnBackdrop: options.closeOnBackdrop !== false,
            closeOnEscape: options.closeOnEscape !== false,
            showClose: options.showClose !== false,
            footer: options.footer || null,
            onOpen: options.onOpen || null,
            onClose: options.onClose || null,
            ...options
        };

        this.modal = null;
        this.backdrop = null;
        this.isOpen = false;
    }

    create() {
        // Create backdrop
        this.backdrop = document.createElement('div');
        this.backdrop.className = 'cinema-modal-backdrop';

        // Create modal
        this.modal = document.createElement('div');
        this.modal.className = `cinema-modal cinema-modal-${this.options.size} ${this.options.animation}`;
        this.modal.tabIndex = -1;
        this.modal.setAttribute('role', 'dialog');
        this.modal.setAttribute('aria-modal', 'true');
        if (this.options.variant) {
            this.modal.classList.add(`cinema-modal-${this.options.variant}`);
        }

        // Create modal content
        const modalContent = document.createElement('div');
        modalContent.className = 'cinema-modal-content';

        // Create header
        const header = document.createElement('div');
        header.className = 'cinema-modal-header';

        const title = document.createElement('h2');
        title.className = 'cinema-modal-title';
        title.id = `cinema-modal-title-${Date.now()}-${Math.random().toString(16).slice(2)}`;
        title.textContent = this.options.title;
        this.modal.setAttribute('aria-labelledby', title.id);
        header.appendChild(title);

        if (this.options.showClose) {
            const closeBtn = document.createElement('button');
            closeBtn.className = 'cinema-modal-close';
            closeBtn.innerHTML = '&times;';
            closeBtn.setAttribute('aria-label', 'Đóng');
            closeBtn.addEventListener('click', () => this.close());
            header.appendChild(closeBtn);
        }

        modalContent.appendChild(header);

        // Create body
        const body = document.createElement('div');
        body.className = 'cinema-modal-body';

        if (this.options.contentHtml) {
            body.innerHTML = this.options.contentHtml;
        } else if (typeof this.options.content === 'string') {
            body.textContent = this.options.content;
        } else if (this.options.content instanceof HTMLElement) {
            body.appendChild(this.options.content);
        }

        modalContent.appendChild(body);

        // Create footer if provided
        if (this.options.footer) {
            const footer = document.createElement('div');
            footer.className = 'cinema-modal-footer';

            if (typeof this.options.footer === 'string') {
                footer.innerHTML = this.options.footer;
            } else if (Array.isArray(this.options.footer)) {
                this.options.footer.forEach(btn => {
                    footer.appendChild(btn);
                });
            }

            modalContent.appendChild(footer);
        }

        this.modal.appendChild(modalContent);

        // Add event listeners
        if (this.options.closeOnBackdrop) {
            this.modal.addEventListener('click', (e) => {
                if (e.target === this.modal) {
                    this.close();
                }
            });
        }

        if (this.options.closeOnEscape) {
            this.escapeHandler = (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            };
            document.addEventListener('keydown', this.escapeHandler);
        }

        // Append to body
        document.body.appendChild(this.backdrop);
        document.body.appendChild(this.modal);
    }

    open() {
        if (this.isOpen) return;

        if (!this.modal) {
            this.create();
        }

        this.previouslyFocused = document.activeElement;

        // Prevent body scroll
        document.body.classList.add('cinema-modal-open');

        // Show modal with animation
        requestAnimationFrame(() => {
            this.backdrop.classList.add('show');
            this.modal.classList.add('show');
        });

        this.isOpen = true;

        // Call onOpen callback
        if (typeof this.options.onOpen === 'function') {
            this.options.onOpen(this);
        }

        // Focus management
        const preferredFocus = this.modal.querySelector('[data-modal-primary], button, [href], input, select, textarea');
        (preferredFocus || this.modal).focus();

        this.focusTrapHandler = (event) => {
            if (event.key !== 'Tab' || !this.isOpen) return;
            const focusable = [...this.modal.querySelectorAll('button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])')];
            if (!focusable.length) {
                event.preventDefault();
                this.modal.focus();
                return;
            }
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };
        document.addEventListener('keydown', this.focusTrapHandler);

        return this;
    }

    close() {
        if (!this.isOpen) return;

        // Hide modal with animation
        this.backdrop.classList.remove('show');
        this.modal.classList.remove('show');

        // Remove after animation completes
        setTimeout(() => {
            if (this.backdrop && this.backdrop.parentNode) {
                this.backdrop.parentNode.removeChild(this.backdrop);
            }
            if (this.modal && this.modal.parentNode) {
                this.modal.parentNode.removeChild(this.modal);
            }

            // Restore body scroll
            document.body.classList.remove('cinema-modal-open');

            // Remove escape key listener
            if (this.escapeHandler) {
                document.removeEventListener('keydown', this.escapeHandler);
            }
            if (this.focusTrapHandler) {
                document.removeEventListener('keydown', this.focusTrapHandler);
            }

            this.isOpen = false;
            this.previouslyFocused?.focus?.({ preventScroll: true });

            // Call onClose callback
            if (typeof this.options.onClose === 'function') {
                this.options.onClose(this);
            }
        }, 300);

        return this;
    }

    destroy() {
        this.close();
        this.modal = null;
        this.backdrop = null;
    }

    // Static convenience methods
    static alert(title, content, options = {}) {
        const modal = new Modal({
            title,
            content,
            ...options,
            footer: [
                this.createButton('OK', () => modal.close(), 'btn-primary')
            ]
        });
        return modal.open();
    }

    static confirm(title, content, onConfirm, options = {}) {
        const modal = new Modal({
            title,
            content,
            variant: 'confirm',
            ...options,
            footer: [
                this.createButton('Hủy', () => modal.close(), 'btn-secondary'),
                this.createButton('Xác nhận', () => {
                    if (typeof onConfirm === 'function') {
                        onConfirm();
                    }
                    modal.close();
                }, 'btn-primary', true)
            ]
        });
        return modal.open();
    }

    static confirmAsync(title, content, options = {}) {
        return new Promise((resolve) => {
            let confirmed = false;
            const modal = Modal.confirm(title, content, () => {
                confirmed = true;
                resolve(true);
            }, {
                ...options,
                onClose: (instance) => {
                    options.onClose?.(instance);
                    if (!confirmed) resolve(false);
                }
            });

            return modal;
        });
    }

    static success(title, content, options = {}) {
        return Modal.alert(title, content, {
            variant: 'success',
            ...options
        });
    }

    static error(title, content, options = {}) {
        return Modal.alert(title, content, {
            variant: 'error',
            ...options
        });
    }

    static warning(title, content, options = {}) {
        return Modal.alert(title, content, {
            variant: 'warning',
            ...options
        });
    }

    static createButton(text, onClick, className = 'btn', primary = false) {
        const btn = document.createElement('button');
        btn.className = `btn ${className}`;
        btn.textContent = text;
        if (primary) btn.dataset.modalPrimary = 'true';
        btn.addEventListener('click', onClick);
        return btn;
    }

    static image(src, title = '', options = {}) {
        const img = document.createElement('img');
        img.src = src;
        img.alt = title;

        const modal = new Modal({
            title,
            content: img,
            size: 'lg',
            ...options
        });

        modal.open();
        modal.modal?.classList.add('cinema-modal-image');
        return modal;
    }
}

// Export to window
if (typeof window !== 'undefined') {
    window.Modal = Modal;
}

// ES6 Module Export
export default Modal;
