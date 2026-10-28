class DataRegion {
    constructor(root) {
        this.root = typeof root === 'string' ? document.querySelector(root) : root;
    }

    show(state) {
        if (!this.root) return;
        this.root.dataset.state = state;
        this.root.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');
        this.root.querySelectorAll('[data-state-panel]').forEach((panel) => {
            panel.classList.toggle('d-none', panel.dataset.statePanel !== state);
        });
    }
}

window.DataRegion = DataRegion;

export default DataRegion;
