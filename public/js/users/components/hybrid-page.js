/**
 * Shared helpers for Blade shell + client-rendered data regions.
 * Keep page chrome stable while only the data-owned child region changes state.
 */
class HybridPage {
    static bindRegion(options = {}) {
        return new HybridRegion(options);
    }

    static getElement(target) {
        if (!target) return null;
        return typeof target === 'string' ? document.querySelector(target) : target;
    }
}

class HybridRegion {
    constructor(options = {}) {
        this.skeleton = HybridPage.getElement(options.skeleton);
        this.content = HybridPage.getElement(options.content);
        this.empty = HybridPage.getElement(options.empty);
        this.error = HybridPage.getElement(options.error);
        this.busyTarget = HybridPage.getElement(options.busyTarget) || this.content?.parentElement || this.content;
    }

    loading() {
        this.setBusy(true);
        this.show(this.skeleton);
        this.hide(this.content);
        this.hide(this.empty);
        this.hide(this.error);
    }

    ready({ empty = false } = {}) {
        this.setBusy(false);
        this.hide(this.skeleton);
        this.toggle(this.content, !empty);
        this.toggle(this.empty, empty);
        this.hide(this.error);
    }

    failed(message = '') {
        this.setBusy(false);
        this.hide(this.skeleton);
        this.hide(this.content);
        this.hide(this.empty);

        if (this.error) {
            if (message) this.error.textContent = message;
            this.show(this.error);
        }
    }

    setBusy(isBusy) {
        this.busyTarget?.setAttribute('aria-busy', String(isBusy));
    }

    show(element) {
        element?.classList.remove('d-none');
    }

    hide(element) {
        element?.classList.add('d-none');
    }

    toggle(element, shouldShow) {
        if (shouldShow) this.show(element);
        else this.hide(element);
    }
}

if (typeof window !== 'undefined') {
    window.CinemaHybridPage = HybridPage;
}

export default HybridPage;
