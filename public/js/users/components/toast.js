/**
 * Toast Notification Component
 * Provides user feedback through non-intrusive notifications
 */

class Toast {
  constructor(options = {}) {
    this.options = {
      position: options.position || 'top-right',
      duration: options.duration || 3000,
      showProgress: options.showProgress !== false,
      closeable: options.closeable !== false
    };
    this.container = this.getOrCreateContainer();
  }

  getOrCreateContainer() {
    let container = document.querySelector(`.toast-container.${this.options.position}`);
    if (!container) {
      container = document.createElement('div');
      container.className = `toast-container ${this.options.position}`;
      document.body.appendChild(container);
    }
    return container;
  }

  show(type, title, message = '') {
    const toast = this.createToast(type, title, message);
    this.container.appendChild(toast);

    requestAnimationFrame(() => {
      toast.classList.add('show');
    });

    if (this.options.duration > 0) {
      this.startAutoClose(toast, this.options.duration);
    }

    return toast;
  }

  createToast(type, title, message) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const icon = this.getIcon(type);
    const closeBtn = this.options.closeable ? this.createCloseButton() : '';
    const progress = this.options.showProgress ? '<div class="toast-progress"></div>' : '';

    toast.innerHTML = `
      <div class="toast-icon">${icon}</div>
      <div class="toast-content">
        <div class="toast-title">${title}</div>
        ${message ? `<div class="toast-message">${message}</div>` : ''}
      </div>
      ${closeBtn}
      ${progress}
    `;

    if (this.options.closeable) {
      toast.querySelector('.toast-close').addEventListener('click', () => {
        this.close(toast);
      });
    }

    return toast;
  }

  getIcon(type) {
    const icons = {
      success: '✓',
      error: '✕',
      warning: '!',
      info: 'i'
    };
    return icons[type] || icons.info;
  }

  createCloseButton() {
    return '<button class="toast-close" aria-label="Close">✕</button>';
  }

  startAutoClose(toast, duration) {
    const progressBar = toast.querySelector('.toast-progress');
    if (progressBar) {
      progressBar.style.width = '100%';
      progressBar.style.transitionDuration = `${duration}ms`;
      requestAnimationFrame(() => {
        progressBar.style.width = '0%';
      });
    }

    setTimeout(() => {
      this.close(toast);
    }, duration);
  }

  close(toast) {
    toast.classList.add('hiding');
    toast.classList.remove('show');

    setTimeout(() => {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, 300);
  }

  // Static methods for convenience
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
}

// Export to window
if (typeof window !== 'undefined') {
  window.Toast = Toast;
}
