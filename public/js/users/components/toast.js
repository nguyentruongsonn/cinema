/**
 * Toast Notification Component - Enhanced
 * Cinema Booking System - User Interface
 *
 * Provides user feedback through non-intrusive notifications
 * Features: Queue system, hover-to-pause, multiple positions, progress bar
 */

class Toast {
    // Static queue for managing multiple toasts
    static queue = [];
    static maxToasts = 5;
    static maxMobileToasts = 3;

    constructor(options = {}) {
        this.options = {
            position: options.position || 'top-right',
            duration: options.duration !== undefined ? options.duration : 3000,
            showProgress: options.showProgress !== false,
            closeable: options.closeable !== false,
            pauseOnHover: options.pauseOnHover !== false,
            icon: options.icon || null
        };
        this.container = this.getOrCreateContainer();
        this.timer = null;
        this.startTime = null;
        this.remainingTime = null;
    }

    /**
     * Get or create toast container for specific position
     */
    getOrCreateContainer() {
        let container = document.querySelector(`.toast-container.${this.options.position}`);
        if (!container) {
            container = document.createElement('div');
            container.className = `toast-container ${this.options.position}`;
            document.body.appendChild(container);
        }
        return container;
    }

    /**
     * Show toast notification
     */
    show(type, title, message = '') {
        // Check if we've exceeded max toasts
        const currentToasts = this.container.querySelectorAll('.toast:not(.hiding)').length;
        const maxAllowed = this.isMobile() ? Toast.maxMobileToasts : Toast.maxToasts;

        if (currentToasts >= maxAllowed) {
            // Queue this toast
            Toast.queue.push({ type, title, message, options: this.options });
            return null;
        }

        const toast = this.createToast(type, title, message);
        this.container.appendChild(toast);

        // Trigger animation
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        // Setup auto-close if duration > 0
        if (this.options.duration > 0) {
            this.startAutoClose(toast, this.options.duration);
        }

        // Setup hover pause
        if (this.options.pauseOnHover && this.options.duration > 0) {
            this.setupHoverPause(toast);
        }

        return toast;
    }

    /**
     * Create toast element
     */
    createToast(type, title, message) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'polite');

        const icon = this.options.icon || this.getIcon(type);
        const closeBtn = this.options.closeable ? this.createCloseButton() : '';
        const progress = this.options.showProgress && this.options.duration > 0
            ? '<div class="toast-progress"></div>'
            : '';

        toast.innerHTML = `
            <div class="toast-icon">${icon}</div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                ${message ? `<div class="toast-message">${message}</div>` : ''}
            </div>
            ${closeBtn}
            ${progress}
        `;

        // Setup close button
        if (this.options.closeable) {
            const closeButton = toast.querySelector('.toast-close');
            closeButton.addEventListener('click', () => {
                this.close(toast);
            });
        }

        return toast;
    }

    /**
     * Get icon for toast type
     */
    getIcon(type) {
        const icons = {
            success: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 0C4.48 0 0 4.48 0 10C0 15.52 4.48 20 10 20C15.52 20 20 15.52 20 10C20 4.48 15.52 0 10 0ZM8 15L3 10L4.41 8.59L8 12.17L15.59 4.58L17 6L8 15Z" fill="currentColor"/>
            </svg>`,
            error: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 0C4.48 0 0 4.48 0 10C0 15.52 4.48 20 10 20C15.52 20 20 15.52 20 10C20 4.48 15.52 0 10 0ZM11 15H9V13H11V15ZM11 11H9V5H11V11Z" fill="currentColor"/>
            </svg>`,
            warning: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M1 17H19L10 2L1 17ZM11 14H9V12H11V14ZM11 10H9V6H11V10Z" fill="currentColor"/>
            </svg>`,
            info: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 0C4.48 0 0 4.48 0 10C0 15.52 4.48 20 10 20C15.52 20 20 15.52 20 10C20 4.48 15.52 0 10 0ZM11 15H9V9H11V15ZM11 7H9V5H11V7Z" fill="currentColor"/>
            </svg>`
        };
        return icons[type] || icons.info;
    }

    /**
     * Create close button
     */
    createCloseButton() {
        return `<button class="toast-close" aria-label="Close notification" type="button">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M11 1L1 11M1 1L11 11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </button>`;
    }

    /**
     * Start auto-close timer with progress bar
     */
    startAutoClose(toast, duration) {
        const progressBar = toast.querySelector('.toast-progress');

        if (progressBar) {
            progressBar.style.width = '100%';
            progressBar.style.transitionDuration = `${duration}ms`;
            requestAnimationFrame(() => {
                progressBar.style.width = '0%';
            });
        }

        this.startTime = Date.now();
        this.remainingTime = duration;

        this.timer = setTimeout(() => {
            this.close(toast);
        }, duration);
    }

    /**
     * Setup hover pause functionality
     */
    setupHoverPause(toast) {
        const progressBar = toast.querySelector('.toast-progress');

        toast.addEventListener('mouseenter', () => {
            // Pause timer
            if (this.timer) {
                clearTimeout(this.timer);
                this.remainingTime = this.remainingTime - (Date.now() - this.startTime);

                // Pause progress bar
                if (progressBar) {
                    const computedStyle = window.getComputedStyle(progressBar);
                    const currentWidth = computedStyle.width;
                    progressBar.style.transitionDuration = '0s';
                    progressBar.style.width = currentWidth;
                }
            }
        });

        toast.addEventListener('mouseleave', () => {
            // Resume timer
            if (this.remainingTime > 0) {
                this.startTime = Date.now();

                // Resume progress bar
                if (progressBar) {
                    progressBar.style.transitionDuration = `${this.remainingTime}ms`;
                    progressBar.style.width = '0%';
                }

                this.timer = setTimeout(() => {
                    this.close(toast);
                }, this.remainingTime);
            }
        });
    }

    /**
     * Close toast
     */
    close(toast) {
        if (!toast || !toast.parentNode) return;

        // Clear timer
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }

        // Animate out
        toast.classList.add('hiding');
        toast.classList.remove('show');

        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);

                // Process queue
                this.processQueue();
            }
        }, 300);
    }

    /**
     * Process queued toasts
     */
    processQueue() {
        if (Toast.queue.length > 0) {
            const queued = Toast.queue.shift();
            const toast = new Toast(queued.options);
            toast.show(queued.type, queued.title, queued.message);
        }
    }

    /**
     * Check if device is mobile
     */
    isMobile() {
        return window.innerWidth < 768;
    }

    // ==========================================
    // Static convenience methods
    // ==========================================

    static success(title, message = '', options = {}) {
        return new Toast(options).show('success', title, message);
    }

    static error(title, message = '', options = {}) {
        return new Toast(options).show('error', title, message);
    }

    static warning(title, message = '', options = {}) {
        return new Toast(options).show('warning', title, message);
    }

    static info(title, message = '', options = {}) {
        return new Toast(options).show('info', title, message);
    }

    /**
     * Clear all toasts
     */
    static clearAll() {
        const containers = document.querySelectorAll('.toast-container');
        containers.forEach(container => {
            const toasts = container.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.classList.add('hiding');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            });
        });
        Toast.queue = [];
    }

    /**
     * Set max toasts limit
     */
    static setMaxToasts(max) {
        Toast.maxToasts = max;
    }

    /**
     * Set max mobile toasts limit
     */
    static setMaxMobileToasts(max) {
        Toast.maxMobileToasts = max;
    }
}

// Export to window
if (typeof window !== 'undefined') {
    window.Toast = Toast;
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Toast;
}

// ES6 Module Export
export default Toast;
