import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { chromium, firefox, webkit } from 'playwright';

const browserCandidates = [
    process.env.EDGE_PATH,
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].filter(Boolean);

const executablePath = browserCandidates.find((candidate) => fs.existsSync(candidate));
const browserName = process.env.PLAYWRIGHT_BROWSER ?? 'chromium';
const browserType = { chromium, firefox, webkit }[browserName];
const browser = await browserType.launch({ headless: true, ...(browserName === 'chromium' && executablePath ? { executablePath } : {}) });

try {
    const page = await browser.newPage();
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));

    await page.route('http://cinema.test/', (route) => route.fulfill({
        status: 200,
        contentType: 'text/html',
        body: `
            <select id="branchFilter"></select>
            <input id="searchInput"><button id="searchBtn" type="button"></button>
            <div id="theatersSkeleton"></div>
            <div id="theatersGrid"></div>
            <div id="emptyState"></div>
            <div id="paginationContainer"></div>
            <div class="theaters-filters-section"></div>
        `,
    }));
    await page.goto('http://cinema.test/');

    await page.evaluate(() => {
        const unsafeBranch = 'Chi nhánh <svg onload="window.__theaterBranchXss = true"></svg>';
        const unsafeName = 'Rạp <img id="theater-xss-marker" src="invalid" onerror="window.__theaterNameXss = true">';
        const unsafeAddress = 'Địa chỉ <svg onload="window.__theaterAddressXss = true"></svg>';

        window.fetch = async (url) => ({
            json: async () => String(url).includes('/cities')
                ? { success: true, data: [unsafeBranch] }
                : {
                    success: true,
                    data: [{
                        id: 9,
                        name: unsafeName,
                        address: unsafeAddress,
                        branch: { name: unsafeBranch },
                        images: [{ url: 'javascript:window.__theaterImageXss=true' }],
                        screens: [{ name: 'IMAX 01', format: { name: 'Standard' } }],
                    }],
                    pagination: { current_page: 1, last_page: 1 },
                },
        });
    });

    await page.addScriptTag({ path: path.resolve('public/js/users/pages/theaters.js') });
    await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
    await page.waitForFunction(() => document.querySelectorAll('.theater-card').length === 1);
    await page.waitForTimeout(100);

    const result = await page.evaluate(() => ({
        branchExecuted: window.__theaterBranchXss === true,
        nameExecuted: window.__theaterNameXss === true,
        addressExecuted: window.__theaterAddressXss === true,
        imageExecuted: window.__theaterImageXss === true,
        injectedMarker: Boolean(document.getElementById('theater-xss-marker')),
        injectedSvg: Boolean(document.querySelector('#theatersGrid svg')),
        gridText: document.getElementById('theatersGrid').textContent,
        backgroundImage: document.querySelector('.theater-img-placeholder, .theater-img').style.backgroundImage,
        badges: Array.from(document.querySelectorAll('.t-badge'), (badge) => badge.textContent),
    }));

    assert.equal(result.branchExecuted, false, 'Branch name executed as active markup');
    assert.equal(result.nameExecuted, false, 'Theater name executed as active markup');
    assert.equal(result.addressExecuted, false, 'Theater address executed as active markup');
    assert.equal(result.imageExecuted, false, 'Unsafe image URL executed');
    assert.equal(result.injectedMarker, false, 'Theater name created an injected element');
    assert.equal(result.injectedSvg, false, 'Theater text created injected SVG markup');
    assert.match(result.gridText, /<img id=/, 'Theater name was not rendered as literal text');
    assert.match(result.gridText, /<svg onload=/, 'Theater branch/address was not rendered as literal text');
    assert.equal(result.backgroundImage, '', 'Unsafe image URL reached a style URL sink');
    assert.deepEqual(result.badges, ['IMAX'], 'Renderer added capabilities absent from API data');
    assert.deepEqual(pageErrors, []);

    console.log('Theaters XSS browser regression passed.');
} finally {
    await browser.close();
}
