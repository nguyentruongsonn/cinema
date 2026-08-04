/**
 * Shared public toast notification component.
 * All customer-facing pages must use this API instead of custom alerts/toasts.
 */
class Toast {
    static queue = [];
    static maxToasts = 4;
    static maxMobileToasts = 3;
    static positions = new Set(['top-right', 'top-left', 'top-center', 'bottom-right', 'bottom-left', 'bottom-center']);

    constructor(options = {}) {
        const requestedPosition = options.position || 'top-right';
        this.options = {
            position: Toast.positions.has(requestedPosition) ? requestedPosition : 'top-right',
            duration: options.duration === undefined ? 3500 : Math.max(0, Number(options.duration) || 0),
            showProgress: options.showProgress !== false,
            closeable: options.closeable !== false,
            pauseOnHover: options.pauseOnHover !== false,
        };
        this.container = this.getOrCreateContainer();
        this.timer = null;
        this.startedAt = 0;
        this.remainingTime = this.options.duration;
    }

    getOrCreateContainer() {
        let container = document.querySelector(`.cinema-toast-container[data-position="${this.options.position}"]`);
        if (container) return container;

        container = document.createElement('div');
        container.className = 'cinema-toast-container';
        container.dataset.position = this.options.position;
        container.setAttribute('aria-label', 'Thông báo');
        document.body.appendChild(container);
        return container;
    }

    show(type, title, message = '') {
        const normalizedType = type === 'danger' ? 'error' : type;
        const currentCount = this.container.querySelectorAll('.cinema-toast:not(.is-hiding)').length;
        const maxAllowed = window.innerWidth < 768 ? Toast.maxMobileToasts : Toast.maxToasts;

        if (currentCount >= maxAllowed) {
            Toast.queue.push({ type: normalizedType, title, message, options: this.options });
            return null;
        }

        const toast = this.createToast(normalizedType, title, message);
        this.container.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('is-visible'));

        if (this.options.duration > 0) this.startAutoClose(toast, this.options.duration);
        if (this.options.pauseOnHover && this.options.duration > 0) this.setupHoverPause(toast);
        return toast;
    }

    createToast(type, title, message) {
        const toast = document.createElement('div');
        toast.className = `cinema-toast cinema-toast--${this.validType(type)}`;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
        toast.setAttribute('aria-atomic', 'true');

        const icon = document.createElement('span');
        icon.className = 'cinema-toast__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = this.iconSvg(type);

        const content = document.createElement('div');
        content.className = 'cinema-toast__content';

        const titleElement = document.createElement('div');
        titleElement.className = 'cinema-toast__title';
        titleElement.textContent = String(title || 'Thông báo');
        content.appendChild(titleElement);

        if (message) {
            const messageElement = document.createElement('div');
            messageElement.className = 'cinema-toast__message';
            messageElement.textContent = String(message);
            content.appendChild(messageElement);
        }

        toast.append(icon, content);

        if (this.options.closeable) {
            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'cinema-toast__close';
            closeButton.setAttribute('aria-label', 'Đóng thông báo');
            closeButton.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
            closeButton.addEventListener('click', () => this.close(toast));
            toast.appendChild(closeButton);
        }

        if (this.options.showProgress && this.options.duration > 0) {
            const progress = document.createElement('span');
            progress.className = 'cinema-toast__progress';
            progress.setAttribute('aria-hidden', 'true');
            toast.appendChild(progress);
        }

        return toast;
    }

    validType(type) {
        return ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info';
    }

    iconSvg(type) {
        const icons = {
            success: '<i class="bi bi-check-lg"></i>',
            error: '<i class="bi bi-exclamation-lg"></i>',
            warning: '<i class="bi bi-exclamation-triangle-fill"></i>',
            info: '<i class="bi bi-info-lg"></i>',
        };
        return icons[this.validType(type)];
    }

    startAutoClose(toast, duration) {
        const progress = toast.querySelector('.cinema-toast__progress');
        if (progress) {
            progress.style.transitionDuration = `${duration}ms`;
            requestAnimationFrame(() => { progress.style.transform = 'scaleX(0)'; });
        }

        this.startedAt = Date.now();
        this.remainingTime = duration;
        this.timer = window.setTimeout(() => this.close(toast), duration);
    }

    setupHoverPause(toast) {
        toast.addEventListener('mouseenter', () => {
            if (!this.timer) return;
            window.clearTimeout(this.timer);
            this.timer = null;
            this.remainingTime = Math.max(0, this.remainingTime - (Date.now() - this.startedAt));

            const progress = toast.querySelector('.cinema-toast__progress');
            if (progress) {
                const scale = this.options.duration > 0 ? this.remainingTime / this.options.duration : 0;
                progress.style.transitionDuration = '0ms';
                progress.style.transform = `scaleX(${scale})`;
            }
        });

        toast.addEventListener('mouseleave', () => {
            if (this.remainingTime <= 0) return;
            this.startedAt = Date.now();
            const progress = toast.querySelector('.cinema-toast__progress');
            if (progress) {
                progress.style.transitionDuration = `${this.remainingTime}ms`;
                progress.style.transform = 'scaleX(0)';
            }
            this.timer = window.setTimeout(() => this.close(toast), this.remainingTime);
        });
    }

    close(toast) {
        if (!toast?.isConnected || toast.classList.contains('is-hiding')) return;
        if (this.timer) window.clearTimeout(this.timer);
        this.timer = null;
        toast.classList.add('is-hiding');
        toast.classList.remove('is-visible');
        window.setTimeout(() => {
            toast.remove();
            this.processQueue();
        }, 220);
    }

    processQueue() {
        const queued = Toast.queue.shift();
        if (!queued) return;
        new Toast(queued.options).show(queued.type, queued.title, queued.message);
    }

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

    static clearAll() {
        document.querySelectorAll('.cinema-toast').forEach(toast => toast.remove());
        Toast.queue = [];
    }
}

window.Toast = Toast;

export default Toast;
