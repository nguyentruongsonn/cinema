class AdminMediaInput {
    constructor(options = {}) {
        this.root = options.root;
        this.input = options.input;
        this.preview = options.preview;
        this.placeholder = options.placeholder;
        this.clearButton = options.clearButton;
        this.maxSize = Number(options.maxSize || 5 * 1024 * 1024);
        this.normalizeSource = options.normalizeSource || ((value) => value);
        this.objectUrl = null;
        this.abortController = new AbortController();
        this.bind();
    }

    bind() {
        const signal = this.abortController.signal;
        this.input?.addEventListener('change', () => {
            const file = this.input.files?.[0];
            if (file) this.useFile(file);
        }, { signal });

        this.clearButton?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            this.clear();
        }, { signal });

        this.root?.addEventListener('dragover', (event) => {
            event.preventDefault();
            this.root.classList.add('is-dragging');
        }, { signal });

        this.root?.addEventListener('dragleave', () => {
            this.root.classList.remove('is-dragging');
        }, { signal });

        this.root?.addEventListener('drop', (event) => {
            event.preventDefault();
            this.root.classList.remove('is-dragging');
            const file = event.dataTransfer?.files?.[0];
            if (!file || !this.validate(file)) return;
            const transfer = new DataTransfer();
            transfer.items.add(file);
            this.input.files = transfer.files;
            this.useFile(file);
        }, { signal });
    }

    validate(file) {
        if (!file.type.startsWith('image/')) {
            window.showAdminToast?.('Vui lòng chọn đúng định dạng hình ảnh.', 'warning');
            return false;
        }
        if (file.size > this.maxSize) {
            window.showAdminToast?.('Hình ảnh không được vượt quá 5MB.', 'warning');
            return false;
        }
        return true;
    }

    useFile(file) {
        if (!this.validate(file)) {
            if (this.input) this.input.value = '';
            return;
        }
        this.revokeObjectUrl();
        this.objectUrl = URL.createObjectURL(file);
        this.show(this.objectUrl);
    }

    show(source) {
        const normalized = String(source || '').startsWith('blob:')
            ? source
            : this.normalizeSource(source);
        if (!normalized || !this.preview) return;
        this.preview.src = normalized;
        this.preview.classList.add('is-visible');
        this.placeholder?.classList.add('d-none');
        this.clearButton?.classList.remove('d-none');
    }

    clear() {
        this.revokeObjectUrl();
        if (this.preview) {
            this.preview.removeAttribute('src');
            this.preview.classList.remove('is-visible');
        }
        this.placeholder?.classList.remove('d-none');
        this.clearButton?.classList.add('d-none');
        if (this.input) this.input.value = '';
    }

    revokeObjectUrl() {
        if (!this.objectUrl) return;
        URL.revokeObjectURL(this.objectUrl);
        this.objectUrl = null;
    }

    destroy() {
        this.abortController.abort();
        this.revokeObjectUrl();
    }
}

window.AdminMediaInput = AdminMediaInput;

export default AdminMediaInput;
