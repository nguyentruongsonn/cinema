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
        this.backdrop.className = 'modal-backdrop';

        // Create modal
        this.modal = document.createElement('div');
        this.modal.className = `modal modal-${this.options.size} ${this.options.animation}`;
        if (this.options.variant) {
            this.modal.classList.add(`modal-${this.options.variant}`);
        }

        // Create modal content
        const modalContent = document.createElement('div');
        modalContent.className = 'modal-content';

        // Create header
        const header = document.createElement('div');
        header.className = 'modal-header';

        const title = document.createElement('h2');
        title.className = 'modal-title';
        title.textContent = this.options.title;
        header.appendChild(title);

        if (this.options.showClose) {
            const closeBtn = document.createElement('button');
            closeBtn.className = 'modal-close';
            closeBtn.innerHTML = '×';
            closeBtn.setAttribute('aria-label', 'Close');
            closeBtn.addEventListener('click', () => this.close());
            header.appendChild(closeBtn);
        }

        modalContent.appendChild(header);

        // Create body
        const body = document.createElement('div');
        body.className = 'modal-body';

        if (typeof this.options.content === 'string') {
            body.innerHTML = this.options.content;
        } else if (this.options.content instanceof HTMLElement) {
            body.appendChild(this.options.content);
        }

        modalContent.appendChild(body);

        // Create footer if provided
        if (this.options.footer) {
            const footer = document.createElement('div');
            footer.className = 'modal-footer';

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

        // Prevent body scroll
        document.body.classList.add('modal-open');

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
        this.modal.focus();

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
            document.body.classList.remove('modal-open');

            // Remove escape key listener
            if (this.escapeHandler) {
                document.removeEventListener('keydown', this.escapeHandler);
            }

            this.isOpen = false;

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
                }, 'btn-primary')
            ]
        });
        return modal.open();
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

    static createButton(text, onClick, className = 'btn') {
        const btn = document.createElement('button');
        btn.className = `btn ${className}`;
        btn.textContent = text;
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

        modal.modal.classList.add('modal-image');
        return modal.open();
    }
}

// Export to window
if (typeof window !== 'undefined') {
    window.Modal = Modal;
}
