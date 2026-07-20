import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import { chromium } from 'playwright';

const baseUrl = process.env.ADMIN_BROWSER_BASE_URL ?? 'http://127.0.0.1:8000';
const edgeCandidates = [
    process.env.EDGE_PATH,
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].filter(Boolean);

const php = spawnSync('php', ['-r', [
    "require 'vendor/autoload.php';",
    "$app = require 'bootstrap/app.php';",
    "$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();",
    "$user = App\\Models\\User::query()->whereHas('role', fn ($query) => $query->whereIn('slug', ['admin', 'super-admin']))->firstOrFail();",
    "echo auth('api')->login($user);",
].join(' ')], {
    cwd: process.cwd(),
    encoding: 'utf8',
});

if (php.status !== 0 || !php.stdout.trim()) {
    throw new Error(`Could not create local admin browser token: ${php.stderr || 'empty token'}`);
}

const browser = await chromium.launch({
    headless: true,
    executablePath: edgeCandidates.find((candidate) => fs.existsSync(candidate)),
});

try {
    const context = await browser.newContext({ baseURL: baseUrl });
    await context.addCookies([{
        name: 'access_token',
        value: php.stdout.trim(),
        domain: '127.0.0.1',
        path: '/',
        httpOnly: true,
        sameSite: 'Lax',
    }]);

    const page = await context.newPage();
    const consoleErrors = [];
    const pageErrors = [];
    let dashboardRequests = 0;
    let comboRequests = 0;
    let screenRequests = 0;
    let screenReferenceRequests = 0;
    const screenRequestUrls = [];
    let seatTemplateRequests = 0;
    let branchOptionRequests = 0;

    page.on('console', (message) => {
        if (message.type() === 'error') consoleErrors.push(message.text());
    });
    page.on('pageerror', (error) => pageErrors.push(error.message));
    page.on('request', (request) => {
        const pathname = new URL(request.url()).pathname;
        if (pathname === '/api/v1/admin/dashboard/stats') dashboardRequests++;
        if (pathname === '/api/v1/admin/combos') comboRequests++;
        if (pathname === '/api/v1/admin/screens') {
            screenRequests++;
            screenRequestUrls.push(request.url());
            if (new URL(request.url()).searchParams.get('include_references') === '1') screenReferenceRequests++;
        }
        if (pathname === '/api/v1/admin/seat-layout-templates') seatTemplateRequests++;
        if (pathname === '/api/v1/admin/branches' && new URL(request.url()).searchParams.get('options') === '1') {
            branchOptionRequests++;
        }
    });

    const dashboardResponsePromise = page.waitForResponse(
        (response) => new URL(response.url()).pathname === '/api/v1/admin/dashboard/stats',
        { timeout: 30_000 },
    );
    const dashboardPage = await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded', timeout: 30_000 });
    const dashboardApi = await dashboardResponsePromise;
    const dashboardPayload = await dashboardApi.json();

    assert.equal(dashboardPage?.status(), 200);
    assert.equal(dashboardApi.status(), 200);
    assert.equal(dashboardPayload.success, true);
    assert.ok(dashboardPayload.data.cards);
    assert.equal(dashboardRequests, 1);

    const navigationState = await page.evaluate(() => {
        const marker = crypto.randomUUID();
        window.__adminNavigationMarker = marker;
        document.getElementById('adminSidebar').dataset.navigationMarker = marker;
        return {
            marker,
            timeOrigin: performance.timeOrigin,
            navigationEntries: performance.getEntriesByType('navigation').length,
        };
    });

    const combosLink = page.locator('#adminSidebar a[href$="/admin/combos"]').first();

    const versionedAdminAssets = await page.locator('link[href*="/css/admin/"], script[src*="/js/admin/"]')
        .evaluateAll((elements) => elements.map((element) => element.href || element.src));
    assert.ok(versionedAdminAssets.length > 0);
    const expectedAssetVersion = await page.evaluate(() => window.APP_CONFIG.assetVersion);
    const incorrectlyVersionedAssets = versionedAdminAssets.filter(
        (url) => new URL(url).searchParams.get('v') !== expectedAssetVersion,
    );
    assert.deepEqual(incorrectlyVersionedAssets, []);

    const combosResponsePromise = page.waitForResponse(
        (response) => new URL(response.url()).pathname === '/api/v1/admin/combos',
        { timeout: 30_000 },
    );
    const combosPagePromise = page.waitForResponse(
        (response) => new URL(response.url()).pathname === '/admin/combos'
            && response.headers()['content-type']?.includes('text/html'),
        { timeout: 30_000 },
    );
    await combosLink.evaluate((link) => link.click());
    await page.waitForURL('**/admin/combos', { timeout: 30_000 });
    const combosPage = await combosPagePromise;
    const combosApi = await combosResponsePromise;
    const combosPayload = await combosApi.json();

    assert.equal(combosPage.status(), 200);
    assert.equal(combosApi.status(), 200);
    assert.equal(combosPayload.success, true);
    assert.ok(Array.isArray(combosPayload.data));
    assert.equal(combosPayload.pagination.per_page, 10);
    assert.equal(comboRequests, 1);

    await page.locator('#btnOpenCreateCombo').click();
    await page.locator('#comboModal').waitFor({ state: 'visible', timeout: 10_000 });
    const openModalState = await page.evaluate(() => {
        const modal = document.getElementById('comboModal');
        const backdrop = document.querySelector('.modal-backdrop');
        return {
            bodyLocked: document.body.classList.contains('modal-open'),
            backdropCount: document.querySelectorAll('.modal-backdrop').length,
            modalZIndex: Number.parseInt(getComputedStyle(modal).zIndex, 10),
            backdropZIndex: Number.parseInt(getComputedStyle(backdrop).zIndex, 10),
        };
    });
    assert.equal(openModalState.bodyLocked, true);
    assert.equal(openModalState.backdropCount, 1);
    assert.ok(openModalState.modalZIndex > openModalState.backdropZIndex);

    await page.locator('#comboModal .btn-close').click();
    await page.locator('#comboModal').waitFor({ state: 'hidden', timeout: 10_000 });
    await page.waitForFunction(() => !document.body.classList.contains('modal-open')
        && document.querySelectorAll('.modal-backdrop').length === 0);

    const navigationStateAfterVisit = await page.evaluate(() => ({
        marker: window.__adminNavigationMarker,
        sidebarMarker: document.getElementById('adminSidebar')?.dataset.navigationMarker,
        timeOrigin: performance.timeOrigin,
        navigationEntries: performance.getEntriesByType('navigation').length,
        turboAvailable: Boolean(window.Turbo),
    }));
    assert.equal(navigationStateAfterVisit.marker, navigationState.marker);
    assert.equal(navigationStateAfterVisit.sidebarMarker, navigationState.marker);
    assert.equal(navigationStateAfterVisit.timeOrigin, navigationState.timeOrigin);
    assert.equal(navigationStateAfterVisit.navigationEntries, navigationState.navigationEntries);
    assert.equal(navigationStateAfterVisit.turboAvailable, true);

    const duplicateCssRequests = await page.evaluate(() => performance.getEntriesByType('resource')
        .map((entry) => entry.name)
        .filter((url) => /css\/admin\/(components\/skeleton|admin-modals|admin-common)\.css/.test(url)));
    assert.deepEqual(duplicateCssRequests, []);

    await page.locator('#btnOpenCreateCombo').click();
    await page.locator('#comboModal').waitFor({ state: 'visible', timeout: 10_000 });
    await page.locator('#adminSidebar a[href$="/admin/dashboard"]').evaluate((link) => link.click());
    await page.waitForURL('**/admin/dashboard', { timeout: 30_000 });
    assert.equal(await page.evaluate(() => window.__adminNavigationMarker), navigationState.marker, 'returning to dashboard must not reload the document');
    assert.equal(await page.locator('.modal-backdrop').count(), 0, 'Turbo navigation must remove modal backdrops');
    assert.equal(await page.evaluate(() => document.body.classList.contains('modal-open')), false, 'Turbo navigation must unlock body scroll');
    await page.locator('#adminSidebar a[href$="/admin/combos"]').evaluate((link) => link.click());
    await page.waitForURL('**/admin/combos', { timeout: 30_000 });
    assert.equal(await page.evaluate(() => window.__adminNavigationMarker), navigationState.marker, 'returning to combos must not reload the document');
    await page.locator('#combosTableBody tr').first().waitFor({ timeout: 10_000 });
    assert.equal(comboRequests, 1);

    const adminPaths = [
        '/admin/revenue',
        '/admin/tickets',
        '/admin/combos/stats',
        '/admin/branches',
        '/admin/theaters',
        '/admin/screens',
        '/admin/seat-layout-templates',
        '/admin/movies',
        '/admin/showtimes',
        '/admin/orders',
        '/admin/products',
        '/admin/promotions',
        '/admin/posts',
        '/admin/banners',
        '/admin/users',
    ];

    for (const pathname of adminPaths) {
        let pageDataResponsePromise = null;
        if (pathname === '/admin/screens') {
            pageDataResponsePromise = page.waitForResponse((response) => {
                const url = new URL(response.url());
                return url.pathname === '/api/v1/admin/screens' && url.searchParams.get('include_references') === '1';
            }, { timeout: 30_000 });
        } else if (pathname === '/admin/seat-layout-templates') {
            pageDataResponsePromise = page.waitForResponse(
                (response) => new URL(response.url()).pathname === '/api/v1/admin/seat-layout-templates',
                { timeout: 30_000 },
            );
        } else if (pathname === '/admin/theaters') {
            pageDataResponsePromise = page.waitForResponse((response) => {
                const url = new URL(response.url());
                return url.pathname === '/api/v1/admin/branches' && url.searchParams.get('options') === '1';
            }, { timeout: 30_000 });
        }

        const htmlResponsePromise = page.waitForResponse(
            (response) => new URL(response.url()).pathname === pathname
                && response.headers()['content-type']?.includes('text/html'),
            { timeout: 30_000 },
        );
        await page.locator(`#adminSidebar a[href$="${pathname}"]`).first().evaluate((link) => link.click());
        await page.waitForURL(`**${pathname}`, { timeout: 30_000 });
        const htmlResponse = await htmlResponsePromise;
        assert.equal(htmlResponse.status(), 200, `${pathname} should render successfully`);
        if (pageDataResponsePromise) {
            const pageDataResponse = await pageDataResponsePromise;
            assert.equal(pageDataResponse.status(), 200, `${pathname} data should load successfully`);
        }
        await page.locator('.admin-main').waitFor({ state: 'visible', timeout: 10_000 });
        await page.waitForTimeout(150);
        const routeState = await page.evaluate(() => ({
            marker: window.__adminNavigationMarker,
            timeOrigin: performance.timeOrigin,
            placeholderActive: Boolean(document.querySelector('#adminSidebar a[href="#"].active')),
        }));
        assert.equal(routeState.marker, navigationState.marker, `${pathname} must not reload the document`);
        assert.equal(routeState.timeOrigin, navigationState.timeOrigin, `${pathname} must preserve timeOrigin`);
        assert.equal(routeState.placeholderActive, false, `${pathname} must not activate placeholder navigation`);

        if (pathname === '/admin/combos/stats') {
            const statsPattern = await page.evaluate(() => ({
                selectorInsideFilter: Boolean(document.querySelector('.filter-bar .admin-segmented-tabs')),
                legacySkeletonCount: document.querySelectorAll('.skeleton, .skeleton-text, .skeleton-chart').length,
                selectedType: document.querySelector('[data-stats-type].active')?.dataset.statsType,
            }));
            assert.equal(statsPattern.selectorInsideFilter, true);
            assert.equal(statsPattern.legacySkeletonCount, 0);
            assert.equal(statsPattern.selectedType, 'combo');
        }
    }

    const finalNavigationState = await page.evaluate(() => ({
        marker: window.__adminNavigationMarker,
        sidebarMarker: document.getElementById('adminSidebar')?.dataset.navigationMarker,
        timeOrigin: performance.timeOrigin,
        navigationEntries: performance.getEntriesByType('navigation').length,
    }));
    assert.equal(finalNavigationState.marker, navigationState.marker);
    assert.equal(finalNavigationState.sidebarMarker, navigationState.marker);
    assert.equal(finalNavigationState.timeOrigin, navigationState.timeOrigin);
    assert.equal(finalNavigationState.navigationEntries, navigationState.navigationEntries);
    assert.equal(screenRequests, 1, `screen requests: ${screenRequestUrls.join(', ')}`);
    assert.equal(screenReferenceRequests, 1);
    assert.equal(seatTemplateRequests, 1);
    assert.equal(branchOptionRequests, 1);

    const lifecycleErrors = [...consoleErrors, ...pageErrors].filter(
        (message) => /ReferenceError|SyntaxError|already been declared|onAdminPageLoad|onAdminPageCleanup/i.test(message),
    );
    assert.deepEqual(lifecycleErrors, []);
    assert.ok(!consoleErrors.some((message) => /unauthorized|forbidden/i.test(message)));

    console.log(`Admin browser regression passed: ${adminPaths.length + 2} Turbo routes, dashboard=${dashboardRequests}, combos=${comboRequests}, screens=${screenRequests}, seat templates=${seatTemplateRequests}, branch options=${branchOptionRequests}.`);
} finally {
    await browser.close();
}
