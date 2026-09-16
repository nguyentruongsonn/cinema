class AdminDialog {
    static async confirm(options = {}) {
        const config = typeof options === 'string' ? { message: options } : options;

        return new Promise((resolve) => {
            const previousFocus = document.activeElement;
            const backdrop = document.createElement('div');
            backdrop.className = 'admin-modal-backdrop admin-dialog-backdrop';
            backdrop.setAttribute('role', 'presentation');

            const dialog = document.createElement('section');
            dialog.className = `admin-modal admin-modal-sm admin-modal-confirm modal-${config.variant || 'warning'}`;
            dialog.setAttribute('role', 'alertdialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.tabIndex = -1;

            const titleId = `admin-dialog-title-${Date.now()}`;
            const descriptionId = `${titleId}-description`;
            dialog.setAttribute('aria-labelledby', titleId);
            dialog.setAttribute('aria-describedby', descriptionId);

            const header = document.createElement('header');
            header.className = 'admin-modal-header';
            const title = document.createElement('h2');
            title.id = titleId;
            title.className = 'admin-modal-title';
            title.textContent = config.title || 'Xác nhận thao tác';
            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'admin-modal-close';
            closeButton.setAttribute('aria-label', 'Đóng');
            closeButton.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
            header.append(title, closeButton);

            const body = document.createElement('div');
            body.className = 'admin-modal-body';
            const icon = document.createElement('div');
            icon.className = 'admin-modal-icon';
            icon.innerHTML = `<i class="bi ${config.icon || 'bi-exclamation-triangle'}" aria-hidden="true"></i>`;
            const message = document.createElement('p');
            message.id = descriptionId;
            message.className = 'admin-modal-message';
            message.textContent = config.message || 'Bạn có chắc muốn tiếp tục?';
            body.append(icon, message);

            if (config.description) {
                const description = document.createElement('p');
                description.className = 'admin-modal-description';
                description.textContent = config.description;
                body.appendChild(description);
            }

            const footer = document.createElement('footer');
            footer.className = 'admin-modal-footer';
            const cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'btn btn-outline-light';
            cancelButton.textContent = config.cancelLabel || 'Hủy';
            const confirmButton = document.createElement('button');
            confirmButton.type = 'button';
            confirmButton.className = config.confirmClass || 'btn btn-danger';
            confirmButton.textContent = config.confirmLabel || 'Xác nhận';
            footer.append(cancelButton, confirmButton);

            dialog.append(header, body, footer);
            backdrop.appendChild(dialog);
            document.body.appendChild(backdrop);
            document.body.classList.add('admin-dialog-open');

            let settled = false;
            const finish = (confirmed) => {
                if (settled) return;
                settled = true;
                document.removeEventListener('keydown', handleKeydown);
                backdrop.classList.remove('active');
                window.setTimeout(() => {
                    backdrop.remove();
                    if (!document.querySelector('.admin-dialog-backdrop')) {
                        document.body.classList.remove('admin-dialog-open');
                    }
                    previousFocus?.focus?.({ preventScroll: true });
                    resolve(confirmed);
                }, 180);
            };

            const handleKeydown = (event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    finish(false);
                    return;
                }

                if (event.key !== 'Tab') return;
                const focusable = [closeButton, cancelButton, confirmButton].filter((element) => !element.disabled);
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

            closeButton.addEventListener('click', () => finish(false));
            cancelButton.addEventListener('click', () => finish(false));
            confirmButton.addEventListener('click', () => finish(true));
            backdrop.addEventListener('click', (event) => {
                if (event.target === backdrop) finish(false);
            });
            document.addEventListener('keydown', handleKeydown);

            requestAnimationFrame(() => {
                backdrop.classList.add('active');
                confirmButton.focus();
            });
        });
    }
}

window.AdminDialog = AdminDialog;

export default AdminDialog;
