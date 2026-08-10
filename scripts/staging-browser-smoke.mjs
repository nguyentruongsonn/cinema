import fs from 'node:fs';
import { chromium } from 'playwright';

const baseUrl = String(process.env.STAGING_BASE_URL || '').replace(/\/$/, '');
if (!/^https?:\/\//.test(baseUrl)) {
    throw new Error('Set STAGING_BASE_URL to the deployed application URL.');
}

const routes = String(process.env.STAGING_BROWSER_ROUTES || '/,/movies,/posts,/theaters,/prices')
    .split(',')
    .map(route => route.trim())
    .filter(Boolean);
const viewports = [
    { name: 'mobile', width: 390, height: 844 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'desktop', width: 1440, height: 900 },
];
const localEdge = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';

const readiness = await fetch(`${baseUrl}/api/v1/health/ready`, {
    headers: { Accept: 'application/json' },
});
if (!readiness.ok) {
    throw new Error(`Readiness returned HTTP ${readiness.status}: ${await readiness.text()}`);
}

const browser = await chromium.launch(fs.existsSync(localEdge)
    ? { executablePath: localEdge, headless: true }
    : { headless: true });
const failures = [];

try {
    for (const viewport of viewports) {
        const context = await browser.newContext({ viewport });
        const page = await context.newPage();
        page.on('pageerror', error => failures.push(`${viewport.name}: ${error.message}`));
        page.on('console', message => {
            if (message.type() === 'error' && !message.text().includes('WebSocket connection')) {
                failures.push(`${viewport.name}: ${message.text()}`);
            }
        });

        for (const route of routes) {
            const response = await page.goto(`${baseUrl}${route}`, {
                waitUntil: 'domcontentloaded',
                timeout: 30000,
            });
            await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
            await page.waitForFunction(
                () => !document.querySelector('[aria-busy="true"]'),
                { timeout: 10000 }
            ).catch(() => {});
            await page.waitForTimeout(200);

            if (!response?.ok()) {
                failures.push(`${viewport.name} ${route}: HTTP ${response?.status()}`);
            }

            const state = await page.evaluate(() => ({
                overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
                title: document.title.trim(),
                bodyVisible: document.body.getBoundingClientRect().height > 0,
            }));
            if (state.overflow) failures.push(`${viewport.name} ${route}: horizontal overflow`);
            if (!state.title) failures.push(`${viewport.name} ${route}: missing document title`);
            if (!state.bodyVisible) failures.push(`${viewport.name} ${route}: empty body`);
        }

        await context.close();
    }
} finally {
    await browser.close();
}

if (failures.length > 0) {
    throw new Error([...new Set(failures)].join('\n'));
}

console.log(`Staging browser smoke passed for ${routes.length} routes and ${viewports.length} viewports.`);
