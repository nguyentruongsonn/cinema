import * as Turbo from '@hotwired/turbo';

window.Turbo = Turbo;
Turbo.session.drive = false;

const html = document.documentElement;
let navigationCompletionFrame = null;
let contentEntryTimer = null;
let navigationVisualTimer = null;
let contentSizeReleaseTimer = null;
let navigationStartedAt = null;
let navigationPrepared = false;

const NAVIGATION_BUSY_DELAY = 1200;

function normalizePath(pathname) {
    const normalized = pathname.replace(/\/+$/, '');
    return normalized || '/';
}

function adminUrlForLink(link) {
    const rawHref = link?.getAttribute('href')?.trim();
    const bootstrapToggle = link?.dataset.bsToggle;
    if (!rawHref || rawHref.startsWith('#') || link.target || link.hasAttribute('download') || ['collapse', 'tab', 'pill'].includes(bootstrapToggle) || link.dataset.turbo === 'false') {
        return null;
    }

    const url = new URL(link.href, window.location.href);
    if (url.origin !== window.location.origin || !url.pathname.startsWith('/admin')) {
        return null;
    }

    return url;
}

function syncSidebar(pathname = window.location.pathname) {
    const sidebar = document.getElementById('adminSidebar');
    if (!sidebar) return;

    const currentPath = normalizePath(pathname);
    const links = [...sidebar.querySelectorAll('a.nav-link')];
    let activeLink = null;

    links.forEach((link) => {
        const url = adminUrlForLink(link);
        const isActive = url && normalizePath(url.pathname) === currentPath;
        link.classList.toggle('active', Boolean(isActive));
        if (isActive) activeLink = link;
    });

    sidebar.querySelectorAll('.has-submenu').forEach((item) => {
        const submenu = item.querySelector(':scope > .collapse');
        const toggle = item.querySelector(':scope > .nav-link');
        const containsActiveLink = Boolean(activeLink && item.contains(activeLink));

        toggle?.classList.toggle('active', containsActiveLink);
        toggle?.setAttribute('aria-expanded', String(containsActiveLink));
        submenu?.classList.toggle('show', containsActiveLink);
    });
}

function resetAdminModalState({ dispose = false } = {}) {
    document.querySelectorAll('.modal').forEach((modal) => {
        const instance = window.bootstrap?.Modal?.getInstance(modal);
        if (dispose && instance && !modal.hasAttribute('data-turbo-permanent')) {
            instance.dispose();
        }

        modal.classList.remove('show');
        modal.style.removeProperty('display');
        modal.removeAttribute('aria-modal');
        modal.removeAttribute('role');
        modal.setAttribute('aria-hidden', 'true');
    });

    document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
}

function getAdminPageContent() {
    return document.getElementById('adminPageContent');
}

function lockCurrentContentSize() {
    // Avoid locking adminPageContent height during Turbo swaps.
    // The previous min-height lock made skeleton placeholders flash as boxed frames
    // and caused visible jank when moving quickly between admin sections.
}

function releaseContentSizeLock() {
    clearTimeout(contentSizeReleaseTimer);
    const content = getAdminPageContent();
    if (!content) return;

    content.classList.remove('is-navigation-sizing-locked');
    content.style.removeProperty('--admin-page-loading-min-height');
}

function showNavigationBusyState() {
    clearTimeout(navigationVisualTimer);
    navigationVisualTimer = setTimeout(() => {
        lockCurrentContentSize();
        html.classList.add('admin-navigating');
    }, NAVIGATION_BUSY_DELAY);
}

function prepareForNavigation() {
    if (navigationPrepared) return;
    navigationPrepared = true;
    lockCurrentContentSize();
    getAdminPageContent()?.setAttribute('aria-busy', 'true');
    window.runAdminPageCleanup?.();
    window.AdminCore?.abortAllRequests?.();
    window.ticketScanner?.cleanup?.();
    resetAdminModalState({ dispose: true });
}

function finishNavigation({ animate = false } = {}) {
    navigationPrepared = false;
    syncSidebar();
    getAdminPageContent()?.removeAttribute('aria-busy');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrfToken && window.APP_CONFIG) window.APP_CONFIG.csrfToken = csrfToken;

    cancelAnimationFrame(navigationCompletionFrame);
    clearTimeout(contentEntryTimer);
    clearTimeout(navigationVisualTimer);
    html.classList.remove('admin-navigating', 'admin-content-enter');
    releaseContentSizeLock();

    if (animate && !window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches) {
        navigationCompletionFrame = requestAnimationFrame(() => {
            html.classList.add('admin-content-enter');
            contentEntryTimer = setTimeout(() => {
                html.classList.remove('admin-content-enter');
            }, 70);
        });
    }

    if (navigationStartedAt !== null) {
        window.__lastAdminNavigationDuration = Math.round((performance.now() - navigationStartedAt) * 10) / 10;
        navigationStartedAt = null;
    }
}

function notifyAdminLayoutStable() {
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            window.dispatchEvent(new Event('resize'));
            document.dispatchEvent(new CustomEvent('admin:layout-stable'));
        });
    });
}

document.addEventListener('click', (event) => {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    const link = event.target.closest('a');
    const url = adminUrlForLink(link);
    if (!url || url.href === window.location.href) return;

    event.preventDefault();
    navigationStartedAt = performance.now();
    navigationPrepared = false;
    syncSidebar(url.pathname);
    showNavigationBusyState();
    Turbo.visit(url.href, { action: link.dataset.turboAction || 'advance' });
}, true);

document.addEventListener('turbo:before-visit', () => {
    showNavigationBusyState();
});

document.addEventListener('turbo:before-cache', () => {
    resetAdminModalState({ dispose: true });
});

document.addEventListener('show.bs.modal', (event) => {
    const modal = event.target;
    if (modal instanceof HTMLElement && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    const otherOpenModal = [...document.querySelectorAll('.modal.show')]
        .some((openModal) => openModal !== modal);
    if (!otherOpenModal) {
        document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
    }
});

document.addEventListener('hidden.bs.modal', () => {
    requestAnimationFrame(() => {
        if (!document.querySelector('.modal.show')) resetAdminModalState();
    });
});

document.addEventListener('turbo:before-render', (event) => {
    prepareForNavigation();

    if (event.detail?.newBody) {
        event.detail.newBody.classList.add('admin-turbo-rendering');
    }
});

document.addEventListener('turbo:render', () => finishNavigation());
document.addEventListener('turbo:load', () => {
    window.__adminTurboLoadedUrl = window.location.href;
    syncSidebar();
    notifyAdminLayoutStable();
});
document.addEventListener('turbo:fetch-request-error', () => {
    cancelAnimationFrame(navigationCompletionFrame);
    clearTimeout(contentEntryTimer);
    clearTimeout(navigationVisualTimer);
    navigationStartedAt = null;
    navigationPrepared = false;
    getAdminPageContent()?.removeAttribute('aria-busy');
    releaseContentSizeLock();
    html.classList.remove('admin-navigating', 'admin-content-enter');
});

finishNavigation();
