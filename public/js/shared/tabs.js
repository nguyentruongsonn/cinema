class CinemaTabs {
    static selector = '[data-tabs]';

    static getTabs(root) {
        return Array.from(root?.querySelectorAll(':scope > [role="tab"], :scope > * > [role="tab"]') || [])
            .filter((tab) => tab.closest(CinemaTabs.selector) === root);
    }

    static activate(tab, options = {}) {
        const root = tab?.closest(CinemaTabs.selector);
        if (!root || tab.disabled || tab.getAttribute('aria-disabled') === 'true') return;

        const tabs = CinemaTabs.getTabs(root);
        tabs.forEach((item) => {
            const active = item === tab;
            item.classList.toggle('active', active);
            item.setAttribute('aria-selected', String(active));
            item.tabIndex = active ? 0 : -1;
        });

        if (options.focus) tab.focus();
        if (options.emit === false) return;

        root.dispatchEvent(new CustomEvent('cinema:tab-change', {
            bubbles: true,
            detail: {
                tab,
                value: tab.dataset.value ?? tab.dataset.category ?? tab.dataset.target ?? '',
                target: tab.dataset.target || tab.getAttribute('aria-controls') || '',
            },
        }));
    }

    static initialize(root = document) {
        root.querySelectorAll(CinemaTabs.selector).forEach((tabList) => {
            const tabs = CinemaTabs.getTabs(tabList);
            if (!tabs.length) return;
            const active = tabs.find((tab) => tab.classList.contains('active') || tab.getAttribute('aria-selected') === 'true') || tabs[0];
            CinemaTabs.activate(active, { emit: false });
        });
    }
}

document.addEventListener('click', (event) => {
    const tab = event.target.closest(`${CinemaTabs.selector} [role="tab"]`);
    if (!tab) return;
    event.preventDefault();
    CinemaTabs.activate(tab);
});

document.addEventListener('keydown', (event) => {
    const tab = event.target.closest(`${CinemaTabs.selector} [role="tab"]`);
    if (!tab || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

    const root = tab.closest(CinemaTabs.selector);
    const tabs = CinemaTabs.getTabs(root).filter((item) => !item.disabled && item.getAttribute('aria-disabled') !== 'true');
    if (!tabs.length) return;

    event.preventDefault();
    const index = tabs.indexOf(tab);
    let nextIndex = index;
    if (event.key === 'Home') nextIndex = 0;
    if (event.key === 'End') nextIndex = tabs.length - 1;
    if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
    if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
    CinemaTabs.activate(tabs[nextIndex], { focus: true });
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => CinemaTabs.initialize(), { once: true });
} else {
    CinemaTabs.initialize();
}

window.CinemaTabs = CinemaTabs;

export default CinemaTabs;
